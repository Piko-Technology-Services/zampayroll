<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ContractVersion extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'employment_contract_versions';

    protected $guarded = ['id', 'company_id'];
}
