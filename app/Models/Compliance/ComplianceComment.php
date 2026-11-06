<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ComplianceComment extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];
}
