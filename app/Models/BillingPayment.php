<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A payment attempt against a ZamPayroll invoice (DPO card / mobile money, or recorded manually). */
class BillingPayment extends Model
{
    use Concerns\BelongsToCompany, Concerns\UsesLandlordDatabase;

    protected $fillable = ['company_id', 'invoice_id', 'provider', 'method', 'amount', 'currency', 'status', 'trans_token', 'trans_ref',
        'phone', 'network', 'result_code', 'result_explanation', 'reference', 'user_id', 'paid_at', 'last_checked_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'last_checked_at' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected function inferCompanyIdFromParent(): ?int
    {
        return null;
    }
}
