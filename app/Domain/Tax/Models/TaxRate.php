<?php

namespace App\Domain\Tax\Models;

use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'tax_rates';

    protected $fillable = [
        'company_id', 'tax_type_id', 'name', 'rate_percent', 'is_inclusive',
        'input_account_id', 'output_account_id', 'effective_date',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'rate_percent' => 'string',
        'is_inclusive' => 'boolean',
        'effective_date' => 'date',
    ];

    public function taxType()
    {
        return $this->belongsTo(TaxType::class);
    }

    public function inputAccount()
    {
        return $this->belongsTo(\App\Domain\Accounting\Models\Account::class, 'input_account_id');
    }

    public function outputAccount()
    {
        return $this->belongsTo(\App\Domain\Accounting\Models\Account::class, 'output_account_id');
    }
}