<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id', 'company_id'];

    protected $casts = [
        'data' => 'array', 'start_date' => 'date', 'end_date' => 'date',
        'sent_at' => 'datetime', 'employee_viewed_at' => 'datetime', 'employee_signed_at' => 'datetime',
        'declined_at' => 'datetime', 'executed_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft'              => 'Draft',
        'sent'               => 'Awaiting employee',
        'declined'           => 'Declined by employee',
        'in_approval'        => 'Awaiting approval',
        'changes'            => 'Changes requested',
        'rejected'           => 'Rejected',
        'executed'           => 'Fully executed',
        'superseded'         => 'Superseded',
        'cancelled'          => 'Cancelled',
    ];

    /** Statuses in which HR may still edit the wording (a new version is created once it has been sent). */
    public const EDITABLE = ['draft', 'declined', 'changes', 'rejected'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class, 'letterhead_id');
    }

    public function versions()
    {
        return $this->hasMany(ContractVersion::class, 'contract_id')->orderByDesc('version');
    }

    public function currentVersion()
    {
        return $this->hasOne(ContractVersion::class, 'contract_id')->where('version', $this->current_version);
    }

    public function signatures()
    {
        return $this->hasMany(ContractSignature::class, 'contract_id')->orderBy('signed_at');
    }

    public function approvals()
    {
        return $this->hasMany(ApprovalRequest::class, 'approvable_id')->where('approvable_type', 'contract')->orderBy('round')->orderBy('position');
    }

    public function comments()
    {
        return $this->hasMany(ComplianceComment::class, 'subject_id')->where('subject_type', 'contract')->orderBy('created_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    public function employeeSignature(): ?ContractSignature
    {
        return $this->signatures->where('signer_type', 'employee')->where('version', $this->current_version)->last();
    }

    protected function inferCompanyIdFromParent(): ?int
    {
        return $this->employee_id ? (int) Employee::withoutGlobalScope('company')->whereKey($this->employee_id)->value('company_id') : null;
    }
}
