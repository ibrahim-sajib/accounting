<?php

namespace App\Domain\Rbac\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCompanyAccess extends Model
{
    use HasFactory;

    protected $table = 'user_company_access';

    protected $fillable = [
        'user_id', 'company_id', 'branch_id', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Branch::class);
    }
}
