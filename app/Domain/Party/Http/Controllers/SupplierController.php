<?php

namespace App\Domain\Party\Http\Controllers;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Http\Requests\SupplierRequest;
use App\Domain\Party\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $suppliers = Supplier::query()
            ->where('company_id', $companyId)
            ->with('apAccount:id,code,name')
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create', [
            'defaultApAccountId' => $this->defaultApAccountId(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated() + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('supplier', 'create', null, $supplier->id, [], $supplier->toArray(), $supplier->company_id);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier,
            'defaultApAccountId' => $this->defaultApAccountId(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $old = $supplier->toArray();
        $supplier->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('supplier', 'update', null, $supplier->id, $old, $supplier->toArray(), $supplier->company_id);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        AuditLogger::log('supplier', 'delete', null, $supplier->id, $supplier->toArray(), [], $supplier->company_id);

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }

    protected function defaultApAccountId(): ?int
    {
        return AccountingSetting::query()
            ->where('company_id', current_company_id())
            ->value('default_ap_account_id');
    }

    protected function accountOptions(): array
    {
        return \App\Domain\Accounting\Models\Account::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($account) => ['value' => $account->id, 'label' => "{$account->code} — {$account->name}"])
            ->all();
    }
}