<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $period = $this->route('period');

        return [
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'name' => ['required', 'string', 'max:60'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:open,closed,locked'],
            'periods' => ['nullable', 'array'],
        ];
    }
}