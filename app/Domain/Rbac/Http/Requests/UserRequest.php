<?php

namespace App\Domain\Rbac\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $isUpdate = $user !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'company_access' => ['nullable', 'array'],
            'company_access.*.company_id' => ['required', 'integer', 'exists:companies,id'],
            'company_access.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'company_access.*.is_default' => ['sometimes', 'boolean'],
        ];
    }
}