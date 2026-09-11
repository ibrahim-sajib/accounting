<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Party\Models\Customer;
use App\Domain\Party\Models\Supplier;
use App\Domain\Product\Models\Product;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Customer $customer;

    private Product $product;

    private TaxRate $vatRate;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private int $inventoryAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->inventoryAccountId = $setting->default_inventory_account_id;

        $this->vatRate = TaxRate::query()
            ->where('company_id', 1)
            ->where('name', 'Standard Rate 15%')
            ->firstOrFail();

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P7',
            'name' => 'Widget',
            'type' => 'product',
            'purchase_price' => 400,
            'sales_price' => 500,
            'tax_rate_id' => $this->vatRate->id,
            'track_inventory' => true,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->supplier = Supplier::query()->create([
            'company_id' => 1,
            'code' => 'SUP-P7',
            'name' => 'Acme Vendor',
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-P7',
            'name' => 'Alpha Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->warehouseA = $this->createWarehouse('WH-A', 'Main Warehouse');
        $this->warehouseB = $this->createWarehouse('WH-B', 'Secondary Warehouse');
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_super_admin_can_view_stock_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/inventory/stock')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Stock/Index')
                ->has('records.data')
                ->has('records.total')
                ->has('summary')
                ->where('summary.product_count', 1));
    }

    public function test_adjustment_create_page_exposes_lookup_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/inventory/adjustments/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Adjustments/Create')
                ->has('warehouses')
                ->has('products')
                ->has('reasons'));
    }

    public function test_transfer_create_page_exposes_lookup_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/inventory/transfers/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Transfers/Create')
                ->has('warehouses')
                ->has('products'));
    }

    // ───────────────────────── Adjustments ─────────────────────────

    public function test_adjustment_draft_can_be_saved_and_viewed(): void
    {
        $this->actingAs($this->admin)->post('/inventory/adjustments', $this->adjustmentPayload())->assertRedirect();

        $adjustment = StockAdjustment::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $adjustment->status);
        $this->assertNull($adjustment->adjustment_no);
        $this->assertSame(1, $adjustment->lines()->count());

        $this->actingAs($this->admin)
            ->get(route('stock-adjustments.show', $adjustment->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Adjustments/Show')
                ->where('adjustment.status', 'draft')
                ->where('adjustment.journal_id', null));
    }

    public function test_posted_adjustment_gets_number_adj_journal_and_movements(): void
    {
        $adjustment = $this->createPostedAdjustment();

        $this->assertSame('posted', $adjustment->status);
        $this->assertStringStartsWith('ADJ-', $adjustment->adjustment_no);
        $this->assertNotNull($adjustment->posted_at);
        // 5 counted from a system qty of 0 at cost 400 → value 2000.
        $this->assertEqualsWithDelta(2000.0, (float) $adjustment->total_value, 0.0001);

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'stock_adjustment')
            ->where('source_id', $adjustment->id)
            ->firstOrFail();

        $this->assertStringStartsWith('ADJ-', $journal->journal_no);
        $this->assertSame('posted', $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(2000.0, (float) $journal->lines->firstWhere('account_id', $this->inventoryAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta((float) $journal->totalDebit(), (float) $journal->totalCredit(), 0.0001);

        $movement = StockMovement::query()
            ->where('company_id', 1)
            ->where('source_type', 'stock_adjustment')
            ->where('source_id', $adjustment->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(5.0, (float) $movement->quantity, 0.0001);
        $this->assertEqualsWithDelta(400.0, (float) $movement->unit_cost, 0.0001);

        $this->assertEqualsWithDelta(5.0, app(StockService::class)->onHandQty(1, $this->product->id, $this->warehouseA->id), 0.0001);
    }

    public function test_posted_adjustment_cannot_be_edited_or_posted_again(): void
    {
        $adjustment = $this->createPostedAdjustment();

        $this->actingAs($this->admin)
            ->get(route('stock-adjustments.edit', $adjustment->id))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->post(route('stock-adjustments.post', $adjustment->id))
            ->assertRedirect();

        $this->assertSame(1, Journal::query()->where('source_type', 'stock_adjustment')->where('source_id', $adjustment->id)->count());
    }

    public function test_adjustment_without_any_cost_is_rejected(): void
    {
        $freeProduct = Product::query()->create([
            'company_id' => 1,
            'sku' => 'FREE-P7',
            'name' => 'Freebie',
            'type' => 'product',
            'purchase_price' => 0,
            'sales_price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post('/inventory/adjustments', $this->adjustmentPayload([
            'lines' => [
                0 => ['product_id' => $freeProduct->id, 'counted_qty' => '1'],
            ],
        ]))->assertRedirect();

        $adjustment = StockAdjustment::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $adjustment->status);
        $this->assertNull($adjustment->adjustment_no);
        $this->assertSame(0, Journal::query()->where('source_type', 'stock_adjustment')->count());
    }

    public function test_draft_adjustment_can_be_updated_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/inventory/adjustments', $this->adjustmentPayload())->assertRedirect();

        $adjustment = StockAdjustment::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->put(route('stock-adjustments.update', $adjustment->id), $this->adjustmentPayload([
            'memo' => 'UPDATED',
            'lines' => [
                0 => ['product_id' => $this->product->id, 'counted_qty' => '9'],
            ],
        ]))->assertRedirect();

        $this->assertSame('UPDATED', $adjustment->fresh()->memo);
        $this->assertEqualsWithDelta(9.0, (float) $adjustment->fresh()->lines->first()->counted_qty, 0.0001);

        $this->actingAs($this->admin)
            ->delete(route('stock-adjustments.destroy', $adjustment->id))
            ->assertRedirect();

        $this->assertNotNull($adjustment->fresh()->deleted_at);
    }

    // ───────────────────────── Transfers ─────────────────────────

    public function test_posted_transfer_moves_stock_between_warehouses_without_a_journal(): void
    {
        $this->createPostedBill();
        $service = app(StockService::class);

        $this->actingAs($this->admin)->post('/inventory/transfers', $this->transferPayload(2))->assertRedirect();

        $transfer = StockTransfer::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $transfer->status);
        $this->assertNull($transfer->transfer_no);

        $this->actingAs($this->admin)
            ->post(route('stock-transfers.post', $transfer->id))
            ->assertRedirect();

        $transfer = $transfer->fresh();
        $this->assertSame('posted', $transfer->status);
        $this->assertStringStartsWith('TR-', $transfer->transfer_no);

        $this->assertSame(0, Journal::query()->where('source_type', 'stock_transfer')->count());

        $out = StockMovement::query()
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transfer->id)
            ->where('movement_type', 'transfer_out')
            ->firstOrFail();
        $in = StockMovement::query()
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transfer->id)
            ->where('movement_type', 'transfer_in')
            ->firstOrFail();

        $this->assertEqualsWithDelta(-2.0, (float) $out->quantity, 0.0001);
        $this->assertEqualsWithDelta($this->warehouseA->id, (int) $out->warehouse_id, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $in->quantity, 0.0001);
        $this->assertEqualsWithDelta($this->warehouseB->id, (int) $in->warehouse_id, 0.0001);
        $this->assertEqualsWithDelta(400.0, (float) $in->unit_cost, 0.0001);

        $this->assertEqualsWithDelta(0.0, $service->onHandQty(1, $this->product->id, $this->warehouseA->id), 0.0001);
        $this->assertEqualsWithDelta(2.0, $service->onHandQty(1, $this->product->id, $this->warehouseB->id), 0.0001);
    }

    public function test_transfer_with_insufficient_stock_is_rejected(): void
    {
        $this->createPostedBill();

        $this->actingAs($this->admin)->post('/inventory/transfers', $this->transferPayload(99))->assertRedirect();

        $transfer = StockTransfer::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('stock-transfers.post', $transfer->id))
            ->assertRedirect();

        $this->assertSame('draft', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->transfer_no);
        $this->assertSame(0, Journal::query()->where('source_type', 'stock_transfer')->count());
    }

    // ───────────────────────── Purchase/Sales integration ─────────────────────────

    public function test_posted_purchase_bill_receives_stock(): void
    {
        $bill = $this->createPostedBill();

        $movement = StockMovement::query()
            ->where('company_id', 1)
            ->where('source_type', 'purchase_bill')
            ->where('source_id', $bill->id)
            ->firstOrFail();

        $this->assertSame('purchase_received', $movement->movement_type);
        $this->assertEqualsWithDelta(2.0, (float) $movement->quantity, 0.0001);
        $this->assertEqualsWithDelta(400.0, (float) $movement->unit_cost, 0.0001);
    }

    public function test_posted_sales_invoice_issues_stock_at_average_cost(): void
    {
        $bill = $this->createPostedBill();
        $invoice = $this->createPostedInvoice();

        $movement = StockMovement::query()
            ->where('company_id', 1)
            ->where('source_type', 'sales_invoice')
            ->where('source_id', $invoice->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame('sales_issued', $movement->movement_type);
        $this->assertEqualsWithDelta(-2.0, (float) $movement->quantity, 0.0001);
        $this->assertEqualsWithDelta(400.0, (float) $movement->unit_cost, 0.0001);

        // The SINV journal's COGS used the same average cost.
        $journal = Journal::query()
            ->where('source_type', 'sales_invoice')
            ->where('source_id', $invoice->id)
            ->firstOrFail();
        $cogsLine = $journal->lines->firstWhere('description', 'Cost of goods sold');
        $this->assertNotNull($cogsLine);
        $this->assertEqualsWithDelta(800.0, (float) $cogsLine->debit, 0.0001);

        // Stock is exhausted: the ledger performs total-value reconciliation.
        $this->assertEqualsWithDelta(0.0, app(StockService::class)->onHandQty(1, $this->product->id), 0.0001);
    }

    public function test_weighted_average_cost_updates_after_second_receipt(): void
    {
        $this->createPostedBill();

        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload([
            'reference' => 'INV-992',
            'lines' => [
                0 => ['product_id' => $this->product->id, 'description' => '', 'quantity' => '2', 'unit_cost' => '600', 'discount_amount' => '0', 'tax_rate_id' => $this->vatRate->id],
            ],
        ]))->assertRedirect();

        $bill = PurchaseBill::query()->where('reference', 'INV-992')->firstOrFail();
        $this->actingAs($this->admin)->post(route('purchase.bills.post', $bill->id))->assertRedirect();

        // (2 × 400 + 2 × 600) ÷ 4 = 500.
        $this->assertEqualsWithDelta(500.0, app(StockService::class)->avgCost(1, $this->product->id), 0.0001);
        $this->assertEqualsWithDelta(4.0, app(StockService::class)->onHandQty(1, $this->product->id), 0.0001);
    }

    public function test_stock_page_flags_low_stock_and_reports_summary_value(): void
    {
        $this->createPostedBill();

        $this->actingAs($this->admin)
            ->get('/inventory/stock')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Stock/Index')
                ->where('summary.total_value', 800)
                ->where('summary.product_count', 1)
                ->where('records.data.0.is_low_stock', true));
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_inventory_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/inventory/stock')->assertForbidden();
        $this->actingAs($user)->get('/inventory/adjustments')->assertForbidden();
        $this->actingAs($user)->get('/inventory/transfers')->assertForbidden();
    }

    public function test_accountant_permissions_allow_inventory_workflow(): void
    {
        $accountant = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);
        $accountant->companyAccess()->create([
            'company_id' => 1,
            'branch_id' => 1,
            'is_default' => true,
        ]);
        $accountant->roles()->attach(
            \App\Domain\Rbac\Models\Role::query()->where('slug', 'accountant')->value('id'),
            ['company_id' => 1]
        );

        $this->actingAs($accountant)->get('/inventory/stock')->assertOk();
        $this->actingAs($accountant)->get('/inventory/adjustments')->assertOk();
        $this->actingAs($accountant)->get('/inventory/transfers')->assertOk();

        $this->actingAs($accountant)->post('/inventory/adjustments', $this->adjustmentPayload())->assertRedirect();
        $this->actingAs($accountant)->post(route('stock-adjustments.post', StockAdjustment::query()->firstOrFail()->id))->assertRedirect();

        $this->actingAs($accountant)->get('/inventory/stock')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.total_value', 2000));
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function createWarehouse(string $code, string $name): Warehouse
    {
        return Warehouse::query()->create([
            'company_id' => 1,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function adjustmentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'adjustment_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouseA->id,
            'reason' => 'count_correction',
            'memo' => null,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'counted_qty' => '5',
                ],
            ],
        ], $overrides);
    }

    private function transferPayload(int $quantity): array
    {
        return [
            'transfer_date' => now()->toDateString(),
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'reference' => 'MOVE-1',
            'memo' => null,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => (string) $quantity,
                ],
            ],
        ];
    }

    private function billPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => null,
            'reference' => 'INV-991',
            'notes' => null,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'description' => '',
                    'quantity' => '2',
                    'unit_cost' => '400',
                    'discount_amount' => '0',
                    'tax_rate_id' => $this->vatRate->id,
                ],
            ],
        ], $overrides);
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => null,
            'reference' => 'INV-S1',
            'notes' => null,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'description' => '',
                    'quantity' => '2',
                    'unit_price' => '500',
                    'discount_amount' => '0',
                    'tax_rate_id' => $this->vatRate->id,
                ],
            ],
        ], $overrides);
    }

    private function createPostedBill(): PurchaseBill
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload())->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('purchase.bills.post', $bill->id))
            ->assertRedirect();

        return $bill->fresh();
    }

    private function createPostedInvoice(): SalesInvoice
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload())->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        return $invoice->fresh();
    }

    private function createPostedAdjustment(): StockAdjustment
    {
        $this->actingAs($this->admin)->post('/inventory/adjustments', $this->adjustmentPayload())->assertRedirect();

        $adjustment = StockAdjustment::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('stock-adjustments.post', $adjustment->id))
            ->assertRedirect();

        return $adjustment->fresh();
    }
}