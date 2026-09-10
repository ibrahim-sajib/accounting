<?php

namespace App\Domain\Tax\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rate = $this->route('rate');
        $companyId = current_company_id();

        $accountExists = Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId));

        return [
            'name' => ['required', 'string', 'max:190'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:999999'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'input_account_id' => ['nullable', $accountExists],
            'output_account_id' => ['nullable', $accountExists],
            'effective_date' => ['required', 'date'],
        ];
    }
}