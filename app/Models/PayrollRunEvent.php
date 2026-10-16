<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The history of a pay run: who calculated, submitted, approved, sent back,
 * locked or paid it, when, and what they said.
 */
class PayrollRunEvent extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['payroll_run_id', 'action', 'step_position', 'step_name', 'user_id', 'comment', 'snapshot'];

    protected $casts = ['snapshot' => 'array'];

    public const LABELS = [
        'calculated' => 'Calculated',
        'submitted'  => 'Submitted for approval',
        'approved'   => 'Approved',
        'sent_back'  => 'Sent back for changes',
        'recalled'   => 'Recalled to draft',
        'locked'     => 'Approved & locked',
        'paid'       => 'Marked as paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
