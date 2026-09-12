<?php

namespace App\Domain\Approval\Models;

use App\Domain\Approval\Services\ApprovalWorkflowService;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'approval_requests';

    protected $fillable = [
        'company_id',
        'module',
        'approvable_type',
        'approvable_id',
        'amount',
        'requested_by',
        'status',
        'current_step',
        'total_steps',
        'decided_by',
        'decided_at',
        'reject_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending->value;
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::Approved->value;
    }

    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::Rejected->value;
    }

    public function summary(): string
    {
        $reference = '#'.$this->approvable_id;

        if ($this->approvable) {
            $no = $this->approvable->invoice_no
                ?? $this->approvable->bill_no
                ?? $this->approvable->expense_no
                ?? $this->approvable->journal_no;
            if ($no) {
                $reference = $no;
            }
        }

        return ApprovalWorkflowService::moduleLabel($this->module)." {$reference}";
    }
}