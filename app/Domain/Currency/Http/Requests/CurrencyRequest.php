<?php

namespace App\Domain\Currency\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currency = $this->route('currency');
        $companyId = current_company_id();

        return [
            'code' => [
                'required', 'string', 'size:3',
                Rule::unique('currencies', 'code')->where('company_id', $companyId)->ignore($currency?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'is_base' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}