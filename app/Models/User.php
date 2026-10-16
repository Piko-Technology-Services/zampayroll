<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\Tenancy;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Roles that a company admin is allowed to assign to invited team members.
     * super_admin is a platform-level role and is never assignable via invitations.
     * `employee` accounts are created from the employee record ("Create portal access").
     */
    public const INVITABLE_ROLES = [
        'company_admin' => 'Company Admin',
        'manager'       => 'Manager',
        'hr'            => 'HR Officer',
    ];

    public const ROLE_EMPLOYEE = 'employee';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'position',
        'must_change_password',
        'last_login_at',
        'portal_invited_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'last_login_at'        => 'datetime',
            'portal_invited_at'    => 'datetime',
            'must_change_password' => 'boolean',
            'password'             => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Dedicated workspaces keep their users in their own database. The landlord
        // keeps an email -> company directory so the main-domain login and
        // "forgot password" can find the right workspace.
        static::saved(function (User $user) {
            if (Tenancy::isDedicated() && $user->company_id) {
                if ($user->wasChanged('email') && $user->getOriginal('email')) {
                    Tenancy::removeFromDirectory($user->getOriginal('email'));
                }
                Tenancy::syncDirectory($user->email, (int) $user->company_id, (int) $user->id);
            }
        });

        static::deleted(function (User $user) {
            if (Tenancy::isDedicated()) {
                Tenancy::removeFromDirectory($user->email);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Roles                                                               */
    /* ------------------------------------------------------------------ */

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company_admin';
    }

    /**
     * Self-service portal user (linked to an employee record). Older accounts that
     * were invited with the legacy "employee" role but no employee link keep
     * their staff access.
     */
    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE && ! empty($this->employee_id);
    }

    public function isStaff(): bool
    {
        return ! $this->isEmployee();
    }

    /* ------------------------------------------------------------------ */
    /*  Role-based permissions (editable per company in Settings → Team)    */
    /* ------------------------------------------------------------------ */

    protected ?CompanyRole $roleModelCache = null;

    public function roleKey(): string
    {
        return \App\Support\Permissions::LEGACY_ROLE_MAP[$this->role] ?? (string) $this->role;
    }

    public function roleModel(): ?CompanyRole
    {
        if ($this->roleModelCache && $this->roleModelCache->key === $this->roleKey()) {
            return $this->roleModelCache;
        }

        $role = CompanyRole::withoutGlobalScope('company')
            ->where('company_id', $this->company_id)->where('key', $this->roleKey())->first();

        if (! $role && $this->company_id && ! CompanyRole::withoutGlobalScope('company')->where('company_id', $this->company_id)->exists()) {
            \App\Support\Permissions::seedDefaultRoles((int) $this->company_id);
            $role = CompanyRole::withoutGlobalScope('company')->where('company_id', $this->company_id)->where('key', $this->roleKey())->first();
        }

        return $this->roleModelCache = $role;
    }

    public function roleName(): string
    {
        return $this->isEmployee() ? 'Employee (portal)' : ($this->roleModel()?->name ?? ucfirst(str_replace('_', ' ', (string) $this->role)));
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isEmployee()) {
            return false;
        }

        if ($this->isCompanyAdmin() || $this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->roleModel()?->allows($permission);
    }

    /**
     * Where this user should land after signing in.
     */
    public function homeRoute(): string
    {
        return $this->isEmployee() ? 'portal.dashboard' : 'dashboard';
    }

    /**
     * Whether this user belongs to the same company as the given company id.
     * Used throughout the app to enforce company data isolation.
     */
    public function belongsToCompany(?int $companyId): bool
    {
        return $companyId !== null && (int) $this->company_id === (int) $companyId;
    }

    /* ------------------------------------------------------------------ */
    /*  Branded notifications                                               */
    /* ------------------------------------------------------------------ */

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
