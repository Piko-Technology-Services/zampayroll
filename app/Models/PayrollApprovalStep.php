<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step in a company's payroll approval chain, e.g. "Finance review" by
 * anyone with the Payroll Officer role, or "MD sign-off" by a named person.
 */
class PayrollApprovalStep extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['position', 'name', 'role_key', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
