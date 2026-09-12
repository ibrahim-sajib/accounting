<?php

namespace App\Domain\Report\Http\Controllers;

use App\Domain\Report\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $report = in_array($request->input('report'), ['general-ledger', 'trial-balance'], true)
            ? $request->input('report')
            : 'general-ledger';

        $fiscalYearId = $request->integer('fiscal_year_id') ?: null;
        $periodId = $request->integer('period_id') ?: null;
        $accountId = $request->integer('account_id') ?: null;

        $props = ['report' => $report];

        if ($report === 'general-ledger') {
            $props = array_merge($props, $this->reportService->generalLedger($companyId, $fiscalYearId, $periodId, $accountId));
        } else {
            $props = array_merge($props, $this->reportService->trialBalance($companyId, $fiscalYearId, $periodId));
        }

        return Inertia::render('Report/Index', $props);
    }
}