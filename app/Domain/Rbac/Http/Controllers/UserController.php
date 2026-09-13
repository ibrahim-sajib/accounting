<?php

namespace App\Domain\Rbac\Http\Controllers;

use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Rbac\Http\Requests\UserRequest;
use App\Domain\Rbac\Models\Role;
use App\Domain\Rbac\Models\UserCompanyAccess;
use App\Domain\Rbac\Models\UserRole;
use App\Models\User;
use App\Support\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when(! $request->user()->is_super_admin, function ($q) {
                return $q->where('company_id', current_company_id());
            })
            ->with(['roles:id,name,slug', 'company:id,name'])
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Users/Create', [
            'companies' => $request->user()->is_super_admin ? Company::query()->orderBy('name')->get(['id', 'name']) : [Company::query()->find(current_company_id())],
            'roles' => $this->roleOptions(),
            'statusOptions' => enum_options(UserStatus::class),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'company_id' => $request->input('company_id') ?? current_company_id(),
            'status' => $request->input('status', 'active'),
            'is_super_admin' => false,
        ]);

        $this->syncAccess($user, $request);

        \App\Domain\Audit\Services\AuditLogger::log('user', 'create', null, $user->id, [], $user->toArray(), $user->company_id);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(Request $request, User $user): Response
    {
        $user->load(['userRoles.role', 'companyAccess']);

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'companies' => $request->user()->is_super_admin ? Company::query()->orderBy('name')->get(['id', 'name']) : [Company::query()->find(current_company_id())],
            'roles' => $this->roleOptions(),
            'userAccess' => $user->companyAccess->map(fn ($access) => [
                'company_id' => $access->company_id,
                'branch_id' => $access->branch_id,
                'is_default' => $access->is_default,
            ]),
            'userRoles' => $user->userRoles->pluck('role_id'),
            'statusOptions' => enum_options(UserStatus::class),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $old = $user->toArray();

        $user->update([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'status' => $request->input('status', 'active'),
            'company_id' => $request->input('company_id') ?? $user->company_id,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->input('password'))]);
        }

        $this->syncAccess($user, $request);

        \App\Domain\Audit\Services\AuditLogger::log('user', 'update', null, $user->id, $old, $user->toArray(), $user->company_id);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        \App\Domain\Audit\Services\AuditLogger::log('user', 'delete', null, $user->id, $user->toArray(), [], $user->company_id);

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    protected function syncAccess(User $user, Request $request): void
    {
        $roleIds = $request->input('roles', []);
        UserRole::query()->where('user_id', $user->id)->delete();
        foreach ($roleIds as $roleId) {
            UserRole::query()->create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'company_id' => $request->input('company_id') ?? current_company_id(),
            ]);
        }

        UserCompanyAccess::query()->where('user_id', $user->id)->delete();
        $accessList = $request->input('company_access', []);

        if ($request->user()->is_super_admin && count($accessList)) {
            foreach ($accessList as $access) {
                UserCompanyAccess::query()->create([
                    'user_id' => $user->id,
                    'company_id' => $access['company_id'],
                    'branch_id' => $access['branch_id'] ?? null,
                    'is_default' => $access['is_default'] ?? false,
                ]);
            }
        } else {
            UserCompanyAccess::query()->create([
                'user_id' => $user->id,
                'company_id' => $request->input('company_id') ?? current_company_id(),
                'is_default' => true,
            ]);
        }
    }

    protected function roleOptions(): array
    {
        return Role::query()
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', current_company_id()))
            ->orderByRaw('company_id IS NULL ASC')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->unique('slug')
            ->values()
            ->map(fn ($role) => ['value' => $role->id, 'label' => $role->name, 'slug' => $role->slug])
            ->all();
    }

    protected function branchOptions(): array
    {
        return Branch::query()
            ->where('company_id', current_company_id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name])
            ->all();
    }
}