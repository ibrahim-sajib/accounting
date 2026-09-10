<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route('account');
        $companyId = current_company_id();

        $codeRules = [
            'required', 'string', 'max:50',
            Rule::unique('accounts', 'code')->where('company_id', $companyId)->ignore($account?->id),
        ];

        $parentRules = [
            'nullable',
            'integer',
            Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
        ];

        if ($account) {
            $parentRules[] = Rule::notIn(array_merge([$account->id], $account->descendantIds()));
        }

        return [
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:190'],
            'name_bn' => ['nullable', 'string', 'max:190'],
            'type' => [
                'required',
                Rule::in(['asset', 'liability', 'equity', 'income', 'expense']),
            ],
            'parent_id' => $parentRules,
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}