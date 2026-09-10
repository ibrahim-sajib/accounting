<?php

namespace App\Support\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
    case Reversed = 'reversed';

    /**
     * Statuses that allow editing the underlying record.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Posted => 'Posted',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Voided => 'Voided',
            self::Reversed => 'Reversed',
        };
    }
}
