<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    /**
     * Only company_admin (or super_admin) users may proceed.
     * Blocks company-settings management, access-code control, and invitations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isCompanyAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Only company administrators can perform this action.');
        }

        return $next($request);
    }
}
