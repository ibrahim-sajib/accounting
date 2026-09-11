<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Support\Enums\InventoryAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
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
                $line['counted_qty'] = is_numeric(($line['counted_qty'] ?? null)) ? (string) $line['counted_qty'] : '0';

                return $line;
            })
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'reason' => ['required', 'string', Rule::in(array_column(InventoryAdjustmentReason::cases(), 'value'))],
            'memo' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ];
    }
}