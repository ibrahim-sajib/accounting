<?php

namespace App\Domain\Payables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MultiBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $allocations = $this->input('allocations');

        if (! is_array($allocations)) {
            $this->merge(['allocations' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'payment_date' => ['required', 'date'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.purchase_bill_id' => ['required', 'integer', 'exists:purchase_bills,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}