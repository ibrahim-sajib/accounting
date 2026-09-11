<?php

namespace App\Domain\Product\Http\Controllers;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Product\Http\Requests\ProductCategoryRequest;
use App\Domain\Product\Http\Requests\ProductRequest;
use App\Domain\Product\Http\Requests\UnitRequest;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Unit;
use App\Domain\Tax\Models\TaxRate;
use App\Support\Enums\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $products = Product::query()
            ->where('company_id', $companyId)
            ->with(['category:id,name', 'unit:id,name,symbol', 'taxRate:id,name,rate_percent'])
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search']),
            'categories' => ProductCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Create', [
            'defaults' => $this->defaultAccounts(),
            'accountOptions' => $this->accountOptions(),
            'taxRateOptions' => $this->taxRateOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'unitOptions' => $this->unitOptions(),
            'typeOptions' => enum_options(ProductType::class),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['type'] = $validated['type'] ?? ProductType::Product->value;

        $product = Product::query()->create($validated + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('product', 'create', null, $product->id, [], $product->toArray(), $product->company_id);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'defaults' => $this->defaultAccounts(),
            'accountOptions' => $this->accountOptions(),
            'taxRateOptions' => $this->taxRateOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'unitOptions' => $this->unitOptions(),
            'typeOptions' => enum_options(ProductType::class),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $old = $product->toArray();
        $product->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('product', 'update', null, $product->id, $old, $product->toArray(), $product->company_id);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        AuditLogger::log('product', 'delete', null, $product->id, $product->toArray(), [], $product->company_id);

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    // ─────────────── Product categories ───────────────

    public function storeCategory(ProductCategoryRequest $request): RedirectResponse
    {
        $category = ProductCategory::query()->create($request->validated() + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('product_category', 'create', null, $category->id, [], $category->toArray(), $category->company_id);

        return redirect()->route('products.index')->with('success', 'Category created.');
    }

    public function updateCategory(ProductCategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        $old = $category->toArray();
        $category->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('product_category', 'update', null, $category->id, $old, $category->toArray(), $category->company_id);

        return redirect()->route('products.index')->with('success', 'Category updated.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'This category is used by products and cannot be deleted.');
        }

        $category->delete();

        AuditLogger::log('product_category', 'delete', null, $category->id, $category->toArray(), [], $category->company_id);

        return redirect()->route('products.index')->with('success', 'Category deleted.');
    }

    // ─────────────── Units ───────────────

    public function storeUnit(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::query()->create($request->validated() + [
            'company_id' => current_company_id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLogger::log('unit', 'create', null, $unit->id, [], $unit->toArray(), $unit->company_id);

        return redirect()->route('products.index')->with('success', 'Unit created.');
    }

    public function updateUnit(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $old = $unit->toArray();
        $unit->update($request->validated() + ['updated_by' => auth()->id()]);

        AuditLogger::log('unit', 'update', null, $unit->id, $old, $unit->toArray(), $unit->company_id);

        return redirect()->route('products.index')->with('success', 'Unit updated.');
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        if (Product::query()->where('company_id', current_company_id())->where('unit_id', $unit->id)->exists()) {
            return back()->with('error', 'This unit is used by products and cannot be deleted.');
        }

        $unit->delete();

        AuditLogger::log('unit', 'delete', null, $unit->id, $unit->toArray(), [], $unit->company_id);

        return redirect()->route('products.index')->with('success', 'Unit deleted.');
    }

    protected function defaultAccounts(): array
    {
        $setting = AccountingSetting::query()->where('company_id', current_company_id())->first();

        return [
            'inventory' => $setting?->default_inventory_account_id,
            'sales' => $setting?->default_sales_account_id,
            'purchase' => $setting?->default_purchase_account_id,
            'cogs' => Account::query()
                ->where('company_id', current_company_id())
                ->where('code', '5221')
                ->value('id'),
        ];
    }

    protected function accountOptions(): array
    {
        return Account::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($account) => ['value' => $account->id, 'label' => "{$account->code} — {$account->name}"])
            ->all();
    }

    protected function taxRateOptions(): array
    {
        return TaxRate::query()
            ->where('company_id', current_company_id())
            ->orderBy('name')
            ->get(['id', 'name', 'rate_percent'])
            ->map(fn ($rate) => ['value' => $rate->id, 'label' => "{$rate->name} ({$rate->rate_percent}%)"])
            ->all();
    }

    protected function categoryOptions(): array
    {
        return ProductCategory::query()
            ->where('company_id', current_company_id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($category) => ['value' => $category->id, 'label' => $category->name])
            ->all();
    }

    protected function unitOptions(): array
    {
        return Unit::query()
            ->where('company_id', current_company_id())
            ->orderBy('name')
            ->get(['id', 'name', 'symbol'])
            ->map(fn ($unit) => ['value' => $unit->id, 'label' => $unit->symbol ? "{$unit->name} ({$unit->symbol})" : $unit->name])
            ->all();
    }
}