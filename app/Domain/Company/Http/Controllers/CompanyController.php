<?php

namespace App\Domain\Company\Http\Controllers;

use App\Domain\Company\Http\Requests\CompanyRequest;
use App\Domain\Company\Models\Company;
use App\Support\Enums\AccountType;
use App\Support\Enums\CompanyStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController
{
    /**
     * List companies (super admin only, across tenants).
     */
    public function index(Request $request): Response
    {
        $this->authorizeCompanyView($request);

        $companies = Company::query()
            ->withCount(['branches', 'users'])
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tax_registration_no', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
            'statusOptions' => enum_options(CompanyStatus::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeCompanyManage($request);

        return Inertia::render('Companies/Create', [
            'statusOptions' => enum_options(CompanyStatus::class),
            'accountingBasisOptions' => enum_options(\App\Support\Enums\AccountingBasis::class),
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($this->normalizePayload($request));

        // Prime defaults for the new company so later modules work immediately.
        $this->provisionDefaults($company);

        \App\Domain\Audit\Services\AuditLogger::log('company', 'create', null, $company->id, [], $company->toArray(), $company->id);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Request $request, Company $company): Response
    {
        $this->authorizeCompanyManage($request);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
            'statusOptions' => enum_options(CompanyStatus::class),
            'accountingBasisOptions' => enum_options(\App\Support\Enums\AccountingBasis::class),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $old = $company->toArray();
        $company->update($this->normalizePayload($request));

        \App\Domain\Audit\Services\AuditLogger::log('company', 'update', null, $company->id, $old, $company->toArray(), $company->id);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company updated successfully.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompanyManage($request);

        if (in_array($company->id, [current_company_id()]) && current_company_id() !== null) {
            return back()->with('error', 'You cannot delete the company you are currently operating on.');
        }

        $company->delete();

        \App\Domain\Audit\Services\AuditLogger::log('company', 'delete', null, $company->id, $company->toArray(), [], $company->id);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Switch the active company context (multi-company support).
     */
    public function switch(Request $request): RedirectResponse
    {
        $companyId = $request->integer('company_id');

        $company = Company::query()->findOrFail($companyId);

        // Super admin can switch anywhere; others must have explicit access.
        if (! $request->user()->is_super_admin) {
            $hasAccess = $request->user()->companyAccess()->where('company_id', $companyId)->exists();

            abort_unless($hasAccess, 403, 'You do not have access to this company.');
        }

        session(['active_company_id' => $company->id]);
        session()->forget('active_branch_id');

        return redirect()->route('dashboard')->with('success', "Switched to {$company->name}.");
    }

    private function authorizeCompanyView(Request $request): void
    {
        if (! $request->user()->is_super_admin) {
            abort(403);
        }
    }

    private function authorizeCompanyManage(Request $request): void
    {
        if (! $request->user()->is_super_admin) {
            abort(403);
        }
    }

    protected function provisionDefaults(Company $company): void
    {
        (new \Database\Seeders\CurrencySeeder())->run();
        (new \Database\Seeders\FiscalYearSeeder())->run();
        (new \Database\Seeders\SystemSettingSeeder())->run();
        (new \Database\Seeders\ChartOfAccountsSeeder())->run();
        (new \Database\Seeders\TaxSeeder())->run();
        (new \Database\Seeders\AccountingSettingSeeder())->run();
        (new \Database\Seeders\MasterDataSeeder())->run();
        (new \Database\Seeders\ExpenseCategorySeeder())->run();
        (new \Database\Seeders\RoleSeeder())->run($company->id);
        \App\Domain\Audit\Services\AuditLogger::log('company', 'provision', null, $company->id, [], ['currencies', 'fiscal_years', 'settings', 'chart_of_accounts', 'tax', 'accounting_settings', 'master_data', 'expense_categories', 'roles'], $company->id);
    }

    protected function normalizePayload(CompanyRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        unset($data['logo']);

        return $data;
    }
}