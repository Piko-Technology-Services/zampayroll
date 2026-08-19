<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    public const STATUSES = [
        'active'      => 'Active',
        'completed'   => 'Completed',
        'paused'      => 'Paused',
        'written_off' => 'Written Off',
    ];

    protected $fillable = [
        'employee_id',
        'loan_request_id',
        'principal_amount',
        'balance',
        'payment_plan',
        'payment_plan_note',
        'installment_amount',
        'start_date',
        'next_deduction_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'principal_amount'    => 'decimal:2',
        'balance'             => 'decimal:2',
        'installment_amount'  => 'decimal:2',
        'start_date'          => 'date',
        'next_deduction_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function loanRequest()
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recompute an employee's cached total_loan_balance / has_active_loan
     * from their loans. Call this after ANY loan balance/status change.
     */
    public static function syncEmployeeCache(int $employeeId): void
    {
        $activeLoans = static::where('employee_id', $employeeId)->where('status', 'active');

        Employee::where('id', $employeeId)->update([
            'total_loan_balance' => (clone $activeLoans)->sum('balance'),
            'has_active_loan'    => (clone $activeLoans)->exists(),
        ]);
    }
}
