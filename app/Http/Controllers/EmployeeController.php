<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Employee;
use App\Services\EmployeePortalService;
use App\Services\HrActions;
use App\Services\PayrollEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /** Everything the add/edit journey can save (photo & documents handled separately). */
    private const FORM_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'nrc_no', 'passport_number', 'personal_email', 'company_email', 'primary_phone', 'secondary_phone',
        'position', 'department', 'branch', 'supervisor', 'employment_nature', 'employment_status',
        'probation_start', 'probation_end', 'contract_start', 'contract_end',
        'emergency_name', 'emergency_relationship', 'emergency_phone',
        'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_address',
        'bank_name', 'bank_branch', 'bank_sort_code', 'bank_account_no',
        'ssn', 'nhima_no', 'tpin', 'net_salary', 'leave_days_entitled', 'leave_days_balance',
    ];

    public const NATURES = ['PERMANENT' => 'Permanent', 'CONTRACT' => 'Contract', 'PROBATION' => 'Probation', 'CASUAL' => 'Casual', 'INTERN' => 'Intern / attachment'];

    /*
    |--------------------------------------------------------------------------
    | LIST ALL EMPLOYEES
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $employees = Employee::with('user')->latest()->get();

        return view('dashboard.employees.index', compact('employees'));
    }

    /*
    |--------------------------------------------------------------------------
    | ADD EMPLOYEE — step-by-step journey
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('dashboard.employees.create', $this->formData(new Employee([
            'nationality'         => 'Zambian',
            'employment_nature'   => 'PERMANENT',
            'employment_status'   => 'Active',
            'leave_days_entitled' => 24,
        ])));
    }

    public function store(Request $request, EmployeePortalService $portal)
    {
        $data = $request->validate($this->rules());

        $employee = new Employee();
        $this->fillFromForm($employee, $data, $request);
        $employee->employee_id = filled($request->employee_id) && ! Employee::where('employee_id', $request->employee_id)->exists()
            ? trim($request->employee_id)
            : app(HrActions::class)->nextEmployeeNumber();
        $employee->employment_status = $employee->employment_status ?: 'Active';
        $employee->employment_nature = $employee->employment_nature ?: 'PERMANENT';
        $employee->nationality = $employee->nationality ?: 'Zambian';
        $employee->leave_days_entitled = $employee->leave_days_entitled ?? 24;
        $employee->save();

        $this->storeFiles($employee, $request);
        $this->primeDerivedSalaryValues($employee);

        $message = "🎉 {$employee->first_name} is on the team! Employee number {$employee->employee_id}.";
        $message .= $this->maybeCreatePortal($employee, $request, $portal);
        if ($request->input('intent') === 'later') {
            $message .= ' Saved the basics — the checklist on the right shows what\'s still missing.';
        }

        return redirect()->route('employees.show', $employee)->with('success', $message)->with('celebrate', true);
    }

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */
    public function show(Employee $employee)
    {
        $employee->load(['user']);

        $payslips = $employee->payrolls()->with('run')->latest('id')->take(12)->get();
        $leave = $employee->leaveRequests()->latest('start_date')->take(12)->get();
        $overtime = $employee->overtimeRequests()->latest('date')->take(8)->get();
        $loans = $employee->loans()->latest('id')->get();
        $since = now()->subDays(30)->startOfDay();
        $attendance = $employee->attendances()->where('date', '>=', $since)->orderByDesc('date')->get();

        $attendanceSummary = $attendance->groupBy(fn ($a) => strtolower((string) $a->status))->map->count();

        return view('dashboard.employees.show', [
            'employee'          => $employee,
            'completeness'      => $employee->profileCompleteness(),
            'payslips'          => $payslips,
            'leave'             => $leave,
            'overtime'          => $overtime,
            'loans'             => $loans,
            'attendance'        => $attendance,
            'attendanceSummary' => $attendanceSummary,
            'lastPayslip'       => $payslips->first(),
            'activeLoanBalance' => (float) $loans->whereIn('status', ['active', 'ACTIVE', 'Active'])->sum('balance') ?: (float) $employee->total_loan_balance,
            'leaveTaken'        => (float) $leave->where('status', 'approved')->filter(fn ($l) => optional($l->start_date)->year === now()->year)->sum('days'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT — same journey, pre-filled
    |--------------------------------------------------------------------------
    */
    public function edit(Request $request, Employee $employee)
    {
        return view('dashboard.employees.edit', $this->formData($employee) + [
            'startStep' => $request->query('step'),
        ]);
    }

    public function update(Request $request, Employee $employee, EmployeePortalService $portal)
    {
        $data = $request->validate($this->rules($employee));

        $this->fillFromForm($employee, $data, $request);
        if (filled($request->employee_id)) {
            $employee->employee_id = trim($request->employee_id);
        }
        $employee->employment_status = $employee->employment_status ?: 'Active';
        $employee->employment_nature = $employee->employment_nature ?: 'PERMANENT';
        $employee->leave_days_entitled = $employee->leave_days_entitled ?? 24;

        // Remove documents the user ticked
        if ($remove = (array) $request->input('remove_documents', [])) {
            $uploads = collect($employee->uploads ?? [])->reject(fn ($u, $i) => in_array((string) $i, $remove, true))->values()->all();
            $employee->uploads = $uploads;
        }
        $employee->save();

        $this->storeFiles($employee, $request);
        $this->primeDerivedSalaryValues($employee);

        $message = "Saved {$employee->first_name}'s profile.";
        $message .= $this->maybeCreatePortal($employee, $request, $portal);

        return redirect()->route('employees.show', $employee)->with('success', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Employee $employee = null): array
    {
        return [
            'employee_id'            => ['nullable', 'string', 'max:40', $employee
                ? \Illuminate\Validation\Rule::unique('employees', 'employee_id')->ignore($employee->id)->where('company_id', $employee->company_id)
                : 'string'],
            'first_name'             => 'required|string|max:80',
            'middle_name'            => 'nullable|string|max:80',
            'last_name'              => 'required|string|max:80',
            'date_of_birth'          => 'nullable|date|before:today',
            'gender'                 => 'nullable|in:Male,Female',
            'nationality'            => 'nullable|string|max:60',
            'nrc_no'                 => 'nullable|string|max:30',
            'passport_number'        => 'nullable|string|max:30',
            'personal_email'         => 'nullable|email|max:120',
            'company_email'          => 'nullable|email|max:120',
            'primary_phone'          => 'nullable|string|max:30',
            'secondary_phone'        => 'nullable|string|max:30',
            'position'               => 'nullable|string|max:100',
            'department'             => 'nullable|string|max:100',
            'branch'                 => 'nullable|string|max:100',
            'supervisor'             => 'nullable|string|max:120',
            'employment_nature'      => 'nullable|in:' . implode(',', array_keys(self::NATURES)),
            'employment_status'      => 'nullable|in:Active,Inactive,Suspended,Terminated',
            'probation_start'        => 'nullable|date',
            'probation_end'          => 'nullable|date',
            'contract_start'         => 'nullable|date',
            'contract_end'           => 'nullable|date',
            'emergency_name'         => 'nullable|string|max:120',
            'emergency_relationship' => 'nullable|string|max:60',
            'emergency_phone'        => 'nullable|string|max:30',
            'next_of_kin_name'       => 'nullable|string|max:120',
            'next_of_kin_phone'      => 'nullable|string|max:30',
            'next_of_kin_address'    => 'nullable|string|max:255',
            'bank_name'              => 'nullable|string|max:100',
            'bank_branch'            => 'nullable|string|max:100',
            'bank_sort_code'         => 'nullable|string|max:20',
            'bank_account_no'        => 'nullable|string|max:40',
            'ssn'                    => 'nullable|string|max:30',
            'nhima_no'               => 'nullable|string|max:30',
            'tpin'                   => 'nullable|string|max:20',
            'net_salary'             => 'nullable|numeric|min:0',
            'leave_days_entitled'    => 'nullable|numeric|min:0|max:366',
            'leave_days_balance'     => 'nullable|numeric|min:-366|max:366',
            'passport_photo'         => 'nullable|image|max:4096',
            'documents'              => 'nullable|array|max:10',
            'documents.*'            => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'create_portal'          => 'nullable|boolean',
            'intent'                 => 'nullable|in:save,later',
        ];
    }

    private function fillFromForm(Employee $employee, array $data, Request $request): void
    {
        foreach (self::FORM_FIELDS as $f) {
            if (! $request->exists($f)) {
                continue; // keep what's stored if the field wasn't on the form
            }
            $v = $data[$f] ?? null;
            $employee->{$f} = is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v);
        }
        foreach (['net_salary', 'leave_days_entitled', 'leave_days_balance'] as $n) {
            if ($employee->{$n} !== null) {
                $employee->{$n} = (float) $employee->{$n};
            }
        }
        if ($employee->bank_name && ! $employee->bank_sort_code && ($bank = Bank::findMatch($employee->bank_name))) {
            $employee->bank_sort_code = $bank->sort_code;
        }
    }

    private function storeFiles(Employee $employee, Request $request): void
    {
        $dirty = false;
        if ($request->hasFile('passport_photo')) {
            $employee->passport_photo = $request->file('passport_photo')->store('uploads/employees/passports', 'public');
            $dirty = true;
        }
        if ($request->hasFile('documents')) {
            $uploads = $employee->uploads ?? [];
            foreach ($request->file('documents') as $file) {
                $uploads[] = [
                    'name'        => $file->getClientOriginalName(),
                    'path'        => $file->store('uploads/employees/documents', 'public'),
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
            $employee->uploads = $uploads;
            $dirty = true;
        }
        if ($dirty) {
            $employee->save();
        }
    }

    private function maybeCreatePortal(Employee $employee, Request $request, EmployeePortalService $portal): string
    {
        if (! $request->boolean('create_portal') || $employee->user()->exists() || ! $request->user()->hasPermission('portal.manage')) {
            return '';
        }
        if ($problem = $portal->eligibilityProblem($employee)) {
            return " (Portal not created: they {$problem}.)";
        }
        try {
            [$user] = $portal->grant($employee, $request->user());

            return " Portal login sent to {$user->email}.";
        } catch (\Throwable $e) {
            report($e);

            return ' (We couldn\'t send the portal email — try again from their profile.)';
        }
    }

    private function formData(Employee $employee): array
    {
        $distinct = fn (string $col) => Employee::query()->whereNotNull($col)->where($col, '!=', '')->distinct()->orderBy($col)->pluck($col);

        return [
            'employee'    => $employee,
            'departments' => $distinct('department'),
            'branches'    => $distinct('branch'),
            'positions'   => $distinct('position'),
            'people'      => Employee::query()->when($employee->exists, fn ($q) => $q->whereKeyNot($employee->id))
                ->orderBy('first_name')->get(['first_name', 'last_name'])->map(fn ($e) => trim("{$e->first_name} {$e->last_name}")),
            'banks'       => Bank::query()->active()->orderBy('name')->get(['name', 'sort_code']),
            'natures'     => self::NATURES,
            'nextNumber'  => $employee->employee_id ?: app(HrActions::class)->nextEmployeeNumber(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE EMPLOYEE
    |--------------------------------------------------------------------------
    */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully');
    }



    
public function downloadSampleCsv()
{
    $headers = [
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nationality',
        'nrc_no',
        'primary_phone',
        'personal_email',
        'position',
        'department',
        'branch',
        'supervisor',
        'contract_start',
        'contract_end',
        'bank_name',
        'bank_account_no',
        'ssn',
        'nhima_no',
        'tpin',
        'net_salary',
        'leave_days_entitled',
        'leave_days_balance',
    ];

    $filename = 'employee_import_template.csv';

    $callback = function () use ($headers) {
        $file = fopen('php://output', 'w');

        // Header row
        fputcsv($file, $headers);

        // Sample row
        fputcsv($file, [
            'EMP001',
            'John',
            'Michael',
            'Doe',
            '1995-06-10',
            'Male',
            'Zambian',
            '123456/78/9',
            '0977000000',
            'john@example.com',
            'Accountant',
            'Finance',
            'Head Office',
            'Jane Manager',
            '2025-04-01',
            '2027-04-01',
            'Zanaco',
            '1234567890',
            'SSN12345',
            'NHIMA98765',
            'TIN98765',
            '5000',
            '24',
            '24',
        ]);

        fclose($file);
    };

    return response()->streamDownload(
        $callback,
        $filename,
        ['Content-Type' => 'text/csv']
    );
}

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt',
    ]);

    $file = fopen($request->file('file')->getRealPath(), 'r');

    if (!$file) {
        return back()->with('error', 'Unable to open the CSV file.');
    }

    /*
    |--------------------------------------------------------------------------
    | READ HEADER
    |--------------------------------------------------------------------------
    */

    $header = fgetcsv($file);

    if (!$header) {
        fclose($file);

        return back()->with('error', 'The CSV file is empty.');
    }

    // Remove BOM and whitespace from headers
    $header = array_map(function ($value) {
        return trim(str_replace("\xEF\xBB\xBF", '', $value));
    }, $header);

    /*
    |--------------------------------------------------------------------------
    | REQUIRED CSV HEADERS
    |--------------------------------------------------------------------------
    */

    $requiredHeaders = [
        'employee_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'position',
        'net_salary',
    ];

    $missingHeaders = array_diff($requiredHeaders, $header);

    if (!empty($missingHeaders)) {
        fclose($file);

        return back()->with(
            'error',
            'The CSV is missing the following required columns: ' .
            implode(', ', $missingHeaders)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EMPLOYEES
    |--------------------------------------------------------------------------
    */

    $imported = 0;
    $failed = 0;
    $errors = [];

    $rowNumber = 1;

    while (($row = fgetcsv($file)) !== false) {

        $rowNumber++;

        // Skip completely empty rows
        if (
            empty(array_filter($row, function ($value) {
                return trim((string) $value) !== '';
            }))
        ) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | PROTECT AGAINST INCORRECT COLUMN COUNT
        |--------------------------------------------------------------------------
        */

        if (count($header) !== count($row)) {

            $failed++;

            $errors[] = "Row {$rowNumber}: Number of columns does not match the CSV header.";

            continue;
        }

        $data = array_combine($header, $row);

        /*
        |--------------------------------------------------------------------------
        | CLEAN DATA
        |--------------------------------------------------------------------------
        */

        $data = array_map(function ($value) {
            return is_string($value)
                ? trim($value)
                : $value;
        }, $data);

        /*
        |--------------------------------------------------------------------------
        | VALIDATE REQUIRED VALUES
        |--------------------------------------------------------------------------
        */

        $requiredFields = [
            'employee_id',
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'position',
            'net_salary',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (
                !isset($data[$field]) ||
                trim((string) $data[$field]) === ''
            ) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {

            $failed++;

            $errors[] =
                "Row {$rowNumber}: Missing required fields: " .
                implode(', ', $missingFields);

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE DATE OF BIRTH
        |--------------------------------------------------------------------------
        */

        try {

            if (!empty($data['date_of_birth'])) {
                \Carbon\Carbon::parse($data['date_of_birth']);
            }

        } catch (\Throwable $e) {

            $failed++;

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): Invalid date of birth.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE SALARY
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($data['net_salary']) ||
            (float) $data['net_salary'] < 0
        ) {

            $failed++;

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): Net salary must be a valid number.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMPLOYEE ID
        |--------------------------------------------------------------------------
        */

        $existingEmployee = Employee::where(
            'employee_id',
            $data['employee_id']
        )->first();

        if ($existingEmployee) {

            $failed++;

            $errors[] =
                "Row {$rowNumber}: Employee ID '{$data['employee_id']}' already exists.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | LEAVE DAYS
        |--------------------------------------------------------------------------
        */

        $leaveDaysEntitled = (
            isset($data['leave_days_entitled']) &&
            $data['leave_days_entitled'] !== ''
        )
            ? (float) $data['leave_days_entitled']
            : null;

        $leaveDaysBalance = (
            isset($data['leave_days_balance']) &&
            $data['leave_days_balance'] !== ''
        )
            ? (float) $data['leave_days_balance']
            : $leaveDaysEntitled;

        /*
        |--------------------------------------------------------------------------
        | CREATE EMPLOYEE
        |--------------------------------------------------------------------------
        */

        try {

            $employee = Employee::create([

                /*
                |--------------------------------------------------------------------------
                | IDENTIFICATION
                |--------------------------------------------------------------------------
                */

                // IMPORTANT:
                // Employee ID comes directly from the CSV.
                'employee_id' => $data['employee_id'] ?? 'EMP-' . date('Y') . '-' . strtoupper(Str::random(5)),

                /*
                |--------------------------------------------------------------------------
                | PERSONAL INFORMATION
                |--------------------------------------------------------------------------
                */

                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],

                'date_of_birth' => !empty($data['date_of_birth'])
                    ? $data['date_of_birth']
                    : null,

                'gender' => $data['gender'] ?? null,

                'nationality' => !empty($data['nationality'])
                    ? $data['nationality']
                    : 'Zambian',

                'nrc_no' => $data['nrc_no'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | CONTACT
                |--------------------------------------------------------------------------
                */

                'primary_phone' => $data['primary_phone'] ?? null,
                'personal_email' => $data['personal_email'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | JOB
                |--------------------------------------------------------------------------
                */

                'position' => $data['position'],
                'department' => $data['department'] ?? null,
                'branch' => $data['branch'] ?? null,
                'supervisor' => $data['supervisor'] ?? null,

                // Automatically set new imports as Active
                'employment_status' => 'Active',

                /*
                |--------------------------------------------------------------------------
                | EMPLOYMENT DATES
                |--------------------------------------------------------------------------
                */

                'contract_start' => $data['contract_start'] ?? null,
                'contract_end' => $data['contract_end'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | FINANCE
                |--------------------------------------------------------------------------
                */

                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,

                'ssn' => $data['ssn'] ?? null,
                'nhima_no' => $data['nhima_no'] ?? null,
                'tpin' => $data['tpin'] ?? null,

                'net_salary' => (float) $data['net_salary'],

                /*
                |--------------------------------------------------------------------------
                | LEAVE
                |--------------------------------------------------------------------------
                */

                'leave_days_entitled' => $leaveDaysEntitled,
                'leave_days_balance' => $leaveDaysBalance,

                /*
                |--------------------------------------------------------------------------
                | SYSTEM FIELDS
                |--------------------------------------------------------------------------
                */

                'passport_photo' => null,
                'uploads' => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PRIME DERIVED SALARY VALUES
            |--------------------------------------------------------------------------
            */

            $this->primeDerivedSalaryValues($employee);

            $imported++;

        } catch (\Throwable $e) {

            $failed++;

            \Log::error('Employee CSV import failed', [
                'row' => $rowNumber,
                'employee_id' => $data['employee_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): " .
                $e->getMessage();

            continue;
        }
    }

    fclose($file);

    /*
    |--------------------------------------------------------------------------
    | IMPORT RESULT
    |--------------------------------------------------------------------------
    */

    if ($failed > 0) {

        $message =
            "{$imported} employee(s) imported successfully. " .
            "{$failed} row(s) could not be imported.";

        if (!empty($errors)) {
            $message .= ' Errors: ' . implode(' | ', $errors);
        }

        return back()->with(
            $imported > 0 ? 'warning' : 'error',
            $message
        );
    }

    return back()->with(
        'success',
        "{$imported} employee(s) imported successfully."
    );
}

    /*
    |--------------------------------------------------------------------------
    | PRIME DERIVED SALARY VALUES (natural_gross_salary, daily_rate_salary,
    | leave_days_value / leave_days_balance seeds) via PayrollEngine.
    |
    | NOTE: PayrollEngine::primeEmployeeInitialValues() reads
    | $employee->net_salary as its target net figure. This form's "salary"
    | field is what's saved to the `salary` column — if your schema uses a
    | separate `net_salary` column set elsewhere (e.g. a payroll setup
    | screen), priming will use that value as-is. If `net_salary` isn't
    | populated yet for an employee, priming is skipped for that employee
    | (see the guard inside PayrollEngine) rather than guessing at a value.
    |--------------------------------------------------------------------------
    */
    private function primeDerivedSalaryValues(Employee $employee): void
    {
        try {
            (new PayrollEngine())->primeEmployeeInitialValues($employee);
        } catch (\Throwable $e) {
            \Log::warning('EmployeeController: failed to prime derived salary values', [
                'employee_id' => $employee->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

}