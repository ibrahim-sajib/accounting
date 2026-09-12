<?php

namespace App\Domain\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');

        return [
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'cash_account_id' => ['required_if:payment_method,cash', 'nullable', 'integer', Rule::exists('cash_accounts', 'id')
                ->where('company_id', $companyId)],
            'bank_account_id' => ['required_if:payment_method,bank', 'nullable', 'integer', Rule::exists('bank_accounts', 'id')
                ->where('company_id', $companyId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('amount') === null || $this->input('amount') === '') {
            $this->merge(['amount' => '0']);
        }
    }
}