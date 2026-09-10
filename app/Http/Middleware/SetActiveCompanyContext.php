<?php

namespace App\Http\Middleware;

use App\Domain\Company\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user always has an active company context.
 * Falls back to their default company or first accessible company.
 */
class SetActiveCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! session()->has('active_company_id')) {
            $user = Auth::user();

            $companyId = null;

            if ($user->is_super_admin) {
                $companyId = $user->company_id ?? Company::query()->value('id');
            } else {
                $companyId = $user->companyAccess()
                    ->orderByDesc('is_default')
                    ->value('company_id');
            }

            session(['active_company_id' => $companyId]);
        }

        return $next($request);
    }
}