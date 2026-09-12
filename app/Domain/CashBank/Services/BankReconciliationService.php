<?php

namespace App\Domain\CashBank\Services;

use App\Domain\CashBank\Exceptions\CashBankPostingException;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\BankStatementImport;
use App\Domain\CashBank\Models\BankStatementLine;
use App\Domain\CashBank\Models\CashBankTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BankReconciliationService
{
    public function __construct(
        protected CashBankService $cashBankService
    ) {}

    /**
     * Import a bank statement CSV (date, description, amount) for a bank account + month.
     */
    public function import(BankAccount $bankAccount, string $month, UploadedFile $file): BankStatementImport
    {
        $path = $file->store('bank-statements', 'public');

        $import = BankStatementImport::query()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'statement_month' => $this->monthStart($month)->toDateString(),
            'file_path' => $path,
            'status' => BankStatementImport::STATUS_IN_PROGRESS,
            'imported_at' => now(),
            'imported_by' => Auth::id(),
        ]);

        $rows = $this->parseCsv($file);

        foreach ($rows as $row) {
            BankStatementLine::query()->create([
                'bank_statement_import_id' => $import->id,
                'line_date' => $row['date'],
                'description' => $row['description'],
                'amount' => $row['amount'],
                'is_reconciled' => false,
            ]);
        }

        $this->autoMatch($import);

        return $import;
    }

    /**
     * Try to auto-match every unmatched statement line against an existing
     * system transaction for the same bank account (same date + magnitude).
     */
    public function autoMatch(BankStatementImport $import): int
    {
        if ($import->isCompleted()) {
            throw new CashBankPostingException('This reconciliation is already completed.');
        }

        $matched = 0;

        foreach ($import->lines()->where('is_reconciled', false)->get() as $line) {
            $transaction = $this->candidateTransaction($import, $line->line_date->toDateString(), abs((float) $line->amount));

            if ($transaction) {
                $line->update([
                    'matched_transaction_id' => $transaction->id,
                    'is_reconciled' => true,
                ]);
                $matched++;
            }
        }

        return $matched;
    }

    public function matchLine(BankStatementImport $import, BankStatementLine $line, int $transactionId): void
    {
        if ($import->isCompleted()) {
            throw new CashBankPostingException('This reconciliation is already completed.');
        }

        if ($line->import_id !== $import->id) {
            throw new CashBankPostingException('This statement line does not belong to the reconciliation.');
        }

        $transaction = CashBankTransaction::query()
            ->where('company_id', $import->company_id)
            ->where('bank_account_id', $import->bank_account_id)
            ->findOrFail($transactionId);

        $line->update([
            'matched_transaction_id' => $transaction->id,
            'is_reconciled' => true,
        ]);
    }

    public function unmatchLine(BankStatementImport $import, BankStatementLine $line): void
    {
        if ($import->isCompleted()) {
            throw new CashBankPostingException('This reconciliation is already completed.');
        }

        if ($line->import_id !== $import->id) {
            throw new CashBankPostingException('This statement line does not belong to the reconciliation.');
        }

        $line->update([
            'matched_transaction_id' => null,
            'is_reconciled' => false,
        ]);
    }

    public function complete(BankStatementImport $import): void
    {
        if ($import->isCompleted()) {
            throw new CashBankPostingException('This reconciliation is already completed.');
        }

        $unmatched = $import->lines()->where('is_reconciled', false)->count();

        if ($unmatched > 0) {
            throw new CashBankPostingException(
                "Cannot complete the reconciliation while {$unmatched} statement line(s) remain unmatched."
            );
        }

        $import->update([
            'status' => BankStatementImport::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => Auth::id(),
        ]);

        $import->bankAccount()->update([
            'last_reconciled_date' => $import->statement_month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    protected function monthStart(string $month): Carbon
    {
        $normalized = strlen($month) === 7 ? $month.'-01' : $month;

        return Carbon::parse($normalized)->startOfMonth();
    }

    protected function candidateTransaction(BankStatementImport $import, string $date, float $magnitude): ?CashBankTransaction
    {
        $normalizedDate = Carbon::parse($date)->toDateString();

        $matchedIds = \DB::table('bank_statement_lines')
            ->whereNotNull('matched_transaction_id')
            ->pluck('matched_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return CashBankTransaction::query()
            ->where('company_id', $import->company_id)
            ->where('bank_account_id', $import->bank_account_id)
            ->whereDate('transaction_date', $normalizedDate)
            ->where('amount', $magnitude)
            ->when($matchedIds, fn ($q) => $q->whereNotIn('id', $matchedIds))
            ->orderBy('id')
            ->first();
    }

    protected function parseCsv(UploadedFile $file): array
    {
        $content = $file->get();
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        $isFirst = true;

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < 2) {
                continue;
            }

            $date = trim($line[0]);
            $description = trim($line[1] ?? '');
            $rawAmount = trim($line[2] ?? $line[1]);

            if ($isFirst && $this->looksLikeHeader($date, $description, $rawAmount)) {
                $isFirst = false;
                continue;
            }

            $isFirst = false;

            $parsedDate = $this->parseDate($date);

            if (! $parsedDate) {
                continue;
            }

            $amount = $this->parseAmount($rawAmount);

            if ($amount === 0.0) {
                continue;
            }

            $rows[] = [
                'date' => $parsedDate,
                'description' => $this->cleanDescription($description),
                'amount' => $amount,
            ];
        }

        fclose($handle);

        return $rows;
    }

    protected function looksLikeHeader(string ...$values): bool
    {
        return collect($values)->contains(fn ($value) => in_array(strtolower($value), ['date', 'transaction date', 'dd/mm/yyyy', 'description', 'details', 'narration', 'amount', 'debit', 'credit'], true));
    }

    protected function parseDate(string $value): ?string
    {
        $cleaned = str_replace(['/', '.'], '-', $value);

        $formats = ['Y-m-d', 'd-m-Y', 'd-m-Y H:i:s', 'M d Y', 'M j, Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $cleaned);
                if ($date) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseAmount(string $value): float
    {
        $cleaned = str_replace([',', '৳', 'BDT', ' '], '', $value);
        $negative = str_contains($cleaned, '(') || str_starts_with($cleaned, '-') || str_ends_with($cleaned, 'DR');

        $number = (float) preg_replace('/[^0-9.\-]/', '', $cleaned);

        return $negative ? -1 * abs($number) : abs($number);
    }

    protected function cleanDescription(string $value): string
    {
        return mb_substr(trim(str_replace(['"', "'"], '', $value)), 0, 200);
    }
}