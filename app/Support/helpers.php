<?php

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use Illuminate\Support\Facades\Auth;

if (! function_exists('current_company_id')) {
    function current_company_id(): ?int
    {
        return session('active_company_id');
    }
}

if (! function_exists('current_company')) {
    function current_company(): ?Company
    {
        $id = current_company_id();

        return $id ? Company::query()->find($id) : null;
    }
}

if (! function_exists('current_user')) {
    function current_user()
    {
        return Auth::user();
    }
}

if (! function_exists('active_fiscal_year')) {
    function active_fiscal_year(): ?FiscalYear
    {
        return FiscalYear::query()
            ->where('company_id', current_company_id())
            ->where('is_active', true)
            ->first();
    }
}

if (! function_exists('active_period')) {
    function active_period(): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', current_company_id())->where('is_active', true))
            ->where('is_active', true)
            ->first();
    }
}

if (! function_exists('current_branch')) {
    function current_branch(): ?Branch
    {
        $branchId = session('active_branch_id');

        return $branchId ? Branch::query()->find($branchId) : null;
    }
}

if (! function_exists('enum_options')) {
    function enum_options(string $enum): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => method_exists($case, 'label') ? $case->label() : str($case->value)->headline()->toString()],
            $enum::cases()
        );
    }
}