<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping trait.
 *
 * Every tenant-owned table carries a company_id (and optionally branch_id)
 * so that a user can never access another company's accounting data.
 */
trait BelongsToCompany
{
    /**
     * The active company context key stored on the session.
     */
    public static function companyScopeKey(): string
    {
        return 'active_company_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Company\Models\Company::class);
    }

    /**
     * Global scope: restrict every query to the given company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable() . '.company_id', $companyId);
    }

    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = session(self::companyScopeKey());

            if ($companyId) {
                $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
            }
        });
    }
}
