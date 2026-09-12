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

class Phase9CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Supplier $otherSupplier;

    private Product $product;

    private TaxRate $vatRate;

    private int $apAccountId;

    private int $cashAccountId;

    private int $advanceAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->apAccountId = $setting->default_ap_account_id;
        $this->cashAccountId = $setting->default_cash_account_id;

        $this->advanceAccountId = Account::query()->where('company_id', 1)->where('code', '1161')->value('id');

        $this->assertNotNull($this->advanceAccountId, 'Advances to Suppliers (1161) must be seeded.');

        $this->vatRate = TaxRate::query()
            ->where('company_id', 1)
            ->where('name', 'Standard Rate 15%')
            ->firstOrFail();

        $this->supplier = $this->createSupplier('SUP-P9A', 'Acme Vendor');
        $this->otherSupplier = $this->createSupplier('SUP-P9B', 'Zeta Suppliers');

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P9',
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

    public function test_super_admin_can_view_payables_pages(): void
    {
        $this->actingAs($this->admin)
            ->get('/payables')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Index')
                ->where('dashboard.total_payable', 0)
                ->has('dashboard.top_suppliers'));

        $this->actingAs($this->admin)
            ->get('/payables/outstanding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Outstanding')
                ->has('rows'));

        $this->actingAs($this->admin)
            ->get('/payables/aging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Aging')
                ->has('rows'));

        $this->actingAs($this->admin)
            ->get('/payables/record-payment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/RecordPayment')
                ->has('suppliers')
                ->has('accounts')
                ->where('selectedSupplierId', null)
                ->where('outstandingBills', []));

        $this->actingAs($this->admin)
            ->get('/payables/advances')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Advances/Index')
                ->has('advances.data'));
    }

    public function test_record_payment_page_prefills_supplier_outstanding_bills(): void
    {
        $bill = $this->createPostedBill($this->supplier);

        $this->actingAs($this->admin)
            ->get('/payables/record-payment?supplier_id='.$this->supplier->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedSupplierId', $this->supplier->id)
                ->where('outstandingBills.0.id', $bill->id)
                ->where('outstandingBills.0.balance_due', 920));
    }

    // ───────────────────────── Multi-bill payments ─────────────────────────

    public function test_multi_bill_payment_posts_one_pmt_journal_and_allocs_both_bills(): void
    {
        $billA = $this->createPostedBill($this->supplier);
        $billB = $this->createPostedBill($this->supplier);

        $this->actingAs($this->admin)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'reference' => 'PAY-MULTI',
            'allocations' => [
                ['purchase_bill_id' => $billA->id, 'amount' => 500],
                ['purchase_bill_id' => $billB->id, 'amount' => 420],
            ],
        ])->assertRedirect();

        $payment = SupplierPayment::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('payment', $payment->type);
        $this->assertStringStartsWith('PY-', $payment->payment_no);
        $this->assertEqualsWithDelta(920.0, (float) $payment->amount, 0.0001);
        $this->assertSame(2, $payment->allocations()->count());

        $journal = Journal::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertStringStartsWith('PMT-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(920.0, (float) $journal->lines->firstWhere('account_id', $this->apAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(920.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->credit, 0.0001);

        $this->assertEqualsWithDelta(500.0, (float) $billA->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(420.0, (float) $billB->fresh()->amount_paid, 0.0001);
        $this->assertSame('partial', $billA->fresh()->paidState());
        $this->assertSame('partial', $billB->fresh()->paidState());
    }

    public function test_payment_over_allocating_one_bill_is_rejected(): void
    {
        $billA = $this->createPostedBill($this->supplier);
        $this->createPostedBill($this->supplier);

        $this->actingAs($this->admin)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['purchase_bill_id' => $billA->id, 'amount' => 2000],
            ],
        ])->assertRedirect();

        $this->assertSame(0, SupplierPayment::query()->count());
        $this->assertEquals(0.0, (float) $billA->fresh()->amount_paid);
    }

    public function test_payment_for_another_suppliers_bill_is_rejected(): void
    {
        $otherBill = $this->createPostedBill($this->otherSupplier);

        $this->actingAs($this->admin)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['purchase_bill_id' => $otherBill->id, 'amount' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame(0, SupplierPayment::query()->count());
    }

    public function test_payment_requires_a_posted_bill(): void
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload($this->supplier))->assertRedirect();

        $draft = PurchaseBill::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['purchase_bill_id' => $draft->id, 'amount' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame(0, SupplierPayment::query()->count());
    }

    // ───────────────────────── Advances ─────────────────────────

    public function test_advance_payment_posts_pmt_journal_against_supplier_advances(): void
    {
        $this->actingAs($this->admin)->post('/payables/advances', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 1000,
            'reference' => 'ADV-001',
        ])->assertRedirect();

        $payment = SupplierPayment::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('advance', $payment->type);
        $this->assertStringStartsWith('PY-', $payment->payment_no);
        $this->assertEqualsWithDelta(1000.0, (float) $payment->amount, 0.0001);
        $this->assertEqualsWithDelta(0.0, $payment->appliedAmount(), 0.0001);
        $this->assertEqualsWithDelta(1000.0, $payment->advanceBalance(), 0.0001);

        $journal = Journal::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->advanceAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->credit, 0.0001);

        $this->actingAs($this->admin)
            ->get(route('supplier-advances.show', $payment->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Advances/Show')
                ->where('advance.balance', 1000)
                ->where('advance.applied_amount', 0));
    }

    public function test_advance_can_be_applied_across_bills_with_saa_journal(): void
    {
        $billA = $this->createPostedBill($this->supplier);
        $billB = $this->createPostedBill($this->supplier);

        $advance = $this->createPostedAdvance($this->supplier, 1000);

        $this->actingAs($this->admin)->post(route('supplier-advances.apply', $advance->id), [
            'allocations' => [
                ['purchase_bill_id' => $billA->id, 'amount' => 400],
                ['purchase_bill_id' => $billB->id, 'amount' => 600],
            ],
        ])->assertRedirect();

        $advance = $advance->fresh();

        $this->assertEqualsWithDelta(400.0, (float) $billA->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(600.0, (float) $billB->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(1000.0, $advance->appliedAmount(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $advance->advanceBalance(), 0.0001);

        $journal = Journal::query()
            ->where('source_type', 'payment_application')
            ->where('source_id', $advance->id)
            ->firstOrFail();

        $this->assertStringStartsWith('SAA-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->apAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->advanceAccountId)->credit, 0.0001);
    }

    public function test_advance_application_above_balance_is_rejected(): void
    {
        $billA = $this->createPostedBill($this->supplier);
        $advance = $this->createPostedAdvance($this->supplier, 500);

        $this->actingAs($this->admin)->post(route('supplier-advances.apply', $advance->id), [
            'allocations' => [
                ['purchase_bill_id' => $billA->id, 'amount' => 900],
            ],
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $billA->fresh()->amount_paid);
    }

    public function test_advance_cannot_be_applied_to_another_suppliers_bill(): void
    {
        $otherBill = $this->createPostedBill($this->otherSupplier);
        $advance = $this->createPostedAdvance($this->supplier, 500);

        $this->actingAs($this->admin)->post(route('supplier-advances.apply', $advance->id), [
            'allocations' => [
                ['purchase_bill_id' => $otherBill->id, 'amount' => 300],
            ],
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $otherBill->fresh()->amount_paid);
    }

    // ───────────────────────── Reports ─────────────────────────

    public function test_outstanding_and_aging_rank_bills_by_bucket(): void
    {
        $this->createPostedBill($this->supplier, [
            'bill_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->createPostedBill($this->supplier, [
            'bill_date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(45)->toDateString(),
        ]);
        $this->createPostedBill($this->supplier, [
            'bill_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/payables/outstanding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Outstanding')
                ->where('totals.balance_due', 2760)
                ->where('totals.count', 3)
                ->has('rows', 3));

        $this->actingAs($this->admin)
            ->get('/payables/aging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Aging')
                ->where('rows.0.supplier_id', $this->supplier->id)
                ->where('rows.0.current', 920)
                ->where('rows.0.b1_30', 920)
                ->where('rows.0.b31_60', 920)
                ->where('rows.0.b90', 0)
                ->where('rows.0.total', 2760));
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_payables_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/payables')->assertForbidden();
        $this->actingAs($user)->get('/payables/outstanding')->assertForbidden();
    }

    public function test_accountant_permissions_allow_payables_workflow(): void
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

        $this->actingAs($accountant)->get('/payables')->assertOk();
        $this->actingAs($accountant)->get('/payables/outstanding')->assertOk();
        $this->actingAs($accountant)->get('/payables/aging')->assertOk();
        $this->actingAs($accountant)->get('/payables/advances')->assertOk();

        $bill = $this->createPostedBill($this->supplier);

        // Record a multi-bill payment.
        $this->actingAs($accountant)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['purchase_bill_id' => $bill->id, 'amount' => 300],
            ],
        ])->assertRedirect();

        // Post an advance and apply it.
        $this->actingAs($accountant)->post('/payables/advances', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 200,
        ])->assertRedirect();

        $advance = SupplierPayment::query()->where('type', 'advance')->firstOrFail();
        $this->actingAs($accountant)
            ->post(route('supplier-advances.apply', $advance->id), [
                'allocations' => [
                    ['purchase_bill_id' => $bill->id, 'amount' => 200],
                ],
            ])
            ->assertRedirect();

        // Pay the remaining balance in full with a second multi-bill payment.
        $bill = $bill->fresh();
        $this->actingAs($accountant)->post('/payables/record-payment', [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['purchase_bill_id' => $bill->id, 'amount' => $bill->balanceDue()],
            ],
        ])->assertRedirect();

        $this->assertSame('paid', $bill->fresh()->paidState());
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function createSupplier(string $code, string $name): Supplier
    {
        return Supplier::query()->create([
            'company_id' => 1,
            'code' => $code,
            'name' => $name,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function billPayload(Supplier $supplier, array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => null,
            'reference' => 'INV-'.$supplier->code,
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

    private function createPostedBill(Supplier $supplier, array $overrides = []): PurchaseBill
    {
        $this->actingAs($this->admin)->post('/purchase/bills', $this->billPayload($supplier, $overrides))->assertRedirect();

        $bill = PurchaseBill::query()
            ->where('company_id', 1)
            ->where('supplier_id', $supplier->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($this->admin)->post(route('purchase.bills.post', $bill->id))->assertRedirect();

        return $bill->fresh();
    }

    private function createPostedAdvance(Supplier $supplier, float $amount): SupplierPayment
    {
        $this->actingAs($this->admin)->post('/payables/advances', [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => $amount,
        ])->assertRedirect();

        return SupplierPayment::query()->where('type', 'advance')->latest('id')->firstOrFail();
    }
}