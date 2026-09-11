<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Party\Models\Supplier;
use App\Domain\Product\Models\Product;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Purchase\Models\SupplierPayment;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Product $product;

    private TaxRate $vatRate;

    private int $apAccountId;

    private int $purchaseAccountId;

    private int $vatInputId;

    private int $cashAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->apAccountId = $setting->default_ap_account_id;
        $this->purchaseAccountId = $setting->default_purchase_account_id;
        $this->cashAccountId = $setting->default_cash_account_id;
        $this->vatInputId = $setting->default_tax_input_account_id;

        $this->vatRate = TaxRate::query()
            ->where('company_id', 1)
            ->where('name', 'Standard Rate 15%')
            ->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'company_id' => 1,
            'code' => 'SUP-P6',
            'name' => 'Acme Vendor',
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P6',
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

    public function test_super_admin_can_view_purchase_bills_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/purchase/bills')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchase/Bills/Index')
                ->has('bills.data')
                ->has('bills.total'));
    }

    public function test_bill_create_page_exposes_lookup_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/purchase/bills/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchase/Bills/Create')
                ->has('suppliers')
                ->has('products')
                ->has('taxRates'));
    }

    // ───────────────────────── Draft lifecycle ─────────────────────────

    public function test_bill_draft_can_be_saved_and_viewed(): void
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload())->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('draft', $bill->status);
        $this->assertNull($bill->bill_no);
        $this->assertSame(1, $bill->lines()->count());
        // Totals are recomputed server-side: 2 × 400 + 15% input tax.
        $this->assertEqualsWithDelta(800.0, (float) $bill->subtotal, 0.0001);
        $this->assertEqualsWithDelta(120.0, (float) $bill->tax_amount, 0.0001);
        $this->assertEqualsWithDelta(920.0, (float) $bill->total, 0.0001);

        $this->actingAs($this->admin)
            ->get(route('purchase.bills.show', $bill->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchase/Bills/Show')
                ->where('bill.status', 'draft')
                ->has('bill.lines', 1)
                ->where('bill.journal_id', null));
    }

    public function test_posted_bill_gets_number_and_pur_journal(): void
    {
        $bill = $this->createPostedBill();

        $this->assertSame('posted', $bill->status);
        $this->assertStringStartsWith('PB-', $bill->bill_no);
        $this->assertNotNull($bill->posted_at);

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'purchase_bill')
            ->where('source_id', $bill->id)
            ->firstOrFail();

        $this->assertStringStartsWith('PUR-', $journal->journal_no);
        $this->assertSame('posted', $journal->status);

        $lines = $journal->lines;
        $this->assertCount(3, $lines);

        $debitPurchase = $lines->firstWhere('account_id', $this->purchaseAccountId);
        $debitVat = $lines->firstWhere('account_id', $this->vatInputId);
        $creditAp = $lines->firstWhere('account_id', $this->apAccountId);

        $this->assertEqualsWithDelta(800.0, (float) $debitPurchase->debit, 0.0001);
        $this->assertEqualsWithDelta(120.0, (float) $debitVat->debit, 0.0001);
        $this->assertEqualsWithDelta(920.0, (float) $creditAp->credit, 0.0001);

        $this->assertEqualsWithDelta((float) $journal->totalDebit(), (float) $journal->totalCredit(), 0.0001);

        // The AP line is tagged with the supplier party.
        $this->assertSame('supplier', $creditAp->party_type);
        $this->assertSame($this->supplier->id, (int) $creditAp->party_id);
    }

    public function test_posted_bill_cannot_be_edited_or_posted_again(): void
    {
        $bill = $this->createPostedBill();

        $this->actingAs($this->admin)
            ->get(route('purchase.bills.edit', $bill->id))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->post(route('purchase.bills.post', $bill->id))
            ->assertRedirect();

        $this->assertSame(1, Journal::query()->where('source_type', 'purchase_bill')->where('source_id', $bill->id)->count());
    }

    public function test_draft_bill_can_be_updated_and_deleted(): void
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload())->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->put(route('purchase.bills.update', $bill->id), $this->billPayload([
            'reference' => 'UPDATED-REF',
            'lines' => [
                0 => ['product_id' => $this->product->id, 'description' => '', 'quantity' => '3', 'unit_cost' => '400', 'discount_amount' => '0', 'tax_rate_id' => $this->vatRate->id],
            ],
        ]))->assertRedirect();

        $bill = $bill->fresh();

        $this->assertSame('UPDATED-REF', $bill->reference);
        $this->assertEqualsWithDelta(1200.0, (float) $bill->subtotal, 0.0001);
        $this->assertEqualsWithDelta(1380.0, (float) $bill->total, 0.0001);

        $this->actingAs($this->admin)
            ->delete(route('purchase.bills.destroy', $bill->id))
            ->assertRedirect();

        $this->assertNotNull($bill->fresh()->deleted_at);
    }

    public function test_empty_numbers_normalize_to_zero(): void
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload([
            'lines' => [
                0 => ['product_id' => $this->product->id, 'description' => '', 'quantity' => '2', 'unit_cost' => '400', 'discount_amount' => '', 'tax_rate_id' => $this->vatRate->id],
            ],
        ]))->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->assertEquals(0.0, (float) $bill->discount_amount);
        $this->assertEqualsWithDelta(920.0, (float) $bill->total, 0.0001);
    }

    // ───────────────────────── Supplier payments ─────────────────────────

    public function test_partial_payment_creates_payment_and_updates_paid_state(): void
    {
        $bill = $this->createPostedBill();

        $this->actingAs($this->admin)->post(route('purchase.bills.pay', $bill->id), [
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 500.00,
            'reference' => 'BANK-TXN-1',
            'memo' => 'Partial payment',
        ])->assertRedirect();

        $payment = SupplierPayment::query()->where('company_id', 1)->firstOrFail();

        $this->assertStringStartsWith('PY-', $payment->payment_no);
        $this->assertSame('posted', $payment->status);
        $this->assertSame($bill->id, $payment->allocations()->first()->purchase_bill_id);

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertStringStartsWith('PMT-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(500.0, (float) $journal->lines->firstWhere('account_id', $this->apAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(500.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->credit, 0.0001);

        $bill = $bill->fresh();
        $this->assertEqualsWithDelta(500.0, (float) $bill->amount_paid, 0.0001);
        $this->assertSame('partial', $bill->paidState());
    }

    public function test_full_payment_sets_bill_paid(): void
    {
        $bill = $this->createPostedBill();

        $this->actingAs($this->admin)->post(route('purchase.bills.pay', $bill->id), [
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 920.00,
        ])->assertRedirect();

        $bill = $bill->fresh();

        $this->assertSame('paid', $bill->paidState());
        $this->assertEqualsWithDelta(920.0, (float) $bill->amount_paid, 0.0001);
        $this->assertSame(1, SupplierPayment::query()->count());
    }

    public function test_payment_above_balance_is_rejected(): void
    {
        $bill = $this->createPostedBill();

        $this->actingAs($this->admin)->post(route('purchase.bills.pay', $bill->id), [
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 2000.00,
        ])->assertRedirect();

        $this->assertSame(0, SupplierPayment::query()->count());
        $this->assertEquals(0.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_payment_is_rejected_for_draft_bills(): void
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload())->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('purchase.bills.pay', $bill->id), [
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 100.00,
        ])->assertRedirect();

        $this->assertSame(0, SupplierPayment::query()->count());
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_purchase_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/purchase/bills')->assertForbidden();
    }

    public function test_accountant_permissions_allow_saving_and_visible_purchase(): void
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

        $this->actingAs($accountant)->get('/purchase/bills')->assertOk();
        $this->actingAs($accountant)->post('/purchase/bills', $this->billPayload())->assertRedirect();
        $this->actingAs($accountant)->get(route('purchase.bills.show', PurchaseBill::query()->firstOrFail()->id))->assertOk();
    }

    // ───────────────────────── Helpers ─────────────────────────

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

    private function createPostedBill(): PurchaseBill
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload())->assertRedirect();

        $bill = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('purchase.bills.post', $bill->id))
            ->assertRedirect();

        return $bill->fresh();
    }
}