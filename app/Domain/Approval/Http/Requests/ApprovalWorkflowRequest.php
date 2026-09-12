<?php

namespace App\Domain\Approval\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'min_amount' => $this->input('min_amount', '0') === '' ? '0' : $this->input('min_amount', '0'),
            'max_amount' => $this->input('max_amount', '') === '' ? null : $this->input('max_amount'),
            'sequence' => $this->input('sequence', 1) === '' ? '1' : $this->input('sequence', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', 'string', 'in:sales_invoice,purchase_bill,expense,journal,payroll,budget'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'approver_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sequence' => ['required', 'integer', 'min:1', 'max:9'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}