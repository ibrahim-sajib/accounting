<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\AccountProtectedException;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Enums\AccountType;

/**
 * Owns every structural rule of the chart of accounts:
 *
 * - type / normal_balance are inherited from the parent account;
 * - level is derived from depth in the tree;
 * - only leaf accounts (no children) are postable;
 * - accounts that are system-owned, have children, or are referenced by
 *   tax rates / accounting settings cannot be deleted.
 */
class AccountService
{
    public function store(int $companyId, array $data): Account
    {
        return $this->applyHierarchy($companyId, $data, null);
    }

    public function update(int $companyId, array $data, Account $account): Account
    {
        return $this->applyHierarchy($companyId, $data, $account);
    }

    protected function applyHierarchy(int $companyId, array $data, ?Account $account): Account
    {
        unset($data['company_id']);

        $parentId = ! empty($data['parent_id']) ? $data['parent_id'] : null;
        $parent = $parentId ? Account::query()->findOrFail($parentId) : null;

        $data['parent_id'] = $parentId;

        if ($account !== null) {
            $this->assertNotDescendantOrSelf($account, $parent);
        }

        if ($parent) {
            $data['type'] = $parent->type;
            $data['level'] = $parent->level + 1;
        } else {
            $data['level'] = 0;
        }

        $data['normal_balance'] = AccountType::from($data['type'])->normalBalance();
        unset($data['is_postable']);

        $saved = $account
            ? tap($account)->update($data)
            : Account::query()->create(array_merge(['company_id' => $companyId], $data));

        $this->syncPostability($companyId);

        return $saved;
    }

    public function destroy(Account $account): void
    {
        $reason = $this->protectionReason($account);

        if ($reason) {
            throw new AccountProtectedException($reason);
        }

        $account->delete();
        $this->syncPostability($account->company_id);
    }

    public function protectionReason(Account $account): ?string
    {
        if ($account->is_system) {
            return 'System accounts cannot be deleted.';
        }

        if ($account->children()->withTrashed()->exists()) {
            return 'This account has child accounts and cannot be deleted.';
        }

        $referencedByTax = TaxRate::query()
            ->where('company_id', $account->company_id)
            ->where(fn ($q) => $q->where('input_account_id', $account->id)->orWhere('output_account_id', $account->id))
            ->exists();

        if ($referencedByTax || $this->isAccountingSettingReference($account)) {
            return 'This account is referenced by tax rates or accounting settings and cannot be deleted.';
        }

        return null;
    }

    protected function isAccountingSettingReference(Account $account): bool
    {
        $id = $account->id;

        return AccountingSetting::query()
            ->where('company_id', $account->company_id)
            ->where(fn ($q) => $q
                ->where('default_sales_account_id', $id)
                ->orWhere('default_purchase_account_id', $id)
                ->orWhere('default_inventory_account_id', $id)
                ->orWhere('default_ar_account_id', $id)
                ->orWhere('default_ap_account_id', $id)
                ->orWhere('default_cash_account_id', $id)
                ->orWhere('default_bank_account_id', $id)
                ->orWhere('default_tax_input_account_id', $id)
                ->orWhere('default_tax_output_account_id', $id))
            ->exists();
    }

    /**
     * Recompute is_postable in both directions: an account is postable iff it
     * has no children. Runs for one company (or all companies when null).
     */
    public function syncPostability(?int $companyId = null): void
    {
        $query = Account::query()->with('children');
        $query->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $query->get()->each(function (Account $account) {
            $postable = $account->children->isEmpty();

            if ($account->is_postable !== $postable) {
                $account->is_postable = $postable;
                $account->save();
            }
        });
    }

    protected function assertNotDescendantOrSelf(Account $account, ?Account $parent): void
    {
        if ($parent === null) {
            return;
        }

        abort_if($parent->id === $account->id, 422, 'An account cannot be its own parent.');

        abort_if(in_array($parent->id, $account->descendantIds(), true), 422, 'An account cannot be moved under one of its own children.');
    }
}