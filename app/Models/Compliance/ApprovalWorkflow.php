<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['contract_types' => 'array', 'is_active' => 'boolean'];

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('position');
    }

    public function appliesToType(?string $type): bool
    {
        return empty($this->contract_types) || in_array($type, $this->contract_types, true);
    }
}
