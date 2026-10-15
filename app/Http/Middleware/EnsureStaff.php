<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HR / admin area. Self-service employees are sent to their portal instead.
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

        return $next($request);
    }
}
