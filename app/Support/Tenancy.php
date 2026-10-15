<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Central place that knows "which company are we working for right now?"
 *
 * Resolution order for the current company id:
 *   1. an explicitly forced id      (Tenancy::runAs(), public forms, queue jobs)
 *   2. the dedicated tenant         (resolved from the sub-domain by IdentifyTenant)
 *   3. the logged-in user's company (shared tenants on the main domain)
 *
 * When none applies (guest pages, artisan) the BelongsToCompany scope is not
 * applied, which keeps console commands and the public forms working.
 */
class Tenancy
{
    protected static ?int $forcedCompanyId = null;

    protected static ?Company $dedicated = null;

    protected static int $scopeDisabled = 0;

    protected static ?Company $companyCache = null;

    /* ------------------------------------------------------------------ */
    /*  Current company                                                     */
    /* ------------------------------------------------------------------ */

    public static function companyId(): ?int
    {
        if (static::$forcedCompanyId) {
            return static::$forcedCompanyId;
        }

        if (static::$dedicated) {
            return (int) static::$dedicated->id;
        }

        // Only read the guard if it has already been resolved this request,
        // or if a session exists — avoids booting auth in artisan/queues.
        try {
            $user = Auth::user();
        } catch (\Throwable $e) {
            $user = null;
        }

        return $user?->company_id ? (int) $user->company_id : null;
    }

    public static function company(): ?Company
    {
        $id = static::companyId();

        if (! $id) {
            return null;
        }

        // Memoised per request/job so loops (payroll generation) don't re-query.
        if (static::$companyCache?->id !== $id) {
            static::$companyCache = Company::find($id);
        }

        return static::$companyCache;
    }

    public static function setCompanyId(?int $companyId): void
    {
        static::$forcedCompanyId = $companyId;
    }

    /**
     * Run a callback as if the given company were the current tenant.
     */
    public static function runAs(int $companyId, callable $callback)
    {
        $previous = static::$forcedCompanyId;
        static::$forcedCompanyId = $companyId;

        try {
            return $callback();
        } finally {
            static::$forcedCompanyId = $previous;
        }
    }

    /**
     * Run a callback with the company scope switched off (super-admin tooling, migrations).
     */
    public static function withoutScope(callable $callback)
    {
        static::$scopeDisabled++;

        try {
            return $callback();
        } finally {
            static::$scopeDisabled--;
        }
    }

    public static function scopeDisabled(): bool
    {
        return static::$scopeDisabled > 0;
    }

    /* ------------------------------------------------------------------ */
    /*  Dedicated tenants                                                   */
    /* ------------------------------------------------------------------ */

    public static function isDedicated(): bool
    {
        return static::$dedicated !== null;
    }

    public static function dedicatedCompany(): ?Company
    {
        return static::$dedicated;
    }

    public static function landlordConnection(): string
    {
        return config('tenancy.landlord_connection', 'mysql');
    }

    public static function tenantConnection(): string
    {
        return config('tenancy.tenant_connection', 'tenant');
    }

    /**
     * Point the "tenant" connection at the given database (does not change the default).
     */
    public static function configureTenantConnection(string $database): void
    {
        $tenant = static::tenantConnection();
        $base = config('database.connections.' . static::landlordConnection());

        config(["database.connections.{$tenant}" => array_merge($base, ['database' => $database])]);

        DB::purge($tenant);
    }

    /**
     * Switch the whole application over to a dedicated tenant's database.
     * $company must be the LANDLORD record (it carries database_name/subdomain).
     */
    public static function switchToDedicated(Company $company): void
    {
        static::configureTenantConnection($company->database_name);

        DB::setDefaultConnection(static::tenantConnection());

        static::$dedicated = $company;
        static::$companyCache = null;
    }

    /**
     * Go back to the landlord database (used by queue workers between jobs).
     */
    public static function switchToLandlord(): void
    {
        DB::setDefaultConnection(static::landlordConnection());
        static::$dedicated = null;
        static::$forcedCompanyId = null;
        static::$companyCache = null;
    }

    /**
     * Temporarily run code inside a dedicated tenant's database, then restore
     * whatever was active before (used by central-domain login / forgot password).
     */
    public static function runInDedicated(Company $company, callable $callback)
    {
        $previousDefault = DB::getDefaultConnection();
        $previousDedicated = static::$dedicated;
        $previousForced = static::$forcedCompanyId;
        $previousRoot = static::currentRootUrl();

        static::switchToDedicated($company);
        URL::forceRootUrl(static::urlFor($company));

        // Make the auth guard re-read users from the tenant connection.
        Auth::forgetGuards();

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($previousDefault);
            static::$dedicated = $previousDedicated;
            static::$forcedCompanyId = $previousForced;
            URL::forceRootUrl($previousRoot);
            static::$companyCache = null;
            Auth::forgetGuards();
        }
    }

    public static function reset(): void
    {
        static::$forcedCompanyId = null;
        static::$dedicated = null;
        static::$scopeDisabled = 0;
        static::$companyCache = null;
    }

    /* ------------------------------------------------------------------ */
    /*  Hosts & URLs                                                        */
    /* ------------------------------------------------------------------ */

    public static function centralDomain(): string
    {
        return strtolower(config('tenancy.central_domain', 'localhost'));
    }

    /**
     * Returns the sub-domain label for a host, or null when the host is the
     * central domain (or something unrelated such as an IP address).
     */
    public static function subdomainFromHost(string $host): ?string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host));
        $central = static::centralDomain();

        if ($host === $central || $host === 'www.' . $central) {
            return null;
        }

        $suffix = '.' . $central;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        // Only single-level sub-domains are tenants (acme.zampayroll.com, not a.b.zampayroll.com)
        return ($label !== '' && ! str_contains($label, '.')) ? $label : null;
    }

    /**
     * Root URL of the central site (keeps scheme, port and base path).
     */
    public static function centralUrl(string $path = '/'): string
    {
        $root = static::baseRoot();
        $parts = parse_url($root);
        $parts['host'] = static::centralDomain();

        return static::buildUrl($parts, $path);
    }

    /**
     * Root (or path) URL of a dedicated tenant, e.g. http://acme.localhost:8000/login
     */
    public static function urlFor(Company $company, string $path = '/'): string
    {
        if (! $company->isDedicated() || ! $company->subdomain) {
            return static::centralUrl($path);
        }

        $parts = parse_url(static::baseRoot());
        $parts['host'] = $company->subdomain . '.' . static::centralDomain();

        return static::buildUrl($parts, $path);
    }

    protected static function baseRoot(): string
    {
        if (app()->runningInConsole() || ! app()->bound('request') || ! request()->getHost()) {
            return rtrim(config('tenancy.central_url') ?: config('app.url'), '/');
        }

        return rtrim(request()->root(), '/');
    }

    protected static function currentRootUrl(): ?string
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return null;
        }

        return request()->root();
    }

    protected static function buildUrl(array $parts, string $path): string
    {
        $url = ($parts['scheme'] ?? 'http') . '://' . $parts['host'];

        if (! empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $basePath = rtrim($parts['path'] ?? '', '/');

        $path = '/' . ltrim($path, '/');

        return rtrim($url . $basePath . $path, '/') ?: $url;
    }

    public static function databaseNameFor(string $subdomain): string
    {
        return config('tenancy.database_prefix', 'zp_') . preg_replace('/[^a-z0-9_]/', '_', strtolower($subdomain));
    }

    /* ------------------------------------------------------------------ */
    /*  Global e-mail directory (landlord)                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Which company (landlord record) owns this email on a dedicated workspace?
     */
    public static function directoryCompanyFor(string $email): ?Company
    {
        $row = DB::connection(static::landlordConnection())
            ->table('tenant_user_directory')
            ->where('email', strtolower($email))
            ->first();

        if (! $row) {
            return null;
        }

        return Company::on(static::landlordConnection())->find($row->company_id);
    }

    /**
     * True when the email already belongs to ANY account on the platform
     * (shared users in the landlord DB, or a dedicated workspace's user).
     */
    public static function emailTaken(string $email, ?int $ignoreUserId = null): bool
    {
        $email = strtolower($email);
        $landlord = DB::connection(static::landlordConnection());

        $inLandlord = $landlord->table('users')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($ignoreUserId && ! static::isDedicated(), fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($inLandlord) {
            return true;
        }

        $dir = $landlord->table('tenant_user_directory')->where('email', $email)->first();

        if (! $dir) {
            return false;
        }

        // Inside a dedicated tenant, the user's own directory entry is not a conflict.
        if (static::isDedicated() && $ignoreUserId && (int) $dir->company_id === (int) static::companyId()
            && (int) $dir->user_id === (int) $ignoreUserId) {
            return false;
        }

        return true;
    }

    public static function syncDirectory(string $email, int $companyId, int $userId): void
    {
        DB::connection(static::landlordConnection())->table('tenant_user_directory')->updateOrInsert(
            ['email' => strtolower($email)],
            ['company_id' => $companyId, 'user_id' => $userId, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public static function removeFromDirectory(string $email): void
    {
        DB::connection(static::landlordConnection())->table('tenant_user_directory')
            ->where('email', strtolower($email))
            ->delete();
    }
}
