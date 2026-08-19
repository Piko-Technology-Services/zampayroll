<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    public const TYPES = [
        'deduction'      => 'Payroll Deduction',
        'manual_payment' => 'Manual Payment',
        'adjustment'     => 'Balance Adjustment',
        'write_off'      => 'Write-off',
    ];

    protected $fillable = [
        'loan_id',
        'payroll_id',
        'amount',
        'balance_after',
        'type',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
