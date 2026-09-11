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

class Phase8CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Customer $otherCustomer;

    private Product $product;

    private TaxRate $vatRate;

    private int $arAccountId;

    private int $cashAccountId;

    private int $advanceAccountId;

    private int $badDebtAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->arAccountId = $setting->default_ar_account_id;
        $this->cashAccountId = $setting->default_cash_account_id;

        $this->advanceAccountId = Account::query()->where('company_id', 1)->where('code', '2161')->value('id');
        $this->badDebtAccountId = Account::query()->where('company_id', 1)->where('code', '5191')->value('id');

        $this->assertNotNull($this->advanceAccountId, 'Customer Advances (2161) must be seeded.');
        $this->assertNotNull($this->badDebtAccountId, 'Bad Debt Expense (5191) must be seeded.');

        $this->vatRate = TaxRate::query()
            ->where('company_id', 1)
            ->where('name', 'Standard Rate 15%')
            ->firstOrFail();

        $this->customer = $this->createCustomer('CUS-P8A', 'Alpha Buyer');
        $this->otherCustomer = $this->createCustomer('CUS-P8B', 'Beta Buyer');

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P8',
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

    public function test_super_admin_can_view_receivables_pages(): void
    {
        $this->actingAs($this->admin)
            ->get('/receivables')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Index')
                ->where('dashboard.total_receivable', 0)
                ->has('dashboard.top_customers'));

        $this->actingAs($this->admin)
            ->get('/receivables/outstanding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Outstanding')
                ->has('rows'));

        $this->actingAs($this->admin)
            ->get('/receivables/aging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Aging')
                ->has('rows'));

        $this->actingAs($this->admin)
            ->get('/receivables/record-payment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/RecordPayment')
                ->has('customers')
                ->has('accounts')
                ->where('selectedCustomerId', null)
                ->where('outstandingInvoices', []));

        $this->actingAs($this->admin)
            ->get('/receivables/advances')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Advances/Index')
                ->has('advances.data'));
    }

    public function test_record_payment_page_prefills_customer_outstanding_invoices(): void
    {
        $invoice = $this->createPostedInvoice($this->customer);

        $this->actingAs($this->admin)
            ->get('/receivables/record-payment?customer_id='.$this->customer->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedCustomerId', $this->customer->id)
                ->where('outstandingInvoices.0.id', $invoice->id)
                ->where('outstandingInvoices.0.balance_due', 1150));
    }

    // ───────────────────────── Multi-invoice receipts ─────────────────────────

    public function test_multi_invoice_payment_posts_one_rct_journal_and_allocs_both_invoices(): void
    {
        $invoiceA = $this->createPostedInvoice($this->customer);
        $invoiceB = $this->createPostedInvoice($this->customer);

        $this->actingAs($this->admin)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'reference' => 'PAY-MULTI',
            'allocations' => [
                ['sales_invoice_id' => $invoiceA->id, 'amount' => 500],
                ['sales_invoice_id' => $invoiceB->id, 'amount' => 650],
            ],
        ])->assertRedirect();

        $receipt = Receipt::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('receipt', $receipt->type);
        $this->assertStringStartsWith('RC-', $receipt->receipt_no);
        $this->assertEqualsWithDelta(1150.0, (float) $receipt->amount, 0.0001);
        $this->assertSame(2, $receipt->allocations()->count());

        $journal = Journal::query()
            ->where('source_type', 'receipt')
            ->where('source_id', $receipt->id)
            ->firstOrFail();

        $this->assertStringStartsWith('RCT-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(1150.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(1150.0, (float) $journal->lines->firstWhere('account_id', $this->arAccountId)->credit, 0.0001);

        $this->assertEqualsWithDelta(500.0, (float) $invoiceA->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(650.0, (float) $invoiceB->fresh()->amount_paid, 0.0001);
        $this->assertSame('partial', $invoiceA->fresh()->paidState());
        $this->assertSame('partial', $invoiceB->fresh()->paidState());
    }

    public function test_payment_over_allocating_one_invoice_is_rejected(): void
    {
        $invoiceA = $this->createPostedInvoice($this->customer);
        $this->createPostedInvoice($this->customer);

        $this->actingAs($this->admin)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['sales_invoice_id' => $invoiceA->id, 'amount' => 2000],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Receipt::query()->count());
        $this->assertEquals(0.0, (float) $invoiceA->fresh()->amount_paid);
    }

    public function test_payment_for_another_customers_invoice_is_rejected(): void
    {
        $otherInvoice = $this->createPostedInvoice($this->otherCustomer);

        $this->actingAs($this->admin)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['sales_invoice_id' => $otherInvoice->id, 'amount' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_payment_requires_a_posted_invoice(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload($this->customer))->assertRedirect();

        $draft = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['sales_invoice_id' => $draft->id, 'amount' => 100],
            ],
        ])->assertRedirect();

        $this->assertSame(0, Receipt::query()->count());
    }

    // ───────────────────────── Advances ─────────────────────────

    public function test_advance_receipt_posts_rct_journal_against_customer_advances(): void
    {
        $this->actingAs($this->admin)->post('/receivables/advances', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 1000,
            'reference' => 'ADV-001',
        ])->assertRedirect();

        $receipt = Receipt::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame('advance', $receipt->type);
        $this->assertStringStartsWith('RC-', $receipt->receipt_no);
        $this->assertEqualsWithDelta(1000.0, (float) $receipt->amount, 0.0001);
        $this->assertEqualsWithDelta(0.0, $receipt->appliedAmount(), 0.0001);
        $this->assertEqualsWithDelta(1000.0, $receipt->advanceBalance(), 0.0001);

        $journal = Journal::query()
            ->where('source_type', 'receipt')
            ->where('source_id', $receipt->id)
            ->firstOrFail();

        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->cashAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->advanceAccountId)->credit, 0.0001);

        $this->actingAs($this->admin)
            ->get(route('advances.show', $receipt->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Advances/Show')
                ->where('advance.balance', 1000)
                ->where('advance.applied_amount', 0));
    }

    public function test_advance_can_be_applied_across_invoices_with_rca_journal(): void
    {
        $invoiceA = $this->createPostedInvoice($this->customer);
        $invoiceB = $this->createPostedInvoice($this->customer);

        $advance = $this->createPostedAdvance($this->customer, 1000);

        $this->actingAs($this->admin)->post(route('advances.apply', $advance->id), [
            'allocations' => [
                ['sales_invoice_id' => $invoiceA->id, 'amount' => 400],
                ['sales_invoice_id' => $invoiceB->id, 'amount' => 600],
            ],
        ])->assertRedirect();

        $advance = $advance->fresh();

        $this->assertEqualsWithDelta(400.0, (float) $invoiceA->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(600.0, (float) $invoiceB->fresh()->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(1000.0, $advance->appliedAmount(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $advance->advanceBalance(), 0.0001);

        $journal = Journal::query()
            ->where('source_type', 'receipt_application')
            ->where('source_id', $advance->id)
            ->firstOrFail();

        $this->assertStringStartsWith('RCA-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->advanceAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $journal->lines->firstWhere('account_id', $this->arAccountId)->credit, 0.0001);
    }

    public function test_advance_application_above_balance_is_rejected(): void
    {
        $invoiceA = $this->createPostedInvoice($this->customer);
        $advance = $this->createPostedAdvance($this->customer, 500);

        $this->actingAs($this->admin)->post(route('advances.apply', $advance->id), [
            'allocations' => [
                ['sales_invoice_id' => $invoiceA->id, 'amount' => 900],
            ],
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $invoiceA->fresh()->amount_paid);
    }

    public function test_advance_cannot_be_applied_to_another_customers_invoice(): void
    {
        $otherInvoice = $this->createPostedInvoice($this->otherCustomer);
        $advance = $this->createPostedAdvance($this->customer, 500);

        $this->actingAs($this->admin)->post(route('advances.apply', $advance->id), [
            'allocations' => [
                ['sales_invoice_id' => $otherInvoice->id, 'amount' => 300],
            ],
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $otherInvoice->fresh()->amount_paid);
    }

    // ───────────────────────── Write-offs ─────────────────────────

    public function test_write_off_posts_worf_journal_and_updates_balance(): void
    {
        $invoice = $this->createPostedInvoice($this->customer);

        // Part-pay first, then write off the rest.
        $this->actingAs($this->admin)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['sales_invoice_id' => $invoice->id, 'amount' => 500],
            ],
        ])->assertRedirect();

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(650.0, $invoice->balanceDue(), 0.0001);

        $this->actingAs($this->admin)->post(route('receivable-write-off.store', $invoice->id), [
            'amount' => 650,
            'reason' => 'Customer declared bankruptcy',
        ])->assertRedirect();

        $invoice = $invoice->fresh();

        $this->assertEqualsWithDelta(650.0, (float) $invoice->write_off_amount, 0.0001);
        $this->assertSame('Customer declared bankruptcy', $invoice->write_off_reason);
        $this->assertNotNull($invoice->written_off_at);
        $this->assertEqualsWithDelta(0.0, $invoice->balanceDue(), 0.0001);
        $this->assertSame('paid', $invoice->paidState());

        $journal = Journal::query()
            ->where('source_type', 'write_off')
            ->where('source_id', $invoice->id)
            ->firstOrFail();

        $this->assertStringStartsWith('WOF-', $journal->journal_no);
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(650.0, (float) $journal->lines->firstWhere('account_id', $this->badDebtAccountId)->debit, 0.0001);
        $this->assertEqualsWithDelta(650.0, (float) $journal->lines->firstWhere('account_id', $this->arAccountId)->credit, 0.0001);
    }

    public function test_write_off_above_balance_is_rejected(): void
    {
        $invoice = $this->createPostedInvoice($this->customer);

        $this->actingAs($this->admin)->post(route('receivable-write-off.store', $invoice->id), [
            'amount' => 5000,
            'reason' => 'Nope',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $invoice->fresh()->write_off_amount);
        $this->assertSame(0, Journal::query()->where('source_type', 'write_off')->count());
    }

    public function test_write_off_requires_reason_and_posted_invoice(): void
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload($this->customer))->assertRedirect();

        $draft = SalesInvoice::query()->where('company_id', 1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('receivable-write-off.store', $draft->id), [
            'amount' => 100,
            'reason' => 'Testing',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $draft->fresh()->write_off_amount);
        $this->assertSame(0, Journal::query()->where('source_type', 'write_off')->count());
    }

    // ───────────────────────── Reports ─────────────────────────

    public function test_outstanding_and_aging_rank_invoices_by_bucket(): void
    {
        $this->createPostedInvoice($this->customer, [
            'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->createPostedInvoice($this->customer, [
            'invoice_date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(45)->toDateString(),
        ]);
        $this->createPostedInvoice($this->customer, [
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/receivables/outstanding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Outstanding')
                ->where('totals.balance_due', 3450)
                ->where('totals.count', 3)
                ->has('rows', 3));

        $this->actingAs($this->admin)
            ->get('/receivables/aging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Receivables/Aging')
                ->where('rows.0.customer_id', $this->customer->id)
                ->where('rows.0.current', 1150)
                ->where('rows.0.b1_30', 1150)
                ->where('rows.0.b31_60', 1150)
                ->where('rows.0.b90', 0)
                ->where('rows.0.total', 3450));
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_user_without_receivables_permission_is_blocked(): void
    {
        $user = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/receivables')->assertForbidden();
        $this->actingAs($user)->get('/receivables/outstanding')->assertForbidden();
    }

    public function test_accountant_permissions_allow_receivables_workflow(): void
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

        $this->actingAs($accountant)->get('/receivables')->assertOk();
        $this->actingAs($accountant)->get('/receivables/outstanding')->assertOk();
        $this->actingAs($accountant)->get('/receivables/aging')->assertOk();
        $this->actingAs($accountant)->get('/receivables/advances')->assertOk();

        $invoice = $this->createPostedInvoice($this->customer);

        // Record a multi-invoice payment.
        $this->actingAs($accountant)->post('/receivables/record-payment', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'allocations' => [
                ['sales_invoice_id' => $invoice->id, 'amount' => 300],
            ],
        ])->assertRedirect();

        // Post an advance and apply it.
        $this->actingAs($accountant)->post('/receivables/advances', [
            'customer_id' => $this->customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => 200,
        ])->assertRedirect();

        $advance = Receipt::query()->where('type', 'advance')->firstOrFail();
        $this->actingAs($accountant)
            ->post(route('advances.apply', $advance->id), [
                'allocations' => [
                    ['sales_invoice_id' => $invoice->id, 'amount' => 200],
                ],
            ])
            ->assertRedirect();

        // Write off the remaining balance.
        $invoice = $invoice->fresh();
        $this->actingAs($accountant)->post(route('receivable-write-off.store', $invoice->id), [
            'amount' => $invoice->balanceDue(),
            'reason' => 'Test write-off by accountant',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(0.0, $invoice->fresh()->balanceDue(), 0.0001);
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function createCustomer(string $code, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => 1,
            'code' => $code,
            'name' => $name,
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function invoicePayload(Customer $customer, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'reference' => 'PO-'.$customer->code,
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

    private function createPostedInvoice(Customer $customer, array $overrides = []): SalesInvoice
    {
        $this->actingAs($this->admin)->post('/sales/invoices', $this->invoicePayload($customer, $overrides))->assertRedirect();

        $invoice = SalesInvoice::query()
            ->where('company_id', 1)
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        return $invoice->fresh();
    }

    private function createPostedAdvance(Customer $customer, float $amount): Receipt
    {
        $this->actingAs($this->admin)->post('/receivables/advances', [
            'customer_id' => $customer->id,
            'receipt_date' => now()->toDateString(),
            'account_id' => $this->cashAccountId,
            'amount' => $amount,
        ])->assertRedirect();

        return Receipt::query()->where('type', 'advance')->latest('id')->firstOrFail();
    }
}