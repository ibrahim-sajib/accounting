<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'journals';

    protected $fillable = [
        'company_id', 'branch_id', 'period_id', 'journal_no', 'journal_date',
        'source_type', 'source_id', 'reference', 'description', 'status',
        'posted_at', 'posted_by', 'reversed_journal_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Branch::class);
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function origin()
    {
        return $this->belongsTo(self::class, 'reversed_journal_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversed_journal_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by');
    }

    public function statusValue(): TransactionStatus
    {
        return TransactionStatus::from($this->status);
    }

    public function sourceTypeValue(): JournalSourceType
    {
        return JournalSourceType::from($this->source_type);
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::Draft->value;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}