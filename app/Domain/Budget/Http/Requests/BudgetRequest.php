<?php

namespace App\Domain\Budget\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (is_string($lines) && $this->isJson($lines)) {
            $lines = json_decode($lines, true);
        }

        if (is_array($lines)) {
            foreach ($lines as $key => $line) {
                if (array_key_exists('budgeted_amount', $line) && $line['budgeted_amount'] === null) {
                    $lines[$key]['budgeted_amount'] = '0';
                }
            }
        }

        $this->merge(['lines' => $lines ?? []]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'lines.*.budgeted_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
        ];
    }
}