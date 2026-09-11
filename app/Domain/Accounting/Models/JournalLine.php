<?php

namespace App\Domain\Accounting\Models;

use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use HasFactory, HasAuditFields;

    protected $table = 'journal_lines';

    protected $fillable = [
        'journal_id', 'account_id', 'party_type', 'party_id', 'description',
        'debit', 'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->whereHas('journal', fn ($q) => $q->where('company_id', $companyId));
    }

    public function scopePosted($query)
    {
        return $query->whereHas('journal', fn ($q) => $q->where('status', 'posted'));
    }
}