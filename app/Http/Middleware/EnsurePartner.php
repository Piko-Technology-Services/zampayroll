<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The partner dashboard is only for partner accounts, and only on the main domain. */
class EnsurePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (\App\Support\Tenancy::isDedicated()) {
            abort(404);
        }

        if (! $user || ! $user->isPartner()) {
            return $user ? redirect()->route($user->homeRoute()) : redirect()->route('login');
        }

        if (! $user->partner) {
            abort(403, 'This partner account has no partner record. Please contact ZamPayroll.');
        }

        return $next($request);
    }
}
