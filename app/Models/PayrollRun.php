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
        'current_step',
        'submitted_at',
        'submitted_by',
        'paid_at',
        'paid_by',
        'include_overtime',
        'approval_chain',
    ];

    protected $casts = [
        'hidden_at'        => 'datetime',
        'finalized_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'paid_at'          => 'datetime',
        'include_overtime' => 'boolean',
        'approval_chain'   => 'array',
    ];

    /*
    | Life of a pay run
    |   Draft → Processed (calculated) → In Review (approval chain) → Approved (locked) → Paid
    | "Sent back" returns it to Processed with a comment.
    */
    public const DRAFT = 'Draft';
    public const CALCULATED = 'Processed';
    public const IN_REVIEW = 'In Review';
    public const APPROVED = 'Approved';
    public const PAID = 'Paid';

    /** Can figures still be changed? Only before it goes for approval. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::CALCULATED, 'Processing', null], true);
    }

    /** Approved or paid: nothing may change any more. */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::APPROVED, self::PAID, 'Finalized', 'Finalised'], true);
    }

    public function isInReview(): bool
    {
        return $this->status === self::IN_REVIEW;
    }

    /** Where the run is in the 5-stage journey (1–5) for the progress bar. */
    public function stage(): int
    {
        return match (true) {
            $this->status === self::PAID => 5,
            $this->isLocked() => 4,
            $this->isInReview() => 3,
            $this->payrolls()->exists() => 2,
            default => 1,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::DRAFT => 'Draft',
            self::CALCULATED => 'Ready to check',
            self::IN_REVIEW => 'Waiting for approval',
            self::APPROVED, 'Finalized', 'Finalised' => 'Approved · locked',
            self::PAID => 'Paid',
            default => (string) $this->status,
        };
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->status === self::PAID => 'paid',
            $this->isLocked() => 'locked',
            $this->isInReview() => 'review',
            default => 'draft',
        };
    }

    public function displayName(): string
    {
        return $this->alias ?: ($this->period_date ? $this->period_date->format('F Y') . ' payroll' : 'Payroll ' . $this->period);
    }

    public function events()
    {
        return $this->hasMany(PayrollRunEvent::class)->orderBy('id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function adjustments()
    {
        return $this->hasMany(PayrollRunAdjustment::class);
    }

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
