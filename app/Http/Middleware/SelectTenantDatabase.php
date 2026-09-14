<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects the request's runtime default database: the active company's tenant
 * database for business routes, or the control-plane connection for platform
 * screens (companies/users/roles/profile/notifications). Runs after auth and
 * the active-company bootstrap, before controllers and the Inertia share.
 */
class SelectTenantDatabase
{
    /**
     * Route name prefixes that stay on the control-plane connection.
     */
    public const PLATFORM_PREFIXES = [
        'companies.',
        'users.',
        'roles.',
        'profile.',
        'notifications.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantManager::class);

        if (! $tenant->enabled()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        $isPlatform = collect(self::PLATFORM_PREFIXES)
            ->contains(fn (string $prefix) => str_starts_with($routeName, $prefix));

        // Always re-assert the platform default here — even for guests — so a
        // previous request that switched this FPM worker's default connection
        // to a tenant database cannot leak that mutation into the next request.
        $tenant->configure(
            $request->user() && ! $isPlatform ? session('active_company_id') : null
        );

        return $next($request);
    }
}