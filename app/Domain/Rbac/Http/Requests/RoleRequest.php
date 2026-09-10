<?php

namespace App\Domain\Rbac\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $companyId = current_company_id();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('company_id', $companyId)->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}