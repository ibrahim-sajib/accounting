<?php

namespace App\Http\Controllers;

use App\Domain\Report\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        return Inertia::render('Dashboard', [
            'summary' => [
                'company_name' => optional(current_company())->name,
                'accounting_basis' => current_company()?->accounting_basis,
            ],
            'metrics' => $this->dashboardService->metrics($companyId),
        ]);
    }
}