<?php

namespace App\Domain\Warehouse\Models;

use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'warehouses';

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'address', 'manager_user_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function manager()
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}