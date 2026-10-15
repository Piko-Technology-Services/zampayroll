<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after the session has started. Makes sure a signed-in user is on the
 * right host for their company:
 *   - a dedicated company's user can never work on the central domain;
 *   - inside a dedicated workspace, only that company's users are accepted.
 */
class VerifyTenantSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if (Tenancy::isDedicated()) {
            if (! $user->belongsToCompany(Tenancy::companyId())) {
                return $this->kickOut($request, Tenancy::centralUrl('/login'));
            }

            return $next($request);
        }

        // Central domain: if this user's company has moved to its own workspace, send them there.
        $company = $user->company;

        if ($company && $company->isDedicated()) {
            return $this->kickOut($request, $company->workspaceUrl('/login'));
        }

        return $next($request);
    }

    private function kickOut(Request $request, string $to): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($to);
    }
}
