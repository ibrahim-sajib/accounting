<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Rbac\Models\Permission;
use App\Domain\Rbac\Models\Role;
use App\Domain\Rbac\Models\UserCompanyAccess;
use App\Domain\Rbac\Models\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'company_id',
        'is_super_admin',
        'status',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'company_id' => 'integer',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('company_id', 'branch_id');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_roles', 'user_id', 'role_id')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id');
    }

    public function companyAccess()
    {
        return $this->hasMany(UserCompanyAccess::class);
    }

    public function accessibleCompanies()
    {
        return $this->belongsToMany(Company::class, 'user_company_access')
            ->withPivot('branch_id', 'is_default');
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function hasPermission(string $permissionSlug, ?int $companyId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $query = $this->roles();

        if ($companyId) {
            $query = $query->wherePivot('company_id', $companyId);
        }

        $roleIds = $query->pluck('roles.id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        // RBAC lives on the control-plane; tenants only mirror it. Read from
        // there even when the request's default connection is a tenant DB.
        $platform = app(\App\Domain\Tenant\Services\TenantManager::class)->platformConnection();

        return Permission::on($platform)
            ->join('role_permissions', function ($join) use ($roleIds) {
                $join->on('role_permissions.permission_id', '=', 'permissions.id')
                    ->whereIn('role_permissions.role_id', $roleIds);
            })
            ->where('permissions.slug', $permissionSlug)
            ->exists();
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function statusValue(): UserStatus
    {
        return UserStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active->value;
    }
}