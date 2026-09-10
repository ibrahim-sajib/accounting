<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $fiscalYear = FiscalYear::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        $activePeriod = $fiscalYear
            ? AccountingPeriod::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('is_active', true)
                ->first()
            : null;

        return Inertia::render('Dashboard', [
            'summary' => [
                'company_name' => optional(current_company())->name,
                'fiscal_year' => optional($fiscalYear)->name,
                'active_period' => optional($activePeriod)->name,
                'accounting_basis' => current_company()?->accounting_basis,
            ],
        ]);
    }
}