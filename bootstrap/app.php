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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetActiveCompanyContext::class,
            \App\Http\Middleware\SelectTenantDatabase::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The default "web" group runs SubstituteBindings after StartSession.
        // Route-model binding (`SalesInvoice $invoice`, etc.) therefore queries
        // the DEFAULT connection BEFORE SelectTenantDatabase has switched to the
        // active company's tenant database — so every bound tenant model fell
        // back to the control-plane connection and 404'd. Lift both tenant
        // middleware into the priority list ahead of SubstituteBindings so the
        // tenant connection is selected before route-model binding resolves.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SelectTenantDatabase::class
        );
        $middleware->prependToPriorityList(
            \App\Http\Middleware\SelectTenantDatabase::class,
            \App\Http\Middleware\SetActiveCompanyContext::class
        );

        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
