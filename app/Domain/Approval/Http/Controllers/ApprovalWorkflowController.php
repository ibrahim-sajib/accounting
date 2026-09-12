<?php

namespace App\Domain\Approval\Http\Controllers;

use App\Domain\Approval\Http\Requests\ApprovalWorkflowRequest;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Rbac\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalWorkflowController
{
    public const MODULES = [
        'sales_invoice' => 'Sales Invoice',
        'purchase_bill' => 'Purchase Bill',
        'expense' => 'Expense',
        'journal' => 'Journal',
        'payroll' => 'Payroll',
        'budget' => 'Budget',
    ];

    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $workflows = ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->with(['approverRole:id,name'])
            ->orderBy('module')
            ->orderBy('sequence')
            ->get()
            ->map(fn (ApprovalWorkflow $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'module' => $w->module,
                'module_label' => self::MODULES[$w->module] ?? $w->module,
                'min_amount' => (string) $w->min_amount,
                'max_amount' => $w->max_amount === null ? null : (string) $w->max_amount,
                'sequence' => $w->sequence,
                'is_active' => (bool) $w->is_active,
                'approver_role_id' => $w->approver_role_id,
                'approver_role' => $w->approverRole?->name,
            ]);

        $roles = Role::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $r) => ['value' => $r->id, 'label' => $r->name])
            ->values()
            ->all();

        return Inertia::render('Approval/Workflows', [
            'workflows' => $workflows,
            'modules' => collect(self::MODULES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'roles' => $roles,
        ]);
    }

    public function store(ApprovalWorkflowRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $workflow = ApprovalWorkflow::query()->create($data + ['company_id' => current_company_id()]);

        AuditLogger::log('approval', 'create', null, $workflow->id, [], $workflow->toArray(), current_company_id());

        return back()->with('success', 'Approval workflow created.');
    }

    public function update(ApprovalWorkflowRequest $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        if ($workflow->company_id !== current_company_id()) {
            abort(403, 'This workflow belongs to a different company.');
        }

        $old = $workflow->toArray();
        $workflow->update($request->validated());

        AuditLogger::log('approval', 'update', null, $workflow->id, $old, $workflow->toArray(), $workflow->company_id);

        return back()->with('success', 'Approval workflow updated.');
    }

    public function destroy(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        if ($workflow->company_id !== current_company_id()) {
            abort(403, 'This workflow belongs to a different company.');
        }

        $id = $workflow->id;
        $workflow->delete();

        AuditLogger::log('approval', 'delete', null, $id, [], [], $workflow->company_id);

        return back()->with('success', 'Approval workflow deleted.');
    }
}