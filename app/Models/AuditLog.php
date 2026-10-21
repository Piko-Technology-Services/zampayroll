<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use Concerns\BelongsToCompany, Concerns\UsesLandlordDatabase;

    public const UPDATED_AT = null;

    protected $fillable = ['company_id', 'user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'summary', 'changes', 'ip'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
