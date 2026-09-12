<?php

namespace App\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentNeedsApproval extends Notification
{
    use Queueable;

    public function __construct(
        public int $approvalRequestId,
        public int $companyId,
        public string $module,
        public string $summary,
        public string $amount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'approval',
            'title' => 'A document awaits your approval',
            'body' => $this->summary.' ('.$this->amount.') has been submitted for approval.',
            'approval_request_id' => $this->approvalRequestId,
            'company_id' => $this->companyId,
            'module' => $this->module,
            'approvals_url' => '/approvals',
        ];
    }
}