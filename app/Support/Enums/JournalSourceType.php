<?php

namespace App\Support\Enums;

enum JournalSourceType: string
{
    case Manual = 'manual';
    case Opening = 'opening';
    case SalesInvoice = 'sales_invoice';
    case PurchaseBill = 'purchase_bill';
    case Receipt = 'receipt';
    case Payment = 'payment';
    case Inventory = 'inventory';
    case Bank = 'bank';
    case Expense = 'expense';
    case Depreciation = 'depreciation';
    case Payroll = 'payroll';
    case Closing = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual Journal',
            self::Opening => 'Opening Balance',
            self::SalesInvoice => 'Sales Invoice',
            self::PurchaseBill => 'Purchase Bill',
            self::Receipt => 'Customer Receipt',
            self::Payment => 'Supplier Payment',
            self::Inventory => 'Inventory',
            self::Bank => 'Bank',
            self::Expense => 'Expense',
            self::Depreciation => 'Depreciation',
            self::Payroll => 'Payroll',
            self::Closing => 'Year-End Closing',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Opening => 'OB',
            self::SalesInvoice => 'SINV',
            self::PurchaseBill => 'PUR',
            self::Receipt => 'RCT',
            self::Payment => 'PMT',
            default => 'GJ',
        };
    }
}