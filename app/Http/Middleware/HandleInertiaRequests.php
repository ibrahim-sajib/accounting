<?php

namespace App\Http\Middleware;

use App\Domain\Company\Models\Company;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $companyId = session('active_company_id');

        $company = $companyId ? Company::query()->find($companyId) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_super_admin' => $user->is_super_admin,
                    'status' => $user->status,
                ] : null,
                'permissions' => $user
                    ? $user->permissions()->distinct()->pluck('permissions.slug')
                    : [],
            ],
            'current_company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'currency_code' => $company->currency_code,
                'country_code' => $company->country_code,
                'status' => $company->status,
                'accounting_basis' => $company->accounting_basis,
                'logo' => $company->logoUrl(),
            ] : null,
            'companies' => $user
                ? $this->listAccessibleCompanies($user)
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private function listAccessibleCompanies($user): array
    {
        if ($user->is_super_admin) {
            return Company::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'currency_code'])
                ->map(fn ($company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'status' => $company->status,
                    'currency_code' => $company->currency_code,
                ])
                ->all();
        }

        return $user->accessibleCompanies()
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name', 'companies.status', 'companies.currency_code'])
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
                'currency_code' => $company->currency_code,
            ])
            ->all();
    }
}