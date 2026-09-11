<?php

namespace App\Domain\Party\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'customers';

    protected $fillable = [
        'company_id', 'code', 'name', 'email', 'phone', 'address', 'tax_no',
        'credit_limit', 'payment_terms_days', 'opening_balance', 'ar_account_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:4',
        'opening_balance' => 'decimal:4',
        'payment_terms_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function arAccount()
    {
        return $this->belongsTo(Account::class, 'ar_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}