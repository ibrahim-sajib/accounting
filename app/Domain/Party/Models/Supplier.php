<?php

namespace App\Domain\Party\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'suppliers';

    protected $fillable = [
        'company_id', 'code', 'name', 'email', 'phone', 'address', 'tax_no',
        'payment_terms_days', 'opening_balance', 'ap_account_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'payment_terms_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function apAccount()
    {
        return $this->belongsTo(Account::class, 'ap_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}