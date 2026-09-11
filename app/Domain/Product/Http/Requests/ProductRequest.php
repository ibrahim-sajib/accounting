<?php

namespace App\Domain\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['purchase_price', 'sales_price'] as $field) {
            if ($this->input($field) === null) {
                $this->merge([$field => 0]);
            }
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $companyId = current_company_id();

        return [
            'sku' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($product?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:product,service'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sales_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sales_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'purchase_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'track_inventory' => ['boolean'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}