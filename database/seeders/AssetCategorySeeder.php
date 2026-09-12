<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Domain\FixedAsset\Models\AssetCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds default fixed-asset categories for every company, wired to the seeded
 * chart of accounts (asset / accumulated depreciation / depreciation expense).
 * Idempotent via firstOrCreate on (company_id, name).
 */
class AssetCategorySeeder extends Seeder
{
    protected array $defaults = [
        'Buildings & Structures' => ['1212', '1221', '5151', 'straight_line', 240],
        'Machinery & Equipment' => ['1213', '1222', '5151', 'straight_line', 60],
        'Furniture, Fixtures & Computers' => ['1214', '1223', '5151', 'straight_line', 36],
        'Vehicles' => ['1215', '1224', '5151', 'straight_line', 60],
    ];

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

            foreach ($this->defaults as $name => [$assetCode, $accumCode, $expenseCode, $method, $life]) {
                $assetAccountId = $accounts->firstWhere('code', $assetCode)?->id;
                $accumAccountId = $accounts->firstWhere('code', $accumCode)?->id;
                $expenseAccountId = $accounts->firstWhere('code', $expenseCode)?->id;

                if (! $assetAccountId || ! $accumAccountId || ! $expenseAccountId) {
                    continue;
                }

                AssetCategory::query()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    [
                        'asset_account_id' => $assetAccountId,
                        'depreciation_expense_account_id' => $expenseAccountId,
                        'accumulated_depreciation_account_id' => $accumAccountId,
                        'default_method' => $method,
                        'default_useful_life_months' => $life,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}