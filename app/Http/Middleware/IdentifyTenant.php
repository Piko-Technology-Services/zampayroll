<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs FIRST in the web stack (before the session starts).
 *
 *   zampayroll.com            -> landlord DB, shared tenants (scoped by company_id)
 *   acme.zampayroll.com       -> Acme's own database
 *
 * Because sessions, cache and password-reset tokens all use the default
 * connection, switching it here gives every dedicated workspace fully
 * separate sessions and data.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = Tenancy::subdomainFromHost($request->getHost());

        if ($subdomain === null) {
            return $next($request);
        }

        $company = Company::on(Tenancy::landlordConnection())
            ->where('subdomain', $subdomain)
            ->where('tenancy_mode', Company::MODE_DEDICATED)
            ->first();

        if (! $company) {
            return response()->view('tenancy.not-found', ['subdomain' => $subdomain], 404);
        }

        if (! $company->isActive() || ! $company->database_name) {
            return response()->view('tenancy.pending', ['company' => $company], 503);
        }

        Tenancy::switchToDedicated($company);

        return $next($request);
    }
}
