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
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
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
}