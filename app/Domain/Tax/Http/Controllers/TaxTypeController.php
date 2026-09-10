<?php

namespace App\Domain\Tax\Http\Controllers;

use App\Domain\Tax\Models\TaxType;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Http\Requests\TaxTypeRequest;
use App\Domain\Accounting\Models\Account;
use App\Support\Enums\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxTypeController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $taxTypes = TaxType::query()
            ->where('company_id', $companyId)
            ->with(['rates' => function ($q) {
                $q->with(['inputAccount', 'outputAccount'])->orderByDesc('effective_date');
            }])
            ->orderBy('name')
            ->get();

        $accountOptions = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Tax/Index', [
            'taxTypes' => $taxTypes,
            'accountOptions' => $accountOptions,
            'accountTypes' => enum_options(AccountType::class),
        ]);
    }

    public function store(TaxTypeRequest $request): RedirectResponse
    {
        $companyId = current_company_id();

        TaxType::query()->create([
            'company_id' => $companyId,
            'name' => $request->input('name'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('tax.index')->with('success', 'Tax type created.');
    }

    public function update(TaxTypeRequest $request, TaxType $taxType): RedirectResponse
    {
        $taxType->update([
            'name' => $request->input('name'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('tax.index')->with('success', 'Tax type updated.');
    }

    public function destroy(TaxType $taxType): RedirectResponse
    {
        if ($taxType->rates()->exists()) {
            return back()->with('error', 'Remove all rates before deleting this tax type.');
        }

        $taxType->delete();

        return redirect()->route('tax.index')->with('success', 'Tax type deleted.');
    }

    public function storeRate(TaxType $taxType, \App\Domain\Tax\Http\Requests\TaxRateRequest $request): RedirectResponse
    {
        $companyId = current_company_id();

        TaxRate::query()->create([
            'company_id' => $companyId,
            'tax_type_id' => $taxType->id,
            'name' => $request->input('name'),
            'rate_percent' => $request->input('rate_percent'),
            'is_inclusive' => $request->boolean('is_inclusive', false),
            'input_account_id' => $request->input('input_account_id'),
            'output_account_id' => $request->input('output_account_id'),
            'effective_date' => $request->input('effective_date'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('tax.index')->with('success', 'Tax rate added.');
    }

    public function updateRate(TaxRate $rate, \App\Domain\Tax\Http\Requests\TaxRateRequest $request): RedirectResponse
    {
        $rate->update([
            'name' => $request->input('name'),
            'rate_percent' => $request->input('rate_percent'),
            'is_inclusive' => $request->boolean('is_inclusive', false),
            'input_account_id' => $request->input('input_account_id'),
            'output_account_id' => $request->input('output_account_id'),
            'effective_date' => $request->input('effective_date'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('tax.index')->with('success', 'Tax rate updated.');
    }

    public function destroyRate(TaxRate $rate): RedirectResponse
    {
        $rate->delete();

        return redirect()->route('tax.index')->with('success', 'Tax rate deleted.');
    }
}