<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created with a temporary password (employee portal) must choose
 * their own password before doing anything else.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('portal.password.*', 'logout')) {
            return redirect()->route('portal.password.edit');
        }

        return $next($request);
    }
}
