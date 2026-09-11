<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Inventory\Services\StockService;
use App\Domain\Product\Models\Product;
use App\Domain\Warehouse\Models\Warehouse;
use App\Support\Enums\ProductType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $products = Product::query()
            ->with('unit:id,name,symbol')
            ->where('company_id', $companyId)
            ->where('type', ProductType::Product->value)
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $holdings = $this->stockService->holdingsByProduct($companyId, $products->pluck('id')->all(), $warehouseId);

        $records = $products->through(function (Product $product) use ($holdings) {
            $h = $holdings[$product->id] ?? ['qty' => 0.0, 'value' => 0.0, 'cost' => 0.0];
            $qty = (float) $h['qty'];
            $cost = (float) $h['cost'];
            $threshold = $product->low_stock_threshold;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit?->symbol,
                'track_inventory' => (bool) $product->track_inventory,
                'threshold' => $threshold !== null ? (float) $threshold : null,
                'qty' => round($qty, 6),
                'cost' => $cost,
                'value' => round($qty * $cost, 4),
                'is_low_stock' => (bool) $product->track_inventory
                    && $threshold !== null
                    && $qty <= (float) $threshold,
            ];
        });

        $summary = $this->summary($companyId, $warehouseId);

        return Inertia::render('Inventory/Stock/Index', [
            'records' => $records,
            'filters' => $request->only(['search', 'warehouse_id']),
            'warehouses' => $this->warehouseOptions(),
            'summary' => $summary,
        ]);
    }

    private function summary(int $companyId, ?int $warehouseId): array
    {
        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('type', ProductType::Product->value)
            ->get(['id', 'track_inventory', 'low_stock_threshold']);

        $holdings = $this->stockService->holdingsByProduct($companyId, $products->pluck('id')->all(), $warehouseId);

        $totalValue = 0.0;
        $lowCount = 0;

        foreach ($products as $product) {
            $qty = (float) ($holdings[$product->id]['qty'] ?? 0);
            $totalValue += (float) ($holdings[$product->id]['value'] ?? 0);

            if ($product->track_inventory && $product->low_stock_threshold !== null && $qty <= (float) $product->low_stock_threshold) {
                $lowCount++;
            }
        }

        return [
            'total_value' => round($totalValue, 4),
            'product_count' => $products->count(),
            'low_stock_count' => $lowCount,
        ];
    }

    private function warehouseOptions(): array
    {
        return Warehouse::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Warehouse $w) => ['value' => $w->id, 'label' => $w->code ? "{$w->code} — {$w->name}" : $w->name])
            ->all();
    }
}