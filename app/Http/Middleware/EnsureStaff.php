<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HR / admin area.
 *  - Self-service employees are sent to their portal.
 *  - Everyone else needs the permission their role grants for this screen
 *    (see App\Support\Permissions::ROUTE_MAP; editable in Settings → Team & roles).
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isEmployee()) {
            if ($request->expectsJson()) {
                abort(403, 'This area is for HR and administrators.');
            }

            return redirect()->route('portal.dashboard');
        }

        $permission = Permissions::forRequest($request);

        if ($user && $permission && ! $user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your role doesn\'t allow this. Ask your administrator for access.'], 403);
            }

            return response()->view('errors.no-access', [
                'permissionLabel' => Permissions::label($permission),
                'roleName' => $user->roleName(),
            ], 403);
        }

        return $next($request);
    }
}
