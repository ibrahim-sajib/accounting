<?php

namespace App\Domain\CashBank\Http\Controllers;

use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\CashBank\Models\CashBankTransaction;
use App\Domain\CashBank\Services\CashBankService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashBankController
{
    public function __construct(
        protected CashBankService $service
    ) {}

    public function index(): Response
    {
        $companyId = current_company_id();

        $cashAccounts = $this->cashAccountsWithBalance($companyId);
        $bankAccounts = $this->bankAccountsWithBalance($companyId);

        $recentTransactions = $this->recentTransactions($companyId);

        return Inertia::render('CashBank/Index', [
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'recentTransactions' => $recentTransactions,
            'totalCash' => round(collect($cashAccounts)->sum('balance'), 4),
            'totalBank' => round(collect($bankAccounts)->sum('balance'), 4),
        ]);
    }

    public function transactions(Request $request): Response
    {
        $companyId = current_company_id();

        $query = CashBankTransaction::query()
            ->where('company_id', $companyId)
            ->with(['cashAccount:id,name', 'bankAccount:id,account_name,bank_name', 'journal:id,journal_no']);

        $type = $request->input('type');
        if ($type) {
            $query->where('transaction_type', $type);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(fn ($q) => $q
                ->where('transaction_no', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            );
        }

        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (CashBankTransaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'transaction_type' => $transaction->transaction_type,
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                    'amount' => (float) $transaction->amount,
                    'reference' => $transaction->reference,
                    'description' => $transaction->description,
                    'cash_account' => $transaction->cashAccount
                        ? ['id' => $transaction->cashAccount->id, 'name' => $transaction->cashAccount->name]
                        : null,
                    'bank_account' => $transaction->bankAccount
                        ? ['id' => $transaction->bankAccount->id, 'account_name' => $transaction->bankAccount->account_name, 'bank_name' => $transaction->bankAccount->bank_name]
                        : null,
                    'journal_no' => $transaction->journal?->journal_no,
                    'journal_id' => $transaction->journal?->id,
                ];
            });

        return Inertia::render('CashBank/Transactions', [
            'transactions' => $transactions,
            'cashAccounts' => CashAccount::query()->where('company_id', $companyId)->where('is_active', true)->get(['id', 'name']),
            'bankAccounts' => BankAccount::query()->where('company_id', $companyId)->where('is_active', true)->get(['id', 'account_name', 'bank_name']),
            'counterAccounts' => $this->glAccountOptions($companyId),
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function accounts(): Response
    {
        $companyId = current_company_id();

        $cashAccounts = $this->cashAccountsWithBalance($companyId);
        $bankAccounts = $this->bankAccountsWithBalance($companyId);

        $postableAccounts = $this->glAccountOptions($companyId);
        $currencies = $this->currencyOptions($companyId);

        return Inertia::render('CashBank/Accounts', [
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'glAccounts' => $postableAccounts,
            'currencies' => $currencies,
        ]);
    }

    public function reconciliations(): Response
    {
        $companyId = current_company_id();

        $bankAccounts = BankAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'account_name', 'bank_name', 'last_reconciled_date'])
            ->map(fn (BankAccount $ba) => [
                'id' => $ba->id,
                'account_name' => $ba->account_name,
                'bank_name' => $ba->bank_name,
                'last_reconciled_date' => $ba->last_reconciled_date?->toDateString(),
            ]);

        $imports = \App\Domain\CashBank\Models\BankStatementImport::query()
            ->where('company_id', $companyId)
            ->with('bankAccount:id,account_name,bank_name')
            ->orderByDesc('statement_month')
            ->limit(10)
            ->get()
            ->map(fn ($import) => [
                'id' => $import->id,
                'statement_month' => $import->statement_month->toDateString(),
                'bank_account' => $import->bankAccount ? ['id' => $import->bankAccount->id, 'account_name' => $import->bankAccount->account_name] : null,
                'status' => $import->status,
                'matched_count' => $import->matchedCount(),
                'total_count' => $import->totalCount(),
                'completed_at' => $import->completed_at?->toDateTimeString(),
            ]);

        return Inertia::render('CashBank/Reconciliations', [
            'bankAccounts' => $bankAccounts,
            'imports' => $imports,
        ]);
    }

    public function showReconciliation(int $importId): Response
    {
        $companyId = current_company_id();

        $import = \App\Domain\CashBank\Models\BankStatementImport::query()
            ->where('company_id', $companyId)
            ->with(['bankAccount:id,account_name,bank_name,last_reconciled_date', 'lines.matchedTransaction.bankAccount:id,account_name', 'lines.matchedTransaction.cashAccount:id,name'])
            ->findOrFail($importId);

        $unmatched = $import->isCompleted()
            ? []
            : $this->service->unmatchedBankTransactions($import->bankAccount, $import->statement_month->toDateString())->all();

        return Inertia::render('CashBank/ReconciliationShow', [
            'import' => [
                'id' => $import->id,
                'statement_month' => $import->statement_month->toDateString(),
                'bank_account' => $import->bankAccount ? ['id' => $import->bankAccount->id, 'account_name' => $import->bankAccount->account_name] : null,
                'status' => $import->status,
                'completed_at' => $import->completed_at?->toDateTimeString(),
                'lines' => $import->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'line_date' => $line->line_date->toDateString(),
                    'description' => $line->description,
                    'amount' => (float) $line->amount,
                    'is_reconciled' => $line->is_reconciled,
                    'matched_transaction' => $line->matchedTransaction ? [
                        'id' => $line->matchedTransaction->id,
                        'transaction_no' => $line->matchedTransaction->transaction_no,
                        'transaction_date' => $line->matchedTransaction->transaction_date->toDateString(),
                        'type' => $line->matchedTransaction->transaction_type,
                        'amount' => (float) $line->matchedTransaction->amount,
                        'account_label' => match (true) {
                            $line->matchedTransaction->cashAccount !== null => $line->matchedTransaction->cashAccount->name,
                            $line->matchedTransaction->bankAccount !== null => $line->matchedTransaction->bankAccount->account_name.' ('.$line->matchedTransaction->bankAccount->bank_name.')',
                            default => ''
                        },
                    ] : null,
                ]),
            ],
            'unmatchedTransactions' => $unmatched,
        ]);
    }

    // ─── helpers ───────────────────────────────────────────────────────────────────

    private function cashAccountsWithBalance(int $companyId): array
    {
        $accounts = CashAccount::query()->where('company_id', $companyId)->get();

        $glIds = $accounts->pluck('gl_account_id')->map(fn ($id) => (int) $id)->all();
        $balances = $this->service->glBalancesFor($glIds);

        return $accounts->map(function (CashAccount $account) use ($balances) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'gl_account_code' => $account->glAccount?->code,
                'gl_account_id' => $account->gl_account_id,
                'balance' => $balances[$account->gl_account_id] ?? 0.0,
                'is_active' => $account->is_active,
            ];
        })->toArray();
    }

    private function bankAccountsWithBalance(int $companyId): array
    {
        $accounts = BankAccount::query()->where('company_id', $companyId)->get();

        $glIds = $accounts->pluck('gl_account_id')->map(fn ($id) => (int) $id)->all();
        $balances = $this->service->glBalancesFor($glIds);

        return $accounts->map(function (BankAccount $account) use ($balances) {
            return [
                'id' => $account->id,
                'account_name' => $account->account_name,
                'account_no' => $account->account_no,
                'bank_name' => $account->bank_name,
                'branch_name' => $account->branch_name,
                'gl_account_code' => $account->glAccount?->code,
                'gl_account_id' => $account->gl_account_id,
                'currency_id' => $account->currency_id,
                'balance' => $balances[$account->gl_account_id] ?? 0.0,
                'last_reconciled_date' => $account->last_reconciled_date?->toDateString(),
                'is_active' => $account->is_active,
            ];
        })->toArray();
    }

    private function recentTransactions(int $companyId): array
    {
        return CashBankTransaction::query()
            ->where('company_id', $companyId)
            ->with('cashAccount:id,name', 'bankAccount:id,account_name,bank_name')
            ->orderByDesc('transaction_date')
            ->limit(10)
            ->get()
            ->map(fn (CashBankTransaction $t) => [
                'id' => $t->id,
                'transaction_no' => $t->transaction_no,
                'transaction_type' => $t->transaction_type,
                'transaction_date' => $t->transaction_date->toDateString(),
                'amount' => (float) $t->amount,
                'cash_account' => $t->cashAccount?->name,
                'bank_account' => $t->bankAccount?->account_name,
                'reference' => $t->reference,
            ])
            ->toArray();
    }

    private function glAccountOptions(int $companyId): array
    {
        return \App\Domain\Accounting\Models\Account::query()
            ->where('company_id', $companyId)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (\App\Domain\Accounting\Models\Account $a) => [
                'value' => $a->id,
                'label' => "{$a->code} — {$a->name}",
            ])
            ->all();
    }

    private function currencyOptions(int $companyId): array
    {
        return \App\Domain\Currency\Models\Currency::query()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (\App\Domain\Currency\Models\Currency $c) => ['value' => $c->id, 'label' => "{$c->code} — {$c->name}"])
            ->all();
    }
}