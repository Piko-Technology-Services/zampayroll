<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['acted_at' => 'datetime', 'acted_as_delegate' => 'boolean'];

    public const STATUSES = ['waiting' => 'Waiting', 'pending' => 'Awaiting action', 'approved' => 'Approved', 'rejected' => 'Rejected',
        'changes' => 'Changes requested', 'cancelled' => 'Cancelled'];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function subject()
    {
        return $this->approvable_type === 'contract'
            ? EmploymentContract::find($this->approvable_id)
            : CompanyPolicy::find($this->approvable_id);
    }

    /** May this user act on this step? Named approver, anyone holding the role, or an explicitly configured delegate. */
    public function canBeActedOnBy(User $user): bool
    {
        if ($this->status !== 'pending' || (int) $user->company_id !== (int) $this->company_id || ! $user->isStaff()) {
            return false;
        }

        return $this->isAssignedTo($user) || $this->isDelegate($user);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->approver_type === 'user'
            ? (int) $this->approver_user_id === (int) $user->id
            : $this->approver_role_key === $user->roleKey();
    }

    public function isDelegate(User $user): bool
    {
        return $this->delegate_user_id && (int) $this->delegate_user_id === (int) $user->id;
    }

    public function approverLabel(): string
    {
        if ($this->approver_type === 'user') {
            return $this->approver?->name ?? 'Removed user';
        }

        return 'Anyone with the ' . (\App\Models\CompanyRole::where('key', $this->approver_role_key)->value('name') ?? $this->approver_role_key) . ' role';
    }
}
