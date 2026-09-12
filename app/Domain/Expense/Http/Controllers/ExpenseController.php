<?php

namespace App\Domain\Expense\Http\Controllers;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\Expense\Exceptions\ExpensePostingException;
use App\Domain\Expense\Http\Requests\ExpenseRequest;
use App\Domain\Expense\Models\Expense;
use App\Domain\Expense\Models\ExpenseCategory;
use App\Domain\Expense\Services\ExpenseService;
use App\Domain\Party\Models\Supplier;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Support\Enums\ExpensePaymentMethod;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenseService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $query = Expense::query()->with(['category', 'taxRate', 'supplier'])
            ->where('company_id', $companyId);

        $query = $this->applyStatusFilter($query, $request->input('status'));

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('payee', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('expense_no', 'like', "%{$search}%");
            });
        }

        $expenses = $query
            ->orderByDesc('expense_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Expense/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'status', 'category_id']),
            'categories' => ExpenseCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expense/Create', $this->formProps());
    }

public function store(ExpenseRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        try {
            $expense = $this->expenseService->store($request->validated(), $companyId);
        } catch (ExpensePostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('expense', 'create', null, $expense->id, [], $expense->fresh()->toArray(), $expense->company_id);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense draft created.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorizeCompany($expense);

        return Inertia::render('Expense/Show', [
            'expense' => $expense->load(['category', 'taxRate', 'supplier', 'cashAccount', 'bankAccount', 'journal']),
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $this->authorizeCompany($expense);

        if ($expense->isPosted()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'Posted expenses cannot be edited.');
        }

        return Inertia::render('Expense/Edit', array_merge($this->formProps(), ['expense' => $expense]));
    }

    public function update(Expense $expense, ExpenseRequest $request): RedirectResponse
    {
        $this->authorizeCompany($expense);

        try {
            $this->expenseService->update($expense, $request->validated());
        } catch (ExpensePostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('expense', 'update', null, $expense->id, $expense->getOriginal(), $expense->fresh()->toArray(), $expense->company_id);

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function post(Expense $expense): RedirectResponse
    {
        $this->authorizeCompany($expense);

        try {
            $this->expenseService->post($expense);
        } catch (ExpensePostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('expense', 'post', null, $expense->id, $expense->getOriginal(), $expense->fresh()->toArray(), $expense->company_id);

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense posted.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorizeCompany($expense);

        try {
            $this->expenseService->destroy($expense);
        } catch (ExpensePostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('expense', 'delete', null, $expense->id, [], [], $expense->company_id);

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    protected function applyStatusFilter($query, ?string $status)
    {
        return match ($status) {
            'posted' => $query->where('status', TransactionStatus::Posted->value),
            'draft' => $query->where('status', TransactionStatus::Draft->value),
            'recurring' => $query->where('is_recurring', true),
            default => $query,
        };
    }

    protected function authorizeCompany(Expense $expense): void
    {
        abort_if((int) $expense->company_id !== (int) session('active_company_id'), 404);
    }

    protected function formProps(): array
    {
        $companyId = (int) session('active_company_id');

        return [
            'categories' => ExpenseCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'expense_account_id'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'expense_account_id' => $c->expense_account_id])
                ->values()
                ->all(),
            'taxRates' => TaxRate::query()
                ->whereHas('taxType', fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('name')
                ->get(['id', 'name', 'rate_percent', 'is_inclusive'])
                ->map(fn ($r) => [
                    'value' => $r->id,
                    'label' => "{$r->name} ({$r->rate_percent}%)",
                    'rate_percent' => (float) $r->rate_percent,
                ])
                ->values()
                ->all(),
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])
                ->values()
                ->all(),
            'bankAccounts' => BankAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => ($a->account_name.' ('.$a->bank_name.')')])
                ->values()
                ->all(),
            'suppliers' => Supplier::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
                ->values()
                ->all(),
            'paymentMethods' => $this->paymentMethodOptions(),
            'defaultCashAccountId' => AccountingSetting::query()->where('company_id', $companyId)->value('default_cash_account_id'),
            'defaultBankAccountId' => AccountingSetting::query()->where('company_id', $companyId)->value('default_bank_account_id'),
        ];
    }

    protected function paymentMethodOptions(): array
    {
        return collect(ExpensePaymentMethod::cases())
            ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
            ->values()
            ->all();
    }
}