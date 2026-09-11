<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Party\Models\Customer;
use App\Domain\Party\Models\Supplier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Unit;
use App\Domain\Warehouse\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
    }

    // ───────────────────────── Master data seeding ─────────────────────────

    public function test_master_data_seeded_for_company(): void
    {
        $this->assertTrue(ProductCategory::query()->where('company_id', 1)->where('name', 'Goods')->exists());
        $this->assertTrue(ProductCategory::query()->where('company_id', 1)->where('name', 'Services')->exists());
        $this->assertTrue(Unit::query()->where('company_id', 1)->where('name', 'Piece')->exists());
        $this->assertTrue(Unit::query()->where('company_id', 1)->where('name', 'Kilogram')->exists());
    }

    // ───────────────────────── Customers ─────────────────────────

    public function test_super_admin_can_view_customers(): void
    {
        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Index')
                ->has('customers.data')
                ->has('customers.total'));
    }

    public function test_customer_can_be_created_with_code_and_name(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'code' => 'C-0001',
            'name' => 'Acme Traders',
            'email' => 'acme@example.com',
            'phone' => '01700000000',
            'credit_limit' => 100000,
            'payment_terms_days' => 30,
            'is_active' => true,
        ])->assertRedirect(route('customers.index'));

        $customer = Customer::query()->where('company_id', 1)->where('code', 'C-0001')->firstOrFail();

        $this->assertSame('Acme Traders', $customer->name);
        $this->assertSame(1, $customer->company_id);
    }

    public function test_customer_code_unique_per_company(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'code' => 'C-0002', 'name' => 'First Buyer',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/customers', [
            'code' => 'C-0002', 'name' => 'Duplicate Buyer',
        ])->assertSessionHasErrors('code');

        $this->assertSame(1, Customer::query()->where('code', 'C-0002')->count());
    }

    public function test_customer_can_be_updated_and_deleted(): void
    {
        $customer = Customer::query()->create([
            'company_id' => 1, 'code' => 'C-0003', 'name' => 'To Be Updated',
        ]);

        $this->actingAs($this->admin)->put("/customers/{$customer->id}", [
            'code' => 'C-0003', 'name' => 'Updated Name', 'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Updated Name', $customer->fresh()->name);

        $this->actingAs($this->admin)->delete("/customers/{$customer->id}")->assertRedirect();

        $this->assertNotNull($customer->fresh()->deleted_at);
    }

    public function test_empty_numeric_fields_do_not_break_customer_create(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'code' => 'C-0004',
            'name' => 'Empty Numeric Buyer',
            'credit_limit' => '',
            'payment_terms_days' => '',
            'opening_balance' => '',
            'is_active' => true,
        ])->assertRedirect(route('customers.index'));

        $customer = Customer::query()->where('company_id', 1)->where('code', 'C-0004')->firstOrFail();

        $this->assertSame(0, (int) $customer->credit_limit);
        $this->assertSame(0, (int) $customer->payment_terms_days);
        $this->assertSame(0, (int) $customer->opening_balance);
    }

    // ───────────────────────── Suppliers ─────────────────────────

    public function test_super_admin_can_view_suppliers(): void
    {
        $this->actingAs($this->admin)
            ->get('/suppliers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suppliers/Index')
                ->has('suppliers.data'));
    }

    public function test_supplier_can_be_created(): void
    {
        $this->actingAs($this->admin)->post('/suppliers', [
            'code' => 'S-0001',
            'name' => 'Global Parts Ltd',
            'payment_terms_days' => 45,
            'opening_balance' => 5000,
            'is_active' => true,
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::query()->where('company_id', 1)->where('code', 'S-0001')->firstOrFail();

        $this->assertSame('Global Parts Ltd', $supplier->name);
        $this->assertSame(45, $supplier->payment_terms_days);
    }

    // ───────────────────────── Products & Services ─────────────────────────

    public function test_super_admin_can_view_products(): void
    {
        $this->actingAs($this->admin)
            ->get('/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Index')
                ->has('products.data')
                ->has('categories')
                ->has('units'));
    }

    public function test_product_can_be_created(): void
    {
        $category = ProductCategory::query()->where('company_id', 1)->where('name', 'Goods')->firstOrFail();
        $unit = Unit::query()->where('company_id', 1)->where('name', 'Piece')->firstOrFail();

        $this->actingAs($this->admin)->post('/products', [
            'sku' => 'SKU-TEST-1',
            'name' => 'Widget A',
            'type' => 'product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'sales_price' => 150,
            'track_inventory' => true,
            'is_active' => true,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('company_id', 1)->where('sku', 'SKU-TEST-1')->firstOrFail();

        $this->assertSame('product', $product->type);
        $this->assertSame($category->id, $product->category_id);
        $this->assertTrue($product->track_inventory);
    }

    public function test_product_type_must_be_product_or_service(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'sku' => 'SKU-BAD-TYPE',
            'name' => 'Bad Type Item',
            'type' => 'crypto',
        ])->assertSessionHasErrors('type');

        $this->assertSame(0, Product::query()->where('sku', 'SKU-BAD-TYPE')->count());
    }

    public function test_empty_price_fields_do_not_break_product_create(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'sku' => 'SKU-EMPTY-PRICE',
            'name' => 'Zero Price Item',
            'type' => 'product',
            'purchase_price' => '',
            'sales_price' => '',
            'is_active' => true,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('company_id', 1)->where('sku', 'SKU-EMPTY-PRICE')->firstOrFail();

        $this->assertSame(0, (int) $product->purchase_price);
        $this->assertSame(0, (int) $product->sales_price);
    }

    public function test_product_category_crud_via_http(): void
    {
        $this->actingAs($this->admin)->post('/product-categories', [
            'name' => 'Electronics',
        ])->assertRedirect(route('products.index'));

        $category = ProductCategory::query()->where('company_id', 1)->where('name', 'Electronics')->firstOrFail();

        $this->actingAs($this->admin)->put("/product-categories/{$category->id}", [
            'name' => 'Consumer Electronics',
        ])->assertRedirect();

        $this->assertSame('Consumer Electronics', $category->fresh()->name);

        $this->actingAs($this->admin)->delete("/product-categories/{$category->id}")->assertRedirect();

        $this->assertNotNull($category->fresh()->deleted_at);
    }

    public function test_unit_crud_via_http(): void
    {
        $this->actingAs($this->admin)->post('/units', [
            'name' => 'Dozen', 'symbol' => 'dz',
        ])->assertRedirect(route('products.index'));

        $unit = Unit::query()->where('company_id', 1)->where('name', 'Dozen')->firstOrFail();

        $this->actingAs($this->admin)->put("/units/{$unit->id}", [
            'name' => 'Dozen (12)', 'symbol' => 'dz',
        ])->assertRedirect();

        $this->assertSame('Dozen (12)', $unit->fresh()->name);
    }

    // ───────────────────────── Warehouses ─────────────────────────

    public function test_super_admin_can_view_warehouses(): void
    {
        $this->actingAs($this->admin)
            ->get('/warehouses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Warehouses/Index')
                ->has('warehouses.data'));
    }

    public function test_warehouse_can_be_created(): void
    {
        $this->actingAs($this->admin)->post('/warehouses', [
            'code' => 'WH-01',
            'name' => 'Main Warehouse',
            'address' => 'Dhaka',
            'is_active' => true,
        ])->assertRedirect(route('warehouses.index'));

        $warehouse = Warehouse::query()->where('company_id', 1)->where('code', 'WH-01')->firstOrFail();

        $this->assertSame('Main Warehouse', $warehouse->name);
    }

    public function test_warehouse_code_unique_per_company(): void
    {
        $this->actingAs($this->admin)->post('/warehouses', [
            'code' => 'WH-02', 'name' => 'First',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/warehouses', [
            'code' => 'WH-02', 'name' => 'Duplicate',
        ])->assertSessionHasErrors('code');
    }

    // ───────────────────────── Provisioning the next company ─────────────────────────

    public function test_new_company_is_provisioned_with_master_data(): void
    {
        $this->actingAs($this->admin)->post('/companies', [
            'name' => 'Phase Three Retail',
            'legal_name' => 'Phase Three Retail Ltd',
            'country_code' => 'BD',
            'currency_code' => 'BDT',
            'accounting_basis' => 'accrual',
            'status' => 'active',
        ])->assertRedirect(route('companies.index'));

        $second = Company::query()->where('name', 'Phase Three Retail')->firstOrFail();

        $this->assertTrue(ProductCategory::query()->where('company_id', $second->id)->where('name', 'Goods')->exists());
        $this->assertTrue(Unit::query()->where('company_id', $second->id)->where('name', 'Piece')->exists());
    }

    // ───────────────────────── Authorization ─────────────────────────

    public function test_non_super_admin_without_permission_is_blocked_from_master_data(): void
    {
        $manager = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($manager)->get('/customers')->assertForbidden();
        $this->actingAs($manager)->get('/suppliers')->assertForbidden();
        $this->actingAs($manager)->get('/products')->assertForbidden();
        $this->actingAs($manager)->get('/warehouses')->assertForbidden();
    }
}