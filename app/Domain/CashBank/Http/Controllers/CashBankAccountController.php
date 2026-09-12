<?php

namespace App\Domain\CashBank\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CashBank\Http\Requests\BankAccountRequest;
use App\Domain\CashBank\Http\Requests\CashAccountRequest;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use Illuminate\Http\RedirectResponse;

class CashBankAccountController
{
    public function storeCash(CashAccountRequest $request): RedirectResponse
    {
        $companyId = current_company_id();

        $data = $request->validated();

        $cash = CashAccount::query()->create([
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'name' => $data['name'],
            'gl_account_id' => $data['gl_account_id'],
            'is_active' => $data['is_active'],
        ]);

        AuditLogger::log('cash_account', 'create', null, $cash->id, [], $cash->toArray(), $companyId);

        return redirect()->route('cash-bank.accounts')->with('success', "Cash account '{$cash->name}' created.");
    }

    public function updateCash(CashAccountRequest $request, CashAccount $cashAccount): RedirectResponse
    {
        abort_unless($cashAccount->company_id === current_company_id(), 403);

        $old = $cashAccount->toArray();
        $data = $request->validated();

        $cashAccount->update($data);

        AuditLogger::log('cash_account', 'update', null, $cashAccount->id, $old, $cashAccount->fresh()->toArray(), $cashAccount->company_id);

        return redirect()->route('cash-bank.accounts')->with('success', "Cash account '{$cashAccount->name}' updated.");
    }

    public function destroyCash(CashAccount $cashAccount): RedirectResponse
    {
        abort_unless($cashAccount->company_id === current_company_id(), 403);

        if ($cashAccount->cashOrBankTransactions()->exists()) {
            return redirect()->route('cash-bank.accounts')->with('error', 'This cash account has transactions and cannot be deleted.');
        }

        AuditLogger::log('cash_account', 'delete', null, $cashAccount->id, $cashAccount->toArray(), [], $cashAccount->company_id);

        $cashAccount->delete();

        return redirect()->route('cash-bank.accounts')->with('success', 'Cash account deleted.');
    }

    public function storeBank(BankAccountRequest $request): RedirectResponse
    {
        $companyId = current_company_id();

        $data = $request->validated();

        $bank = BankAccount::query()->create([
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'account_name' => $data['account_name'],
            'account_no' => $data['account_no'],
            'bank_name' => $data['bank_name'],
            'branch_name' => $data['branch_name'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'gl_account_id' => $data['gl_account_id'],
            'is_active' => $data['is_active'],
        ]);

        AuditLogger::log('bank_account', 'create', null, $bank->id, [], $bank->toArray(), $companyId);

        return redirect()->route('cash-bank.accounts')->with('success', "Bank account '{$bank->account_name}' created.");
    }

    public function updateBank(BankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        abort_unless($bankAccount->company_id === current_company_id(), 403);

        $old = $bankAccount->toArray();
        $data = $request->validated();

        $bankAccount->update($data);

        AuditLogger::log('bank_account', 'update', null, $bankAccount->id, $old, $bankAccount->fresh()->toArray(), $bankAccount->company_id);

        return redirect()->route('cash-bank.accounts')->with('success', "Bank account '{$bankAccount->account_name}' updated.");
    }

    public function destroyBank(BankAccount $bankAccount): RedirectResponse
    {
        abort_unless($bankAccount->company_id === current_company_id(), 403);

        if ($bankAccount->cashOrBankTransactions()->exists()) {
            return redirect()->route('cash-bank.accounts')->with('error', 'This bank account has transactions and cannot be deleted.');
        }

        AuditLogger::log('bank_account', 'delete', null, $bankAccount->id, $bankAccount->toArray(), [], $bankAccount->company_id);

        $bankAccount->delete();

        return redirect()->route('cash-bank.accounts')->with('success', 'Bank account deleted.');
    }
}