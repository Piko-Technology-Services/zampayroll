<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [

        'employee_id',
        'payroll_run_id',
        'pay_period',

        'company',
        'branch',
        'cost_centre',

        'date_engaged',

        'salary_rate',

        'total_income_ytd',
        'net_tax_ytd',
        'tax_ytd',
        'napsa_ytd',
        'leave_days',
        'leave_days_value',

        'total_income',
        'total_deductions',
        'net_pay',

        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

   public function run()
{
    return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
}



    /** True once the pay run has gone for approval (or is approved/paid). */
    public function isLocked(): bool
    {
        if (in_array($this->status, ['Approved', 'Paid'], true)) {
            return true;
        }

        return $this->run ? ! $this->run->isEditable() : false;
    }
}
