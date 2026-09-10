<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingSettingController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $setting = AccountingSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                ['created_by' => auth()->id(), 'updated_by' => auth()->id()]
            );

        $accountOptions = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('AccountingSettings/Index', [
            'setting' => $setting,
            'accountOptions' => $accountOptions,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $companyId = current_company_id();

        $request->validate([
            'default_sales_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_purchase_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_ar_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_ap_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_cash_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_bank_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_tax_input_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_tax_output_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'voucher_numbering' => ['nullable', 'json'],
        ]);

        $validated = $request->only([
            'default_sales_account_id', 'default_purchase_account_id',
            'default_inventory_account_id', 'default_ar_account_id', 'default_ap_account_id',
            'default_cash_account_id', 'default_bank_account_id',
            'default_tax_input_account_id', 'default_tax_output_account_id',
            'voucher_numbering',
        ]);

        $validated['updated_by'] = auth()->id();

        if (is_string($validated['voucher_numbering'] ?? null)) {
            $validated['voucher_numbering'] = json_decode($validated['voucher_numbering'], true);
        }

        AccountingSetting::query()
            ->where('company_id', $companyId)
            ->update($validated);

        return redirect()->route('accounting-settings.index')->with('success', 'Accounting configuration updated.');
    }
}