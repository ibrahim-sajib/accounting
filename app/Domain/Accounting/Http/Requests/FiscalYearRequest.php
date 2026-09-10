<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fiscalYear = $this->route('fiscal_year');
        $companyId = current_company_id();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('fiscal_years', 'name')->where('company_id', $companyId)->ignore($fiscalYear?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}