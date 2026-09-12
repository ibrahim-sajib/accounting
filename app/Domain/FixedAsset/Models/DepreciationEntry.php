<?php

namespace App\Domain\FixedAsset\Models;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepreciationEntry extends Model
{
    use HasFactory;

    protected $table = 'depreciation_entries';

    public $timestamps = true;

    protected $fillable = [
        'company_id', 'fixed_asset_id', 'period_id', 'monthly_amount',
        'accumulated_amount', 'journal_id', 'created_by',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:4',
        'accumulated_amount' => 'decimal:4',
    ];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}