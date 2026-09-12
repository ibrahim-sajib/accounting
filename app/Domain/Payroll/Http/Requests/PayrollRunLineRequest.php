<?php

namespace App\Domain\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollRunLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_pay' => ['required', 'numeric', 'min:0'],
            'deductions_total' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['gross_pay', 'deductions_total'] as $field) {
            if (array_key_exists($field, $this->all()) && ($this->input($field) === null || $this->input($field) === '')) {
                $this->merge([$field => '0']);
            }
        }
    }
}