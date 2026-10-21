<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_hr_confirm',
        'leave_allow_negative',
        'overtime_settings',
        'name',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'province',
        'logo_path',
        'work_days',
        'working_days_per_month',
        'tpin',
        'napsa_employer_number',
        'nhima_employer_number',
        'pacra_registration_number',
        'workers_comp_number',
        'vat_number',
        'access_code',
        'access_code_active',
        'company_code',

        // Tenancy & onboarding
        'tenancy_mode',
        'subdomain',
        'database_name',
        'status',
        'industry',
        'employee_count_range',
        'pay_frequency',
        'pay_day',
        'currency',
        'interested_modules',
        'heard_about_us',
        'onboarding_completed_at',
        'provisioned_at',

        // ZamPayroll subscription (managed by App\Services\Billing\Subscriptions)
        'plan',
        'billing_type',
        'billing_cycle',
        'subscription_status',
        'trial_ends_at',
        'paid_until',
        'billing_anchor',
        'last_billed_at',
        'billing_email',
        'subdomain_requested_at',
        'requested_subdomain',
        'billing_notes',
    ];

    public const MODE_SHARED = 'shared';
    public const MODE_DEDICATED = 'dedicated';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_AWAITING_DATABASE = 'awaiting_database';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUSPENDED = 'suspended';

    public const INDUSTRIES = [
        'agriculture'   => ['label' => 'Agriculture',             'icon' => 'bi-flower1'],
        'mining'        => ['label' => 'Mining & Energy',         'icon' => 'bi-gem'],
        'retail'        => ['label' => 'Retail & Trade',          'icon' => 'bi-bag'],
        'hospitality'   => ['label' => 'Hospitality & Tourism',   'icon' => 'bi-cup-hot'],
        'finance'       => ['label' => 'Finance & Insurance',     'icon' => 'bi-bank'],
        'technology'    => ['label' => 'Technology',              'icon' => 'bi-cpu'],
        'health'        => ['label' => 'Health',                  'icon' => 'bi-heart-pulse'],
        'education'     => ['label' => 'Education',               'icon' => 'bi-mortarboard'],
        'construction'  => ['label' => 'Construction',            'icon' => 'bi-bricks'],
        'manufacturing' => ['label' => 'Manufacturing',           'icon' => 'bi-gear-wide-connected'],
        'logistics'     => ['label' => 'Transport & Logistics',   'icon' => 'bi-truck'],
        'ngo'           => ['label' => 'NGO / Non-profit',        'icon' => 'bi-globe2'],
        'government'    => ['label' => 'Government / Parastatal', 'icon' => 'bi-building'],
        'professional'  => ['label' => 'Professional Services',   'icon' => 'bi-briefcase'],
        'other'         => ['label' => 'Something else',          'icon' => 'bi-stars'],
    ];

    public const PROVINCES = [
        'Central', 'Copperbelt', 'Eastern', 'Luapula', 'Lusaka',
        'Muchinga', 'Northern', 'North-Western', 'Southern', 'Western',
    ];

    public const PAY_FREQUENCIES = [
        'monthly'     => 'Monthly',
        'fortnightly' => 'Every two weeks',
        'weekly'      => 'Weekly',
    ];

    public const MODULES = [
        'payroll'    => ['label' => 'Payroll & payslips',   'icon' => 'bi-cash-stack'],
        'leave'      => ['label' => 'Leave management',     'icon' => 'bi-calendar-check'],
        'attendance' => ['label' => 'Attendance',           'icon' => 'bi-clock-history'],
        'overtime'   => ['label' => 'Overtime',             'icon' => 'bi-alarm'],
        'loans'      => ['label' => 'Staff loans',          'icon' => 'bi-cash-coin'],
        'portal'     => ['label' => 'Employee self-service','icon' => 'bi-phone'],
        'statutory'  => ['label' => 'ZRA / NAPSA / NHIMA',  'icon' => 'bi-shield-check'],
    ];

    /**
     * Fields that make up a "complete" company profile (shown as a progress bar).
     */
    public const PROFILE_FIELDS = [
        'email'                     => 'Company email',
        'phone'                     => 'Phone number',
        'address'                   => 'Physical address',
        'city'                      => 'City / town',
        'province'                  => 'Province',
        'tpin'                      => 'ZRA TPIN',
        'pacra_registration_number' => 'PACRA number',
        'napsa_employer_number'     => 'NAPSA employer number',
        'nhima_employer_number'     => 'NHIMA employer number',
        'logo_path'                 => 'Company logo',
    ];

    protected $casts = [
        'leave_hr_confirm' => 'boolean',
        'overtime_settings' => 'array',
        'leave_allow_negative' => 'boolean',
        'access_code_active' => 'boolean',
        'work_days' => 'array',
        'working_days_per_month' => 'integer',
        'interested_modules' => 'array',
        'pay_day' => 'integer',
        'onboarding_completed_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'paid_until' => 'datetime',
        'billing_anchor' => 'date',
        'last_billed_at' => 'datetime',
        'subdomain_requested_at' => 'datetime',
        'payroll_allow_self_approval' => 'boolean',
    ];

    /** Every new company starts with a free trial. */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->trial_ends_at) {
                $company->trial_ends_at = now()->addDays((int) \App\Services\Billing\PlatformSettings::get('trial_days'));
                $company->subscription_status = $company->subscription_status ?: 'trialing';
            }
        });
    }

    /**
     * Short, readable code staff type at sign-in, e.g. "PIK-4821".
     * Unique across the whole platform (checked on the landlord database).
     */
    public static function makeCompanyCode(?string $name): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $name));
        $prefix = str_pad(substr($letters, 0, 3), 3, 'Z');
        $conn = \App\Support\Tenancy::landlordConnection();

        do {
            $code = $prefix . '-' . random_int(1000, 9999);
        } while (\Illuminate\Support\Facades\DB::connection($conn)->table('companies')->where('company_code', $code)->exists());

        return $code;
    }

    public function roles()
    {
        return $this->hasMany(CompanyRole::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Tenancy helpers                                                     */
    /* ------------------------------------------------------------------ */

    public function isDedicated(): bool
    {
        return $this->tenancy_mode === self::MODE_DEDICATED;
    }

    public function isShared(): bool
    {
        return ! $this->isDedicated();
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    /**
     * The workspace root URL users of this company sign in on.
     */
    public function workspaceUrl(string $path = '/'): string
    {
        return \App\Support\Tenancy::urlFor($this, $path);
    }

    public function workspaceHost(): string
    {
        return $this->isDedicated() && $this->subdomain
            ? $this->subdomain . '.' . \App\Support\Tenancy::centralDomain()
            : \App\Support\Tenancy::centralDomain();
    }

    /* ------------------------------------------------------------------ */
    /*  Profile completion                                                  */
    /* ------------------------------------------------------------------ */

    public function missingProfileFields(): array
    {
        return collect(self::PROFILE_FIELDS)
            ->filter(fn ($label, $field) => blank($this->{$field}))
            ->all();
    }

    public function profileCompletion(): int
    {
        $total = count(self::PROFILE_FIELDS) + 1; // +1 for the basics captured at sign-up
        $done = $total - count($this->missingProfileFields());

        return (int) round(($done / $total) * 100);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function pendingInvitations()
    {
        return $this->invitations()->where('status', 'pending')->where('expires_at', '>', now());
    }

    /**
     * Generate a brand new, unique, active access code for this company.
     */
    public function generateAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (static::where('access_code', $code)->exists());

        $this->access_code = $code;
        $this->access_code_active = true;
        $this->save();

        return $code;
    }

    public function deactivateAccessCode(): void
    {
        $this->access_code_active = false;
        $this->save();
    }
}
