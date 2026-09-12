<?php

namespace App\Domain\FixedAsset\Models;

use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetDisposal extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'asset_disposals';

    protected $fillable = [
        'company_id', 'fixed_asset_id', 'disposal_date', 'proceeds',
        'book_value', 'gain_loss_amount', 'journal_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'proceeds' => 'decimal:4',
        'book_value' => 'decimal:4',
        'gain_loss_amount' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function gains(): bool
    {
        return $this->gain_loss_amount > 0;
    }
}