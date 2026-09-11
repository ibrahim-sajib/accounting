<?php

namespace App\Domain\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $companyId = current_company_id();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')->where('company_id', $companyId)->ignore($category?->id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'is_active' => ['boolean'],
        ];
    }
}