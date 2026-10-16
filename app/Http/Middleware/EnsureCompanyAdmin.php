<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    /**
     * Company settings, access codes and invitations: administrators, or any
     * role the administrator has given "Company settings" or "Manage team" to.
     * (Fine-grained checks happen in EnsureStaff.)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->hasPermission('settings.company') || $user->hasPermission('team.manage'))) {
            abort(403, 'Only company administrators can perform this action.');
        }

        return $next($request);
    }
}
