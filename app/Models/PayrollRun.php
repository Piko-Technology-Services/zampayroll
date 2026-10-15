<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class PayrollRun extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

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

    // audited_by is a fillable column but had no relation defined yet — added
    // so the tile view (and anything else) can show who audited a run.
    public function auditedBy()
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('hidden_at');
    }

    public function scopeHiddenOnly($query)
    {
        return $query->whereNotNull('hidden_at');
    }

    /**
     * `period` is free text ("2026-07", "July 2026", "2026-07-01", ...), not a
     * real date column. This parses it into a Carbon date (first of the month)
     * for year/month drill-down and sorting. Returns null if it can't be parsed,
     * so callers can fall back to created_at or flag the row for correction.
     */
    public function getPeriodDateAttribute(): ?\Carbon\Carbon
    {
        if (! $this->period) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($this->period)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
