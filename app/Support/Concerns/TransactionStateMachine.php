<?php

namespace App\Support\Concerns;

/**
 * Shared transaction state machine trait.
 *
 * Provides the standarded Draft → Submitted → (Pending Approval) → Approved
 * → Posted state transitions, plus the immutable-posted rule.
 *
 * Phase 1 introduces the trait and the enum; consuming modules wire it up
 * when transactional models are built.
 */
trait TransactionStateMachine
{
    /**
     * The status column used by the consuming model.
     */
    public function statusColumn(): string
    {
        return 'status';
    }

    public function canTransitionTo(string $target): bool
    {
        $current = $this->{$this->statusColumn()};

        $allowed = [
            'draft' => ['submitted', 'cancelled'],
            'submitted' => ['pending_approval', 'draft'],
            'pending_approval' => ['approved', 'rejected'],
            'approved' => ['posted', 'rejected'],
            'rejected' => ['draft', 'cancelled'],
        ];

        return in_array($target, $allowed[$current] ?? [], true);
    }

    public function transitionTo(string $status, ?int $actorId = null): static
    {
        $target = \App\Support\Enums\TransactionStatus::tryFrom($status);

        abort_unless($target !== null, 422, "Invalid status [{$status}].");
        abort_unless($this->{$this->statusColumn()} !== 'posted', 422, 'Posted transactions are immutable.');
        abort_unless($this->canTransitionTo($status), 422, "Cannot transition [{$this->{$this->statusColumn()}}] to [{$status}].");

        $this->{$this->statusColumn()} = $status;
        $this->save();

        return $this;
    }
}
