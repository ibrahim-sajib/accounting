<?php

namespace App\Domain\Rbac\Http\Controllers;

use App\Domain\Rbac\Http\Requests\RoleRequest;
use App\Domain\Rbac\Models\Permission;
use App\Domain\Rbac\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController
{
    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->when($request->user()->is_super_admin, function ($q) {
                return $q->whereNull('company_id');
            }, function ($q) {
                return $q->where('company_id', current_company_id());
            })
            ->when($request->query('search'), fn ($q, $search) => $q
                ->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'description' => $request->input('description'),
            'company_id' => $request->user()->is_super_admin ? null : current_company_id(),
            'is_system' => false,
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        \App\Domain\Audit\Services\AuditLogger::log('role', 'create', null, $role->id, [], $role->toArray(), current_company_id());

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Request $request, Role $role): Response
    {
        abort_if($role->is_system && ! $request->user()->is_super_admin, 403);

        $role->load('permissions');

        return Inertia::render('Roles/Edit', [
            'role' => $role->only(['id', 'name', 'slug', 'description', 'is_system', 'company_id']),
            'selectedPermissions' => $role->permissions->pluck('id'),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'System roles cannot be modified.');

        $old = $role->only(['name', 'description']);
        $role->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        \App\Domain\Audit\Services\AuditLogger::log('role', 'update', null, $role->id, $old, $role->toArray(), $role->company_id);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');

        $role->delete();

        \App\Domain\Audit\Services\AuditLogger::log('role', 'delete', null, $role->id, $role->toArray(), [], $role->company_id);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    protected function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('module')
            ->get()
            ->groupBy('module')
            ->map(function ($permissions) {
                return [
                    'module' => $permissions->first()->module,
                    'permissions' => $permissions->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => $p->slug,
                        'action' => $p->action,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }
}