<?php

namespace App\Domain\CashBank\Http\Requests;

use App\Support\Enums\CashBankTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('transaction_type');

        $rules = [
            'transaction_type' => ['required', Rule::enum(CashBankTransactionType::class)],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'to_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id', 'different:bank_account_id'],
            'counter_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];

        if (! $type) {
            return $rules;
        }

        $enum = CashBankTransactionType::tryFrom($type);

        if (! $enum) {
            return $rules;
        }

        if ($enum->isTransfer()) {
            $rules['bank_account_id'] = ['required', 'integer', 'exists:bank_accounts,id'];
            $rules['to_bank_account_id'] = ['required', 'integer', 'exists:bank_accounts,id', 'different:bank_account_id'];
        } elseif ($enum->requiresCash() && $enum->isBank()) {
            // deposit (cash→bank) and withdrawal (bank→cash) need both a cash and a bank account.
            $rules['cash_account_id'] = ['required', 'integer', 'exists:cash_accounts,id'];
            $rules['bank_account_id'] = ['required', 'integer', 'exists:bank_accounts,id'];
        } else {
            $subject = $enum->requiresCash() ? 'cash_account_id' : 'bank_account_id';
            $rules[$subject] = ['required', 'integer', "exists:".($enum->requiresCash() ? 'cash_accounts' : 'bank_accounts').",id"];
        }

        if ($enum->requiresCounter()) {
            $rules['counter_account_id'] = ['required', 'integer', 'exists:accounts,id'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (['cash_account_id', 'bank_account_id', 'to_bank_account_id', 'counter_account_id'] as $field) {
            if (($this->input($field) ?? '') === '') {
                $this->merge([$field => null]);
            }
        }
    }
}