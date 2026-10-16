<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Company;

class Employee extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [

        /*
        |--------------------------------------------------------------------------
        | IDENTIFICATION
        |--------------------------------------------------------------------------
        */
        'employee_id',
        'company_id',
        /*
        |--------------------------------------------------------------------------
        | PERSONAL INFO
        |--------------------------------------------------------------------------
        */
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nationality',
        'nrc_no',
        'passport_number',
        'passport_photo',

        /*
        |--------------------------------------------------------------------------
        | CONTACT INFO
        |--------------------------------------------------------------------------
        */
        'personal_email',
        'company_email',
        'primary_phone',
        'secondary_phone',

        /*
        |--------------------------------------------------------------------------
        | JOB INFO
        |--------------------------------------------------------------------------
        */
        'position',
        'department',
        'branch',
        'supervisor',
        'employment_status',
        'employment_nature',

        /*
        |--------------------------------------------------------------------------
        | EMPLOYMENT DATES
        |--------------------------------------------------------------------------
        */
        'probation_start',
        'probation_end',
        'contract_start',
        'contract_end',

        /*
        |--------------------------------------------------------------------------
        | EMERGENCY CONTACT
        |--------------------------------------------------------------------------
        */
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',

        /*
        |--------------------------------------------------------------------------
        | NEXT OF KIN
        |--------------------------------------------------------------------------
        */
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_address',

        /*
        |--------------------------------------------------------------------------
        | FINANCE
        |--------------------------------------------------------------------------
        */
        'bank_name',
        'bank_branch',
        'bank_sort_code',
        'bank_account_no',
        'ssn',
        'nhima_no',
        'tpin',
        'salary',
        'net_salary',
        'leave_days_entitled',
        'leave_days_balance',
        'leave_days_value',
        'natural_gross_salary',
        'daily_rate_salary',

        /*
        |--------------------------------------------------------------------------
        | DOCUMENTS
        |--------------------------------------------------------------------------
        */
        'uploads',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'date_of_birth'   => 'date',
        'probation_start' => 'date',
        'probation_end'   => 'date',
        'contract_start'  => 'date',
        'contract_end'    => 'date',
        'uploads'         => 'array',
        'salary'          => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    // Auto calculate age
    public function getAgeAttribute()
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->age
            : null;
    }

    // Full name helper
    public function getFullNameAttribute()
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    // Passport photo URL helper
    public function getPassportPhotoUrlAttribute()
    {
        return $this->passport_photo
            ? asset($this->passport_photo)
            : asset('assets/images/avatar/default.png');
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD HELPERS
    |--------------------------------------------------------------------------
    */

    public function addUpload($name, $path)
    {
        $uploads = $this->uploads ?? [];

        $uploads[] = [
            'name' => $name,
            'path' => $path,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $this->uploads = $uploads;
        $this->save();
    }

    public function removeUpload($index)
    {
        $uploads = $this->uploads ?? [];

        if (isset($uploads[$index])) {
            unset($uploads[$index]);
            $this->uploads = array_values($uploads);
            $this->save();
        }
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SELF-SERVICE PORTAL
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function hasPortalAccess(): bool
    {
        return $this->relationLoaded('user') ? $this->user !== null : $this->user()->exists();
    }

    /**
     * The address portal credentials are sent to: company email first, personal as fallback.
     */
    public function getPortalEmailAttribute(): ?string
    {
        return $this->company_email ?: $this->personal_email ?: null;
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRequests()
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function loanRequests()
    {
        return $this->hasMany(LoanRequest::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    // `employee_id` on this table is the human EIN, not a parent key.
    protected function inferCompanyIdFromParent(): ?int
    {
        return null;
    }

    public function getWorkingDaysPerMonthAttribute(): int
    {
        $days = $this->company?->working_days_per_month;

        return ($days && $days > 0) ? (int) $days : 26;
    }

    /*
    |--------------------------------------------------------------------------
    | PROFILE COMPLETENESS
    |--------------------------------------------------------------------------
    */

    /** What a complete file needs, grouped the same way as the add-employee journey. */
    public const PROFILE_CHECKS = [
        'who'       => ['label' => 'Personal', 'fields' => ['first_name' => 'First name', 'last_name' => 'Last name', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'nrc_no' => 'NRC']],
        'job'       => ['label' => 'Job', 'fields' => ['position' => 'Job title', 'department' => 'Department', 'contract_start' => 'Start date']],
        'pay'       => ['label' => 'Pay & bank', 'fields' => ['net_salary' => 'Take-home pay', 'bank_name' => 'Bank', 'bank_account_no' => 'Account number']],
        'statutory' => ['label' => 'ZRA · NAPSA · NHIMA', 'fields' => ['tpin' => 'TPIN', 'ssn' => 'NAPSA number', 'nhima_no' => 'NHIMA number']],
        'contact'   => ['label' => 'Contact', 'fields' => ['primary_phone' => 'Mobile', 'portal_email' => 'Email', 'next_of_kin_name' => 'Next of kin']],
    ];

    /**
     * @return array{percent:int, missing:array<string,array<string,string>>, done:int, total:int}
     */
    public function profileCompleteness(): array
    {
        $missing = [];
        $done = $total = 0;
        foreach (self::PROFILE_CHECKS as $step => $group) {
            foreach ($group['fields'] as $field => $label) {
                $total++;
                $value = $this->{$field};
                if ($value !== null && $value !== '' && ! ($field === 'net_salary' && (float) $value <= 0)) {
                    $done++;
                } else {
                    $missing[$step][$field] = $label;
                }
            }
        }

        return ['percent' => (int) round($done / max($total, 1) * 100), 'missing' => $missing, 'done' => $done, 'total' => $total];
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr((string) $this->first_name, 0, 1) . mb_substr((string) $this->last_name, 0, 1));
    }

    /** Public URL for the photo, or null (views show initials instead). */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->passport_photo ? asset('storage/' . $this->passport_photo) : null;
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}