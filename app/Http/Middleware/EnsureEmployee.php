<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee self-service portal: only accounts linked to an employee record.
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isEmployee()) {
            return redirect()->route('dashboard');
        }

        if (! $user->employee) {
            abort(403, 'Your portal account is no longer linked to an employee record. Please contact HR.');
        }

        return $next($request);
    }
}
