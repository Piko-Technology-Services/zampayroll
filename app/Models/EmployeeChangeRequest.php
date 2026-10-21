<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeChangeRequest extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    /** What employees may ask to change, per type (HR approves before it is applied). */
    public const FIELDS = [
        'bank' => [
            'bank_name' => 'Bank',
            'bank_branch' => 'Branch',
            'bank_account_no' => 'Account number',
        ],
    ];

    public const STATUSES = ['pending' => 'Waiting for HR', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    protected $fillable = ['company_id', 'employee_id', 'type', 'changes', 'previous', 'documents', 'note', 'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_comment'];

    protected $casts = ['changes' => 'array', 'previous' => 'array', 'documents' => 'array', 'reviewed_at' => 'datetime'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function typeLabel(): string
    {
        return ['bank' => 'Bank details'][$this->type] ?? ucfirst($this->type);
    }
}
