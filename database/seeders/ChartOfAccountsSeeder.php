<?php

namespace Database\Seeders;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Support\Enums\AccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default Chart of Accounts for every company.
 *
 * The chart is defined as a nested tree. Each seed run walks the tree,
 * computing `level` from depth, inheriting `type` from the nearest ancestor
 * that declares one, deriving `normal_balance` from the type and marking
 * leaves as `is_postable`. Idempotent via firstOrCreate on (company_id, code).
 */
class ChartOfAccountsSeeder extends Seeder
{
    protected array $chart = [
        [
            'code' => '1000', 'name' => 'Assets', 'name_bn' => 'সম্পদ', 'type' => 'asset', 'children' => [
                ['code' => '1100', 'name' => 'Current Assets', 'children' => [
                    ['code' => '1110', 'name' => 'Cash & Bank', 'children' => [
                        ['code' => '1111', 'name' => 'Cash on Hand'],
                        ['code' => '1112', 'name' => 'Bank Accounts - BDT'],
                        ['code' => '1113', 'name' => 'Bank Accounts - USD'],
                    ]],
                    ['code' => '1120', 'name' => 'Accounts Receivable', 'children' => [
                        ['code' => '1121', 'name' => 'Trade Receivables'],
                    ]],
                    ['code' => '1130', 'name' => 'Inventory', 'children' => [
                        ['code' => '1131', 'name' => 'General Inventory'],
                    ]],
                    ['code' => '1140', 'name' => 'Prepaid Expenses', 'children' => [
                        ['code' => '1141', 'name' => 'Prepaid Expenses'],
                    ]],
                    ['code' => '1150', 'name' => 'VAT Receivable', 'children' => [
                        ['code' => '1151', 'name' => 'Input VAT'],
                    ]],
                    ['code' => '1160', 'name' => 'Advance Payments', 'children' => [
                        ['code' => '1161', 'name' => 'Advances to Suppliers'],
                    ]],
                ]],
                ['code' => '1200', 'name' => 'Non-Current Assets', 'children' => [
                    ['code' => '1210', 'name' => 'Property, Plant & Equipment', 'children' => [
                        ['code' => '1211', 'name' => 'Land & Land Improvements'],
                        ['code' => '1212', 'name' => 'Buildings & Structures'],
                        ['code' => '1213', 'name' => 'Machinery & Equipment'],
                        ['code' => '1214', 'name' => 'Furniture, Fixtures & Computers'],
                        ['code' => '1215', 'name' => 'Vehicles'],
                    ]],
                    ['code' => '1220', 'name' => 'Accumulated Depreciation', 'children' => [
                        ['code' => '1221', 'name' => 'Accumulated Depreciation - Buildings'],
                        ['code' => '1222', 'name' => 'Accumulated Depreciation - Machinery'],
                        ['code' => '1223', 'name' => 'Accumulated Depreciation - Furniture'],
                        ['code' => '1224', 'name' => 'Accumulated Depreciation - Vehicles'],
                    ]],
                ]],
            ],
        ],
        [
            'code' => '2000', 'name' => 'Liabilities', 'name_bn' => 'দায়', 'type' => 'liability', 'children' => [
                ['code' => '2100', 'name' => 'Current Liabilities', 'children' => [
                    ['code' => '2110', 'name' => 'Accounts Payable', 'children' => [
                        ['code' => '2111', 'name' => 'Trade Payables'],
                    ]],
                    ['code' => '2120', 'name' => 'VAT Payable', 'children' => [
                        ['code' => '2121', 'name' => 'Output VAT'],
                    ]],
                    ['code' => '2130', 'name' => 'Withholding Tax Payable', 'children' => [
                        ['code' => '2131', 'name' => 'Withholding Tax Payable'],
                    ]],
                    ['code' => '2140', 'name' => 'Accrued Expenses', 'children' => [
                        ['code' => '2141', 'name' => 'Accrued Salaries & Benefits'],
                        ['code' => '2142', 'name' => 'Accrued Utilities'],
                    ]],
                    ['code' => '2150', 'name' => 'Short-Term Debt', 'children' => [
                        ['code' => '2151', 'name' => 'Short-Term Loans'],
                    ]],
                ]],
                ['code' => '2200', 'name' => 'Long-Term Liabilities', 'children' => [
                    ['code' => '2210', 'name' => 'Long-Term Debt', 'children' => [
                        ['code' => '2211', 'name' => 'Long-Term Loans'],
                    ]],
                ]],
            ],
        ],
        [
            'code' => '3000', 'name' => 'Equity', 'name_bn' => 'মালিকানা', 'type' => 'equity', 'children' => [
                ['code' => '3100', 'name' => "Owner's Equity", 'children' => [
                    ['code' => '3110', 'name' => "Owner's Capital", 'children' => [
                        ['code' => '3111', 'name' => "Owner's Capital"],
                    ]],
                ]],
                ['code' => '3200', 'name' => 'Retained Earnings', 'children' => [
                    ['code' => '3210', 'name' => 'Retained Earnings', 'children' => [
                        ['code' => '3211', 'name' => 'Retained Earnings'],
                    ]],
                ]],
            ],
        ],
        [
            'code' => '4000', 'name' => 'Income', 'name_bn' => 'আয়', 'type' => 'income', 'children' => [
                ['code' => '4100', 'name' => 'Revenue', 'children' => [
                    ['code' => '4110', 'name' => 'Sales Revenue', 'children' => [
                        ['code' => '4111', 'name' => 'Product Sales'],
                        ['code' => '4112', 'name' => 'Service Revenue'],
                    ]],
                    ['code' => '4120', 'name' => 'Sales Returns & Allowances', 'children' => [
                        ['code' => '4121', 'name' => 'Sales Returns'],
                    ]],
                ]],
                ['code' => '4200', 'name' => 'Other Income', 'children' => [
                    ['code' => '4210', 'name' => 'Interest & Other Income', 'children' => [
                        ['code' => '4211', 'name' => 'Interest Income'],
                        ['code' => '4212', 'name' => 'Miscellaneous Income'],
                    ]],
                ]],
            ],
        ],
        [
            'code' => '5000', 'name' => 'Expenses', 'name_bn' => 'ব্যয়', 'type' => 'expense', 'children' => [
                ['code' => '5100', 'name' => 'Operating Expenses', 'children' => [
                    ['code' => '5110', 'name' => 'Salaries & Wages', 'children' => [
                        ['code' => '5111', 'name' => 'Salaries & Wages'],
                    ]],
                    ['code' => '5120', 'name' => 'Rent Expense', 'children' => [
                        ['code' => '5121', 'name' => 'Office Rent'],
                    ]],
                    ['code' => '5130', 'name' => 'Utilities', 'children' => [
                        ['code' => '5131', 'name' => 'Electricity, Water & Gas'],
                    ]],
                    ['code' => '5140', 'name' => 'Office & Administrative', 'children' => [
                        ['code' => '5141', 'name' => 'Office Supplies'],
                        ['code' => '5142', 'name' => 'Travel & Entertainment'],
                        ['code' => '5143', 'name' => 'Repairs & Maintenance'],
                        ['code' => '5144', 'name' => 'Communications'],
                    ]],
                    ['code' => '5150', 'name' => 'Depreciation Expense', 'children' => [
                        ['code' => '5151', 'name' => 'Depreciation Expense'],
                    ]],
                    ['code' => '5160', 'name' => 'Marketing & Advertising', 'children' => [
                        ['code' => '5161', 'name' => 'Advertising & Marketing'],
                    ]],
                    ['code' => '5170', 'name' => 'Insurance', 'children' => [
                        ['code' => '5171', 'name' => 'Insurance Expense'],
                    ]],
                    ['code' => '5180', 'name' => 'Bank Charges & Interest', 'children' => [
                        ['code' => '5181', 'name' => 'Bank Charges & Interest'],
                        ['code' => '5182', 'name' => 'Inventory Adjustment Expense'],
                    ]],
                ]],
                ['code' => '5200', 'name' => 'Cost of Goods Sold', 'children' => [
                    ['code' => '5210', 'name' => 'Purchases', 'children' => [
                        ['code' => '5211', 'name' => 'Purchases - Products'],
                    ]],
                    ['code' => '5220', 'name' => 'COGS', 'children' => [
                        ['code' => '5221', 'name' => 'Cost of Goods Sold'],
                    ]],
                ]],
                ['code' => '5300', 'name' => 'Tax & Duties', 'children' => [
                    ['code' => '5310', 'name' => 'Tax Expense', 'children' => [
                        ['code' => '5311', 'name' => 'Income Tax Expense'],
                        ['code' => '5312', 'name' => 'VAT Expense (Non-Recoverable)'],
                    ]],
                ]],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (Company::all() as $company) {
            DB::transaction(function () use ($company) {
                foreach ($this->chart as $root) {
                    $this->seedNode($company, $root);
                }
            });
        }
    }

    protected function seedNode(Company $company, array $node, int $parentId = 0, string $inheritedType = null, int $level = 0): void
    {
        $type = $node['type'] ?? $inheritedType;
        $normalBalance = $type
            ? AccountType::from($type)->normalBalance()
            : 'debit';

        $account = Account::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => $node['code']],
            [
                'name' => $node['name'],
                'name_bn' => $node['name_bn'] ?? null,
                'type' => $type,
                'parent_id' => $parentId ?: null,
                'level' => $level,
                'normal_balance' => $normalBalance,
                'is_active' => true,
                'is_postable' => empty($node['children']),
            ]
        );

        foreach ($node['children'] ?? [] as $child) {
            $this->seedNode($company, $child, $account->id, $type, $level + 1);
        }
    }
}