<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Permission middleware: aborts 403 unless the user holds the permission,
     * or is a super admin.
     *
     * Usage: ->middleware('permission:company.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }

        if (! $request->user()->hasPermission($permission, session('active_company_id'))) {
            abort(403, "You do not have the [{$permission}] permission.");
        }

        return $next($request);
    }
}