<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'contact_email',
        'contact_phone',
        'service',
        'amount',
        'method',
        'proof_path',
        'confirmed_sent',
        'comment',
        'status',
        'admin_notified_at',
        'user_notified_at',
    ];

    protected $casts = [
        'confirmed_sent'    => 'boolean',
        'amount'            => 'decimal:2',
        'admin_notified_at' => 'datetime',
        'user_notified_at'  => 'datetime',
    ];

    /**
     * List of services a payment can be made for. Kept here so the
     * controller and any future admin UI share one source of truth.
     */
    public static function services(): array
    {
        return [
            'Monthly Payroll Subscription',
            'Bi-Annual Payroll Subscription',
            'Annual Payroll Subscription',
            'One-Time Payroll Run',
            'HR Module Add-on',
            'Setup & Onboarding Fee',
            'Reseller Program Fee',
            'Other',
        ];
    }

    /**
     * Absolute URL to the uploaded proof-of-payment file.
     */
    public function proofUrl(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->proof_path);
    }
}
