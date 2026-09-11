<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Inventory\Http\Requests\StockAdjustmentRequest;
use App\Domain\Inventory\Exceptions\StockPostingException;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockAdjustmentLine;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Product\Models\Product;
use App\Domain\Warehouse\Models\Warehouse;
use App\Support\Enums\InventoryAdjustmentReason;
use App\Support\Enums\ProductType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $adjustments = StockAdjustment::query()
            ->with('warehouse:id,name')
            ->where('company_id', $companyId)
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->where('adjustment_no', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%"));
            })
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (StockAdjustment $adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_no' => $adjustment->adjustment_no,
                    'adjustment_date' => $adjustment->adjustment_date->toDateString(),
                    'warehouse_id' => $adjustment->warehouse_id,
                    'warehouse' => $adjustment->warehouse?->name,
                    'reason' => $adjustment->reason,
                    'reason_label' => InventoryAdjustmentReason::tryFrom($adjustment->reason)?->label(),
                    'status' => $adjustment->status,
                    'total_value' => (float) $adjustment->total_value,
                    'line_count' => $adjustment->lines()->count(),
                ];
            });

        return Inertia::render('Inventory/Adjustments/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Adjustments/Create', [
            'warehouses' => $this->warehouseOptions(),
            'products' => $this->productOptions(),
            'systemQty' => $this->stockService->currentStockMap(current_company_id()),
            'reasons' => enum_options(InventoryAdjustmentReason::class),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = current_company_id();

        $adjustment = StockAdjustment::query()->create([
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'adjustment_date' => $data['adjustment_date'],
            'warehouse_id' => $data['warehouse_id'],
            'reason' => $data['reason'],
            'memo' => $data['memo'] ?? null,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->saveLines($adjustment, $data['lines']);

        AuditLogger::log('stock_adjustment', 'create', null, $adjustment->id, [], $adjustment->fresh()->load('lines')->toArray(), $companyId);

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', 'Stock adjustment draft saved.');
    }

    public function show(StockAdjustment $adjustment): Response
    {
        $this->authorizeAdjustment($adjustment);

        $adjustment->load([
            'warehouse:id,name',
            'lines.product:id,sku,name',
            'journal',
        ]);

        return Inertia::render('Inventory/Adjustments/Show', [
            'adjustment' => [
                'id' => $adjustment->id,
                'adjustment_no' => $adjustment->adjustment_no,
                'adjustment_date' => $adjustment->adjustment_date->toDateString(),
                'warehouse' => $adjustment->warehouse?->name,
                'reason' => InventoryAdjustmentReason::tryFrom($adjustment->reason)?->label(),
                'memo' => $adjustment->memo,
                'status' => $adjustment->status,
                'total_value' => (float) $adjustment->total_value,
                'posted_at' => $adjustment->posted_at?->toDateTimeString(),
                'lines' => $adjustment->lines->map(fn (StockAdjustmentLine $line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name,
                    'product_sku' => $line->product?->sku,
                    'system_qty' => (float) $line->system_qty,
                    'counted_qty' => (float) $line->counted_qty,
                    'quantity_delta' => (float) $line->quantity_delta,
                    'unit_cost' => (float) $line->unit_cost,
                    'line_value' => (float) $line->line_value,
                ]),
                'journal_id' => $adjustment->journal?->id,
                'journal_no' => $adjustment->journal?->journal_no,
            ],
        ]);
    }

    public function edit(StockAdjustment $adjustment): Response
    {
        $this->authorizeAdjustment($adjustment);

        abort_if(! $adjustment->isDraft(), 422, 'Only draft adjustments can be edited.');

        $adjustment->load('lines');

        return Inertia::render('Inventory/Adjustments/Edit', [
            'adjustment' => [
                'id' => $adjustment->id,
                'adjustment_date' => $adjustment->adjustment_date->toDateString(),
                'warehouse_id' => $adjustment->warehouse_id,
                'reason' => $adjustment->reason,
                'memo' => $adjustment->memo,
            ],
            'lines' => $adjustment->lines->map(fn (StockAdjustmentLine $line) => [
                'product_id' => $line->product_id,
                'counted_qty' => (float) $line->counted_qty,
            ]),
            'warehouses' => $this->warehouseOptions(),
            'products' => $this->productOptions(),
            'systemQty' => $this->stockService->currentStockMap(current_company_id()),
            'reasons' => enum_options(InventoryAdjustmentReason::class),
            'today' => now()->toDateString(),
        ]);
    }

    public function update(StockAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAdjustment($adjustment);

        abort_if(! $adjustment->isDraft(), 422, 'Only draft adjustments can be updated.');

        $old = $adjustment->load('lines')->toArray();
        $data = $request->validated();

        $adjustment->update([
            'adjustment_date' => $data['adjustment_date'],
            'warehouse_id' => $data['warehouse_id'],
            'reason' => $data['reason'],
            'memo' => $data['memo'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $adjustment->lines()->delete();
        $this->saveLines($adjustment, $data['lines']);

        AuditLogger::log('stock_adjustment', 'update', null, $adjustment->id, $old, $adjustment->fresh()->load('lines')->toArray(), $adjustment->company_id);

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', 'Stock adjustment draft updated.');
    }

    public function post(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAdjustment($adjustment);

        try {
            $adjustment = $this->stockService->postAdjustment($adjustment);

            AuditLogger::log('stock_adjustment', 'post', null, $adjustment->id, [], $adjustment->toArray(), $adjustment->company_id);

            return redirect()
                ->route('stock-adjustments.show', $adjustment)
                ->with('success', "Adjustment {$adjustment->adjustment_no} posted.");
        } catch (StockPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAdjustment($adjustment);

        abort_if(! $adjustment->isDraft(), 422, 'Only draft adjustments can be deleted.');

        $companyId = $adjustment->company_id;
        $adjustment->delete();

        AuditLogger::log('stock_adjustment', 'delete', null, $adjustment->id, [], [], $companyId);

        return redirect()
            ->route('stock-adjustments.index')
            ->with('success', 'Draft adjustment deleted.');
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function authorizeAdjustment(StockAdjustment $adjustment): void
    {
        abort_if($adjustment->company_id !== current_company_id(), 403, 'This adjustment belongs to a different company.');
    }

    private function saveLines(StockAdjustment $adjustment, array $lines): void
    {
        $warehouseId = $adjustment->warehouse_id;
        $companyId = $adjustment->company_id;

        $rows = [];

        foreach ($lines as $line) {
            $countedQty = (float) $line['counted_qty'];
            $systemQty = $this->stockService->onHandQty($companyId, (int) $line['product_id'], $warehouseId);

            $rows[] = [
                'company_id' => $companyId,
                'product_id' => (int) $line['product_id'],
                'system_qty' => round($systemQty, 6),
                'counted_qty' => round($countedQty, 6),
                'quantity_delta' => round($countedQty - $systemQty, 6),
            ];
        }

        $adjustment->lines()->createMany($rows);
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

    private function productOptions(): array
    {
        return Product::query()
            ->with('unit:id,name,symbol')
            ->where('company_id', current_company_id())
            ->where('type', ProductType::Product->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'unit_id'])
            ->map(fn (Product $product) => [
                'value' => $product->id,
                'label' => $product->sku ? "{$product->name} ({$product->sku})" : $product->name,
                'unit' => $product->unit?->symbol,
            ])
            ->all();
    }
}