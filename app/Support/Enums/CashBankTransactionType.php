<?php

namespace App\Support\Enums;

enum CashBankTransactionType: string
{
    case CashReceipt = 'cash_receipt';
    case CashPayment = 'cash_payment';
    case BankDeposit = 'bank_deposit';
    case BankWithdrawal = 'bank_withdrawal';
    case BankTransfer = 'bank_transfer';
    case BankCharge = 'bank_charge';
    case BankInterest = 'bank_interest';

    public function label(): string
    {
        return match ($this) {
            self::CashReceipt => 'Cash Receipt',
            self::CashPayment => 'Cash Payment',
            self::BankDeposit => 'Bank Deposit',
            self::BankWithdrawal => 'Bank Withdrawal',
            self::BankTransfer => 'Bank Transfer',
            self::BankCharge => 'Bank Charge',
            self::BankInterest => 'Bank Interest',
        };
    }

    public function isCash(): bool
    {
        return in_array($this, [self::CashReceipt, self::CashPayment], true);
    }

    public function isBank(): bool
    {
        return ! $this->isCash();
    }

    /**
     * Which accounts this transaction type needs:
     *  cash     — must supply cash_account_id
     *  bank     — must supply bank_account_id
     *  counter  — must supply counter_account_id (income/expense GL)
     *  transfer — optional transfer between two bank accounts
     */
    public function requiresCash(): bool
    {
        return in_array($this, [self::CashReceipt, self::CashPayment, self::BankDeposit, self::BankWithdrawal], true);
    }

    public function requiresBank(): bool
    {
        return $this->isBank();
    }

    public function requiresCounter(): bool
    {
        return in_array($this, [self::CashReceipt, self::CashPayment, self::BankCharge, self::BankInterest], true);
    }

    public function isTransfer(): bool
    {
        return $this === self::BankTransfer;
    }
}