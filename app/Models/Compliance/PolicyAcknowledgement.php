<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class PolicyAcknowledgement extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public const STATEMENT = 'I confirm that I have received this policy and have read and understood it. I understand that this acknowledgement confirms receipt and does not take away any of my rights under the law.';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
