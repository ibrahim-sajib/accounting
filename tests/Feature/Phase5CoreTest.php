<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Party\Models\Customer;
use App\Domain\Product\Models\Product;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    private TaxRate $vatRate;

    private int $arAccountId;

    private int $salesAccountId;

    private int $vatPayableId;

    private int $cogsAccountId;

    private int $inventoryAccountId;

    private int $cashAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->arAccountId = $setting->default_ar_account_id;
        $this->salesAccountId = $setting->default_sales_account_id;
        $this->cashAccountId = $setting->default_cash_account_id;
        $this->inventoryAccountId = $setting->default_inventory_account_id;
        $this->vatPayableId = $setting->default_tax_output_account_id;
        $this->cogsAccountId = Account::query()->where('company_id', 1)->where('code', '5221')->value('id');

        $this->vatRate = TaxRate::query()
            ->where('company_id', 1)
            ->where('name', 'Standard Rate 15%')
            ->firstOrFail();

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-P5',
            'name' => 'Alpha Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P5',
            'name' => 'Widget',
            'type' => 'product',
            'purchase_price' => 400,
            'sales_price' => 500,
            'tax_rate_id' => $this->vatRate->id,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_super_admin_can_view_sales_invoices_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/sales/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/Index')
                ->has('invoices.data')
                ->has('invoices.total'));
    }

    public function test_invoice_create_page_exposes_lookup_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/sales/invoices/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/Create')
                ->has('customers')
                ->has('products')
                ->has('taxRates'));
    }

    // ───────────────────────── Draft lifecycle ─────────────────────────

    public function test_invoice_draft_can_be_saved_and_viewed(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload())->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $invoice->status);
        $this->assertNull($invoice->invoice_no);
        $this->assertSame(1, $invoice->lines()->count());
        // Totals are recomputed server-side: 2 × 500 + 15% tax.
        $this->assertEqualsWithDelta(1000.0, (float) $invoice->subtotal, 0.0001);
        $this->assertEqualsWithDelta(150.0, (float) $invoice->tax_amount, 0.0001);
        $this->assertEqualsWithDelta(1150.0, (float) $invoice->total, 0.0001);

        $this->actingAs($this->admin)
            ->get(route('sales.invoices.show', $invoice->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/Show')
                ->where('invoice.status', 'draft')
                ->has('invoice.lines', 1)
                ->where('invoice.journal_id', null));
    }

    public function test_posted_invoice_gets_number_and_sinv_journal(): void
    {
        $invoice = $this->createPostedInvoice();

        $this->assertSame('posted', $invoice->status);
        $this->assertStringStartsWith('SL-', $invoice->invoice_no);
        $this->assertNotNull($invoice->posted_at);

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'sales_invoice')
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $this->assertStringStartsWith('SINV-', $journal->journal_no);
        $this->assertSame('posted', $journal->status);

        $lines = $journal->lines;
        $this->assertCount(5, $lines);

        $debitAr = $lines->firstWhere('account_id', $this->arAccountId);
        $creditRevenue = $lines->firstWhere('account_id', $this->salesAccountId);
        $creditVat = $lines->firstWhere('account_id', $this->vatPayableId);
        $debitCogs = $lines->firstWhere('account_id', $this->cogsAccountId);
        $creditInventory = $lines->firstWhere('account_id', $this->inventoryAccountId);

        $this->assertEqualsWithDelta(1150.0, (float) $debitAr->debit, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $creditRevenue->credit, 0.0001);
        $this->assertEqualsWithDelta(150.0, (float) $creditVat->credit, 0.0001);
        $this->assertEqualsWithDelta(800.0, (float) $debitCogs->debit, 0.0001);
        $this->assertEqualsWithDelta(800.0, (float) $creditInventory->credit, 0.0001);

        $this->assertEqualsWithDelta((float) $journal->totalDebit(), (float) $journal->totalCredit(), 0.0001);

        // The AR line is tagged with the customer party.
        $this->assertSame('customer', $debitAr->party_type);
        $this->assertSame($this->customer->id, (int) $debitAr->party_id);
    }

    public function test_posted_invoice_cannot_be_edited_or_posted_again(): void
    {
        $invoice = $this->createPostedInvoice();

        $this->actingAs($this->admin)
            ->get(route('sales.invoices.edit', $invoice->id))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        $this->assertSame(1, Journal::query()->where('source_type', 'sales_invoice')->where('source_id', $invoice->id)->count());
    }

    public function test_draft_invoice_can_be_updated_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload())->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->put(route('sales.invoices.update', $invoice->id), $this->invoicePayload([
            'reference' => 'UPDATED-REF',
            'lines' => [
                0 => ['product_id' => $this->product->id, 'description' => '', 'quantity' => '3', 'unit_price' => '500', 'discount_amount' => '0', 'tax_rate_id' => $this->vatRate->id],
            ],
        ]))->assertRedirect();

        $invoice = $invoice->fresh();

        $this->assertSame('UPDATED-REF', $invoice->reference);
        $this->assertEqualsWithDelta(1500.0, (float) $invoice->subtotal, 0.0001);
        $this->assertEqualsWithDelta(1725.0, (float) $invoice->total, 0.0001);

        $this->actingAs($this->admin)
            ->delete(route('sales.invoices.destroy', $invoice->id))
            ->assertRedirect();

        $this->assertNotNull($invoice->fresh()->deleted_at);
    }

    public function test_empty_numbers_normalize_to_zero(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload([
            'lines' => [
                0 => ['product_id' => $this->product->id, 'description' => '', 'quantity' => '2', 'unit_price' => '500', 'discount_amount' => '', 'tax_rate_id' => $this->vatRate->id],
            ],
        ]))->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->assertEquals(0.0, (float) $invoice->discount_amount);
        $this->assertEqualsWithDelta(1150.0, (float) $invoice->total, 0.0001);
    }

    // ───────────────────────── Receipts / payments ─────────────────────────

    public function test_partial_payment_creates_receipt_and_updates_paid_state(): void
    {
        $invoice = $this->createPostedInvoice();

        $this->actingAs($this->admin)->post(route('sales.invoices.pay', $invoice->id), [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 500.00,
            'reference' => 'BANK-TXN-1',
            'memo' => 'Partial payment',
        ])->assertRedirect();

        $receipt = Receipt::query()->where('company_id', 1)->firstOrFail();

        $this->assertStringStartsWith('RC-', $receipt->receipt_no);
        $this->assertSame('posted', $receipt->status);
        $this->assertSame($invoice->id, $receipt->allocations()->first()->sales_invoice_id);

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'receipt')
            ->where('source_id', $receipt->id)
            ->firstOrFail();

        $this->assertStringStartsWith('RCT-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(500.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(500.0, (float) $journal->lines->firstWhere('account_id', $this->arAccountId)->credit, 0.0001);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(500.0, (float) $invoice->amount_paid, 0.0001);
        $this->assertSame('partial', $invoice->paidState());
    }

    public function test_full_payment_sets_invoice_paid(): void
    {
        $invoice = $this->createPostedInvoice();

        $this->actingAs($this->admin)->post(route('sales.invoices.pay', $invoice->id), [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 1150.00,
        ])->assertRedirect();

        $invoice = $invoice->fresh();

        $this->assertSame('paid', $invoice->paidState());
        $this->assertEqualsWithDelta(1150.0, (float) $invoice->amount_paid, 0.0001);
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_payment_above_balance_is_rejected(): void
    {
        $invoice = $this->createPostedInvoice();

        $this->actingAs($this->admin)->post(route('sales.invoices.pay', $invoice->id), [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 2000.00,
        ])->assertRedirect();

        $this->assertSame(0, Receipt::query()->count());
        $this->assertEquals(0.0, (float) $invoice->fresh()->amount_paid);
    }

    public function test_payment_is_rejected_for_draft_invoices(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload())->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('sales.invoices.pay', $invoice->id), [
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 100.00,
        ])->assertRedirect();

        $this->assertSame(0, Receipt::query()->count());
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_sales_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/sales/invoices')->assertForbidden();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function invoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => null,
            'reference' => 'PO-001',
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

    private function createPostedInvoice(): SalesInvoice
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload())->assertRedirect();

        $invoice = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        return $invoice->fresh();
    }
}