<?php

namespace App\Domain\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');

        return [
            'category_id' => ['required', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'payee' => ['required', 'string', 'max:200'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.9999'],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'payable'])],
            'cash_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_frequency' => ['nullable', Rule::in(['weekly', 'monthly', 'yearly']), 'required_if:is_recurring,true'],
            'next_generation_date' => ['nullable', 'date', 'after:expense_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && ($this->input('amount') === null || $this->input('amount') === '')) {
            $this->merge(['amount' => 0]);
        }

        if ($this->input('is_recurring') === false) {
            $this->merge([
                'recurrence_frequency' => null,
                'next_generation_date' => null,
            ]);
        }

        $paymentMethod = $this->input('payment_method');

        if ($paymentMethod === 'cash') {
            $this->merge(['bank_account_id' => null, 'supplier_id' => null]);
        } elseif ($paymentMethod === 'bank') {
            $this->merge(['cash_account_id' => null, 'supplier_id' => null]);
        } elseif ($paymentMethod === 'payable') {
            $this->merge(['cash_account_id' => null, 'bank_account_id' => null]);
        }
    }
}