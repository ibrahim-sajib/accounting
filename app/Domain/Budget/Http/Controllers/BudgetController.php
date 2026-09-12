<?php

namespace App\Domain\Budget\Http\Controllers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Budget\Exceptions\BudgetPostingException;
use App\Domain\Budget\Http\Requests\BudgetRequest;
use App\Domain\Budget\Models\Budget;
use App\Domain\Budget\Services\BudgetService;
use App\Http\Controllers\Controller;
use App\Support\Enums\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(protected BudgetService $budgetService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $budgets = Budget::query()
            ->with(['fiscalYear'])
            ->where('company_id', $companyId)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Budget/Index', [
            'budgets' => $budgets->through(fn (Budget $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'status' => $b->status,
                'notes' => $b->notes,
                'fiscal_year_name' => $b->fiscalYear?->name,
                'fiscal_year' => $b->fiscalYear ? ['id' => $b->fiscalYear->id, 'name' => $b->fiscalYear->name, 'start_date' => $b->fiscalYear->start_date->toDateString(), 'end_date' => $b->fiscalYear->end_date->toDateString()] : null,
                'total_budgeted' => round($b->totalBudgeted(), 4),
                'line_count' => $b->lines()->count(),
            ]),
            'filters' => $request->only(['status']),
            'statuses' => enum_options(\App\Support\Enums\BudgetStatus::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Budget/Create', $this->formProps());
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        try {
            $budget = $this->budgetService->storeBudget($request->validated(), $companyId);
        } catch (BudgetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('budget', 'create', 'budget', $budget->id, [], $budget->fresh()->toArray(), $budget->company_id);

        return redirect()->route('budgets.show', $budget)->with('success', 'Budget created.');
    }

    public function show(Budget $budget): Response
    {
        $this->authorizeCompany($budget);

        return Inertia::render('Budget/Show', [
            'budget' => $this->serialize($budget),
            'variance' => $this->budgetService->variance($budget),
        ]);
    }

    public function edit(Budget $budget): Response|RedirectResponse
    {
        $this->authorizeCompany($budget);

        try {
            if ($budget->status !== \App\Support\Enums\BudgetStatus::Draft->value) {
                throw new BudgetPostingException('Only draft budgets can be edited.');
            }
        } catch (BudgetPostingException $e) {
            return redirect()->route('budgets.show', $budget)->with('error', $e->getMessage());
        }

        return Inertia::render('Budget/Edit', array_merge($this->formProps($budget), [
            'budget' => $this->serialize($budget->fresh()),
        ]));
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorizeCompany($budget);

        $companyId = (int) session('active_company_id');

        try {
            $this->budgetService->updateBudget($budget, $request->validated(), $companyId);
        } catch (BudgetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('budget', 'update', 'budget', $budget->id, [], $budget->fresh()->toArray(), $budget->company_id);

        return redirect()->route('budgets.show', $budget)->with('success', 'Budget updated.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorizeCompany($budget);

        try {
            $this->budgetService->destroyBudget($budget);
        } catch (BudgetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('budget', 'delete', 'budget', $budget->id, [], [], $budget->company_id);

        return redirect()->route('budgets.index')->with('success', 'Budget deleted.');
    }

    public function post(Budget $budget): RedirectResponse
    {
        $this->authorizeCompany($budget);

        try {
            $this->budgetService->postBudget($budget);
        } catch (BudgetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('budget', 'post', 'budget', $budget->id, [], ['status' => $budget->status], $budget->company_id);

        return redirect()->route('budgets.show', $budget)->with('success', 'Budget posted.');
    }

    protected function authorizeCompany(Budget $budget): void
    {
        abort_if((int) $budget->company_id !== (int) session('active_company_id'), 404);
    }

    protected function serialize(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'status' => $budget->status,
            'notes' => $budget->notes,
            'fiscal_year_id' => $budget->fiscal_year_id,
            'fiscalYear' => $budget->fiscalYear ? [
                'id' => $budget->fiscalYear->id,
                'name' => $budget->fiscalYear->name,
                'start_date' => $budget->fiscalYear->start_date->toDateString(),
                'end_date' => $budget->fiscalYear->end_date->toDateString(),
            ] : null,
            'total_budgeted' => round($budget->totalBudgeted(), 4),
        ];
    }

    protected function formProps(?Budget $budget = null): array
    {
        $companyId = (int) session('active_company_id');

        $lines = [];

        if ($budget) {
            $lines = $budget->lines()
                ->get(['id', 'account_id', 'period_id', 'budgeted_amount'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'account_id' => $l->account_id,
                    'period_id' => $l->period_id,
                    'budgeted_amount' => (float) $l->budgeted_amount,
                ])
                ->values()
                ->all();
        }

        return [
            'fiscalYears' => FiscalYear::query()
                ->where('company_id', $companyId)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name.' ('.substr($f->start_date->toDateString(), 0, 4).')'])
                ->values()
                ->all(),
            'accounts' => Account::query()
                ->where('company_id', $companyId)
                ->where('is_postable', true)
                ->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->code.' — '.$a->name])
                ->values()
                ->all(),
            'periods' => AccountingPeriod::query()
                ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('fiscal_year_id')
                ->orderBy('start_date')
                ->get(['id', 'name', 'fiscal_year_id'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'fiscal_year_id' => $p->fiscal_year_id])
                ->values()
                ->all(),
            'initialLines' => $lines,
            'initialBudget' => $budget ? [
                'name' => $budget->name,
                'fiscal_year_id' => $budget->fiscal_year_id,
                'notes' => $budget->notes,
            ] : null,
        ];
    }
}