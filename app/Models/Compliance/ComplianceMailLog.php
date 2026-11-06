<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ComplianceMailLog extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
