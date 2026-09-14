<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Domain\Expense\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(?int $companyId = null): void
    {
        $companies = $companyId
            ? [Company::query()->findOrFail($companyId)]
            : Company::query()->get();

        foreach ($companies as $company) {
            $accounts = Account::query()
                ->where('company_id', $company->id)
                ->where('is_postable', true)
                ->where('is_active', true)
                ->get();

            $defaults = [
                'Rent' => ['5121', '5120'],
                'Utilities' => ['5131', '5130'],
                'Salaries' => ['5111', '5110'],
                'Office Supplies' => ['5141', '5140'],
                'Travel' => ['5142', '5140'],
            ];

            foreach ($defaults as $name => $codes) {
                $accountId = null;

                foreach ($codes as $code) {
                    $account = $accounts->firstWhere('code', $code);
                    if ($account) {
                        $accountId = $account->id;
                        break;
                    }
                }

                if (! $accountId) {
                    continue;
                }

                $category = ExpenseCategory::withTrashed()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    ['expense_account_id' => $accountId, 'is_active' => true]
                );
                if ($category->trashed()) {
                    $category->restore();
                }
            }
        }
    }
}