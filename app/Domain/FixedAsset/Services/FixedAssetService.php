<?php

namespace App\Domain\FixedAsset\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\FixedAsset\Exceptions\FixedAssetPostingException;
use App\Domain\FixedAsset\Models\AssetDisposal;
use App\Domain\FixedAsset\Models\AssetCategory;
use App\Domain\FixedAsset\Models\DepreciationEntry;
use App\Domain\FixedAsset\Models\FixedAsset;
use App\Support\Enums\AssetPaymentMethod;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\DepreciationMethod;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fixed Assets engine (module 21): register → capitalize (FA journal) →
 * monthly depreciation batch (DEP journal) → disposal (DSP journal).
 */
class FixedAssetService
{
    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    /**
     * @param  array{category_id: int, asset_code: string, name: string, acquisition_date: string, acquisition_cost: float|string, useful_life_months?: int, method?: string, location?: ?string, acquisition_method?: string, cash_account_id?: ?int, bank_account_id?: ?int, supplier_id?: ?int, payable_account_id?: ?int}  $data
     */
    public function store(array $data, int $companyId): FixedAsset
    {
        return DB::transaction(function () use ($data, $companyId) {
            $category = AssetCategory::query()->where('company_id', $companyId)->findOrFail($data['category_id']);

            $cost = round((float) $data['acquisition_cost'], 4);

            $this->assertPaymentAccounts($data, $companyId);

            return FixedAsset::query()->create([
                'company_id' => $companyId,
                'branch_id' => session('active_branch_id'),
                'category_id' => $category->id,
                'asset_code' => $data['asset_code'],
                'name' => $data['name'],
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost' => $cost,
                'useful_life_months' => (int) ($data['useful_life_months'] ?? $category->default_useful_life_months),
                'method' => $data['method'] ?? $category->default_method,
                'location' => $data['location'] ?? null,
                'status' => AssetStatus::Draft->value,
                'acquisition_method' => $data['acquisition_method'] ?? 'cash',
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'payable_account_id' => $this->resolvePayableAccount($data, $companyId),
            ]);
        });
    }

    /**
     * @param  array{category_id: int, asset_code: string, name: string, acquisition_date: string, acquisition_cost: float|string, useful_life_months?: int, method?: string, location?: ?string, acquisition_method?: string, cash_account_id?: ?int, bank_account_id?: ?int, supplier_id?: ?int}  $data
     */
    public function update(FixedAsset $asset, array $data): FixedAsset
    {
        if (! $asset->isDraft()) {
            throw new FixedAssetPostingException('Capitalized or disposed assets cannot be changed.');
        }

        $this->assertPaymentAccounts($data, $asset->company_id);

        $asset->update([
            'category_id' => $data['category_id'],
            'asset_code' => $data['asset_code'],
            'name' => $data['name'],
            'acquisition_date' => $data['acquisition_date'],
            'acquisition_cost' => round((float) $data['acquisition_cost'], 4),
            'useful_life_months' => (int) ($data['useful_life_months'] ?? $asset->category?->default_useful_life_months ?: 36),
            'method' => $data['method'] ?? $asset->method,
            'location' => $data['location'] ?? null,
            'acquisition_method' => $data['acquisition_method'] ?? 'cash',
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'payable_account_id' => $this->resolvePayableAccount($data, $asset->company_id),
            'updated_by' => Auth::id(),
        ]);

        return $asset;
    }

    /**
     * Capitalize a registered asset — posts the acquisition journal
     * (Fixed Asset Dr | Cash/Bank/AP Cr) and activates it.
     *
     * @return array{asset: FixedAsset, journal: Journal}
     */
    public function capitalize(FixedAsset $asset): array
    {
        if (! $asset->isDraft()) {
            throw new FixedAssetPostingException('Only draft assets can be capitalized.');
        }

        $journal = $this->postAcquisitionJournal($asset);

        $asset->update([
            'status' => AssetStatus::Active->value,
            'journal_id' => $journal->id,
            'posted_at' => now(),
            'posted_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return ['asset' => $asset->fresh(), 'journal' => $journal];
    }

    /**
     * Run the monthly depreciation batch for one period. Posts one DEP journal
     * covering every active asset that has not yet been depreciated for the period.
     *
     * @return array{journal: ?Journal, entries: int<0, max>, assets: int<0, max>}
     */
    public function runDepreciation(int $periodId, int $companyId): array
    {
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->findOrFail($periodId);

        $assets = FixedAsset::query()
            ->where('company_id', $companyId)
            ->where('status', AssetStatus::Active->value)
            ->get()
            ->filter(fn (FixedAsset $a) => ! $a->depreciationBookedForPeriod($period->id))
            ->values();

        if ($assets->isEmpty()) {
            return ['journal' => null, 'entries' => 0, 'assets' => 0];
        }

        $lines = [];
        $rows = [];

        foreach ($assets as $asset) {
            $monthly = $this->monthlyDepreciation($asset);
            if ($monthly <= 0) {
                continue;
            }

            $rows[] = [
                'asset' => $asset,
                'monthly' => $monthly,
                'accumulated' => $asset->accumulatedDepreciationAmount() + $monthly,
            ];

            $lines[] = $this->line(
                $this->postable($asset->category->depreciation_expense_account_id, 'The depreciation expense account is not postable.'),
                "Depreciation — {$asset->name} ({$asset->asset_code})",
                $monthly,
                0
            );
            $lines[] = $this->line(
                $this->postable($asset->category->accumulated_depreciation_account_id, 'The accumulated depreciation account is not postable.'),
                "Accumulated depreciation — {$asset->name} ({$asset->asset_code})",
                0,
                $monthly
            );
        }

        if (empty($rows)) {
            return ['journal' => null, 'entries' => 0, 'assets' => 0];
        }

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($period, $lines, $rows, $companyId) {
            $journal = Journal::query()->create([
                'company_id' => $companyId,
                'branch_id' => session('active_branch_id'),
                'period_id' => $period->id,
                'journal_date' => $period->end_date,
                'source_type' => JournalSourceType::Depreciation->value,
                'reference' => 'Depreciation '.$period->name,
                'description' => 'Monthly depreciation — '.$period->name,
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(array_map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines));

            $this->postingService->post($journal);

            foreach ($rows as $row) {
                DepreciationEntry::query()->create([
                    'company_id' => $companyId,
                    'fixed_asset_id' => $row['asset']->id,
                    'period_id' => $period->id,
                    'monthly_amount' => $row['monthly'],
                    'accumulated_amount' => $row['accumulated'],
                    'journal_id' => $journal->id,
                    'created_by' => Auth::id(),
                ]);
            }

            return ['journal' => $journal, 'entries' => count($rows), 'assets' => count($rows)];
        });
    }

    /**
     * Dispose an asset — cash Dr (proceeds), accumulated depreciation Dr,
     * fixed asset Cr (cost), plus gain Cr / loss Dr.
     *
     * @param  array{disposal_date: string, proceeds: float|string, proceeds_cash_account_id?: ?int}  $data
     * @return array{disposal: AssetDisposal, journal: Journal}
     */
    public function dispose(FixedAsset $asset, array $data): array
    {
        if (! $asset->isActive()) {
            throw new FixedAssetPostingException('Only active assets can be disposed.');
        }

        $proceeds = round((float) $data['proceeds'], 4);
        $accumulated = $asset->accumulatedDepreciationAmount();
        $bookValue = $asset->bookValue();
        $gainLoss = round($proceeds - $bookValue, 4);

        $lines = [];

        $cashGl = $this->disposalCashGl($asset->company_id, $data['proceeds_cash_account_id'] ?? null);
        $accumAcct = $this->postable($asset->category->accumulated_depreciation_account_id, 'The accumulated depreciation account is not postable.');
        $assetAcct = $this->postable($asset->category->asset_account_id, 'The asset account is not postable.');

        $lines[] = $this->line($cashGl, "Disposal proceeds — {$asset->name}", $proceeds, 0);
        $lines[] = $this->line($accumAcct, "Accumulated depreciation written off — {$asset->name}", $accumulated, 0);
        $lines[] = $this->line($assetAcct, "Asset disposed — {$asset->name} ({$asset->asset_code})", 0, $asset->acquisition_cost);

        if ($gainLoss > 0) {
            $lines[] = $this->line($this->gainOnDisposalAccount($asset->company_id), "Gain on disposal — {$asset->name}", 0, $gainLoss);
        } elseif ($gainLoss < 0) {
            $lines[] = $this->line($this->lossOnDisposalAccount($asset->company_id), "Loss on disposal — {$asset->name}", abs($gainLoss), 0);
        }

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($asset, $data, $proceeds, $accumulated, $bookValue, $gainLoss, $lines) {
            $journal = Journal::query()->create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id ?? session('active_branch_id'),
                'period_id' => $this->periodFor($asset->company_id, $data['disposal_date'])?->id,
                'journal_date' => $data['disposal_date'],
                'source_type' => JournalSourceType::AssetDisposal->value,
                'source_id' => $asset->id,
                'reference' => "Disposal of {$asset->asset_code}",
                'description' => "Asset disposal — {$asset->name}",
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(array_map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines));

            $this->postingService->post($journal);

            $disposal = AssetDisposal::query()->create([
                'company_id' => $asset->company_id,
                'fixed_asset_id' => $asset->id,
                'disposal_date' => $data['disposal_date'],
                'proceeds' => $proceeds,
                'book_value' => $bookValue,
                'gain_loss_amount' => $gainLoss,
                'journal_id' => $journal->id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $asset->update([
                'status' => AssetStatus::Disposed->value,
                'disposal_date' => $data['disposal_date'],
                'disposal_proceeds' => $proceeds,
                'disposed_at' => now(),
                'disposed_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return ['disposal' => $disposal, 'journal' => $journal];
        });
    }

    public function destroy(FixedAsset $asset): void
    {
        if (! $asset->isDraft()) {
            throw new FixedAssetPostingException('Capitalized or disposed assets cannot be deleted.');
        }

        $asset->delete();
    }

    // ─── journal construction ───────────────────────────────────────────────

    /**
     * @return list<array{account_id: int, party_type: ?string, party_id: ?int, description: string, debit: float, credit: float}>
     */
    public function buildAcquisitionJournalLines(FixedAsset $asset): array
    {
        $cost = (float) $asset->acquisition_cost;

        $lines = [];

        $lines[] = $this->line(
            $this->postable($asset->category->asset_account_id, 'The asset account is not postable.'),
            "Acquisition — {$asset->name} ({$asset->asset_code})",
            $cost,
            0
        );

        $lines[] = $this->creditLine($asset, $cost);

        return $lines;
    }

    protected function creditLine(FixedAsset $asset, float $total): array
    {
        $method = AssetPaymentMethod::tryFrom($asset->acquisition_method ?? 'cash')
            ?? AssetPaymentMethod::Cash;

        return match ($method) {
            AssetPaymentMethod::Payable => $this->line($this->payableAccountFor($asset), "Payable on acquisition — {$asset->name}", 0, $total),
            AssetPaymentMethod::Bank => $this->line($this->bankGl($asset->bank_account_id, $asset->company_id), "Acquisition paid via bank — {$asset->name}", 0, $total),
            default => $this->line($this->cashGl($asset->cash_account_id, $asset->company_id), "Acquisition paid via cash — {$asset->name}", 0, $total),
        };
    }

    protected function postAcquisitionJournal(FixedAsset $asset): Journal
    {
        $lines = $this->buildAcquisitionJournalLines($asset);

        $this->postingService->assertBalanced($lines);

        return DB::transaction(function () use ($asset, $lines) {
            $journal = Journal::query()->create([
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id ?? session('active_branch_id'),
                'period_id' => $this->periodFor($asset->company_id, $asset->acquisition_date->toDateString())?->id,
                'journal_date' => $asset->acquisition_date,
                'source_type' => JournalSourceType::Capitalization->value,
                'source_id' => $asset->id,
                'reference' => $asset->asset_code,
                'description' => "Asset acquisition — {$asset->name}",
                'status' => TransactionStatus::Draft->value,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $journal->lines()->createMany(array_map(fn (array $l) => [
                'account_id' => $l['account_id'],
                'party_type' => $l['party_type'],
                'party_id' => $l['party_id'],
                'description' => $l['description'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ], $lines));

            $this->postingService->post($journal);

            return $journal;
        });
    }

    protected function line(int $accountId, string $description, float $debit, float $credit, ?string $partyType = null, ?int $partyId = null): array
    {
        return [
            'account_id' => $accountId,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    protected function payableAccountFor(FixedAsset $asset): int
    {
        if ($asset->payable_account_id) {
            return $this->postable($asset->payable_account_id, 'The payable account is not postable.');
        }

        $accountId = $asset->supplier?->ap_account_id
            ?? \App\Domain\Accounting\Models\AccountingSetting::query()
                ->where('company_id', $asset->company_id)
                ->value('default_ap_account_id');

        return $this->postable((int) $accountId, 'There is no postable accounts payable account.');
    }

    protected function cashGl(?int $cashAccountId, int $companyId): int
    {
        $account = $cashAccountId ? CashAccount::query()->where('company_id', $companyId)->find($cashAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new FixedAssetPostingException('The selected cash account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The cash account has no postable GL account.');
    }

    protected function bankGl(?int $bankAccountId, int $companyId): int
    {
        $account = $bankAccountId ? \App\Domain\CashBank\Models\BankAccount::query()->where('company_id', $companyId)->find($bankAccountId) : null;

        if (! $account || $account->is_active === false) {
            throw new FixedAssetPostingException('The selected bank account does not exist or is inactive.');
        }

        return $this->postable($account->gl_account_id, 'The bank account has no postable GL account.');
    }

    protected function disposalCashGl(int $companyId, ?int $preferredId): int
    {
        if ($preferredId) {
            return $this->cashGl($preferredId, $companyId);
        }

        $default = CashAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('gl_account_id');

        if (! $default) {
            throw new FixedAssetPostingException('There is no active cash account to receive disposal proceeds.');
        }

        return $this->postable($default, 'The disposal cash account is not postable.');
    }

    protected function gainOnDisposalAccount(int $companyId): int
    {
        $id = Account::query()->where('company_id', $companyId)->where('code', '4213')->value('id');

        return $this->postable((int) $id, 'The Gain on Disposal account (4213) is not set up.');
    }

    protected function lossOnDisposalAccount(int $companyId): int
    {
        $id = Account::query()->where('company_id', $companyId)->where('code', '5193')->value('id');

        return $this->postable((int) $id, 'The Loss on Disposal account (5193) is not set up.');
    }

    protected function postable(int $accountId, string $message): int
    {
        $account = Account::query()->find($accountId);

        if (! $account || ! $account->is_postable || ! $account->is_active) {
            throw new FixedAssetPostingException($message);
        }

        return $accountId;
    }

    // ─── depreciation math ──────────────────────────────────────────────────

    /**
     * The depreciation amount to book for the asset's next month.
     */
    public function monthlyDepreciation(FixedAsset $asset): float
    {
        $method = $asset->methodValue();
        $cost = (float) $asset->acquisition_cost;
        $life = max(1, (int) $asset->useful_life_months);
        $book = $asset->bookValue();

        if ($book <= 0) {
            return 0.0;
        }

        if ($method === DepreciationMethod::StraightLine) {
            return round($cost / $life, 4);
        }

        $amount = round($book * (2 / $life), 4);

        return min($amount, $book);
    }

    /**
     * Expected future entries (sequence, amount, accumulated) for the Show page schedule.
     *
     * @return list<array{sequence: int, amount: float, accumulated: float}>
     */
    public function depreciationSchedule(FixedAsset $asset): array
    {
        $posted = $asset->depreciationEntries()->count();
        $accumulated = $asset->accumulatedDepreciationAmount();
        $schedule = [];

        $probe = clone $asset;
        $_entries = $asset->depreciationEntries()->get();

        for ($i = $posted + 1; $i <= max($posted + 1, (int) $asset->useful_life_months); $i++) {
            $amount = $this->monthlyDepreciation($asset);
            if ($amount <= 0) {
                break;
            }

            $accumulated = round($accumulated + $amount, 4);
            $schedule[] = [
                'sequence' => $i,
                'amount' => $amount,
                'accumulated' => $accumulated,
            ];
        }

        return $schedule;
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    protected function resolvePayableAccount(array $data, int $companyId): ?int
    {
        if (($data['acquisition_method'] ?? '') !== 'payable') {
            return null;
        }

        if (! empty($data['supplier_id'])) {
            $supplier = \App\Domain\Party\Models\Supplier::query()->where('company_id', $companyId)->find($data['supplier_id']);

            if ($supplier?->ap_account_id) {
                return $supplier->ap_account_id;
            }
        }

        return \App\Domain\Accounting\Models\AccountingSetting::query()
            ->where('company_id', $companyId)
            ->value('default_ap_account_id');
    }

    protected function assertPaymentAccounts(array $data, int $companyId): void
    {
        $method = $data['acquisition_method'] ?? '';

        if ($method === 'cash' && empty($data['cash_account_id'])) {
            throw new FixedAssetPostingException('A cash account is required when paying by cash.');
        }

        if ($method === 'bank' && empty($data['bank_account_id'])) {
            throw new FixedAssetPostingException('A bank account is required when paying by bank.');
        }

        if ($method === 'payable' && empty($data['supplier_id'])) {
            throw new FixedAssetPostingException('A supplier is required when paying as accounts payable.');
        }
    }

    protected function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}