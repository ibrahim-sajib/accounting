<?php

namespace App\Domain\Rbac\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'name', 'slug', 'module', 'action',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
