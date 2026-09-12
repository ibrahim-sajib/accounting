<?php

namespace App\Domain\Document\Models;

use App\Domain\Accounting\Models\Journal;
use App\Domain\Expense\Models\Expense;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'attachable_type',
        'attachable_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whitelist of attachable models → the permission slug mutation requires.
     */
    public const ALLOWED_TYPES = [
        SalesInvoice::class => 'sales.update',
        PurchaseBill::class => 'purchase.update',
        Expense::class => 'expense.update',
        Journal::class => 'journal.update',
    ];
}