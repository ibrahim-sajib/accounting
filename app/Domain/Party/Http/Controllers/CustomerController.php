<?php

namespace App\Domain\Party\Http\Controllers;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Party\Http\Requests\CustomerRequest;
use App\Domain\Party\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->with('arAccount:id,code,name')
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create', [
            'defaultArAccountId' => $this->defaultArAccountId(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated() + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('customer', 'create', null, $customer->id, [], $customer->toArray(), $customer->company_id);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'defaultArAccountId' => $this->defaultArAccountId(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $old = $customer->toArray();
        $customer->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('customer', 'update', null, $customer->id, $old, $customer->toArray(), $customer->company_id);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        AuditLogger::log('customer', 'delete', null, $customer->id, $customer->toArray(), [], $customer->company_id);

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    protected function defaultArAccountId(): ?int
    {
        return AccountingSetting::query()
            ->where('company_id', current_company_id())
            ->value('default_ar_account_id');
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