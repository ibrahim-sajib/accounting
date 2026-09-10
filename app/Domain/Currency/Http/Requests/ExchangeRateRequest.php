<?php

namespace App\Domain\Currency\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => [
                'required', 'date',
                Rule::unique('exchange_rates', 'effective_date')
                    ->where('currency_id', $this->input('currency_id')),
            ],
        ];
    }
}