<?php

namespace App\Services\Statutory;

use App\Models\Company;
use App\Models\PayrollRule;
use App\Models\PayrollRun;
use App\Models\StatutoryReturn;
use App\Services\PayrollEngine;
use App\Support\Audit;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * NAPSA, NHIMA and ZRA PAYE returns.
 *
 * Figures come from the payroll lines (D00 PAYE, D01 NAPSA, D02 NHIMA). A return covers one
 * pay run, or the whole month (every approved run in it, added up per employee — the
 * authorities expect one line per employee per month). Before downloading, HR can review
 * the grid, fix cells, add or remove lines and save; the saved version is what gets exported
 * until it's reset to the payroll figures.
 */
class StatutoryReturns
{
    public const KINDS = [
        'napsa' => ['label' => 'NAPSA — iCARE returns', 'short' => 'NAPSA', 'format' => 'csv', 'header' => true],
        'nhima' => ['label' => 'NHIMA — health insurance returns', 'short' => 'NHIMA', 'format' => 'csv', 'header' => false],
        'zra'   => ['label' => 'ZRA — PAYE returns', 'short' => 'ZRA PAYE', 'format' => 'xlsx', 'header' => true],
    ];

    /** Column definitions: key => [label (as in the upload file), type, editable]. */
    public function columns(string $kind): array
    {
        return match ($kind) {
            'napsa' => [
                'employer'  => ['company napsa number', 'text'], 'year' => ['year', 'int'], 'month' => ['month', 'int'],
                'ssn' => ['ssn', 'text'], 'nrc' => ['nrc', 'text'], 'last' => ['lastName', 'text'], 'first' => ['first name', 'text'],
                'middle' => ['middle name', 'text'], 'dob' => ['dob', 'text'], 'gross' => ['gross', 'money'],
                'employee' => ['employee tax payable', 'money'], 'company' => ['company tax payable', 'money'], 'flag' => ['', 'text'],
            ],
            'nhima' => [
                'employer'  => ['nhima employer number', 'text'], 'year' => ['year', 'int'], 'month' => ['month', 'int'],
                'ssn' => ['ssn', 'text'], 'nrc' => ['nrc', 'text'], 'last' => ['lastName', 'text'], 'first' => ['first name', 'text'],
                'middle' => ['middle name', 'text'], 'dob' => ['dob', 'text'], 'gross' => ['gross', 'money'],
                'employee' => ['employee contribution', 'money'], 'company' => ['company contribution', 'money'], 'flag' => ['', 'text'],
            ],
            'zra' => [
                'tpin' => ['tpin', 'text'], 'name' => ['fullName', 'text'], 'nature' => ['employmentNature', 'text'],
                'gross' => ['grossEmoluments', 'money'], 'chargeable' => ['chargeableEmoluments', 'money'],
                'credit' => ['totalTaxCredit', 'money'], 'tax' => ['taxDeducted', 'money'], 'adjusted' => ['taxAdjusted', 'money'],
            ],
        };
    }

    /** Pay runs a return covers. */
    public function runsFor(PayrollRun $run, string $scope): Collection
    {
        if ($scope !== 'month' || ! $run->period_date) {
            return collect([$run]);
        }

        $month = $run->period_date->format('Y-m');

        return PayrollRun::query()->get()
            ->filter(fn ($r) => $r->period_date && $r->period_date->format('Y-m') === $month && ($r->isLocked() || $r->id === $run->id))
            ->sortBy('id')->values();
    }

    public function scopeKey(PayrollRun $run, string $scope): string
    {
        return $scope === 'month' && $run->period_date ? 'month:' . $run->period_date->format('Y-m') : 'run:' . $run->id;
    }

    /** Fresh figures from payroll, one line per employee, plus per-line checks. */
    public function generate(string $kind, Collection $runs): array
    {
        $company = Tenancy::company() ?? Company::on(Tenancy::landlordConnection())->find($runs->first()?->company_id);
        $period = $runs->first()?->period_date ?? now();
        $rules = PayrollRule::query()->get()->keyBy('code');
        $nonChargeable = $rules->filter(fn ($r) => $r->type === 'earning' && in_array($r->tax_profile, ['non_taxable', 'napsa_only'], true) && ! in_array($r->code, ['P00', 'P01', 'P02', 'P03'], true))->keys()->all();
        $notPensionable = $rules->filter(fn ($r) => $r->type === 'earning' && $r->tax_profile === 'non_taxable' && ! in_array($r->code, ['P00', 'P01', 'P02', 'P03'], true))->keys()->all();
        $napsaRate = (float) ($rules['D01']->value ?? 5);
        $nhimaRate = (float) ($rules['D02']->value ?? 1);
        $napsaCap = (float) ($rules['SYS01']->value ?? 0);
        $engine = app(PayrollEngine::class);

        $payrolls = $runs->flatMap(fn ($r) => $r->payrolls()->with(['employee', 'items'])->get());
        $byEmployee = $payrolls->filter(fn ($p) => $p->employee && (float) $p->total_income > 0)->groupBy('employee_id');

        $rows = [];
        foreach ($byEmployee as $employeeId => $list) {
            $e = $list->first()->employee;
            $items = $list->flatMap->items;
            $sum = fn ($code) => round((float) $items->where('code', $code)->sum('amount'), 2);
            $gross = round((float) $list->sum('total_income'), 2);
            $basic = $sum('P00');
            $chargeable = round($gross - (float) $items->where('type', 'earning')->whereIn('code', $nonChargeable)->sum('amount'), 2);
            $napsa = $sum('D01');
            $nhima = $sum('D02');
            $paye = $sum('D00');
            $dob = $e->date_of_birth ? Carbon::parse($e->date_of_birth)->format('d/m/Y') : '';

            $cells = match ($kind) {
                'napsa' => ['employer' => (string) ($company?->napsa_employer_number ?? ''), 'year' => $period->year, 'month' => $period->month,
                    'ssn' => (string) ($e->ssn ?? ''), 'nrc' => (string) ($e->nrc_no ?? ''), 'last' => (string) $e->last_name, 'first' => (string) $e->first_name,
                    'middle' => (string) ($e->middle_name ?? ''), 'dob' => $dob, 'gross' => $gross, 'employee' => $napsa, 'company' => $napsa, 'flag' => 'I'],
                'nhima' => ['employer' => (string) ($company?->nhima_employer_number ?? ''), 'year' => $period->year, 'month' => $period->month,
                    'ssn' => (string) ($e->ssn ?? ''), 'nrc' => (string) ($e->nrc_no ?? ''), 'last' => (string) $e->last_name, 'first' => (string) $e->first_name,
                    'middle' => (string) ($e->middle_name ?? ''), 'dob' => $dob, 'gross' => $gross, 'employee' => $nhima, 'company' => $nhima, 'flag' => 'I'],
                'zra' => ['tpin' => (string) ($e->tpin ?? ''), 'name' => strtoupper(trim($e->first_name . ' ' . $e->last_name)),
                    'nature' => strtoupper((string) ($e->employment_nature ?: 'PERMANENT')), 'gross' => $gross, 'chargeable' => $chargeable,
                    'credit' => 0, 'tax' => $paye, 'adjusted' => 0],
            };

            // Checks
            $checks = [];
            if ($kind === 'napsa') {
                foreach (['ssn' => 'NAPSA SSN', 'nrc' => 'NRC', 'dob' => 'date of birth'] as $f => $l) {
                    if ($cells[$f] === '') {
                        $checks[] = ['col' => $f, 'level' => 'fix', 'msg' => "No {$l}"];
                    }
                }
                $pensionable = $gross - (float) $items->where('type', 'earning')->whereIn('code', $notPensionable)->sum('amount');
                $expected = round(min($pensionable * $napsaRate / 100, $napsaCap > 0 ? $napsaCap * $list->count() : PHP_FLOAT_MAX), 2);
                if (abs($expected - $napsa) > 0.05 * $list->count()) {
                    $checks[] = ['col' => 'employee', 'level' => 'check', 'msg' => 'Expected about K ' . number_format($expected, 2) . " ({$napsaRate}% of pensionable pay)"];
                }
            } elseif ($kind === 'nhima') {
                foreach (['nrc' => 'NRC', 'ssn' => 'SSN'] as $f => $l) {
                    if ($cells[$f] === '') {
                        $checks[] = ['col' => $f, 'level' => 'fix', 'msg' => "No {$l}"];
                    }
                }
                $expected = round($basic * $nhimaRate / 100, 2);
                if (abs($expected - $nhima) > 0.05 * $list->count()) {
                    $checks[] = ['col' => 'employee', 'level' => 'check', 'msg' => 'Expected about K ' . number_format($expected, 2) . " ({$nhimaRate}% of basic pay)"];
                }
            } else {
                if ($cells['tpin'] === '') {
                    $checks[] = ['col' => 'tpin', 'level' => 'fix', 'msg' => 'No TPIN'];
                } elseif (! preg_match('/^\d{10}$/', $cells['tpin'])) {
                    $checks[] = ['col' => 'tpin', 'level' => 'check', 'msg' => 'TPIN should be 10 digits'];
                }
                $expected = round($engine->calculatePaye($chargeable), 2);
                if (abs($expected - $paye) > 0.05) {
                    $checks[] = ['col' => 'tax', 'level' => 'check', 'msg' => 'PAYE on K ' . number_format($chargeable, 2) . ' is K ' . number_format($expected, 2)
                        . ($list->count() > 1 ? ' for the month — pay runs taxed separately can under- or over-deduct' : '')];
                }
            }

            $rows[] = ['key' => 'e' . $employeeId, 'employee_id' => (int) $employeeId, 'label' => trim($e->first_name . ' ' . $e->last_name), 'cells' => $cells, 'checks' => $checks, 'runs' => $list->count()];
        }

        usort($rows, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $rows;
    }

    /** Saved (edited) version for this return, if any. */
    public function saved(string $kind, PayrollRun $run, string $scope): ?StatutoryReturn
    {
        return StatutoryReturn::query()->where('kind', $kind)->where('scope_key', $this->scopeKey($run, $scope))->first();
    }

    /**
     * What the preview shows and what gets exported: saved edits if there are any, otherwise the
     * payroll figures. Each cell carries the payroll value so edits can be highlighted.
     */
    public function current(string $kind, PayrollRun $run, string $scope): array
    {
        $runs = $this->runsFor($run, $scope);
        $fresh = collect($this->generate($kind, $runs))->keyBy('key');
        $saved = $this->saved($kind, $run, $scope);

        $rows = $saved ? collect($saved->rows)->map(function ($r) use ($fresh) {
            $f = $fresh->get($r['key']);
            $r['original'] = $f['cells'] ?? null;
            $r['checks'] = $f['checks'] ?? [];
            $r['label'] = $r['label'] ?? ($f['label'] ?? 'Added line');

            return $r;
        })->values() : $fresh->map(fn ($r) => $r + ['original' => $r['cells']])->values();

        // People paid since the edits were saved
        $missing = $saved ? $fresh->keys()->diff(collect($saved->rows)->pluck('key'))->diff(collect($saved->removed ?? []))->values() : collect();

        return [
            'kind'    => $kind,
            'meta'    => self::KINDS[$kind],
            'columns' => $this->columns($kind),
            'rows'    => $rows,
            'runs'    => $runs,
            'saved'   => $saved,
            'missing' => $missing->map(fn ($k) => $fresh[$k]['label'])->values(),
            'totals'  => $this->totals($kind, $rows),
            'period'  => $runs->first()?->period_date,
        ];
    }

    public function totals(string $kind, Collection $rows): array
    {
        $money = array_keys(array_filter($this->columns($kind), fn ($c) => $c[1] === 'money'));

        return collect($money)->mapWithKeys(fn ($k) => [$k => round((float) $rows->sum(fn ($r) => (float) ($r['cells'][$k] ?? 0)), 2)])->all();
    }

    /** Save HR's edited grid. $rows: [{key, label, cells:{col:value}}]; $removed: keys of payroll lines taken out. */
    public function save(string $kind, PayrollRun $run, string $scope, array $rows, array $removed, ?int $userId): StatutoryReturn
    {
        $cols = $this->columns($kind);
        $clean = collect($rows)->map(function ($r) use ($cols) {
            $cells = [];
            foreach ($cols as $k => [$label, $type]) {
                $v = trim((string) ($r['cells'][$k] ?? ''));
                $cells[$k] = match ($type) {
                    'money' => $v === '' ? 0 : round((float) str_replace([',', 'K', ' '], '', $v), 2),
                    'int'   => $v === '' ? '' : (int) $v,
                    default => mb_substr($v, 0, 120),
                };
            }

            return ['key' => (string) ($r['key'] ?? ('new' . bin2hex(random_bytes(4)))), 'label' => mb_substr((string) ($r['label'] ?? ''), 0, 120), 'employee_id' => $r['employee_id'] ?? null, 'cells' => $cells];
        })->values()->all();

        $record = StatutoryReturn::query()->updateOrCreate(
            ['kind' => $kind, 'scope_key' => $this->scopeKey($run, $scope)],
            ['payroll_run_id' => $run->id, 'period' => $run->period_date?->format('Y-m') ?? $run->period, 'rows' => $clean, 'removed' => array_values($removed), 'edited_by' => $userId]
        );
        Audit::record('statutory.edited', $record, self::KINDS[$kind]['short'] . ' return for ' . ($run->period_date?->format('F Y') ?? $run->period) . ' edited before export (' . count($clean) . ' lines)');

        return $record;
    }

    public function reset(string $kind, PayrollRun $run, string $scope): void
    {
        $this->saved($kind, $run, $scope)?->delete();
        Audit::record('statutory.reset', $run, self::KINDS[$kind]['short'] . ' return reset to payroll figures');
    }

    /** Download response for the return (saved edits included). */
    public function download(string $kind, PayrollRun $run, string $scope, bool $withHeader = null)
    {
        $data = $this->current($kind, $run, $scope);
        $cols = $data['columns'];
        $headerRow = array_map(fn ($c) => $c[0], array_values($cols));
        $withHeader ??= self::KINDS[$kind]['header'];
        $period = $data['period']?->format('Y-m') ?? $run->period;
        $suffix = str_starts_with($this->scopeKey($run, $scope), 'run:') && $data['runs']->count() === 1 && $this->runsFor($run, 'month')->count() > 1 ? '-run' . $run->id : '';
        $xlsx = self::KINDS[$kind]['format'] === 'xlsx';
        $lines = $data['rows']->map(fn ($r) => array_map(fn ($k) => $this->exportValue($cols[$k][1], $r['cells'][$k] ?? '', $xlsx), array_keys($cols)))->all();

        $saved = $data['saved'];
        if ($saved) {
            $saved->forceFill(['exported_at' => now()])->save();
        }
        Audit::record('statutory.exported', $run, self::KINDS[$kind]['short'] . " return downloaded for {$period}" . ($saved ? ' (with edits)' : ''));

        if (self::KINDS[$kind]['format'] === 'xlsx') {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('PayeeEmployeeDetails');
            $sheet->fromArray($headerRow, null, 'A1');
            foreach ($lines as $i => $line) {
                foreach (array_values($line) as $c => $v) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . ($i + 2);
                    // TPINs and names stay text; amounts stay numbers
                    is_float($v) || is_int($v) ? $sheet->setCellValue($cell, $v) : $sheet->setCellValueExplicit($cell, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);

            return response()->streamDownload(fn () => $writer->save('php://output'), "zra-paye-{$period}{$suffix}.xlsx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($lines, $headerRow, $withHeader) {
            $h = fopen('php://output', 'w');
            if ($withHeader) {
                fputcsv($h, $headerRow);
            }
            foreach ($lines as $line) {
                fputcsv($h, $line);
            }
            fclose($h);
        }, "{$kind}-returns-{$period}{$suffix}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Amounts as 2-decimal text in CSV ("1234.50") and numbers in Excel; IDs always text. */
    private function exportValue(string $type, $v, bool $xlsx)
    {
        return match ($type) {
            'money' => $xlsx ? round((float) $v, 2) : number_format((float) $v, 2, '.', ''),
            'int'   => $v === '' ? '' : (int) $v,
            default => (string) $v,
        };
    }
}
