<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ZamPayroll partner (reseller): HR consultancy firms, HR practitioners,
 * HR students and freelancers who bring in client companies with their own code.
 */
class Partner extends Model
{
    use Concerns\UsesLandlordDatabase;

    public const TYPES = [
        'hr_firm'         => 'HR consultancy firm',
        'hr_practitioner' => 'HR practitioner',
        'hr_student'      => 'HR student',
        'freelancer'      => 'Freelancer',
        'accountant'      => 'Accountant / payroll bureau',
        'other'           => 'Other',
    ];

    protected $fillable = ['user_id', 'code', 'name', 'email', 'phone', 'partner_type', 'firm_name', 'city', 'pitch', 'status',
        'commission_rate', 'payout_method', 'payout_details', 'admin_notes', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime', 'commission_rate' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function commissions()
    {
        return $this->hasMany(PartnerCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->partner_type] ?? ucfirst((string) $this->partner_type);
    }

    /** The link clients use to sign up; it fills in the partner code for them. */
    public function joinUrl(): string
    {
        return route('partners.join', $this->code);
    }

    /** Name shown to a client who types the code: the firm, or the partner's name. */
    public function publicName(): string
    {
        return $this->firm_name ?: $this->name;
    }
}
