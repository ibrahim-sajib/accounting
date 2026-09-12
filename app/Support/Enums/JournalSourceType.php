<?php

namespace App\Support\Enums;

enum JournalSourceType: string
{
    case Manual = 'manual';
    case Opening = 'opening';
    case SalesInvoice = 'sales_invoice';
    case PurchaseBill = 'purchase_bill';
    case Receipt = 'receipt';
    case ReceiptApplication = 'receipt_application';
    case Payment = 'payment';
    case PaymentApplication = 'payment_application';
    case WriteOff = 'write_off';
    case StockAdjustment = 'stock_adjustment';
    case Inventory = 'inventory';
    case Bank = 'bank';
    case Expense = 'expense';
    case Depreciation = 'depreciation';
    case Capitalization = 'capitalization';
    case AssetDisposal = 'asset_disposal';
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
            self::ReceiptApplication => 'Advance Applied',
            self::Payment => 'Supplier Payment',
            self::PaymentApplication => 'Advance Applied (Supplier)',
            self::WriteOff => 'Receivable Write-off',
            self::StockAdjustment => 'Stock Adjustment',
            self::Inventory => 'Inventory',
            self::Bank => 'Bank',
            self::Expense => 'Expense',
            self::Depreciation => 'Depreciation',
            self::Capitalization => 'Asset Acquisition',
            self::AssetDisposal => 'Asset Disposal',
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
            self::ReceiptApplication => 'RCA',
            self::Payment => 'PMT',
            self::PaymentApplication => 'SAA',
            self::WriteOff => 'WOF',
            self::StockAdjustment => 'ADJ',
            self::Bank => 'CBT',
            self::Expense => 'EXP',
            self::Depreciation => 'DEP',
            self::Capitalization => 'FA',
            self::AssetDisposal => 'DSP',
            self::Payroll => 'PYR',
            self::Closing => 'YEC',
            default => 'GJ',
        };
    }
}