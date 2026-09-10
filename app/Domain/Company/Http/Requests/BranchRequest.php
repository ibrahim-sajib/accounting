<?php

namespace App\Domain\Company\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branch = $this->route('branch');

        $companyId = current_company_id() ?? $this->input('company_id');

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('branches', 'code')->where('company_id', $companyId)->ignore($branch?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}