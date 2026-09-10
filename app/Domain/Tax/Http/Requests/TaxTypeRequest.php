<?php

namespace App\Domain\Tax\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taxType = $this->route('taxType');
        $companyId = current_company_id();

        return [
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('tax_types', 'name')->where('company_id', $companyId)->ignore($taxType?->id),
            ],
        ];
    }
}