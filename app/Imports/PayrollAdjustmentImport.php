<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollRule;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Services\PayrollEngine;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PayrollAdjustmentImport implements ToCollection, WithHeadingRow
{
    protected $run;
    protected $ruleId;

    public function __construct(
        PayrollRun $run,
        int $ruleId
    ) {
        $this->run = $run;
        $this->ruleId = $ruleId;
    }

    public function collection(Collection $rows)
    {
        $rule = PayrollRule::findOrFail(
            $this->ruleId
        );

        $engine = app(PayrollEngine::class);

        foreach ($rows as $row) {

            /*
            |--------------------------------------------------------------------------
            | READ CSV COLUMNS BY HEADER NAME
            |--------------------------------------------------------------------------
            */

            $employeeNumber = trim(
                (string) ($row['employee_number'] ?? '')
            );

            $employeeName = trim(
                (string) ($row['employee_name'] ?? '')
            );

            $amount = $row['amount'] ?? null;


            /*
            |--------------------------------------------------------------------------
            | SKIP EMPTY ROWS
            |--------------------------------------------------------------------------
            */

            if ($employeeNumber === '') {
                continue;
            }

            if ($amount === null || $amount === '' || !is_numeric($amount)) {
                continue;
            }

            $amount = (float) $amount;

            if ($amount <= 0) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | FIND EMPLOYEE USING EMPLOYEE NUMBER
            |--------------------------------------------------------------------------
            |
            | CSV:
            |
            | employee_number = EMP001
            |
            | Database:
            |
            | employees.id = 15
            | employees.employee_id = EMP001
            |
            |--------------------------------------------------------------------------
            */

            $employee = Employee::where(
                'employee_id',
                $employeeNumber
            )->first();


            /*
            |--------------------------------------------------------------------------
            | EMPLOYEE NOT FOUND
            |--------------------------------------------------------------------------
            */

            if (!$employee) {

                \Log::warning(
                    'Payroll adjustment import: employee not found',
                    [
                        'employee_number' => $employeeNumber,
                        'payroll_run_id' => $this->run->id,
                    ]
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | CREATE PAYROLL ADJUSTMENT
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Use $employee->id here, NOT $employeeNumber.
            |
            |--------------------------------------------------------------------------
            */

            PayrollRunAdjustment::create([

                'payroll_run_id' => $this->run->id,

                'employee_id' => $employee->id,

                'payroll_rule_id' => $rule->id,

                'name' => $rule->name,

                'type' => $rule->type,

                'formula_type' => $rule->formula_type,

                'value' => $amount,

                'tax_profile' => $rule->tax_profile,

                'active' => true,
            ]);


            /*
            |--------------------------------------------------------------------------
            | FIND PAYROLL RECORD
            |--------------------------------------------------------------------------
            */

            $payroll = Payroll::where(
                'payroll_run_id',
                $this->run->id
            )
            ->where(
                'employee_id',
                $employee->id
            )
            ->first();


            /*
            |--------------------------------------------------------------------------
            | REBUILD PAYROLL
            |--------------------------------------------------------------------------
            */

            if ($payroll) {
                $engine->build($payroll);
            }
        }
    }
}