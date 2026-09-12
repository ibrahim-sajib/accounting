<?php

namespace App\Domain\Expense\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Expense\Http\Requests\ExpenseCategoryRequest;
use App\Domain\Expense\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $categories = ExpenseCategory::query()
            ->with(['expenseAccount:id,code,name'])
            ->where('company_id', $companyId)
            ->withTrashed()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Expense/Categories', [
            'categories' => $categories,
            'postableAccounts' => Account::query()
                ->where('company_id', $companyId)
                ->where('is_postable', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        $category = ExpenseCategory::query()->create([
            'company_id' => $companyId,
            'name' => $request->input('name'),
            'expense_account_id' => $request->input('expense_account_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('expense_category', 'create', null, $category->id, [], $category->fresh()->toArray(), $companyId);

        return redirect()->route('expense-categories.index')->with('success', 'Expense category created.');
    }

    public function update(ExpenseCategory $category, ExpenseCategoryRequest $request): RedirectResponse
    {
        abort_if((int) $category->company_id !== (int) session('active_company_id'), 404);

        $category->update([
            'name' => $request->input('name'),
            'expense_account_id' => $request->input('expense_account_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('expense_category', 'update', null, $category->id, $category->getOriginal(), $category->fresh()->toArray(), $category->company_id);

        return redirect()->route('expense-categories.index')->with('success', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        abort_if((int) $category->company_id !== (int) session('active_company_id'), 404);

        if ($category->expenses()->count() > 0) {
            $category->update(['is_active' => false]);

            AuditLogger::log('expense_category', 'disable', null, $category->id, [], $category->fresh()->toArray(), $category->company_id);

            return back()->with('error', 'Category is in use — it has been deactivated instead.');
        }

        $category->delete();

        AuditLogger::log('expense_category', 'delete', null, $category->id, [], [], $category->company_id);

        return redirect()->route('expense-categories.index')->with('success', 'Expense category deleted.');
    }
}