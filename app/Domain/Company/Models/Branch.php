<?php

namespace App\Domain\Company\Models;

use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\BranchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'branches';

    protected $fillable = [
        'company_id', 'code', 'name', 'address', 'city', 'state', 'zip_code',
        'country_code', 'email', 'phone', 'manager_user_id', 'status',
        'created_by', 'updated_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function manager()
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_user_id');
    }

    public function statusValue(): BranchStatus
    {
        return BranchStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === BranchStatus::Active->value;
    }
}
