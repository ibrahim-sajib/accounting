<?php

namespace App\Domain\CashBank\Models;

use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankStatementImport extends Model
{
    use HasFactory, BelongsToCompany, HasAuditFields;

    protected $table = 'bank_statement_imports';

    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_month',
        'file_path',
        'status',
        'imported_at',
        'completed_at',
        'imported_by',
        'completed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'statement_month' => 'date',
        'imported_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines()
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function matchedCount(): int
    {
        return $this->lines()->where('is_reconciled', true)->count();
    }

    public function totalCount(): int
    {
        return $this->lines()->count();
    }
}