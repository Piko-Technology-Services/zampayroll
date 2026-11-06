<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AlertDismissal extends Model
{
    use BelongsToCompany;

    protected $table = 'compliance_alert_dismissals';

    protected $guarded = ['id', 'company_id'];
}
