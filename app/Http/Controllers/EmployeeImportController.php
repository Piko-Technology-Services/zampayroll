<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeColumnMapper;
use App\Services\EmployeePortalService;
use App\Services\HrActions;
use App\Services\Import\SmartEmployeeParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Bring your team" — the 3-step bulk importer:
 *   1. drop an Excel/CSV file (or paste from a spreadsheet)
 *   2. check how columns were matched
 *   3. review every row, fix anything, import.
 */
class EmployeeImportController extends Controller
{
    public const MAX_ROWS = 2000;

    public function __construct(private HrActions $hr)
    {
    }

    public function index()
    {
        return view('dashboard.employees.import', [
            'fieldOptions' => EmployeeColumnMapper::options(),
            'employeeCount' => Employee::count(),
        ]);
    }

    /**
     * Step 1 → 2: read the file / pasted text and guess the column matching.
     * Any layout works: spreadsheets with titles above the headings, several sheets,
     * no headings at all, or plain messy text (one person per paragraph or line).
     */
    public function parse(Request $request, SmartEmployeeParser $parser)
    {
        $request->validate([
            'file'  => 'nullable|file|max:10240|mimes:csv,txt,xlsx,xls,tsv,ods',
            'paste' => 'nullable|string|max:2000000',
        ]);

        try {
            $result = $request->hasFile('file')
                ? $parser->fromFile($request->file('file'))
                : $parser->fromText((string) $request->input('paste'));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => "We couldn't read that file. Try saving it as .xlsx or .csv and upload again."], 422);
        }

        if (! $result['rows']) {
            return response()->json(['message' => $request->hasFile('file')
                ? "We couldn't find any people in that file. Check that it has names in it — headings are optional."
                : "We couldn't find any names in that text. Put each person on their own line or paragraph, starting with their name."], 422);
        }
        if (count($result['rows']) > self::MAX_ROWS) {
            return response()->json(['message' => 'That is more than ' . self::MAX_ROWS . ' people. Please split the file and import in parts.'], 422);
        }
        if (! $request->hasFile('file')) {
            $result['source'] = $result['mode'] === 'text' ? 'Your pasted text' : 'Pasted list';
        }

        return response()->json($result);
    }

    /** Step 2 → 3: clean each mapped row and flag problems / existing people. */
    public function preview(Request $request)
    {
        $data = $request->validate(['rows' => 'required|array|max:' . self::MAX_ROWS, 'rows.*' => 'array']);

        $seen = [];
        $out = [];
        foreach ($data['rows'] as $i => $row) {
            $out[] = $this->check($row, $seen, $i);
        }

        return response()->json([
            'rows'    => $out,
            'summary' => [
                'ready'     => collect($out)->where('status', 'ready')->count(),
                'warning'   => collect($out)->where('status', 'warning')->count(),
                'duplicate' => collect($out)->where('status', 'duplicate')->count(),
                'error'     => collect($out)->where('status', 'error')->count(),
            ],
        ]);
    }

    /** Step 3 → done. */
    public function commit(Request $request, EmployeePortalService $portal)
    {
        $data = $request->validate([
            'rows'           => 'required|array|max:' . self::MAX_ROWS,
            'rows.*'         => 'array',
            'duplicates'     => 'nullable|in:skip,update',
            'create_portals' => 'nullable|boolean',
        ]);
        $updateDuplicates = ($data['duplicates'] ?? 'skip') === 'update';

        $created = $updated = $skipped = $portals = 0;
        $problems = [];
        $newIds = [];

        foreach ($data['rows'] as $i => $row) {
            [$clean, $warnings] = $this->hr->normaliseEmployee($row);
            $label = trim(($clean['first_name'] ?? '') . ' ' . ($clean['last_name'] ?? '')) ?: 'Row ' . ($i + 1);

            if (empty($clean['first_name']) || empty($clean['last_name'])) {
                $skipped++;
                $problems[] = "{$label}: first and last name are needed";

                continue;
            }

            try {
                DB::transaction(function () use ($clean, $updateDuplicates, &$created, &$updated, &$skipped, &$newIds) {
                    if ($existing = $this->hr->findDuplicate($clean)) {
                        if (! $updateDuplicates) {
                            $skipped++;

                            return;
                        }
                        unset($clean['employee_id']);
                        $existing->fill($clean)->save();
                        $updated++;

                        return;
                    }
                    $newIds[] = $this->hr->createEmployee($clean)->id;
                    $created++;
                });
            } catch (\Throwable $e) {
                report($e);
                $skipped++;
                $problems[] = "{$label}: could not be saved";
            }
        }

        if (! empty($data['create_portals']) && $newIds) {
            foreach (Employee::whereIn('id', $newIds)->doesntHave('user')->get() as $emp) {
                if (! $portal->eligibilityProblem($emp)) {
                    try {
                        $portal->grant($emp, $request->user());
                        $portals++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        return response()->json(compact('created', 'updated', 'skipped', 'portals', 'problems') + [
            'total' => Employee::count(),
        ]);
    }

    /** A ready-to-fill Excel-friendly template. */
    public function template(): StreamedResponse
    {
        $headers = ['First name', 'Middle name', 'Surname', 'Employee number', 'NRC', 'Date of birth', 'Gender',
            'Mobile', 'Email', 'Job title', 'Department', 'Branch', 'Employment type', 'Start date',
            'Net pay', 'Bank', 'Account number', 'NAPSA', 'TPIN', 'NHIMA', 'Leave days'];
        $example = ['Chanda', '', 'Mwila', '', '123456/10/1', '15/03/1990', 'Female',
            '0977123456', 'chanda@example.com', 'Accountant', 'Finance', 'Lusaka', 'Permanent', '01/02/2024',
            '8500', 'Zanaco', '0123456789012', '', '', '', '24'];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'zampayroll-employees-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function check(array $row, array &$seen, int $index): array
    {
        [$clean, $warnings] = $this->hr->normaliseEmployee($row);
        $status = $warnings ? 'warning' : 'ready';
        $note = $warnings ? ucfirst(implode(', ', $warnings)) : 'Looks good';
        $existing = null;

        if (empty($clean['first_name']) || empty($clean['last_name'])) {
            return ['data' => $row, 'status' => 'error', 'note' => 'First and last name are needed'];
        }

        $key = strtolower(($clean['nrc_no'] ?? '') ?: ($clean['first_name'] . ' ' . $clean['last_name']));
        if (isset($seen[$key])) {
            $status = 'warning';
            $note = 'Appears twice in your list (row ' . ($seen[$key] + 1) . ')';
        }
        $seen[$key] = $seen[$key] ?? $index;

        if ($dup = $this->hr->findDuplicate($clean)) {
            $status = 'duplicate';
            $note = "Already on your team as {$dup->first_name} {$dup->last_name} ({$dup->employee_id})";
            $existing = $dup->id;
        }

        return ['data' => $row, 'status' => $status, 'note' => $note, 'existing' => $existing];
    }
}
