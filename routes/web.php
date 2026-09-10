<?php

use App\Domain\Accounting\Http\Controllers\AccountingPeriodController;
use App\Domain\Accounting\Http\Controllers\AccountingSettingController;
use App\Domain\Accounting\Http\Controllers\AccountController;
use App\Domain\Accounting\Http\Controllers\FiscalYearController;
use App\Domain\Company\Http\Controllers\BranchController;
use App\Domain\Company\Http\Controllers\CompanyController;
use App\Domain\Currency\Http\Controllers\CurrencyController;
use App\Domain\Rbac\Http\Controllers\RoleController;
use App\Domain\Rbac\Http\Controllers\UserController;
use App\Domain\Settings\Http\Controllers\SystemSettingController;
use App\Domain\Tax\Http\Controllers\TaxTypeController;
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';