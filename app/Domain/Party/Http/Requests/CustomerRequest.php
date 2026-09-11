<?php

namespace App\Domain\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['credit_limit', 'payment_terms_days', 'opening_balance'] as $field) {
            if ($this->input($field) === null) {
                $this->merge([$field => 0]);
            }
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $companyId = current_company_id();

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('customers', 'code')->where('company_id', $companyId)->ignore($customer?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'ar_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ];
    }
}