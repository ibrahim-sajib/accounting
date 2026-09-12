<?php

namespace App\Domain\Accounting\Http\Requests;

use App\Domain\Accounting\Models\FiscalYear;
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
            'fiscal_year_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                if ($periodId = $this->route('period')) {
                    // keep the fiscal year of an existing period unchanged
                    return;
                }
                $owned = FiscalYear::query()->where('id', $value)->where('company_id', current_company_id())->exists();
                if (! $owned) {
                    $fail('The selected fiscal year is not part of your active company.');
                }
            }],
            'name' => ['required', 'string', 'max:60'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:open,closed,locked'],
            'periods' => ['nullable', 'array'],
        ];
    }
}