<?php

namespace App\Domain\FixedAsset\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FixedAssetDisposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');

        return [
            'disposal_date' => ['required', 'date'],
            'proceeds' => ['required', 'numeric', 'min:0'],
            'proceeds_cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')
                ->where('company_id', $companyId)],
        ];
    }
}