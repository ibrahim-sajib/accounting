<?php

namespace App\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecision extends Notification
{
    use Queueable;

    public function __construct(
        public int $approvalRequestId,
        public int $companyId,
        public string $module,
        public string $status,
        public string $summary,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->status === 'approved' ? 'approved' : 'rejected';

        $body = $this->summary.' was '.$verb;
        if ($this->reason) {
            $body .= ' — reason: '.$this->reason;
        }

        return [
            'category' => 'approval',
            'title' => 'Approval request '.$verb,
            'body' => $body,
            'approval_request_id' => $this->approvalRequestId,
            'company_id' => $this->companyId,
            'module' => $this->module,
            'approvals_url' => '/approvals',
        ];
    }
}