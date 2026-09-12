<?php

namespace App\Domain\FixedAsset\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('asset_categories', 'name')
                ->where('company_id', $companyId)
                ->ignore($categoryId, 'id')],
            'asset_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')
                ->where('company_id', $companyId)
                ->where('is_postable', 1)],
            'depreciation_expense_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')
                ->where('company_id', $companyId)
                ->where('is_postable', 1)],
            'accumulated_depreciation_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')
                ->where('company_id', $companyId)
                ->where('is_postable', 1)],
            'default_method' => ['required', Rule::in(['straight_line', 'declining_balance'])],
            'default_useful_life_months' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}