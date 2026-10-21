<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The ZamPayroll admin console is only for platform administrators, and only on the main domain. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (\App\Support\Tenancy::isDedicated() || ! $user || ! $user->isPlatformAdmin()) {
            abort(404);
        }

        return $next($request);
    }
}
