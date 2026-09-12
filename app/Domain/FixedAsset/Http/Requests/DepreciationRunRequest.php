<?php

namespace App\Domain\FixedAsset\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepreciationRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
        ];
    }
}