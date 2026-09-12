<?php

namespace App\Domain\CashBank\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CashBank\Exceptions\CashBankPostingException;
use App\Domain\CashBank\Http\Requests\CashBankTransactionRequest;
use App\Domain\CashBank\Models\CashBankTransaction;
use App\Domain\CashBank\Services\CashBankService;
use Illuminate\Http\RedirectResponse;

class CashBankTransactionController
{
    public function __construct(
        protected CashBankService $service
    ) {}

    public function store(CashBankTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['company_id'] = current_company_id();

        try {
            $transaction = $this->service->createTransaction($data);

            AuditLogger::log('cash_bank_transaction', 'create', null, $transaction->id, [], $transaction->fresh()->toArray(), $transaction->company_id);

            return redirect()
                ->route('cash-bank.transactions')
                ->with('success', 'Transaction '.$transaction->transaction_no.' posted.');
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(CashBankTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->company_id === current_company_id(), 403);

        try {
            $this->service->deleteTransaction($transaction);

            AuditLogger::log('cash_bank_transaction', 'delete', null, $transaction->id, [], [], $transaction->company_id);

            return redirect()
                ->route('cash-bank.transactions')
                ->with('success', 'Transaction deleted.');
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}