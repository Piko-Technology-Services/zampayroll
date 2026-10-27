<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Commission a partner earned on one paid invoice of a company they referred. */
class PartnerCommission extends Model
{
    use Concerns\UsesLandlordDatabase;

    protected $fillable = ['partner_id', 'company_id', 'invoice_id', 'invoice_number', 'base_amount', 'rate', 'amount', 'status', 'paid_at', 'payout_reference'];

    protected $casts = ['paid_at' => 'datetime', 'base_amount' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
