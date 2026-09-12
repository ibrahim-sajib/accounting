<?php

namespace App\Domain\FixedAsset\Http\Requests;

use App\Domain\FixedAsset\Models\FixedAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');
        $asset = $this->route('asset');
        $assetId = $asset instanceof FixedAsset ? $asset->id : null;

        return [
            'category_id' => ['required', 'integer', Rule::exists('asset_categories', 'id')
                ->where('company_id', $companyId)],
            'asset_code' => ['required', 'string', 'max:50', Rule::unique('fixed_assets', 'asset_code')
                ->where('company_id', $companyId)
                ->ignore($assetId, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            // useful_life_months/method fall back to the category defaults server-side.
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', Rule::in(['straight_line', 'declining_balance'])],
            'location' => ['nullable', 'string', 'max:255'],
            'acquisition_method' => ['required', Rule::in(['cash', 'bank', 'payable'])],
            'cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')
                ->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')
                ->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')
                ->where('company_id', $companyId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->method) && $this->input('acquisition_method') === 'cash') {
            // no-op; category default is applied in the service
        }
    }
}