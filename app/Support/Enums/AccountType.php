<?php

namespace App\Support\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }

    /**
     * The normal balance side for the account type.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Income => 'credit',
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::Asset => 'সম্পদ',
            self::Liability => 'দায়',
            self::Equity => 'মালিকানা',
            self::Income => 'আয়',
            self::Expense => 'ব্যয়',
        };
    }
}
