<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalance extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'opening_balances';

    protected $fillable = [
        'company_id', 'fiscal_year_id', 'account_id', 'debit', 'credit',
        'status', 'journal_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function scopeFiscalYear($query, int $fiscalYearId)
    {
        return $query->where('fiscal_year_id', $fiscalYearId);
    }
}