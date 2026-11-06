<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['tags' => 'array', 'issue_date' => 'date', 'effective_date' => 'date', 'expiry_date' => 'date', 'review_date' => 'date',
        'share_with_employees' => 'boolean'];

    public const ACCESS = [
        'company'    => 'Company-wide (all staff with document access)',
        'hr'         => 'HR only',
        'finance'    => 'Finance and payroll',
        'management' => 'Management only',
        'department' => 'One department',
        'employee'   => 'One employee (and HR)',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function versions()
    {
        return $this->hasMany(CompanyDocumentVersion::class, 'document_id')->orderByDesc('version');
    }

    public function latestVersion()
    {
        return $this->hasOne(CompanyDocumentVersion::class, 'document_id')->latestOfMany('version');
    }

    public function expiryState(): ?string
    {
        if (! $this->expiry_date) {
            return null;
        }
        if ($this->expiry_date->isPast() && ! $this->expiry_date->isToday()) {
            return 'expired';
        }

        return $this->expiry_date->lte(now()->addDays(30)) ? 'expiring' : 'valid';
    }

    public function reviewDue(): bool
    {
        return $this->review_date && $this->review_date->lte(now()->addDays(30));
    }
}
