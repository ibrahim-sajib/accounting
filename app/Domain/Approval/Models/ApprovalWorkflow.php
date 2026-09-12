<?php

namespace App\Domain\Approval\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use BelongsToCompany;

    protected $table = 'approval_workflows';

    protected $fillable = [
        'company_id',
        'module',
        'name',
        'min_amount',
        'max_amount',
        'approver_role_id',
        'approver_user_id',
        'sequence',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:4',
        'max_amount' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForCompany($q, ?int $companyId = null)
    {
        return $q->where('company_id', $companyId ?? current_company_id());
    }

    public function approverRole()
    {
        return $this->belongsTo(\App\Domain\Rbac\Models\Role::class, 'approver_role_id');
    }

    public function approverUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'approver_user_id');
    }
}