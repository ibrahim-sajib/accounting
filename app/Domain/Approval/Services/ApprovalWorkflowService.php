<?php

namespace App\Domain\Approval\Services;

use App\Domain\Approval\Exceptions\ApprovalException;
use App\Domain\Approval\Models\ApprovalRequest;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Notification\Services\NotificationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    private const MODULE_LABELS = [
        'sales_invoice' => 'Sales Invoice',
        'purchase_bill' => 'Purchase Bill',
        'expense' => 'Expense',
        'journal' => 'Journal',
        'payroll' => 'Payroll',
        'budget' => 'Budget',
    ];

    public static function moduleLabel(string $module): string
    {
        return self::MODULE_LABELS[$module] ?? ucwords(str_replace('_', ' ', $module));
    }

    public static function moduleFor(Model $model): string
    {
        return match (true) {
            $model instanceof \App\Domain\Sales\Models\SalesInvoice => 'sales_invoice',
            $model instanceof \App\Domain\Purchase\Models\PurchaseBill => 'purchase_bill',
            $model instanceof \App\Domain\Expense\Models\Expense => 'expense',
            $model instanceof \App\Domain\Accounting\Models\Journal => 'journal',
            $model instanceof \App\Domain\Payroll\Models\PayrollRun => 'payroll',
            $model instanceof \App\Domain\Budget\Models\Budget => 'budget',
            default => 'document',
        };
    }

    /**
     * Active, amount-matching workflows for a module, ordered by sequence.
     */
    public function workflowChain(string $module, int $companyId, float $amount): Collection
    {
        return ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->where('module', $module)
            ->where('is_active', true)
            ->get()
            ->filter(fn (ApprovalWorkflow $w) => $amount >= $w->min_amount
                && ($w->max_amount === null || $amount <= $w->max_amount))
            ->sortBy('sequence')
            ->values();
    }

    public function requiresApproval(string $module, int $companyId, float $amount): bool
    {
        return $this->workflowChain($module, $companyId, $amount)->isNotEmpty();
    }

    /**
     * Ensure a document is approved before it may be posted.
     *
     * @return ApprovalRequest|null  a PENDING (blocking) request, or null when posting may proceed
     *                               (no rule matched, or an approved request already exists)
     */
    public function submitForApproval(string $module, int $companyId, Model $approvable, float $amount, ?int $requestedBy): ?ApprovalRequest
    {
        $chain = $this->workflowChain($module, $companyId, $amount);

        if ($chain->isEmpty()) {
            return null;
        }

        $latest = ApprovalRequest::query()
            ->where('company_id', $companyId)
            ->where('module', $module)
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey())
            ->latest('id')
            ->first();

        // Already handled — an approved request lets posting proceed; a pending one still blocks.
        if ($latest && ($latest->isApproved() || $latest->isPending())) {
            return $latest->isPending() ? $latest : null;
        }

        $request = ApprovalRequest::query()->create([
            'company_id' => $companyId,
            'module' => $module,
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'amount' => $amount,
            'requested_by' => $requestedBy ?? auth()->id(),
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => $chain->count(),
        ]);

        NotificationService::notifyApprovers($request);

        return $request;
    }

    public function approverCan(ApprovalRequest $request, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $stepIndex = max(0, $request->current_step - 1);
        $chain = $this->workflowChain($request->module, $request->company_id, (float) $request->amount);
        $workflow = $chain->get($stepIndex);

        if (! $workflow) {
            return false;
        }

        if ($workflow->approver_role_id && $user->roles()->wherePivot('company_id', $request->company_id)
                ->where('roles.id', $workflow->approver_role_id)->exists()) {
            return true;
        }

        if ($workflow->approver_user_id && (int) $user->id === (int) $workflow->approver_user_id) {
            return true;
        }

        return false;
    }

    public function approve(ApprovalRequest $request, User $user): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user) {
            if (! $request->isPending()) {
                throw new ApprovalException('This approval request is no longer pending.');
            }

            if (! $this->approverCan($request, $user)) {
                throw new ApprovalException('You are not the designated approver for this step.');
            }

            $chain = $this->workflowChain($request->module, $request->company_id, (float) $request->amount);

            $request->current_step = $request->current_step + 1;
            $request->decided_by = $user->id;
            $request->decided_at = now();

            if ($request->current_step > $chain->count()) {
                $request->status = 'approved';
            }

            $request->save();

            if ($request->status === 'approved') {
                NotificationService::notifyRequester($request);
            }
        });

        return $request->fresh();
    }

    public function reject(ApprovalRequest $request, User $user, ?string $reason = null): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user, $reason) {
            if (! $request->isPending()) {
                throw new ApprovalException('This approval request is no longer pending.');
            }

            if (! $this->approverCan($request, $user)) {
                throw new ApprovalException('You are not the designated approver for this step.');
            }

            $request->status = 'rejected';
            $request->decided_by = $user->id;
            $request->decided_at = now();
            $request->reject_reason = $reason;
            $request->save();
        });

        NotificationService::notifyRequester($request->fresh());

        return $request->fresh();
    }

    /**
     * Latest approval status for a document, or null when never submitted.
     */
    public function statusFor(Model $approvable): ?string
    {
        return ApprovalRequest::query()
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey())
            ->latest('id')
            ->value('status');
    }
}