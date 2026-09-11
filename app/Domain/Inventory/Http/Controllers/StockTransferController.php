<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Inventory\Exceptions\StockPostingException;
use App\Domain\Inventory\Http\Requests\StockTransferRequest;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\StockTransferLine;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Product\Models\Product;
use App\Domain\Warehouse\Models\Warehouse;
use App\Support\Enums\ProductType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $transfers = StockTransfer::query()
            ->with('fromWarehouse:id,name', 'toWarehouse:id,name')
            ->where('company_id', $companyId)
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->where('transfer_no', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%"));
            })
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (StockTransfer $transfer) {
                return [
                    'id' => $transfer->id,
                    'transfer_no' => $transfer->transfer_no,
                    'transfer_date' => $transfer->transfer_date->toDateString(),
                    'from_warehouse' => $transfer->fromWarehouse?->name,
                    'to_warehouse' => $transfer->toWarehouse?->name,
                    'reference' => $transfer->reference,
                    'status' => $transfer->status,
                    'line_count' => $transfer->lines()->count(),
                ];
            });

        return Inertia::render('Inventory/Transfers/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Transfers/Create', [
            'warehouses' => $this->warehouseOptions(),
            'products' => $this->productOptions(),
            'systemQty' => $this->stockService->currentStockMap(current_company_id()),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = current_company_id();

        $transfer = StockTransfer::query()->create([
            'company_id' => $companyId,
            'branch_id' => session('active_branch_id'),
            'transfer_date' => $data['transfer_date'],
            'from_warehouse_id' => $data['from_warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'reference' => $data['reference'] ?? null,
            'memo' => $data['memo'] ?? null,
            'status' => TransactionStatus::Draft->value,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->saveLines($transfer, $data['lines']);

        AuditLogger::log('stock_transfer', 'create', null, $transfer->id, [], $transfer->fresh()->load('lines')->toArray(), $companyId);

        return redirect()
            ->route('stock-transfers.show', $transfer)
            ->with('success', 'Stock transfer draft saved.');
    }

    public function show(StockTransfer $transfer): Response
    {
        $this->authorizeTransfer($transfer);

        $transfer->load([
            'fromWarehouse:id,name,code',
            'toWarehouse:id,name,code',
            'lines.product:id,sku,name',
        ]);

        return Inertia::render('Inventory/Transfers/Show', [
            'transfer' => [
                'id' => $transfer->id,
                'transfer_no' => $transfer->transfer_no,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'from_warehouse' => $transfer->fromWarehouse,
                'to_warehouse' => $transfer->toWarehouse,
                'reference' => $transfer->reference,
                'memo' => $transfer->memo,
                'status' => $transfer->status,
                'posted_at' => $transfer->posted_at?->toDateTimeString(),
                'lines' => $transfer->lines->map(fn (StockTransferLine $line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name,
                    'product_sku' => $line->product?->sku,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'line_value' => (float) $line->line_value,
                ]),
            ],
        ]);
    }

    public function edit(StockTransfer $transfer): Response
    {
        $this->authorizeTransfer($transfer);

        abort_if(! $transfer->isDraft(), 422, 'Only draft transfers can be edited.');

        $transfer->load('lines');

        return Inertia::render('Inventory/Transfers/Edit', [
            'transfer' => [
                'id' => $transfer->id,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'from_warehouse_id' => $transfer->from_warehouse_id,
                'to_warehouse_id' => $transfer->to_warehouse_id,
                'reference' => $transfer->reference,
                'memo' => $transfer->memo,
            ],
            'lines' => $transfer->lines->map(fn (StockTransferLine $line) => [
                'product_id' => $line->product_id,
                'quantity' => (float) $line->quantity,
            ]),
            'warehouses' => $this->warehouseOptions(),
            'products' => $this->productOptions(),
            'systemQty' => $this->stockService->currentStockMap(current_company_id()),
            'today' => now()->toDateString(),
        ]);
    }

    public function update(StockTransferRequest $request, StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($transfer);

        abort_if(! $transfer->isDraft(), 422, 'Only draft transfers can be updated.');

        $old = $transfer->load('lines')->toArray();
        $data = $request->validated();

        $transfer->update([
            'transfer_date' => $data['transfer_date'],
            'from_warehouse_id' => $data['from_warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'reference' => $data['reference'] ?? null,
            'memo' => $data['memo'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $transfer->lines()->delete();
        $this->saveLines($transfer, $data['lines']);

        AuditLogger::log('stock_transfer', 'update', null, $transfer->id, $old, $transfer->fresh()->load('lines')->toArray(), $transfer->company_id);

        return redirect()
            ->route('stock-transfers.show', $transfer)
            ->with('success', 'Stock transfer draft updated.');
    }

    public function post(StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($transfer);

        try {
            $transfer = $this->stockService->postTransfer($transfer);

            AuditLogger::log('stock_transfer', 'post', null, $transfer->id, [], $transfer->toArray(), $transfer->company_id);

            return redirect()
                ->route('stock-transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_no} posted.");
        } catch (StockPostingException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(StockTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($transfer);

        abort_if(! $transfer->isDraft(), 422, 'Only draft transfers can be deleted.');

        $companyId = $transfer->company_id;
        $transfer->delete();

        AuditLogger::log('stock_transfer', 'delete', null, $transfer->id, [], [], $companyId);

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', 'Draft transfer deleted.');
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function authorizeTransfer(StockTransfer $transfer): void
    {
        abort_if($transfer->company_id !== current_company_id(), 403, 'This transfer belongs to a different company.');
    }

    private function saveLines(StockTransfer $transfer, array $lines): void
    {
        $rows = [];

        foreach ($lines as $line) {
            $rows[] = [
                'company_id' => $transfer->company_id,
                'product_id' => (int) $line['product_id'],
                'quantity' => (float) $line['quantity'],
            ];
        }

        $transfer->lines()->createMany($rows);
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