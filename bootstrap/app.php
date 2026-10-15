<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Multi-tenancy: pick the database from the sub-domain BEFORE the
        // session starts, then verify the signed-in user belongs to this host.
        $middleware->web(
            prepend: [\App\Http\Middleware\IdentifyTenant::class],
            append: [\App\Http\Middleware\VerifyTenantSession::class],
        );

        $middleware->alias([
            'company_admin'  => \App\Http\Middleware\EnsureCompanyAdmin::class,
            'staff'          => \App\Http\Middleware\EnsureStaff::class,
            'employee'       => \App\Http\Middleware\EnsureEmployee::class,
            'force.password' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn ($request) => route($request->user()?->homeRoute() ?? 'dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
