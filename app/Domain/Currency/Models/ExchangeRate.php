<?php

namespace App\Domain\Currency\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'exchange_rates';

    protected $fillable = [
        'currency_id', 'rate', 'effective_date',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}