<?php

namespace App\Domain\Rbac\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'roles';

    protected $fillable = [
        'company_id', 'name', 'slug', 'description', 'is_system',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_roles');
    }
}
