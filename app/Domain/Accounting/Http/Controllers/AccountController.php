<?php

namespace App\Domain\Accounting\Http\Controllers;

use App\Domain\Accounting\Exceptions\AccountProtectedException;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Http\Requests\AccountRequest;
use App\Domain\Accounting\Services\AccountService;
use App\Support\Enums\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->with('parent')
            ->orderBy('code')
            ->get();

        $parentOptions = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level']);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'parentOptions' => $parentOptions,
            'typeOptions' => enum_options(AccountType::class),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = current_company_id();

        $parentOptions = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level']);

        return Inertia::render('Accounts/Create', [
            'parentOptions' => $parentOptions,
            'typeOptions' => enum_options(AccountType::class),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $companyId = current_company_id();
        $account = $this->accountService->store($companyId, $request->validated());

        \App\Domain\Audit\Services\AuditLogger::log('account', 'create', null, $account->id, [], $account->toArray(), $companyId);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(Request $request, Account $account): Response
    {
        $companyId = current_company_id();

        $parentOptions = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level']);

        $typeOptions = collect(['asset', 'liability', 'equity', 'income', 'expense'])
            ->map(fn (string $t) => ['value' => $t, 'label' => AccountType::from($t)->label()]);

        return Inertia::render('Accounts/Edit', [
            'account' => $account,
            'parentOptions' => $parentOptions,
            'typeOptions' => $typeOptions,
        ]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $companyId = current_company_id();
        $this->accountService->update($companyId, $request->validated(), $account);

        \App\Domain\Audit\Services\AuditLogger::log('account', 'update', $account->toArray(), $account->id, [], $request->validated(), $companyId);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $companyId = current_company_id();

        try {
            $this->accountService->destroy($account);

            \App\Domain\Audit\Services\AuditLogger::log('account', 'delete', null, $account->id, $account->toArray(), [], $companyId);

            return redirect()->route('accounts.index')->with('success', 'Account deleted.');
        } catch (AccountProtectedException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}