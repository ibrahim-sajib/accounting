<?php

namespace App\Domain\Party\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['payment_terms_days', 'opening_balance'] as $field) {
            if ($this->input($field) === null) {
                $this->merge([$field => 0]);
            }
        }
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $companyId = current_company_id();

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('suppliers', 'code')->where('company_id', $companyId)->ignore($supplier?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'ap_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['boolean'],
        ];
    }
}