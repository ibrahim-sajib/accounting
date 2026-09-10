<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Seeds the accounting configuration (default posting accounts and voucher
 * numbering) for every company, referencing the seeded chart of accounts.
 */
class AccountingSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            $accountId = fn (string $code) => Account::where('company_id', $company->id)->where('code', $code)->value('id');

            AccountingSetting::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'default_sales_account_id' => $accountId('4111'),
                    'default_purchase_account_id' => $accountId('5211'),
                    'default_inventory_account_id' => $accountId('1131'),
                    'default_ar_account_id' => $accountId('1121'),
                    'default_ap_account_id' => $accountId('2111'),
                    'default_cash_account_id' => $accountId('1111'),
                    'default_bank_account_id' => $accountId('1112'),
                    'default_tax_input_account_id' => $accountId('1151'),
                    'default_tax_output_account_id' => $accountId('2121'),
                    'voucher_numbering' => [
                        'sales' => 'SL-{fiscal_year}-{sequence:6}',
                        'purchase' => 'PR-{fiscal_year}-{sequence:6}',
                        'journal' => 'JV-{fiscal_year}-{sequence:6}',
                        'payment' => 'PM-{fiscal_year}-{sequence:6}',
                        'receipt' => 'RC-{fiscal_year}-{sequence:6}',
                    ],
                ]
            );
        }
    }
}