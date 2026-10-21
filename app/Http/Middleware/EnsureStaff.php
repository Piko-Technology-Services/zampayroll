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

        // Platform administrators have no company: keep them in the /platform console
        // so an unscoped account can never open a company's screens.
        if ($user && $user->isPlatformAdmin()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Use the ZamPayroll admin console.'], 403)
                : redirect()->route('platform.dashboard');
        }

        // A staff account must belong to a company.
        if ($user && ! $user->company_id) {
            abort(403, 'This account is not linked to a company.');
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

        // Trial over / unpaid past the grace period: the account becomes read-only.
        // Viewing, billing, help and signing out keep working; data is never deleted.
        if ($user && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $this->alwaysAllowed($request)) {
            $subs = app(\App\Services\Billing\Subscriptions::class);
            if ($subs->isLimited($subs->company((int) $user->company_id))) {
                $msg = 'Your ZamPayroll subscription needs attention — the account is read-only until it\'s paid. Go to Settings → Billing.';

                return $request->expectsJson()
                    ? response()->json(['message' => $msg, 'billing_url' => route('billing.index')], 402)
                    : redirect()->route('billing.index')->with('error', $msg);
            }
        }

        return $next($request);
    }

    private function alwaysAllowed(Request $request): bool
    {
        return $request->routeIs('billing.*', 'support.*', 'logout', 'profile.*', 'ai.message', 'ai.reset');
    }
}
