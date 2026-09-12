<?php

use App\Domain\Accounting\Http\Controllers\AccountingPeriodController;
use App\Domain\Accounting\Http\Controllers\AccountingSettingController;
use App\Domain\Accounting\Http\Controllers\AccountController;
use App\Domain\Accounting\Http\Controllers\FiscalYearController;
use App\Domain\Accounting\Http\Controllers\JournalController;
use App\Domain\Accounting\Http\Controllers\OpeningBalanceController;
use App\Domain\CashBank\Http\Controllers\BankReconciliationController;
use App\Domain\CashBank\Http\Controllers\CashBankAccountController;
use App\Domain\CashBank\Http\Controllers\CashBankController;
use App\Domain\CashBank\Http\Controllers\CashBankTransactionController;
use App\Domain\Company\Http\Controllers\BranchController;
use App\Domain\Company\Http\Controllers\CompanyController;
use App\Domain\Currency\Http\Controllers\CurrencyController;
use App\Domain\Expense\Http\Controllers\ExpenseCategoryController;
use App\Domain\Expense\Http\Controllers\ExpenseController;
use App\Domain\FixedAsset\Http\Controllers\AssetCategoryController;
use App\Domain\FixedAsset\Http\Controllers\FixedAssetController;
use App\Domain\Payroll\Http\Controllers\PayrollRunController;
use App\Domain\Payroll\Http\Controllers\EmployeeController;
use App\Domain\Payroll\Http\Controllers\DepartmentController;
use App\Domain\Payroll\Http\Controllers\DesignationController;
use App\Domain\Inventory\Http\Controllers\StockAdjustmentController;
use App\Domain\Inventory\Http\Controllers\StockController;
use App\Domain\Inventory\Http\Controllers\StockTransferController;
use App\Domain\Party\Http\Controllers\CustomerController;
use App\Domain\Party\Http\Controllers\SupplierController;
use App\Domain\Payables\Http\Controllers\PayableController;
use App\Domain\Product\Http\Controllers\ProductController;
use App\Domain\Rbac\Http\Controllers\RoleController;
use App\Domain\Rbac\Http\Controllers\UserController;
use App\Domain\Receivables\Http\Controllers\ReceivableController;
use App\Domain\Sales\Http\Controllers\SalesInvoiceController;
use App\Domain\Purchase\Http\Controllers\PurchaseBillController;
use App\Domain\Settings\Http\Controllers\SystemSettingController;
use App\Domain\Tax\Http\Controllers\TaxTypeController;
use App\Domain\Warehouse\Http\Controllers\WarehouseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('permission:company.switch')->post('/companies/switch', [CompanyController::class, 'switch'])
        ->name('companies.switch');

    Route::middleware('permission:company.view')->get('/companies', [CompanyController::class, 'index'])
        ->name('companies.index');
    Route::middleware('permission:company.create')->get('/companies/create', [CompanyController::class, 'create'])
        ->name('companies.create');
    Route::middleware('permission:company.create')->post('/companies', [CompanyController::class, 'store'])
        ->name('companies.store');
    Route::middleware('permission:company.update')->get('/companies/{company}/edit', [CompanyController::class, 'edit'])
        ->name('companies.edit');
    Route::middleware('permission:company.update')->put('/companies/{company}', [CompanyController::class, 'update'])
        ->name('companies.update');
    Route::middleware('permission:company.delete')->delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->name('companies.destroy');

    Route::middleware('permission:branch.view')->get('/branches', [BranchController::class, 'index'])
        ->name('branches.index');
    Route::middleware('permission:branch.create')->get('/branches/create', [BranchController::class, 'create'])
        ->name('branches.create');
    Route::middleware('permission:branch.create')->post('/branches', [BranchController::class, 'store'])
        ->name('branches.store');
    Route::middleware('permission:branch.update')->get('/branches/{branch}/edit', [BranchController::class, 'edit'])
        ->name('branches.edit');
    Route::middleware('permission:branch.update')->put('/branches/{branch}', [BranchController::class, 'update'])
        ->name('branches.update');
    Route::middleware('permission:branch.delete')->delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->name('branches.destroy');

    Route::middleware('permission:role.view')->get('/roles', [RoleController::class, 'index'])
        ->name('roles.index');
    Route::middleware('permission:role.create')->get('/roles/create', [RoleController::class, 'create'])
        ->name('roles.create');
    Route::middleware('permission:role.create')->post('/roles', [RoleController::class, 'store'])
        ->name('roles.store');
    Route::middleware('permission:role.update')->get('/roles/{role}/edit', [RoleController::class, 'edit'])
        ->name('roles.edit');
    Route::middleware('permission:role.update')->put('/roles/{role}', [RoleController::class, 'update'])
        ->name('roles.update');
    Route::middleware('permission:role.delete')->delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->name('roles.destroy');

    Route::middleware('permission:user.view')->get('/users', [UserController::class, 'index'])
        ->name('users.index');
    Route::middleware('permission:user.create')->get('/users/create', [UserController::class, 'create'])
        ->name('users.create');
    Route::middleware('permission:user.create')->post('/users', [UserController::class, 'store'])
        ->name('users.store');
    Route::middleware('permission:user.update')->get('/users/{user}/edit', [UserController::class, 'edit'])
        ->name('users.edit');
    Route::middleware('permission:user.update')->put('/users/{user}', [UserController::class, 'update'])
        ->name('users.update');
    Route::middleware('permission:user.delete')->delete('/users/{user}', [UserController::class, 'destroy'])
        ->name('users.destroy');

    Route::middleware('permission:fiscal_year.view')->get('/fiscal-years', [FiscalYearController::class, 'index'])
        ->name('fiscal-years.index');
    Route::middleware('permission:fiscal_year.create')->get('/fiscal-years/create', [FiscalYearController::class, 'create'])
        ->name('fiscal-years.create');
    Route::middleware('permission:fiscal_year.create')->post('/fiscal-years', [FiscalYearController::class, 'store'])
        ->name('fiscal-years.store');
    Route::middleware('permission:fiscal_year.update')->get('/fiscal-years/{fiscal_year}/edit', [FiscalYearController::class, 'edit'])
        ->name('fiscal-years.edit');
    Route::middleware('permission:fiscal_year.update')->put('/fiscal-years/{fiscal_year}', [FiscalYearController::class, 'update'])
        ->name('fiscal-years.update');
    Route::middleware('permission:fiscal_year.close')->post('/fiscal-years/{fiscal_year}/close', [FiscalYearController::class, 'close'])
        ->name('fiscal-years.close');
    Route::middleware('permission:fiscal_year.reopen')->post('/fiscal-years/{fiscal_year}/reopen', [FiscalYearController::class, 'reopen'])
        ->name('fiscal-years.reopen');
    Route::middleware('permission:fiscal_year.delete')->delete('/fiscal-years/{fiscal_year}', [FiscalYearController::class, 'destroy'])
        ->name('fiscal-years.destroy');

    Route::middleware('permission:period.view')->get('/accounting-periods', [AccountingPeriodController::class, 'index'])
        ->name('accounting-periods.index');
    Route::middleware('permission:period.create')->post('/accounting-periods', [AccountingPeriodController::class, 'store'])
        ->name('accounting-periods.store');
    Route::middleware('permission:period.update')->put('/accounting-periods/{period}', [AccountingPeriodController::class, 'update'])
        ->name('accounting-periods.update');
    Route::middleware('permission:period.close')->post('/accounting-periods/{period}/close', [AccountingPeriodController::class, 'close'])
        ->name('accounting-periods.close');
    Route::middleware('permission:period.reopen')->post('/accounting-periods/{period}/reopen', [AccountingPeriodController::class, 'reopen'])
        ->name('accounting-periods.reopen');
    Route::middleware('permission:period.lock')->post('/accounting-periods/{period}/lock', [AccountingPeriodController::class, 'lock'])
        ->name('accounting-periods.lock');
    Route::middleware('permission:period.update')->post('/accounting-periods/{period}/set-active', [AccountingPeriodController::class, 'setActive'])
        ->name('accounting-periods.set-active');
    Route::middleware('permission:period.delete')->delete('/accounting-periods/{period}', [AccountingPeriodController::class, 'destroy'])
        ->name('accounting-periods.destroy');

    Route::middleware('permission:currency.view')->get('/currencies', [CurrencyController::class, 'index'])
        ->name('currencies.index');
    Route::middleware('permission:currency.create')->post('/currencies', [CurrencyController::class, 'store'])
        ->name('currencies.store');
    Route::middleware('permission:currency.update')->put('/currencies/{currency}', [CurrencyController::class, 'update'])
        ->name('currencies.update');
    Route::middleware('permission:currency.delete')->delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])
        ->name('currencies.destroy');
    Route::middleware('permission:exchange_rate.create')->post('/currencies/{currency}/rates', [CurrencyController::class, 'storeRate'])
        ->name('currencies.rates.store');
    Route::middleware('permission:exchange_rate.delete')->delete('/exchange-rates/{rate}', [CurrencyController::class, 'destroyRate'])
        ->name('exchange-rates.destroy');

    Route::middleware('permission:settings.view')->get('/settings', [SystemSettingController::class, 'index'])
        ->name('settings.index');
    Route::middleware('permission:settings.update')->put('/settings', [SystemSettingController::class, 'update'])
        ->name('settings.update');

    // ── Chart of Accounts ──
    Route::middleware('permission:account.view')->get('/accounts', [AccountController::class, 'index'])
        ->name('accounts.index');
    Route::middleware('permission:account.create')->get('/accounts/create', [AccountController::class, 'create'])
        ->name('accounts.create');
    Route::middleware('permission:account.create')->post('/accounts', [AccountController::class, 'store'])
        ->name('accounts.store');
    Route::middleware('permission:account.update')->get('/accounts/{account}/edit', [AccountController::class, 'edit'])
        ->name('accounts.edit');
    Route::middleware('permission:account.update')->put('/accounts/{account}', [AccountController::class, 'update'])
        ->name('accounts.update');
    Route::middleware('permission:account.delete')->delete('/accounts/{account}', [AccountController::class, 'destroy'])
        ->name('accounts.destroy');

    // ── Tax / VAT ──
    Route::middleware('permission:tax.view')->get('/tax', [TaxTypeController::class, 'index'])
        ->name('tax.index');
    Route::middleware('permission:tax.create')->post('/tax', [TaxTypeController::class, 'store'])
        ->name('tax.store');
    Route::middleware('permission:tax.update')->put('/tax/{taxType}', [TaxTypeController::class, 'update'])
        ->name('tax.update');
    Route::middleware('permission:tax.delete')->delete('/tax/{taxType}', [TaxTypeController::class, 'destroy'])
        ->name('tax.destroy');
    Route::middleware('permission:tax.create')->post('/tax/{taxType}/rates', [TaxTypeController::class, 'storeRate'])
        ->name('tax.rates.store');
    Route::middleware('permission:tax.update')->put('/tax/rates/{rate}', [TaxTypeController::class, 'updateRate'])
        ->name('tax.rates.update');
    Route::middleware('permission:tax.delete')->delete('/tax/rates/{rate}', [TaxTypeController::class, 'destroyRate'])
        ->name('tax.rates.destroy');

    // ── Accounting Configuration ──
    Route::middleware('permission:accounting_config.view')->get('/accounting-settings', [AccountingSettingController::class, 'index'])
        ->name('accounting-settings.index');
    Route::middleware('permission:accounting_config.update')->put('/accounting-settings', [AccountingSettingController::class, 'update'])
        ->name('accounting-settings.update');

    // ── Journals (General Journal) ──
    Route::middleware('permission:journal.view')->get('/journals', [JournalController::class, 'index'])
        ->name('journals.index');
    Route::middleware('permission:journal.create')->get('/journals/create', [JournalController::class, 'create'])
        ->name('journals.create');
    Route::middleware('permission:journal.create')->post('/journals', [JournalController::class, 'store'])
        ->name('journals.store');
    Route::middleware('permission:journal.view')->get('/journals/{journal}', [JournalController::class, 'show'])
        ->name('journals.show');
    Route::middleware('permission:journal.update')->get('/journals/{journal}/edit', [JournalController::class, 'edit'])
        ->name('journals.edit');
    Route::middleware('permission:journal.update')->put('/journals/{journal}', [JournalController::class, 'update'])
        ->name('journals.update');
    Route::middleware('permission:journal.post')->post('/journals/{journal}/post', [JournalController::class, 'post'])
        ->name('journals.post');
    Route::middleware('permission:journal.post')->post('/journals/{journal}/reverse', [JournalController::class, 'reverse'])
        ->name('journals.reverse');
    Route::middleware('permission:journal.delete')->delete('/journals/{journal}', [JournalController::class, 'destroy'])
        ->name('journals.destroy');

    // ── Opening Balances ──
    Route::middleware('permission:opening_balance.view')->get('/opening-balances', [OpeningBalanceController::class, 'index'])
        ->name('opening-balances.index');
    Route::middleware('permission:opening_balance.create')->get('/opening-balances/{fiscal_year}/entry', [OpeningBalanceController::class, 'entry'])
        ->name('opening-balances.entry');
    Route::middleware('permission:opening_balance.create')->post('/opening-balances/{fiscal_year}/save', [OpeningBalanceController::class, 'save'])
        ->name('opening-balances.save');
    Route::middleware('permission:opening_balance.post')->post('/opening-balances/{fiscal_year}/post', [OpeningBalanceController::class, 'post'])
        ->name('opening-balances.post');

    // ── Customers ──
    Route::middleware('permission:customer.view')->get('/customers', [CustomerController::class, 'index'])
        ->name('customers.index');
    Route::middleware('permission:customer.create')->get('/customers/create', [CustomerController::class, 'create'])
        ->name('customers.create');
    Route::middleware('permission:customer.create')->post('/customers', [CustomerController::class, 'store'])
        ->name('customers.store');
    Route::middleware('permission:customer.update')->get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->name('customers.edit');
    Route::middleware('permission:customer.update')->put('/customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');
    Route::middleware('permission:customer.delete')->delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('customers.destroy');

    // ── Suppliers ──
    Route::middleware('permission:supplier.view')->get('/suppliers', [SupplierController::class, 'index'])
        ->name('suppliers.index');
    Route::middleware('permission:supplier.create')->get('/suppliers/create', [SupplierController::class, 'create'])
        ->name('suppliers.create');
    Route::middleware('permission:supplier.create')->post('/suppliers', [SupplierController::class, 'store'])
        ->name('suppliers.store');
    Route::middleware('permission:supplier.update')->get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->name('suppliers.edit');
    Route::middleware('permission:supplier.update')->put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('suppliers.update');
    Route::middleware('permission:supplier.delete')->delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->name('suppliers.destroy');

    // ── Products / Services ──
    Route::middleware('permission:product.view')->get('/products', [ProductController::class, 'index'])
        ->name('products.index');
    Route::middleware('permission:product.create')->get('/products/create', [ProductController::class, 'create'])
        ->name('products.create');
    Route::middleware('permission:product.create')->post('/products', [ProductController::class, 'store'])
        ->name('products.store');
    Route::middleware('permission:product.update')->get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->name('products.edit');
    Route::middleware('permission:product.update')->put('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update');
    Route::middleware('permission:product.delete')->delete('/products/{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');
    // Categories
    Route::middleware('permission:product.create')->post('/product-categories', [ProductController::class, 'storeCategory'])
        ->name('product-categories.store');
    Route::middleware('permission:product.update')->put('/product-categories/{category}', [ProductController::class, 'updateCategory'])
        ->name('product-categories.update');
    Route::middleware('permission:product.delete')->delete('/product-categories/{category}', [ProductController::class, 'destroyCategory'])
        ->name('product-categories.destroy');
    // Units
    Route::middleware('permission:product.create')->post('/units', [ProductController::class, 'storeUnit'])
        ->name('units.store');
    Route::middleware('permission:product.update')->put('/units/{unit}', [ProductController::class, 'updateUnit'])
        ->name('units.update');
    Route::middleware('permission:product.delete')->delete('/units/{unit}', [ProductController::class, 'destroyUnit'])
        ->name('units.destroy');

    // ── Warehouses ──
    Route::middleware('permission:warehouse.view')->get('/warehouses', [WarehouseController::class, 'index'])
        ->name('warehouses.index');
    Route::middleware('permission:warehouse.create')->get('/warehouses/create', [WarehouseController::class, 'create'])
        ->name('warehouses.create');
    Route::middleware('permission:warehouse.create')->post('/warehouses', [WarehouseController::class, 'store'])
        ->name('warehouses.store');
    Route::middleware('permission:warehouse.update')->get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])
        ->name('warehouses.edit');
    Route::middleware('permission:warehouse.update')->put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])
        ->name('warehouses.update');
    Route::middleware('permission:warehouse.delete')->delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
        ->name('warehouses.destroy');

    // ── Sales Invoices ──
    Route::middleware('permission:sales.view')->get('/sales/invoices', [SalesInvoiceController::class, 'index'])
        ->name('sales.invoices.index');
    Route::middleware('permission:sales.create')->get('/sales/invoices/create', [SalesInvoiceController::class, 'create'])
        ->name('sales.invoices.create');
    Route::middleware('permission:sales.create')->post('/sales/invoices', [SalesInvoiceController::class, 'store'])
        ->name('sales.invoices.store');
    Route::middleware('permission:sales.view')->get('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'show'])
        ->name('sales.invoices.show');
    Route::middleware('permission:sales.update')->get('/sales/invoices/{invoice}/edit', [SalesInvoiceController::class, 'edit'])
        ->name('sales.invoices.edit');
    Route::middleware('permission:sales.update')->put('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'update'])
        ->name('sales.invoices.update');
    Route::middleware('permission:sales.post')->post('/sales/invoices/{invoice}/post', [SalesInvoiceController::class, 'post'])
        ->name('sales.invoices.post');
    Route::middleware('permission:receipt.post')->post('/sales/invoices/{invoice}/pay', [SalesInvoiceController::class, 'pay'])
        ->name('sales.invoices.pay');
    Route::middleware('permission:sales.delete')->delete('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'destroy'])
        ->name('sales.invoices.destroy');

    Route::middleware('permission:receivables.view')->get('/receivables', [ReceivableController::class, 'index'])
        ->name('receivables.index');
    Route::middleware('permission:receivables.view')->get('/receivables/outstanding', [ReceivableController::class, 'outstanding'])
        ->name('outstanding.index');
    Route::middleware('permission:receivables.view')->get('/receivables/aging', [ReceivableController::class, 'aging'])
        ->name('aging.index');

    Route::middleware('permission:receipt.post')->get('/receivables/record-payment', [ReceivableController::class, 'createPayment'])
        ->name('payment.index');
    Route::middleware('permission:receipt.post')->post('/receivables/record-payment', [ReceivableController::class, 'storePayment'])
        ->name('payment.store');

    Route::middleware('permission:receivables.view')->get('/receivables/advances', [ReceivableController::class, 'advances'])
        ->name('advances.index');
    Route::middleware('permission:receivables.advance')->post('/receivables/advances', [ReceivableController::class, 'storeAdvance'])
        ->name('advances.store');
    Route::middleware('permission:receivables.view')->get('/receivables/advances/{receipt}', [ReceivableController::class, 'showAdvance'])
        ->name('advances.show');
    Route::middleware('permission:receipt.post')->post('/receivables/advances/{receipt}/apply', [ReceivableController::class, 'storeAdvanceApplication'])
        ->name('advances.apply');

    Route::middleware('permission:receivables.write_off')->post('/receivables/invoices/{invoice}/write-off', [ReceivableController::class, 'writeOff'])
        ->name('receivable-write-off.store');

    // ── Purchase Bills ──
    Route::middleware('permission:purchase.view')->get('/purchase/bills', [PurchaseBillController::class, 'index'])
        ->name('purchase.bills.index');
    Route::middleware('permission:purchase.create')->get('/purchase/bills/create', [PurchaseBillController::class, 'create'])
        ->name('purchase.bills.create');
    Route::middleware('permission:purchase.create')->post('/purchase/bills', [PurchaseBillController::class, 'store'])
        ->name('purchase.bills.store');
    Route::middleware('permission:purchase.view')->get('/purchase/bills/{bill}', [PurchaseBillController::class, 'show'])
        ->name('purchase.bills.show');
    Route::middleware('permission:purchase.update')->get('/purchase/bills/{bill}/edit', [PurchaseBillController::class, 'edit'])
        ->name('purchase.bills.edit');
    Route::middleware('permission:purchase.update')->put('/purchase/bills/{bill}', [PurchaseBillController::class, 'update'])
        ->name('purchase.bills.update');
    Route::middleware('permission:purchase.post')->post('/purchase/bills/{bill}/post', [PurchaseBillController::class, 'post'])
        ->name('purchase.bills.post');
    Route::middleware('permission:payment.post')->post('/purchase/bills/{bill}/pay', [PurchaseBillController::class, 'pay'])
        ->name('purchase.bills.pay');
    Route::middleware('permission:purchase.delete')->delete('/purchase/bills/{bill}', [PurchaseBillController::class, 'destroy'])
        ->name('purchase.bills.destroy');

    // ── Payables (AP workflow) ──
    Route::middleware('permission:payables.view')->get('/payables', [PayableController::class, 'index'])
        ->name('payables.index');
    Route::middleware('permission:payables.view')->get('/payables/outstanding', [PayableController::class, 'outstanding'])
        ->name('payable-outstanding.index');
    Route::middleware('permission:payables.view')->get('/payables/aging', [PayableController::class, 'aging'])
        ->name('payable-aging.index');

    Route::middleware('permission:payment.post')->get('/payables/record-payment', [PayableController::class, 'createPayment'])
        ->name('supplier-payment.index');
    Route::middleware('permission:payment.post')->post('/payables/record-payment', [PayableController::class, 'storePayment'])
        ->name('supplier-payment.store');

    Route::middleware('permission:payables.view')->get('/payables/advances', [PayableController::class, 'advances'])
        ->name('supplier-advances.index');
    Route::middleware('permission:payables.advance')->post('/payables/advances', [PayableController::class, 'storeAdvance'])
        ->name('supplier-advances.store');
    Route::middleware('permission:payables.view')->get('/payables/advances/{payment}', [PayableController::class, 'showAdvance'])
        ->name('supplier-advances.show');
    Route::middleware('permission:payment.post')->post('/payables/advances/{payment}/apply', [PayableController::class, 'storeAdvanceApplication'])
        ->name('supplier-advances.apply');

    // ── Inventory: Stock view ──
    Route::middleware('permission:inventory.view')->get('/inventory/stock', [StockController::class, 'index'])
        ->name('stock.index');

    // ── Inventory: Stock Adjustments ──
    Route::middleware('permission:inventory.view')->get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])
        ->name('stock-adjustments.index');
    Route::middleware('permission:inventory.create')->get('/inventory/adjustments/create', [StockAdjustmentController::class, 'create'])
        ->name('stock-adjustments.create');
    Route::middleware('permission:inventory.create')->post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])
        ->name('stock-adjustments.store');
    Route::middleware('permission:inventory.view')->get('/inventory/adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])
        ->name('stock-adjustments.show');
    Route::middleware('permission:inventory.update')->get('/inventory/adjustments/{adjustment}/edit', [StockAdjustmentController::class, 'edit'])
        ->name('stock-adjustments.edit');
    Route::middleware('permission:inventory.update')->put('/inventory/adjustments/{adjustment}', [StockAdjustmentController::class, 'update'])
        ->name('stock-adjustments.update');
    Route::middleware('permission:inventory.adjust')->post('/inventory/adjustments/{adjustment}/post', [StockAdjustmentController::class, 'post'])
        ->name('stock-adjustments.post');
    Route::middleware('permission:inventory.delete')->delete('/inventory/adjustments/{adjustment}', [StockAdjustmentController::class, 'destroy'])
        ->name('stock-adjustments.destroy');

    // ── Inventory: Stock Transfers ──
    Route::middleware('permission:inventory.view')->get('/inventory/transfers', [StockTransferController::class, 'index'])
        ->name('stock-transfers.index');
    Route::middleware('permission:inventory.create')->get('/inventory/transfers/create', [StockTransferController::class, 'create'])
        ->name('stock-transfers.create');
    Route::middleware('permission:inventory.create')->post('/inventory/transfers', [StockTransferController::class, 'store'])
        ->name('stock-transfers.store');
    Route::middleware('permission:inventory.view')->get('/inventory/transfers/{transfer}', [StockTransferController::class, 'show'])
        ->name('stock-transfers.show');
    Route::middleware('permission:inventory.update')->get('/inventory/transfers/{transfer}/edit', [StockTransferController::class, 'edit'])
        ->name('stock-transfers.edit');
    Route::middleware('permission:inventory.update')->put('/inventory/transfers/{transfer}', [StockTransferController::class, 'update'])
        ->name('stock-transfers.update');
    Route::middleware('permission:inventory.transfer')->post('/inventory/transfers/{transfer}/post', [StockTransferController::class, 'post'])
        ->name('stock-transfers.post');
    Route::middleware('permission:inventory.delete')->delete('/inventory/transfers/{transfer}', [StockTransferController::class, 'destroy'])
        ->name('stock-transfers.destroy');

    // ── Cash & Bank ──
    Route::middleware('permission:bank.view')->get('/cash-bank', [CashBankController::class, 'index'])
        ->name('cash-bank.index');
    Route::middleware('permission:bank.view')->get('/cash-bank/transactions', [CashBankController::class, 'transactions'])
        ->name('cash-bank.transactions');
    Route::middleware('permission:bank.view')->get('/cash-bank/accounts', [CashBankController::class, 'accounts'])
        ->name('cash-bank.accounts');
    Route::middleware('permission:bank.view')->get('/cash-bank/reconciliations', [CashBankController::class, 'reconciliations'])
        ->name('cash-bank.reconciliations');
    Route::middleware('permission:bank.view')->get('/cash-bank/reconciliations/{import}', [CashBankController::class, 'showReconciliation'])
        ->name('cash-bank.reconciliations.show');

    Route::middleware('permission:bank.create')->post('/cash-bank/transactions', [CashBankTransactionController::class, 'store'])
        ->name('cash-bank.transactions.store');
    Route::middleware('permission:bank.delete')->delete('/cash-bank/transactions/{transaction}', [CashBankTransactionController::class, 'destroy'])
        ->name('cash-bank.transactions.destroy');

    Route::middleware('permission:bank.create')->post('/cash-bank/accounts/cash', [CashBankAccountController::class, 'storeCash'])
        ->name('cash-bank.accounts.store-cash');
    Route::middleware('permission:bank.update')->put('/cash-bank/accounts/cash/{cashAccount}', [CashBankAccountController::class, 'updateCash'])
        ->name('cash-bank.accounts.update-cash');
    Route::middleware('permission:bank.delete')->delete('/cash-bank/accounts/cash/{cashAccount}', [CashBankAccountController::class, 'destroyCash'])
        ->name('cash-bank.accounts.destroy-cash');
    Route::middleware('permission:bank.create')->post('/cash-bank/accounts/bank', [CashBankAccountController::class, 'storeBank'])
        ->name('cash-bank.accounts.store-bank');
    Route::middleware('permission:bank.update')->put('/cash-bank/accounts/bank/{bankAccount}', [CashBankAccountController::class, 'updateBank'])
        ->name('cash-bank.accounts.update-bank');
    Route::middleware('permission:bank.delete')->delete('/cash-bank/accounts/bank/{bankAccount}', [CashBankAccountController::class, 'destroyBank'])
        ->name('cash-bank.accounts.destroy-bank');

    Route::middleware('permission:bank.reconcile')->post('/cash-bank/reconciliations', [BankReconciliationController::class, 'store'])
        ->name('cash-bank.reconciliations.store');
    Route::middleware('permission:bank.reconcile')->post('/cash-bank/reconciliations/{import}/auto-match', [BankReconciliationController::class, 'autoMatch'])
        ->name('cash-bank.reconciliations.auto-match');
    Route::middleware('permission:bank.reconcile')->post('/cash-bank/reconciliations/{import}/{line}/match', [BankReconciliationController::class, 'matchLine'])
        ->name('cash-bank.reconciliations.match-line');
    Route::middleware('permission:bank.reconcile')->post('/cash-bank/reconciliations/{import}/{line}/unmatch', [BankReconciliationController::class, 'unmatchLine'])
        ->name('cash-bank.reconciliations.unmatch-line');
    Route::middleware('permission:bank.reconcile')->post('/cash-bank/reconciliations/{import}/complete', [BankReconciliationController::class, 'complete'])
        ->name('cash-bank.reconciliations.complete');

    Route::middleware('permission:expense.view')->get('/expenses', [ExpenseController::class, 'index'])
        ->name('expenses.index');

    Route::middleware('permission:expense.view')->get('/expenses/categories', [ExpenseCategoryController::class, 'index'])
        ->name('expense-categories.index');
    Route::middleware('permission:expense.create')->post('/expenses/categories', [ExpenseCategoryController::class, 'store'])
        ->name('expense-categories.store');
    Route::middleware('permission:expense.update')->put('/expenses/categories/{category}', [ExpenseCategoryController::class, 'update'])
        ->name('expense-categories.update');
    Route::middleware('permission:expense.delete')->delete('/expenses/categories/{category}', [ExpenseCategoryController::class, 'destroy'])
        ->name('expense-categories.destroy');

    Route::middleware('permission:expense.create')->get('/expenses/create', [ExpenseController::class, 'create'])
        ->name('expenses.create');
    Route::middleware('permission:expense.create')->post('/expenses', [ExpenseController::class, 'store'])
        ->name('expenses.store');
    Route::middleware('permission:expense.view')->get('/expenses/{expense}', [ExpenseController::class, 'show'])
        ->name('expenses.show');
    Route::middleware('permission:expense.update')->get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])
        ->name('expenses.edit');
    Route::middleware('permission:expense.update')->put('/expenses/{expense}', [ExpenseController::class, 'update'])
        ->name('expenses.update');
    Route::middleware('permission:expense.post')->post('/expenses/{expense}/post', [ExpenseController::class, 'post'])
        ->name('expenses.post');
    Route::middleware('permission:expense.delete')->delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->name('expenses.destroy');

    // Fixed Assets (module 21) — categories/run-depreciation must precede /{fixed_asset}.
    Route::middleware('permission:fixed_asset.view')->get('/fixed-assets', [FixedAssetController::class, 'index'])
        ->name('fixed-assets.index');
    Route::middleware('permission:fixed_asset.view')->get('/fixed-assets/categories', [AssetCategoryController::class, 'index'])
        ->name('asset-categories.index');
    Route::middleware('permission:fixed_asset.create')->post('/fixed-assets/categories', [AssetCategoryController::class, 'store'])
        ->name('asset-categories.store');
    Route::middleware('permission:fixed_asset.update')->put('/fixed-assets/categories/{category}', [AssetCategoryController::class, 'update'])
        ->name('asset-categories.update');
    Route::middleware('permission:fixed_asset.delete')->delete('/fixed-assets/categories/{category}', [AssetCategoryController::class, 'destroy'])
        ->name('asset-categories.destroy');
    Route::middleware('permission:fixed_asset.depreciate')->post('/fixed-assets/run-depreciation', [FixedAssetController::class, 'depreciate'])
        ->name('fixed-assets.depreciate');

    Route::middleware('permission:fixed_asset.create')->get('/fixed-assets/create', [FixedAssetController::class, 'create'])
        ->name('fixed-assets.create');
    Route::middleware('permission:fixed_asset.create')->post('/fixed-assets', [FixedAssetController::class, 'store'])
        ->name('fixed-assets.store');
    Route::middleware('permission:fixed_asset.view')->get('/fixed-assets/{asset}', [FixedAssetController::class, 'show'])
        ->name('fixed-assets.show');
    Route::middleware('permission:fixed_asset.update')->get('/fixed-assets/{asset}/edit', [FixedAssetController::class, 'edit'])
        ->name('fixed-assets.edit');
    Route::middleware('permission:fixed_asset.update')->put('/fixed-assets/{asset}', [FixedAssetController::class, 'update'])
        ->name('fixed-assets.update');
    Route::middleware('permission:fixed_asset.update')->post('/fixed-assets/{asset}/capitalize', [FixedAssetController::class, 'capitalize'])
        ->name('fixed-assets.capitalize');
    Route::middleware('permission:fixed_asset.delete')->post('/fixed-assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])
        ->name('fixed-assets.dispose');
    Route::middleware('permission:fixed_asset.delete')->delete('/fixed-assets/{asset}', [FixedAssetController::class, 'destroy'])
        ->name('fixed-assets.destroy');

    // Payroll (module 22) — runs + employees (+ department/designation sub-resources).
    Route::middleware('permission:payroll.view')->get('/payroll', [PayrollRunController::class, 'index'])
        ->name('payroll.index');
    Route::middleware('permission:payroll.process')->post('/payroll/process', [PayrollRunController::class, 'store'])
        ->name('payroll.process');
    Route::middleware('permission:payroll.view')->get('/payroll/runs/{run}', [PayrollRunController::class, 'show'])
        ->name('payroll.runs.show');
    Route::middleware('permission:payroll.update')->put('/payroll/runs/{run}/lines/{line}', [PayrollRunController::class, 'updateLine'])
        ->name('payroll.runs.lines.update');
    Route::middleware('permission:payroll.post')->post('/payroll/runs/{run}/post', [PayrollRunController::class, 'post'])
        ->name('payroll.runs.post');
    Route::middleware('permission:payroll.delete')->delete('/payroll/runs/{run}', [PayrollRunController::class, 'destroy'])
        ->name('payroll.runs.destroy');
    Route::middleware('permission:payroll.post')->post('/payroll/runs/{run}/payments', [PayrollRunController::class, 'storePayment'])
        ->name('payroll.payments.store');

    Route::middleware('permission:payroll.view')->get('/payroll/employees', [EmployeeController::class, 'index'])
        ->name('payroll.employees');
    Route::middleware('permission:payroll.create')->get('/payroll/employees/create', [EmployeeController::class, 'create'])
        ->name('payroll.employees.create');
    Route::middleware('permission:payroll.create')->post('/payroll/employees', [EmployeeController::class, 'store'])
        ->name('payroll.employees.store');
    Route::middleware('permission:payroll.view')->get('/payroll/employees/{employee}', [EmployeeController::class, 'show'])
        ->name('payroll.employees.show');
    Route::middleware('permission:payroll.update')->get('/payroll/employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->name('payroll.employees.edit');
    Route::middleware('permission:payroll.update')->put('/payroll/employees/{employee}', [EmployeeController::class, 'update'])
        ->name('payroll.employees.update');
    Route::middleware('permission:payroll.delete')->delete('/payroll/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->name('payroll.employees.destroy');

    Route::middleware('permission:payroll.create')->post('/payroll/departments', [DepartmentController::class, 'store'])
        ->name('payroll.departments.store');
    Route::middleware('permission:payroll.update')->put('/payroll/departments/{department}', [DepartmentController::class, 'update'])
        ->name('payroll.departments.update');
    Route::middleware('permission:payroll.delete')->delete('/payroll/departments/{department}', [DepartmentController::class, 'destroy'])
        ->name('payroll.departments.destroy');
    Route::middleware('permission:payroll.create')->post('/payroll/designations', [DesignationController::class, 'store'])
        ->name('payroll.designations.store');
    Route::middleware('permission:payroll.update')->put('/payroll/designations/{designation}', [DesignationController::class, 'update'])
        ->name('payroll.designations.update');
    Route::middleware('permission:payroll.delete')->delete('/payroll/designations/{designation}', [DesignationController::class, 'destroy'])
        ->name('payroll.designations.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';