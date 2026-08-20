<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class PayrollRun extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'period',
        'status',
        'total_income',
        'total_deductions',
        'net_pay',
        'finalized_at',
        'finalized_by',
        'alias',
        'created_by',
        'audited_by',
        'audited_at',
    ];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function finalizedBy()
{
    return $this->belongsTo(User::class, 'finalized_by');
}

public function creator()
{
    return $this->belongsTo(User::class, 'created_by');
}

    public function scopeVisible($query)
    {
        return $query->whereNull('hidden_at');
    }

    public function scopeHiddenOnly($query)
    {
        return $query->whereNotNull('hidden_at');
    }
}