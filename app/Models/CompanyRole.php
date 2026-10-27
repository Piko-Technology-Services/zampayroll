<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CompanyRole extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'name', 'description', 'permissions', 'is_system'];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return in_array($this->key, Permissions::ADMIN_ROLES, true);
    }

    public function allows(string $permission): bool
    {
        $perms = $this->permissions ?? [];

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'key')->where('company_id', $this->company_id);
    }

    /**
     * key => name for the current company (seeding defaults if this company has none yet).
     */
    public static function options(): Collection
    {
        $companyId = \App\Support\Tenancy::companyId();

        // Seeds a new company's roles, and adds built-in roles introduced later (e.g. Board member).
        if ($companyId && static::whereIn('key', array_keys(Permissions::DEFAULT_ROLES))->count() < count(Permissions::DEFAULT_ROLES)) {
            Permissions::seedDefaultRoles($companyId);
        }

        return static::orderByDesc('is_system')->orderBy('id')->pluck('name', 'key');
    }
}
