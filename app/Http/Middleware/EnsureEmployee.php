<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee self-service portal: any account linked to an employee record —
 * portal-only employees, and team members (HR, managers, board…) who are also employees.
 * Board members have no attendance, leave or overtime.
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPortal()) {
            return redirect()->route('dashboard');
        }

        if (! $user->employee) {
            abort(403, 'Your portal account is no longer linked to an employee record. Please contact HR.');
        }

        if ($user->employee->isBoardMember() && $request->routeIs('portal.leave*', 'portal.attendance*', 'portal.overtime*')) {
            return redirect()->route('portal.dashboard')->with('error', 'Board members don\'t record attendance, leave or overtime.');
        }

        return $next($request);
    }
}
