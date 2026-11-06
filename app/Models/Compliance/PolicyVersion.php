<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PolicyVersion extends Model
{
    use BelongsToCompany;

    protected $table = 'company_policy_versions';

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['published_at' => 'datetime'];
}
