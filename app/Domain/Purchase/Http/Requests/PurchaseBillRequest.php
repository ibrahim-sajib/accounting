<?php

namespace App\Domain\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines') ?? [];

        $lines = collect($lines)
            ->map(function (array $line) {
                $line['unit_cost'] = $this->normalizeNumber($line['unit_cost'] ?? null);
                $line['discount_amount'] = $this->normalizeNumber($line['discount_amount'] ?? null);

                return $line;
            })
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
        ];
    }

    private function normalizeNumber(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }
}