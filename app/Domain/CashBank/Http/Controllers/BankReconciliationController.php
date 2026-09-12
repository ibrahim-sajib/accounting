<?php

namespace App\Domain\CashBank\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CashBank\Exceptions\CashBankPostingException;
use App\Domain\CashBank\Http\Requests\BankStatementImportRequest;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\BankStatementImport;
use App\Domain\CashBank\Models\BankStatementLine;
use App\Domain\CashBank\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankReconciliationController
{
    public function __construct(
        protected BankReconciliationService $service
    ) {}

    public function store(BankStatementImportRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $bankAccount = BankAccount::query()
            ->where('company_id', current_company_id())
            ->findOrFail($data['bank_account_id']);

        $import = $this->service->import($bankAccount, $data['statement_month'], $request->file('statement_file'));

        $matched = $import->matchedCount();

        AuditLogger::log('bank_statement_import', 'create', null, $import->id, [], $import->toArray(), $bankAccount->company_id);

        return redirect()
            ->route('cash-bank.reconciliations.show', $import)
            ->with('success', "Statement imported with {$import->totalCount()} line(s); {$matched} auto-matched.");
    }

    public function autoMatch(Request $request, BankStatementImport $import): RedirectResponse
    {
        abort_unless($import->company_id === current_company_id(), 403);

        try {
            $matched = $this->service->autoMatch($import);

            AuditLogger::log('bank_statement_import', 'auto_match', null, $import->id, [], ['matched' => $matched], $import->company_id);

            return back()->with('success', "Auto-matched {$matched} statement line(s).");
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function matchLine(Request $request, BankStatementImport $import, BankStatementLine $line): RedirectResponse
    {
        abort_unless($import->company_id === current_company_id(), 403);

        try {
            $this->service->matchLine($import, $line, (int) $request->input('transaction_id'));

            return back()->with('success', 'Statement line matched to a transaction.');
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function unmatchLine(Request $request, BankStatementImport $import, BankStatementLine $line): RedirectResponse
    {
        abort_unless($import->company_id === current_company_id(), 403);

        try {
            $this->service->unmatchLine($import, $line);

            return back()->with('success', 'Statement line unmatched.');
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, BankStatementImport $import): RedirectResponse
    {
        abort_unless($import->company_id === current_company_id(), 403);

        try {
            $this->service->complete($import);

            AuditLogger::log('bank_statement_import', 'complete', null, $import->id, [], $import->fresh()->toArray(), $import->company_id);

            return redirect()
                ->route('cash-bank.reconciliations')
                ->with('success', 'Reconciliation completed. Transactions on or before '.$import->statement_month->copy()->endOfMonth()->toDateString().' are now locked.');
        } catch (CashBankPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}