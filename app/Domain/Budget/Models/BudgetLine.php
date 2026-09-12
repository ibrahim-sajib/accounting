<?php

namespace App\Domain\Budget\Models;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetLine extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'budget_lines';

    protected $fillable = [
        'budget_id', 'account_id', 'period_id', 'budgeted_amount',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:4',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }
}