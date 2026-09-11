<?php

namespace App\Domain\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $companyId = current_company_id();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('units', 'name')->where('company_id', $companyId)->ignore($unit?->id),
            ],
            'symbol' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }
}