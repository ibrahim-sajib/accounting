<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Settings\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    protected array $settings = [
        'general' => [
            'default_language' => ['en', 'string'],
            'default_currency' => ['BDT', 'string'],
        ],
        'localization' => [
            'date_format' => ['d/m/Y', 'string'],
            'time_format' => ['h:i A', 'string'],
            'number_decimal' => ['2', 'int'],
            'number_thousand_separator' => [',', 'string'],
            'currency_position' => ['left', 'string'],
            'show_currency_symbol' => ['1', 'bool'],
        ],
        'numbering' => [
            'invoice_prefix' => ['INV', 'string'],
            'invoice_start' => ['1000', 'int'],
            'invoice_suffix' => ['', 'string'],
            'purchase_prefix' => ['PUR', 'string'],
            'purchase_start' => ['1000', 'int'],
            'journal_prefix' => ['JV', 'string'],
            'journal_start' => ['1000', 'int'],
            'receipt_prefix' => ['RCT', 'string'],
            'payment_prefix' => ['PMT', 'string'],
            'include_fiscal_year_in_numbering' => ['0', 'bool'],
        ],
        'notifications' => [
            'email_invoice_due' => ['0', 'bool'],
            'email_invoice_overdue' => ['0', 'bool'],
            'email_payment_due' => ['0', 'bool'],
            'in_app_pending_approvals' => ['1', 'bool'],
            'in_app_low_stock' => ['1', 'bool'],
            'in_app_period_closing' => ['1', 'bool'],
        ],
        'security' => [
            'password_expiry_days' => ['90', 'int'],
            'max_login_attempts' => ['5', 'int'],
            'session_lifetime' => ['120', 'int'],
        ],
        'accounting' => [
            'allow_negative_stock' => ['0', 'bool'],
            'inventory_valuation_method' => ['weighted_average', 'string'],
            'rounding_precision' => ['2', 'int'],
        ],
    ];

    public function run(): void
    {
        $companies = Company::query()->get();

        foreach ($companies as $company) {
            foreach ($this->settings as $group => $items) {
                foreach ($items as $key => [$value, $type]) {
                    SystemSetting::query()->updateOrCreate(
                        ['company_id' => $company->id, 'key' => $key],
                        ['group' => $group, 'value' => $value, 'type' => $type]
                    );
                }
            }
        }
    }
}