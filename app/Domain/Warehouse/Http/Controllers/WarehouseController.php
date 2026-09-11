<?php

namespace App\Domain\Warehouse\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Warehouse\Http\Requests\WarehouseRequest;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->with(['branch:id,name', 'manager:id,name'])
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Warehouses/Create', [
            'branches' => $this->branchOptions(),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $warehouse = Warehouse::query()->create($request->validated() + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('warehouse', 'create', null, $warehouse->id, [], $warehouse->toArray(), $warehouse->company_id);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created successfully.');
    }

    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('Warehouses/Edit', [
            'warehouse' => $warehouse,
            'branches' => $this->branchOptions(),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $old = $warehouse->toArray();
        $warehouse->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('warehouse', 'update', null, $warehouse->id, $old, $warehouse->toArray(), $warehouse->company_id);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->delete();

        AuditLogger::log('warehouse', 'delete', null, $warehouse->id, $warehouse->toArray(), [], $warehouse->company_id);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted successfully.');
    }

    protected function branchOptions(): array
    {
        return \App\Domain\Company\Models\Branch::query()
            ->where('company_id', current_company_id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name])
            ->all();
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