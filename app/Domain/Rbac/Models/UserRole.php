<?php

namespace App\Domain\Rbac\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $table = 'user_roles';

    protected $fillable = [
        'user_id', 'role_id', 'company_id', 'branch_id',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
