<?php

namespace App\Domain\CashBank\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\CashBank\Exceptions\CashBankPostingException;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\CashBank\Models\CashBankTransaction;
use App\Support\Enums\CashBankTransactionType;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cash & Bank engine (module 18): direct cash receipts/payments, bank deposits,
 * withdrawals, transfers, charges and interest — each posted straight to a
 * balanced CBT journal.
 */
class CashBankService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    /**
     * @param  array{transaction_type: string, transaction_date: string, amount: float|string, cash_account_id?: ?int, bank_account_id?: ?int, to_bank_account_id?: ?int, counter_account_id?: ?int, reference?: ?string, description?: ?string}  $data
     */
    public function createTransaction(array $data): CashBankTransaction
    {
        $type = CashBankTransactionType::from($data['transaction_type']);
        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw new CashBankPostingException('The transaction amount must be greater than zero.');
        }

        $lines = $this->buildLines($type, $data, $amount);

        return DB::transaction(function () use ($data, $type, $amount, $lines) {
            $companyId = (int) $data['company_id'];
            $date = $data['transaction_date'];

            $transaction = CashBankTransaction::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? session('active_branch_id'),
                'transaction_no' => $this->nextTransactionNo($companyId, $date),
                'transaction_type' => $type->value,
                'transaction_date' => $date,
                'amount' => $amount,
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'to_bank_account_id' => $data['to_bank_account_id'] ?? null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->postJournal($transaction, $lines);

            return $transaction;
        });
    }

    public function deleteTransaction(CashBankTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if (($transaction->bankAccount?->last_reconciled_date
                    && ! $transaction->transaction_date->gt($transaction->bankAccount->last_reconciled_date))
                && ! (Auth::user()?->is_super_admin)
            ) {
                throw new CashBankPostingException('Transactions on or before the last reconciled date cannot be deleted.');
            }

            if ($transaction->journal_id) {
                $transaction->journal()->delete();
            }

            $transaction->delete();
        });
    }

    /**
     * Balance of a GL account derived from posted journal lines (debits minus credits).
     */
    public function glBalance(int $accountId): float
    {
        $net = JournalLine::query()
            ->selectRaw('SUM(debit) - SUM(credit) as net')
            ->where('account_id', $accountId)
            ->first();

        return round((float) ($net->net ?? 0), 4);
    }

    /**
     * Balances for a set of GL account ids keyed by account id.
     */
    public function glBalancesFor(array $accountIds): array
    {
        if (empty($accountIds)) {
            return [];
        }

        $rows = JournalLine::query()
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as net')
            ->groupBy('account_id')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [(int) $row->account_id => round((float) $row->net, 4)])->all();
    }

    /**
     * @return array<int, array{account_id: int, debit: float, credit: float, description: string}>
     */
    protected function buildLines(CashBankTransactionType $type, array $data, float $amount): array
    {
        $companyId = (int) $data['company_id'];
        $reference = $data['reference'] ?? null;

        $cash = fn (): ?int => $this->cashGl((int) $data['cash_account_id'], $companyId);
        $bank = fn (): ?int => $this->bankGl((int) $data['bank_account_id'], $companyId);
        $counter = fn (): ?int => $this->counterGl((int) $data['counter_account_id'], $companyId);

        $lines = match ($type) {
            CashBankTransactionType::CashReceipt => [
                $this->line($cash(), 'Cash receipt', $amount, 0, $reference),
                $this->line($counter(), 'Cash receipt', 0, $amount, $reference),
            ],
            CashBankTransactionType::CashPayment => [
                $this->line($counter(), 'Cash payment', $amount, 0, $reference),
                $this->line($cash(), 'Cash payment', 0, $amount, $reference),
            ],
            CashBankTransactionType::BankDeposit => [
                $this->line($bank(), 'Bank deposit', $amount, 0, $reference),
                $this->line($cash(), 'Bank deposit', 0, $amount, $reference),
            ],
            CashBankTransactionType::BankWithdrawal => [
                $this->line($cash(), 'Bank withdrawal', $amount, 0, $reference),
                $this->line($bank(), 'Bank withdrawal', 0, $amount, $reference),
            ],
            CashBankTransactionType::BankTransfer => [
                $this->line($this->toBankGl((int) $data['to_bank_account_id'], $companyId), 'Bank transfer in', $amount, 0, $reference),
                $this->line($bank(), 'Bank transfer out', 0, $amount, $reference),
            ],
            CashBankTransactionType::BankCharge => [
                $this->line($counter(), 'Bank charge', $amount, 0, $reference),
                $this->line($bank(), 'Bank charge', 0, $amount, $reference),
            ],
            CashBankTransactionType::BankInterest => [
                $this->line($bank(), 'Bank interest', $amount, 0, $reference),
                $this->line($counter(), 'Bank interest', 0, $amount, $reference),
            ],
        };

        return $lines;
    }

    protected function line(?int $accountId, string $description, float $debit, float $credit, ?string $reference): array
    {
        if (! $accountId) {
            throw new CashBankPostingException('Please complete the account fields for this transaction type.');
        }

        return [
            'account_id' => $accountId,
            'party_type' => null,
            'party_id' => null,
            'description' => $description.($reference ? " — {$reference}" : ''),
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    protected function cashGl(int $cashAccountId, int $companyId): int
    {
        $account = CashAccount::query()->where('company_id', $companyId)->find($cashAccountId);

        if (! $account || ! $account->is_active) {
            throw new CashBankPostingException('The selected cash account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The cash account has no postable GL account.');
    }

    protected function bankGl(int $bankAccountId, int $companyId): int
    {
        $account = BankAccount::query()->where('company_id', $companyId)->find($bankAccountId);

        if (! $account || ! $account->is_active) {
            throw new CashBankPostingException('The selected bank account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The bank account has no postable GL account.');
    }

    protected function toBankGl(int $bankAccountId, int $companyId): int
    {
        return $this->bankGl($bankAccountId, $companyId);
    }

    protected function counterGl(int $accountId, int $companyId): int
    {
        return $this->postable($accountId, 'Please select a postable counter account (income or expense).');
    }

    protected function postable(int $accountId, string $message): int
    {
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_postable || ! $account->is_active) {
            throw new CashBankPostingException($message);
        }

        return $accountId;
    }

    protected function postJournal(CashBankTransaction $transaction, array $lines): Journal
    {
        $journal = Journal::query()->create([
            'company_id' => $transaction->company_id,
            'branch_id' => $transaction->branch_id ?? session('active_branch_id'),
            'period_id' => $this->periodFor($transaction->company_id, $transaction->transaction_date->toDateString())?->id,
            'journal_date' => $transaction->transaction_date,
            'source_type' => JournalSourceType::Bank->value,
            'source_id' => $transaction->id,
            'reference' => $transaction->transaction_no,
            'description' => ucwords(str_replace('_', ' ', $transaction->transaction_type)).' '.$transaction->transaction_no,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $journal->lines()->createMany($lines);

        $this->postingService->post($journal);

        $transaction->update(['journal_id' => $journal->id, 'updated_by' => Auth::id()]);

        return $journal;
    }

    protected function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function nextTransactionNo(int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = CashBankTransaction::query()
            ->where('company_id', $companyId)
            ->where('transaction_no', 'like', "CBT-{$year}-%")
            ->orderByDesc('transaction_no')
            ->value('transaction_no');

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("CBT-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('CBT-%s-%04d', $year, $sequence);
    }

    /**
     * Unmatched bank transactions available for reconciliation matching.
     */
    public function unmatchedBankTransactions(BankAccount $bankAccount, string $month): \Illuminate\Support\Collection
    {
        $start = Carbon::parse($month)->startOfMonth()->toDateString();
        $end = Carbon::parse($month)->endOfMonth()->toDateString();
        $glId = $bankAccount->gl_account_id;

        $alreadyMatched = \DB::table('bank_statement_lines')
            ->whereNotNull('matched_transaction_id')
            ->pluck('matched_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return CashBankTransaction::query()
            ->where('company_id', $bankAccount->company_id)
            ->where('bank_account_id', $bankAccount->id)
            ->whereBetween('transaction_date', [$start, $end])
            ->when($alreadyMatched, fn ($q) => $q->whereNotIn('id', $alreadyMatched))
            ->orderBy('transaction_date')
            ->get()
            ->map(function (CashBankTransaction $transaction) use ($glId) {
                $line = $transaction->journal?->lines->firstWhere('account_id', $glId);

                $glAmount = $line ? round((float) $line->debit - (float) $line->credit, 4) : 0.0;

                return [
                    'id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                    'type' => $transaction->transaction_type,
                    'amount' => (float) $transaction->amount,
                    'gl_amount' => $glAmount,
                ];
            });
    }
}