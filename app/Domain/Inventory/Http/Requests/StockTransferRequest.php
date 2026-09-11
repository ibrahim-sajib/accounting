<?php

namespace App\Domain\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines') ?? [];

        $lines = collect($lines)
            ->reject(fn (array $line) => empty($line['product_id']))
            ->map(function (array $line) {
                $line['quantity'] = is_numeric(($line['quantity'] ?? null)) ? (string) $line['quantity'] : '0';

                return $line;
            })
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', Rule::notIn([$this->input('from_warehouse_id')])],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}