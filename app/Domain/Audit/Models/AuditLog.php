<?php

namespace App\Domain\Audit\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id', 'user_id', 'module', 'action', 'record_type',
        'record_id', 'old_values', 'new_values', 'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Append-only log: no created_by/updated_by stamping.
    public const UPDATED_AT = null;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}