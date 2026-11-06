<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['signed_at' => 'datetime'];
}
