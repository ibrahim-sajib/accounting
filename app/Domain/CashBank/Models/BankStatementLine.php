<?php

namespace App\Domain\CashBank\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankStatementLine extends Model
{
    use HasFactory;

    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'bank_statement_import_id',
        'line_date',
        'description',
        'amount',
        'matched_transaction_id',
        'is_reconciled',
    ];

    protected $casts = [
        'amount' => 'float',
        'line_date' => 'date',
        'is_reconciled' => 'boolean',
    ];

    public function import()
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function matchedTransaction()
    {
        return $this->belongsTo(CashBankTransaction::class, 'matched_transaction_id');
    }
}