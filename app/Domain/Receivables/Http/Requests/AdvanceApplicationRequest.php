<?php

namespace App\Domain\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceApplicationRequest extends FormRequest
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
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}