<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A kind of leave the company offers. Companies set their own rules here
 * (paid or not, whether it uses the annual balance, documents, yearly limits) —
 * ZamPayroll doesn't hard-code statutory day counts.
 */
class LeaveType extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'key', 'name', 'description', 'is_paid', 'deducts_balance', 'requires_document', 'document_after_days',
        'days_per_year', 'max_days_per_request', 'employees_can_apply', 'is_active', 'sort',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'deducts_balance' => 'boolean',
        'requires_document' => 'boolean',
        'employees_can_apply' => 'boolean',
        'is_active' => 'boolean',
        'document_after_days' => 'integer',
        'days_per_year' => 'decimal:2',
        'max_days_per_request' => 'decimal:2',
    ];

    /** Starting set for every company — all editable. Day limits are left for the company to set. */
    public const DEFAULTS = [
        'annual'        => ['Annual leave', true, true, false, 'Uses the yearly leave balance.'],
        'sick'          => ['Sick leave', true, false, true, 'A sick note may be asked for.'],
        'maternity'     => ['Maternity leave', true, false, true, null],
        'paternity'     => ['Paternity leave', true, false, false, null],
        'compassionate' => ['Compassionate leave', true, false, false, 'Bereavement or serious family illness.'],
        'family'        => ['Family responsibility leave', true, false, false, null],
        'study'         => ['Study leave', true, false, false, null],
        'unpaid'        => ['Unpaid leave', false, false, false, 'Not paid. Agree it with HR first.'],
        'other'         => ['Other', true, false, false, null],
    ];

    public function needsDocument(float $days): bool
    {
        return $this->requires_document && ($this->document_after_days === null || $days > $this->document_after_days);
    }

    public function attendanceStatus(): string
    {
        return $this->key === 'sick' ? 'sick' : 'leave';
    }
}
