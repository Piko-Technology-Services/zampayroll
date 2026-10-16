<?php

namespace App\Services;

use App\Mail\LeaveDecided;
use App\Mail\OvertimeDecided;
use App\Models\Employee;
use App\Models\LeaveRecord;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The core HR operations, in one place, so the screens and AI mode
 * run exactly the same logic (same validation, ledgers and emails).
 */
class HrActions
{
    /* ------------------------------------------------------------------ */
    /*  Leave / overtime / loan decisions                                   */
    /* ------------------------------------------------------------------ */

    public function decideLeave(LeaveRequest $leave, string $status, ?string $comment, ?User $by): void
    {
        DB::transaction(function () use ($leave, $status, $comment, $by) {
            $leave->update([
                'status'      => $status,
                'hr_comment'  => $comment,
                'reviewed_by' => $by?->id,
                'reviewed_at' => now(),
            ]);

            if ($status === 'approved') {
                // The full day-count is attributed to the month the leave starts in.
                $employee = $leave->employee;

                $record = LeaveRecord::firstOrNew([
                    'employee_id' => $employee->id,
                    'year'        => $leave->start_date->year,
                    'month'       => $leave->start_date->month,
                ]);
                $record->days_taken = (float) ($record->days_taken ?? 0) + (float) $leave->days;
                $record->save();

                if ($employee->leave_days_balance !== null) {
                    $employee->decrement('leave_days_balance', $leave->days);
                }
            }
        });

        $this->mailEmployee($leave->employee, new LeaveDecided($leave));
    }

    public function decideOvertime(OvertimeRequest $overtime, string $status, ?string $comment, ?User $by): void
    {
        $overtime->update([
            'status'      => $status,
            'hr_comment'  => $comment,
            'reviewed_by' => $by?->id,
            'reviewed_at' => now(),
        ]);

        $this->mailEmployee($overtime->employee, new OvertimeDecided($overtime));
    }

    /**
     * Loan decisions keep going through the loan dashboard (it can create the ledger entry);
     * this only records the status for AI-mode rejections/approvals.
     */
    public function decideLoan(LoanRequest $loan, string $status, ?string $comment, ?User $by): void
    {
        $loan->update([
            'status'      => $status,
            'hr_comment'  => $comment,
            'reviewed_by' => $by?->id,
            'reviewed_at' => now(),
        ]);

        if (class_exists(\App\Mail\LoanDecided::class)) {
            $this->mailEmployee($loan->employee, new \App\Mail\LoanDecided($loan));
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Payroll                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Create (or refresh) the run for a month and calculate every active employee.
     * Returns the run. Finalised runs are never touched.
     */
    public function preparePayroll(string $period, ?string $alias, ?User $by): PayrollRun
    {
        $month = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        $existing = PayrollRun::where('period', $period)->latest('id')->first();
        if ($existing && ! $existing->isEditable()) {
            throw new \RuntimeException($existing->isInReview()
                ? "The {$month->format('F Y')} payroll is waiting for approval — recall it first to change it."
                : "The {$month->format('F Y')} payroll is already approved and locked.");
        }

        $run = $existing ?? PayrollRun::create([
            'period'     => $period,
            'alias'      => $alias ?: $month->format('F Y') . ' payroll',
            'created_by' => $by?->id,
            'status'     => PayrollRun::DRAFT,
        ]);

        $this->generatePayroll($run, $by);

        return $run->fresh();
    }

    /**
     * Calculate every active employee for a run. Loan repayments due this month
     * and (optionally) approved overtime are pulled in automatically. Loan
     * repayments are only posted to the loan ledger when the run is approved.
     */
    public function generatePayroll(PayrollRun $run, ?User $by = null): void
    {
        if (! $run->isEditable()) {
            throw new \RuntimeException($run->isInReview()
                ? 'This payroll is waiting for approval. Recall it to make changes.'
                : 'This payroll run is approved and locked.');
        }

        $engine = app(PayrollEngine::class);
        $loanService = app(LoanRepaymentService::class);
        $active = Employee::where('employment_status', 'Active')->get();

        // People who are no longer active drop out of an unapproved run.
        $run->payrolls()->whereNotIn('employee_id', $active->pluck('id'))->get()->each(function ($p) {
            $p->items()->delete();
            $p->delete();
        });

        foreach ($active as $employee) {
            $payroll = Payroll::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'pay_period'   => $run->period,
                    'company'      => \App\Support\Tenancy::company()?->name ?? 'ZAMPAYROLL',
                    'branch'       => $employee->branch,
                    'cost_centre'  => $employee->department,
                    'date_engaged' => $employee->contract_start,
                    'salary_rate'  => $employee->net_salary ?? 0,
                    'status'       => 'Draft',
                ]
            );

            // Loan deductions and overtime must exist before the engine builds the payslip.
            $loanService->syncForEmployee($run, $employee->id);
            $this->syncOvertime($run, $employee->id);
            $engine->build($payroll);
        }

        $run->update([
            'status'           => PayrollRun::CALCULATED,
            'total_income'     => $run->payrolls()->sum('total_income'),
            'total_deductions' => $run->payrolls()->sum('total_deductions'),
            'net_pay'          => $run->payrolls()->sum('net_pay'),
        ]);

        app(\App\Services\PayrollApprovals::class)->log($run, 'calculated', $by ?? auth()->user());
    }

    /**
     * Approved overtime dated inside the run's month becomes a taxable earning.
     * Idempotent: rewritten on every recalculation.
     */
    public function syncOvertime(PayrollRun $run, int $employeeId): void
    {
        PayrollRunAdjustment::where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->whereNotNull('overtime_request_id')
            ->delete();

        if (! $run->include_overtime || ! $run->period_date) {
            return;
        }

        $items = OvertimeRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('date', [$run->period_date->copy()->startOfMonth()->toDateString(), $run->period_date->copy()->endOfMonth()->toDateString()])
            ->get();

        foreach ($items as $ot) {
            if ((float) $ot->amount <= 0) {
                continue;
            }
            PayrollRunAdjustment::create([
                'payroll_run_id'      => $run->id,
                'employee_id'         => $employeeId,
                'overtime_request_id' => $ot->id,
                'name'                => 'Overtime · ' . $ot->date->format('j M') . ' (' . rtrim(rtrim(number_format((float) $ot->hours, 2), '0'), '.') . 'h)',
                'type'                => 'earning',
                'formula_type'        => 'fixed',
                'value'               => $ot->amount,
                'tax_profile'         => 'taxable',
                'active'              => true,
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Employees                                                           */
    /* ------------------------------------------------------------------ */

    public const EMPLOYEE_FIELDS = [
        'employee_id', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality',
        'nrc_no', 'passport_number', 'personal_email', 'company_email', 'primary_phone',
        'secondary_phone', 'position', 'department', 'branch', 'supervisor', 'employment_nature',
        'contract_start', 'contract_end', 'probation_end',
        'bank_name', 'bank_branch', 'bank_account_no', 'ssn', 'nhima_no', 'tpin',
        'salary', 'net_salary', 'leave_days_entitled', 'leave_days_balance',
        'emergency_name', 'emergency_phone', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_address',
    ];

    /**
     * Normalise one loosely-structured row (from AI, CSV or a paste).
     * Returns [cleanRow, warnings[]].
     */
    public function normaliseEmployee(array $row): array
    {
        $clean = [];
        $warnings = [];

        foreach (self::EMPLOYEE_FIELDS as $f) {
            $v = $row[$f] ?? null;
            if (is_string($v)) {
                $v = trim($v);
            }
            if ($v === '' || $v === null) {
                continue;
            }
            $clean[$f] = $v;
        }

        // Split a single "name" into first/last when needed.
        if (empty($clean['first_name']) && ! empty($row['name'])) {
            $parts = preg_split('/\s+/', trim($row['name']));
            $clean['first_name'] = array_shift($parts);
            $clean['last_name'] = array_pop($parts) ?? '';
            if ($parts) {
                $clean['middle_name'] = implode(' ', $parts);
            }
        }

        foreach (['salary', 'net_salary', 'leave_days_entitled', 'leave_days_balance'] as $money) {
            if (isset($clean[$money])) {
                $clean[$money] = (float) preg_replace('/[^\d.]/', '', (string) $clean[$money]);
            }
        }

        if (isset($clean['employment_nature'])) {
            $n = Str::upper($clean['employment_nature']);
            $clean['employment_nature'] = match (true) {
                str_contains($n, 'PERM') => 'PERMANENT',
                str_contains($n, 'CONTRACT') || str_contains($n, 'FIXED') => 'CONTRACT',
                str_contains($n, 'CASUAL') || str_contains($n, 'TEMP') => 'CASUAL',
                str_contains($n, 'PROB') => 'PROBATION',
                str_contains($n, 'INTERN') || str_contains($n, 'ATTACH') => 'INTERN',
                default => 'PERMANENT',
            };
        }

        if (isset($clean['employee_id']) && Employee::where('employee_id', $clean['employee_id'])->exists()) {
            $warnings[] = "employee number {$clean['employee_id']} already used — a new one will be given";
            unset($clean['employee_id']);
        }

        foreach (['date_of_birth', 'contract_start', 'contract_end', 'probation_end'] as $d) {
            if (isset($clean[$d])) {
                try {
                    $clean[$d] = $this->parseDate((string) $clean[$d]);
                } catch (\Throwable $e) {
                    unset($clean[$d]);
                    $warnings[] = "couldn't read the " . str_replace(['contract_start', 'contract_end', 'probation_end', 'date_of_birth'], ['start date', 'contract end date', 'probation end date', 'date of birth'], $d);
                }
            }
        }

        foreach (['personal_email', 'company_email'] as $e) {
            if (isset($clean[$e])) {
                $clean[$e] = Str::lower($clean[$e]);
                if (! filter_var($clean[$e], FILTER_VALIDATE_EMAIL)) {
                    unset($clean[$e]);
                    $warnings[] = ($e === 'company_email' ? 'work' : 'personal') . ' email looks wrong';
                }
            }
        }

        if (isset($clean['gender'])) {
            $g = Str::lower($clean['gender']);
            $clean['gender'] = str_starts_with($g, 'f') ? 'Female' : (str_starts_with($g, 'm') ? 'Male' : ucfirst($g));
        }

        if (empty($clean['first_name']) || empty($clean['last_name'])) {
            $warnings[] = 'missing first or last name';
        }
        if (empty($clean['net_salary']) && empty($clean['salary'])) {
            $warnings[] = 'no salary yet';
        }
        if (empty($clean['nrc_no'])) {
            $warnings[] = 'no NRC';
        }

        return [$clean, $warnings];
    }

    /**
     * Dates as people type them in Zambia: 15/03/1990, 15-03-1990, 1990-03-15, 15 Mar 1990,
     * or an Excel serial number (32947).
     */
    public function parseDate(string $v): string
    {
        $v = trim($v);

        if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (float) $v > 1000 && (float) $v < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) {
            $y = strlen($m[3]) === 2 ? (int) ('20' . $m[3]) : (int) $m[3];
            if ($y > (int) date('Y') + 1) {
                $y -= 100;
            }
            // day/month/year (Zambian convention); swap if the "month" can't be a month
            [$d, $mo] = (int) $m[2] > 12 ? [(int) $m[2], (int) $m[1]] : [(int) $m[1], (int) $m[2]];

            return Carbon::create($y, $mo, $d)->toDateString();
        }

        return Carbon::parse($v)->toDateString();
    }

    /**
     * Find an existing employee that matches (same NRC, email or full name).
     */
    public function findDuplicate(array $clean): ?Employee
    {
        return Employee::query()
            ->where(function ($q) use ($clean) {
                if (! empty($clean['nrc_no'])) {
                    $q->orWhere('nrc_no', $clean['nrc_no']);
                }
                foreach (['company_email', 'personal_email'] as $e) {
                    if (! empty($clean[$e])) {
                        $q->orWhere('company_email', $clean[$e])->orWhere('personal_email', $clean[$e]);
                    }
                }
                if (! empty($clean['first_name']) && ! empty($clean['last_name'])) {
                    $q->orWhere(fn ($w) => $w->where('first_name', $clean['first_name'])->where('last_name', $clean['last_name']));
                }
            })
            ->first();
    }

    public function createEmployee(array $clean): Employee
    {
        $employee = Employee::create(array_merge([
            'employee_id'         => $this->nextEmployeeNumber(),
            'employment_status'   => 'Active',
            'nationality'         => 'Zambian',
            'leave_days_entitled' => 24,
        ], $clean));

        try {
            (new PayrollEngine())->primeEmployeeInitialValues($employee);
        } catch (\Throwable $e) {
            report($e);
        }

        return $employee;
    }

    public function nextEmployeeNumber(): string
    {
        do {
            $ein = 'EMP-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Employee::where('employee_id', $ein)->exists());

        return $ein;
    }

    /* ------------------------------------------------------------------ */

    private function mailEmployee(?Employee $employee, $mailable): void
    {
        $to = $employee?->personal_email ?: $employee?->company_email;

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
