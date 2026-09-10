<?php

namespace App\Domain\Company\Http\Controllers;

use App\Domain\Company\Http\Requests\BranchRequest;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Support\Enums\BranchStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()->is_super_admin && $request->has('company_id')
            ? $request->integer('company_id')
            : current_company_id();

        $branches = Branch::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with('manager:id,name')
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'filters' => $request->only(['search', 'company_id']),
            'companies' => $request->user()->is_super_admin ? Company::query()->orderBy('name')->get(['id', 'name']) : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = current_company_id();
        $company = $companyId ? Company::query()->find($companyId) : null;

        return Inertia::render('Branches/Create', [
            'company' => $company,
            'managers' => $this->managerOptions(),
            'statusOptions' => enum_options(BranchStatus::class),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = Branch::query()->create($request->validated() + ['company_id' => current_company_id()]);

        \App\Domain\Audit\Services\AuditLogger::log('branch', 'create', null, $branch->id, [], $branch->toArray(), current_company_id());

        return redirect()
            ->route('branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function edit(Request $request, Branch $branch): Response
    {
        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
            'company' => $branch->company,
            'managers' => $this->managerOptions(),
            'statusOptions' => enum_options(BranchStatus::class),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $old = $branch->toArray();
        $branch->update($request->validated());

        \App\Domain\Audit\Services\AuditLogger::log('branch', 'update', null, $branch->id, $old, $branch->toArray(), $branch->company_id);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $branch->delete();

        \App\Domain\Audit\Services\AuditLogger::log('branch', 'delete', null, $branch->id, $branch->toArray(), [], $branch->company_id);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Branch deleted successfully.');
    }

    protected function managerOptions(): array
    {
        return \App\Models\User::query()
            ->where(fn ($q) => $q->where('is_super_admin', true)->orWhere('company_id', current_company_id()))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($user) => ['value' => $user->id, 'label' => "{$user->name} ({$user->email})"])
            ->all();
    }
}