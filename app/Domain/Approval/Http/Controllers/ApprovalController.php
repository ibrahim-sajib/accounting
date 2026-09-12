<?php

namespace App\Domain\Approval\Http\Controllers;

use App\Domain\Approval\Exceptions\ApprovalException;
use App\Domain\Approval\Models\ApprovalRequest;
use App\Domain\Approval\Services\ApprovalWorkflowService;
use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController
{
    public function index(Request $request, ApprovalWorkflowService $service): Response
    {
        $pending = ApprovalRequest::query()
            ->where('company_id', current_company_id())
            ->where('status', 'pending')
            ->with(['requestedBy:id,name'])
            ->latest('id')
            ->get();

        $decided = ApprovalRequest::query()
            ->where('company_id', current_company_id())
            ->where('status', '!=', 'pending')
            ->with(['requestedBy:id,name', 'decidedBy:id,name'])
            ->latest('id')
            ->limit(20)
            ->get();

        $map = fn (ApprovalRequest $r, int $i) => [
            'id' => $r->id,
            'module' => $r->module,
            'module_label' => ApprovalWorkflowService::moduleLabel($r->module),
            'summary' => $r->summary(),
            'amount' => (string) $r->amount,
            'status' => $r->status,
            'current_step' => $r->current_step,
            'total_steps' => $r->total_steps,
            'reject_reason' => $r->reject_reason,
            'requested_by' => $r->requestedBy?->name,
            'decided_by' => $r->decidedBy?->name,
            'decided_at' => $r->decided_at?->toISOString(),
            'can_approve' => $r->isPending() && $service->approverCan($r, $request->user()),
        ];

        return Inertia::render('Approval/Index', [
            'pending' => $pending->map($map)->values()->all(),
            'decided' => $decided->map($map)->values()->all(),
            'pending_count' => $pending->count(),
        ]);
    }

    public function approve(Request $request, ApprovalWorkflowService $service, ApprovalRequest $approvalRequest): RedirectResponse
    {
        try {
            $approvalRequest = $service->approve($approvalRequest, $request->user());

            AuditLogger::log('approval', 'approve', null, $approvalRequest->id, [], $approvalRequest->toArray(), $approvalRequest->company_id);

            return redirect()
                ->route('approvals.index')
                ->with('success', 'Approval request approved.');
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, ApprovalWorkflowService $service, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate(['reject_reason' => ['nullable', 'string', 'max:1000']]);

        try {
            $approvalRequest = $service->reject($approvalRequest, $request->user(), $data['reject_reason'] ?? null);

            AuditLogger::log('approval', 'reject', null, $approvalRequest->id, [], $approvalRequest->toArray(), $approvalRequest->company_id);

            return redirect()
                ->route('approvals.index')
                ->with('success', 'Approval request rejected.');
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}