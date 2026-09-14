<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Party\Models\Customer;
use App\Domain\Party\Models\Supplier;
use App\Domain\Product\Models\Product;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the tenant route-model binding bug: the create →
 * redirect → show flow must render the freshly created draft (HTTP 200),
 * never a 404. In Docker, SubstituteBindings used to resolve bound models
 * on the control-plane connection BEFORE SelectTenantDatabase switched the
 * default connection to the active company's tenant database, so every
 * `SalesInvoice $invoice` / `PurchaseBill $bill` route 404'd right after
 * creation ("create hoyna — Not Found").
 */
class SalesPurchaseShowRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Supplier $supplier;

    private Product $product;

    private int $arAccountId;

    private int $apAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();
        $this->arAccountId = $setting->default_ar_account_id;
        $this->apAccountId = $setting->default_ap_account_id;

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-SR',
            'name' => 'Show Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->supplier = Supplier::query()->create([
            'company_id' => 1,
            'code' => 'SUP-SR',
            'name' => 'Show Vendor',
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'SR-SKU',
            'name' => 'Show Widget',
            'type' => 'product',
            'purchase_price' => 100,
            'sales_price' => 150,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function sales_invoice_is_created_then_its_show_page_renders()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/sales/invoices', [
            'customer_id' => $this->customer->id,
            'ar_account_id' => $this->arAccountId,
            'invoice_date' => now()->toDateString(),
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => '2',
                    'unit_price' => '150',
                    'tax_rate_id' => null,
                ],
            ],
        ])->assertRedirect();

        $invoice = SalesInvoice::query()->firstOrFail();

        $this->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/Show')
                ->has('invoice')
                ->where('invoice.id', $invoice->id)
                ->where('invoice.status', 'draft'));
    }

    /** @test */
    public function purchase_bill_is_created_then_its_show_page_renders()
    {
        $this->actingAs($this->admin);

        $this->post('/purchase/bills', [
            'supplier_id' => $this->supplier->id,
            'ap_account_id' => $this->apAccountId,
            'bill_date' => now()->toDateString(),
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => '5',
                    'unit_cost' => '100',
                    'tax_rate_id' => null,
                ],
            ],
        ])->assertRedirect();

        $bill = PurchaseBill::query()->firstOrFail();

        $this->get(route('purchase.bills.show', $bill))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchase/Bills/Show')
                ->has('bill')
                ->where('bill.id', $bill->id)
                ->where('bill.status', 'draft'));
    }

    /** @test */
    public function create_pages_render_with_master_data_options()
    {
        $this->actingAs($this->admin);

        $this->get('/sales/invoices/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/Create')
                ->has('customers')
                ->has('products')
                ->has('taxRates'));

        $this->get('/purchase/bills/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Purchase/Bills/Create')
                ->has('suppliers')
                ->has('products')
                ->has('taxRates'));
    }
}