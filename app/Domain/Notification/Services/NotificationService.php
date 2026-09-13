<?php

namespace App\Domain\Notification\Services;

use App\Domain\Approval\Models\ApprovalRequest;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Notification\Notifications\ApprovalDecision;
use App\Domain\Notification\Notifications\DocumentNeedsApproval;
use App\Domain\Rbac\Models\UserRole;
use App\Domain\Tenant\Services\TenantManager;
use App\Models\User;

class NotificationService
{
    public static function notifyApprovers(ApprovalRequest $request): void
    {
        // Approvers are platform registry rows; tenants only carry mirrors.
        $platform = app(TenantManager::class)->platformConnection();

        $chain = ApprovalWorkflow::query()
            ->where('company_id', $request->company_id)
            ->where('module', $request->module)
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get()
            ->filter(fn (ApprovalWorkflow $w) => (float) $request->amount >= (float) $w->min_amount
                && ($w->max_amount === null || (float) $request->amount <= (float) $w->max_amount))
            ->values();

        $targets = collect();

        foreach ($chain as $workflow) {
            if ($workflow->approver_user_id) {
                $targets->push((int) $workflow->approver_user_id);
                continue;
            }

            if ($workflow->approver_role_id) {
                $ids = UserRole::on($platform)
                    ->where('role_id', $workflow->approver_role_id)
                    ->where('company_id', $request->company_id)
                    ->pluck('user_id');
                $targets = $targets->merge($ids);
            }
        }

        $users = User::on($platform)->whereKey($targets->unique())->get();

        $notification = new DocumentNeedsApproval(
            $request->id,
            $request->company_id,
            $request->module,
            $request->summary(),
            number_format((float) $request->amount, 2),
        );

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }

    public static function notifyRequester(ApprovalRequest $request): void
    {
        $platform = app(TenantManager::class)->platformConnection();
        $requester = User::on($platform)->find($request->requested_by);

        if (! $requester) {
            return;
        }

        $requester->notify(new ApprovalDecision(
            $request->id,
            $request->company_id,
            $request->module,
            $request->status,
            $request->summary(),
            $request->reject_reason,
        ));
    }
}