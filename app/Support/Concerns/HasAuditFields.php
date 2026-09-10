<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically stamp created_by / updated_by on models
 * that expose those columns.
 */
trait HasAuditFields
{
    public static function bootHasAuditFields(): void
    {
        static::creating(function (Model $model) {
            if (in_array('created_by', $model->getFillable(), true) && ! $model->created_by) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function (Model $model) {
            if (in_array('updated_by', $model->getFillable(), true)) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
