<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    public const ACTIONS = ['review' => 'Reviews', 'approve' => 'Approves', 'sign' => 'Signs for the company'];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }
}
