<?php

namespace App\Domain\Company\Models;

use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\AccountingBasis;
use App\Support\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'companies';

    protected $fillable = [
        'name', 'legal_name', 'logo_path', 'country_code', 'currency_code',
        'tax_registration_no', 'vat_registration_no', 'accounting_basis',
        'status', 'settings', 'email', 'phone', 'address', 'city', 'state',
        'zip_code', 'fiscal_year_start', 'fiscal_year_end',
        'database_name', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'fiscal_year_start' => 'date',
        'fiscal_year_end' => 'date',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }

    public function accountingBasis(): AccountingBasis
    {
        return AccountingBasis::from($this->accounting_basis);
    }

    public function statusValue(): CompanyStatus
    {
        return CompanyStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active->value;
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
