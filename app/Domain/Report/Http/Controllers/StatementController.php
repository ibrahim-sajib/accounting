<?php

namespace App\Domain\Report\Http\Controllers;

use App\Domain\Report\Services\StatementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    public function __construct(protected StatementService $statementService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $statement = in_array($request->input('statement'), ['income', 'balance-sheet', 'cash-flow', 'equity'], true)
            ? $request->input('statement')
            : 'income';

        $fiscalYearId = $request->integer('fiscal_year_id') ?: null;
        $periodId = $request->integer('period_id') ?: null;

        $props = ['statement' => $statement];

        $props = array_merge($props, match ($statement) {
            'balance-sheet' => $this->statementService->balanceSheet($companyId, $fiscalYearId, $periodId),
            'cash-flow' => $this->statementService->cashFlow($companyId, $fiscalYearId, $periodId),
            'equity' => $this->statementService->changesInEquity($companyId, $fiscalYearId, $periodId),
            default => $this->statementService->incomeStatement($companyId, $fiscalYearId, $periodId),
        });

        return Inertia::render('Statements/Index', $props);
    }
}