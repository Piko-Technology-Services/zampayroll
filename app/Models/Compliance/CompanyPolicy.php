<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyPolicy extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'company_policies';

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['review_date' => 'date', 'published_at' => 'datetime', 'requires_acknowledgement' => 'boolean'];

    public const STATUSES = ['draft' => 'Draft', 'in_approval' => 'Awaiting approval', 'changes' => 'Changes requested',
        'active' => 'Active', 'archived' => 'Archived'];

    public function versions()
    {
        return $this->hasMany(PolicyVersion::class, 'policy_id')->orderByDesc('version');
    }

    public function version(int $v): ?PolicyVersion
    {
        return PolicyVersion::where('policy_id', $this->id)->where('version', $v)->first();
    }

    public function activeVersionRow(): ?PolicyVersion
    {
        return $this->active_version ? $this->version($this->active_version) : null;
    }

    public function draftVersionRow(): ?PolicyVersion
    {
        return $this->version($this->current_version);
    }

    public function approvals()
    {
        return $this->hasMany(ApprovalRequest::class, 'approvable_id')->where('approvable_type', 'policy')->orderBy('round')->orderBy('position');
    }

    public function acknowledgements()
    {
        return $this->hasMany(PolicyAcknowledgement::class, 'policy_id');
    }

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class, 'letterhead_id');
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** A draft revision is being prepared while an earlier version stays active. */
    public function hasPendingRevision(): bool
    {
        return $this->active_version && $this->current_version > $this->active_version;
    }
}
