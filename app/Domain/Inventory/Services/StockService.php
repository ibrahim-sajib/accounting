<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Accounting\Exceptions\JournalPostingException;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Inventory\Exceptions\StockPostingException;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Product\Models\Product;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Warehouse\Models\Warehouse;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\ProductType;
use App\Support\Enums\StockMovementType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** Plan-account code for the inventory adjustment expense (COA leaf, expense normal balance). */
    public const INVENTORY_ADJUSTMENT_EXPENSE_CODE = '5182';

    public function __construct(
        protected JournalPostingService $postingService
    ) {}

    // ── Ledger queries ────────────────────────────────────────────────

    public function onHandQty(int $companyId, int $productId, ?int $warehouseId = null): float
    {
        $q = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId);

        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (float) $q->sum('quantity');
    }

    /**
     * Weighted-average cost from the movement ledger (total value ÷ total quantity).
     * Returns 0 when there is no stock.
     */
    public function avgCost(int $companyId, int $productId): float
    {
        $row = StockMovement::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->selectRaw('SUM(quantity) as qty, SUM(line_value) as value')
            ->first();

        $qty = (float) $row->qty;

        if ($qty == 0) {
            return 0.0;
        }

        return round((float) $row->value / $qty, 4);
    }

    /**
     * Cost to value a unit of the product right now: the weighted-average ledger cost,
     * falling back to the product's configured purchase price when nothing is in stock.
     */
    public function costFor(int $companyId, int $productId, ?Product $product = null): float
    {
        $cost = $this->avgCost($companyId, $productId);

        if ($cost > 0) {
            return $cost;
        }

        $product = $product ?? Product::query()->withTrashed()->find($productId);

        return round((float) ($product?->purchase_price ?? 0), 4);
    }

    /**
     * Grouped on-hand + value per product+warehouse, optionally scoped to one warehouse.
     *
     * @return array<int, array{qty: float, value: float, cost: float}>
     */
    public function holdingsByProduct(int $companyId, array $productIds, ?int $warehouseId = null): array
    {
        $q = StockMovement::query()
            ->where('company_id', $companyId)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(quantity) as qty, SUM(line_value) as value')
            ->groupBy('product_id');

        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        $result = [];

        foreach ($q->get() as $row) {
            $qty = (float) $row->qty;
            $value = (float) $row->value;
            $result[(int) $row->product_id] = [
                'qty' => $qty,
                'value' => round($value, 4),
                'cost' => $qty != 0 ? round($value / $qty, 4) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Flat "productId:warehouseId" → on-hand qty map for the adjustment/transfer forms
     * (warehouseId key is empty for movements without a warehouse).
     *
     * @return array<string, string>
     */
    public function currentStockMap(int $companyId): array
    {
        $rows = StockMovement::query()
            ->where('company_id', $companyId)
            ->selectRaw('product_id, COALESCE(warehouse_id, 0) as warehouse_id, SUM(quantity) as qty')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $key = (int) $row->product_id.':'.($row->warehouse_id ?: '');
            $map[$key] = (string) round((float) $row->qty, 6);
        }

        return $map;
    }

    // ── Integration from other phases ───────────────────────────────

    /**
     * A posted purchase bill brings stock in (product-type lines only). Costs the receipt
     * at the net line value ÷ quantity so the ledger mirrors the PUR journal's Inventory Dr.
     */
    public function receivePurchaseBill(PurchaseBill $bill): void
    {
        foreach ($bill->lines as $line) {
            $product = $line->product;
            $quantity = (float) $line->quantity;

            if (! $product || $product->type !== ProductType::Product->value || $quantity <= 0) {
                continue;
            }

            $cost = $quantity > 0
                ? round((float) $line->line_total / $quantity, 4)
                : (float) $line->unit_cost;

            $this->add(
                $bill->company_id,
                $line->product_id,
                StockMovementType::PurchaseReceived,
                $quantity,
                $cost,
                $this->warehouseFor($bill->company_id),
                'purchase_bill',
                $bill->id,
                $bill->bill_no,
                'Purchased goods received'
            );
        }
    }

    /**
     * A posted sales invoice issues stock (product-type lines only) at the current
     * weighted-average cost — the same cost the SINV journal books as COGS. Falls back
     * to the product's purchase price when nothing is in stock.
     */
    public function issueSalesInvoice(SalesInvoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $product = $line->product;
            $quantity = (float) $line->quantity;

            if (! $product || $product->type !== ProductType::Product->value || $quantity <= 0) {
                continue;
            }

            $this->add(
                $invoice->company_id,
                $line->product_id,
                StockMovementType::SalesIssued,
                -$quantity,
                $this->costFor($invoice->company_id, $line->product_id, $product),
                $this->warehouseFor($invoice->company_id),
                'sales_invoice',
                $invoice->id,
                $invoice->invoice_no,
                'Goods sold and delivered'
            );
        }
    }

    // ── Stock adjustments ────────────────────────────────────────────

    /**
     * Post a draft adjustment: recomputes each line's system qty from the ledger and the
     * delta vs the counted qty, posts an ADJ journal (Inventory Dr/Cr | Inventory
     * Adjustment Expense Cr/Dr), and records the adjustment movements.
     *
     * @throws StockPostingException
     */
    public function postAdjustment(StockAdjustment $adjustment): StockAdjustment
    {
        if (! $adjustment->isDraft()) {
            throw new StockPostingException('Only draft adjustments can be posted.');
        }

        return DB::transaction(function () use ($adjustment) {
            $adjustment->load(['lines.product', 'warehouse']);
            $companyId = $adjustment->company_id;
            $warehouseId = $adjustment->warehouse_id;
            $setting = AccountingSetting::query()->where('company_id', $companyId)->first();

            $inventoryByAccount = [];
            $expenseByAccount = [];
            $totalValue = 0.0;
            $materialLines = 0;

            foreach ($adjustment->lines as $line) {
                $product = $line->product;

                if (! $product) {
                    throw new StockPostingException('An adjustment line references a product that no longer exists.');
                }

                if ($product->type !== ProductType::Product->value) {
                    continue;
                }

                $systemQty = $this->onHandQty($companyId, $line->product_id, $warehouseId);
                $delta = round((float) $line->counted_qty - $systemQty, 6);

                if ($delta == 0) {
                    $line->update(['system_qty' => $systemQty, 'quantity_delta' => 0, 'unit_cost' => 0, 'line_value' => 0]);
                    continue;
                }

                $cost = $this->costFor($companyId, $line->product_id, $product);

                if ($cost <= 0) {
                    throw new StockPostingException(
                        "Cannot value the adjustment for '{$product->name}' — it has no cost. Receive stock or set a purchase price first."
                    );
                }

                $value = round($delta * $cost, 4);

                $inventoryAccount = $product->inventory_account_id ?? $setting?->default_inventory_account_id;
                $expenseAccount = Account::query()
                    ->where('company_id', $companyId)
                    ->where('code', self::INVENTORY_ADJUSTMENT_EXPENSE_CODE)
                    ->value('id')
                    ?? $setting?->default_purchase_account_id;

                if (! $inventoryAccount || ! $this->isPostable($inventoryAccount)) {
                    throw new StockPostingException("No postable inventory account is configured for '{$product->name}'.");
                }

                if (! $expenseAccount || ! $this->isPostable($expenseAccount)) {
                    throw new StockPostingException('No postable inventory adjustment expense account is configured.');
                }

                $line->update([
                    'system_qty' => $systemQty,
                    'quantity_delta' => $delta,
                    'unit_cost' => $cost,
                    'line_value' => $value,
                ]);

                $inventoryByAccount[(int) $inventoryAccount] = round(($inventoryByAccount[(int) $inventoryAccount] ?? 0) + $value, 4);
                $expenseByAccount[(int) $expenseAccount] = round(($expenseByAccount[(int) $expenseAccount] ?? 0) + $value, 4);
                $totalValue += abs($value);
                $materialLines++;
            }

            if ($materialLines === 0) {
                throw new StockPostingException('The adjustment has no count difference to post.');
            }

            $lines = [];

            foreach ($inventoryByAccount as $accountId => $value) {
                if ($value > 0) {
                    $lines[] = ['account_id' => $accountId, 'debit' => round($value, 4), 'credit' => 0];
                } else {
                    $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => round(-$value, 4)];
                }
            }

            foreach ($expenseByAccount as $accountId => $value) {
                if ($value > 0) {
                    $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => round($value, 4)];
                } else {
                    $lines[] = ['account_id' => $accountId, 'debit' => round(-$value, 4), 'credit' => 0];
                }
            }

            $this->postingService->assertBalanced($lines);

            $adjustmentNo = $this->nextAdjustmentNumber($companyId, $adjustment->adjustment_date->toDateString());

            try {
                $journal = Journal::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $adjustment->branch_id,
                    'period_id' => $this->periodFor($companyId, $adjustment->adjustment_date->toDateString())?->id,
                    'journal_date' => $adjustment->adjustment_date,
                    'source_type' => JournalSourceType::StockAdjustment->value,
                    'source_id' => $adjustment->id,
                    'reference' => $adjustmentNo,
                    'description' => 'Stock adjustment '.$adjustmentNo,
                    'status' => TransactionStatus::Draft->value,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $journal->lines()->createMany(collect($lines)->map(fn (array $l) => [
                    'account_id' => $l['account_id'],
                    'party_type' => null,
                    'party_id' => null,
                    'description' => 'Inventory adjustment',
                    'debit' => $l['debit'],
                    'credit' => $l['credit'],
                ])->all());

                $this->postingService->post($journal);
            } catch (JournalPostingException $e) {
                throw new StockPostingException($e->getMessage());
            }

            $adjustment->update([
                'adjustment_no' => $adjustmentNo,
                'status' => TransactionStatus::Posted->value,
                'total_value' => round($totalValue, 4),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($adjustment->fresh()->lines as $line) {
                if ((float) $line->quantity_delta != 0) {
                    $this->add(
                        $companyId,
                        $line->product_id,
                        StockMovementType::Adjustment,
                        (float) $line->quantity_delta,
                        (float) $line->unit_cost,
                        $warehouseId,
                        'stock_adjustment',
                        $adjustment->id,
                        $adjustmentNo,
                        'Stock count adjusted ('.($adjustment->reason ?: 'adjustment').')'
                    );
                }
            }

            return $adjustment->fresh()->load(['lines.product', 'warehouse']);
        });
    }

    // ── Stock transfers ──────────────────────────────────────────────

    /**
     * Post a draft transfer: moves quantity between warehouses at the current cost,
     * recording transfer_out / transfer_in movements. No journal impact.
     *
     * @throws StockPostingException
     */
    public function postTransfer(StockTransfer $transfer): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw new StockPostingException('Only draft transfers can be posted.');
        }

        return DB::transaction(function () use ($transfer) {
            $transfer->load(['lines.product']);
            $companyId = $transfer->company_id;

            if ($transfer->from_warehouse_id === $transfer->to_warehouse_id) {
                throw new StockPostingException('The source and destination warehouses must be different.');
            }

            $hasLine = false;

            foreach ($transfer->lines as $line) {
                $product = $line->product;
                $quantity = (float) $line->quantity;

                if (! $product || $product->type !== ProductType::Product->value) {
                    continue;
                }

                if ($quantity <= 0) {
                    continue;
                }

                $onHand = $this->onHandQty($companyId, $line->product_id, $transfer->from_warehouse_id);

                if ($quantity > $onHand + 0.0001) {
                    throw new StockPostingException(
                        "Insufficient stock of '{$product->name}' at the source warehouse: {$onHand} available, {$quantity} requested."
                    );
                }

                $cost = $this->costFor($companyId, $line->product_id, $product);
                $line->update([
                    'unit_cost' => round($cost, 4),
                    'line_value' => round($quantity * $cost, 4),
                ]);

                $hasLine = true;

                $this->add(
                    $companyId,
                    $line->product_id,
                    StockMovementType::TransferOut,
                    -$quantity,
                    $cost,
                    $transfer->from_warehouse_id,
                    'stock_transfer',
                    $transfer->id,
                    null,
                    'Transferred to warehouse #'.$transfer->to_warehouse_id
                );

                $this->add(
                    $companyId,
                    $line->product_id,
                    StockMovementType::TransferIn,
                    $quantity,
                    $cost,
                    $transfer->to_warehouse_id,
                    'stock_transfer',
                    $transfer->id,
                    null,
                    'Received from warehouse #'.$transfer->from_warehouse_id
                );
            }

            if (! $hasLine) {
                throw new StockPostingException('The transfer needs at least one line with a positive quantity.');
            }

            $transferNo = $this->nextTransferNumber($companyId, $transfer->transfer_date->toDateString());

            $transfer->update([
                'transfer_no' => $transferNo,
                'status' => TransactionStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $transfer->fresh()->load(['lines.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function warehouseFor(int $companyId): ?int
    {
        return Warehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    public function nextAdjustmentNumber(int $companyId, string $date): string
    {
        return $this->nextDocumentNumber(StockAdjustment::class, 'adjustment_no', 'ADJ', $companyId, $date);
    }

    public function nextTransferNumber(int $companyId, string $date): string
    {
        return $this->nextDocumentNumber(StockTransfer::class, 'transfer_no', 'TR', $companyId, $date);
    }

    private function nextDocumentNumber(string $model, string $column, string $prefix, int $companyId, string $date): string
    {
        $year = Carbon::parse($date)->format('Y');

        $latest = $model::query()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where($column, 'like', "{$prefix}-{$year}-%")
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;

        if ($latest) {
            $sequence = ((int) str($latest)->after("{$prefix}-{$year}-")->before('-')->toString()) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    private function periodFor(int $companyId, string $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    private function isPostable(int $accountId): bool
    {
        $account = Account::query()->find($accountId);

        return $account && $account->is_postable;
    }

    private function add(
        int $companyId,
        int $productId,
        StockMovementType $type,
        float $quantity,
        float $unitCost,
        ?int $warehouseId,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reference = null,
        ?string $memo = null
    ): void {
        $qty = round($quantity, 6);
        $cost = round($unitCost, 4);

        StockMovement::query()->create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => $type->value,
            'quantity' => $qty,
            'unit_cost' => $cost,
            'line_value' => round($qty * $cost, 4),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference' => $reference,
            'memo' => $memo,
            'created_by' => Auth::id(),
        ]);
    }
}