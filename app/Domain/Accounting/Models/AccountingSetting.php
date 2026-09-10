<?php

namespace App\Domain\Accounting\Models;

use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingSetting extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'accounting_settings';

    protected $fillable = [
        'company_id',
        'default_sales_account_id', 'default_purchase_account_id',
        'default_inventory_account_id', 'default_ar_account_id', 'default_ap_account_id',
        'default_cash_account_id', 'default_bank_account_id',
        'default_tax_input_account_id', 'default_tax_output_account_id',
        'voucher_numbering',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'voucher_numbering' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Company::class);
    }

    public function defaultSalesAccount()
    {
        return $this->belongsTo(Account::class, 'default_sales_account_id');
    }

    public function defaultPurchaseAccount()
    {
        return $this->belongsTo(Account::class, 'default_purchase_account_id');
    }

    public function defaultInventoryAccount()
    {
        return $this->belongsTo(Account::class, 'default_inventory_account_id');
    }

    public function defaultArAccount()
    {
        return $this->belongsTo(Account::class, 'default_ar_account_id');
    }

    public function defaultApAccount()
    {
        return $this->belongsTo(Account::class, 'default_ap_account_id');
    }

    public function defaultCashAccount()
    {
        return $this->belongsTo(Account::class, 'default_cash_account_id');
    }

    public function defaultBankAccount()
    {
        return $this->belongsTo(Account::class, 'default_bank_account_id');
    }

    public function defaultTaxInputAccount()
    {
        return $this->belongsTo(Account::class, 'default_tax_input_account_id');
    }

    public function defaultTaxOutputAccount()
    {
        return $this->belongsTo(Account::class, 'default_tax_output_account_id');
    }
}