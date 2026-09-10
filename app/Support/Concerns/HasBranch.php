<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasBranch
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Company\Models\Branch::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where($this->getTable() . '.branch_id', $branchId);
    }
}
