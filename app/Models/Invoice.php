<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A ZamPayroll invoice to one of its client companies. */
class Invoice extends Model
{
    use Concerns\BelongsToCompany, Concerns\UsesLandlordDatabase;

    protected $fillable = ['company_id', 'number', 'kind', 'period_start', 'period_end', 'lines', 'subtotal', 'vat_rate', 'vat', 'total',
        'currency', 'status', 'due_at', 'paid_at', 'emailed_at', 'reminders_sent', 'meta'];

    protected $casts = [
        'lines' => 'array', 'meta' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'due_at' => 'date',
        'paid_at' => 'datetime', 'emailed_at' => 'datetime', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payments()
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_at && $this->due_at->endOfDay()->isPast();
    }

    public function title(): string
    {
        return $this->lines[0]['description'] ?? 'ZamPayroll subscription';
    }

    protected function inferCompanyIdFromParent(): ?int
    {
        return null;
    }
}
