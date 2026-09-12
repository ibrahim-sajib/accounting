<?php

namespace App\Domain\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = session('active_company_id');

        return [
            'name' => ['required', 'string', 'max:100'],
            'expense_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')
                ->where('company_id', $companyId)
                ->where('is_postable', 1)],
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