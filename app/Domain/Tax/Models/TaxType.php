<?php

namespace App\Domain\Tax\Models;

use App\Support\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxType extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'tax_types';

    protected $fillable = [
        'company_id', 'name', 'created_by', 'updated_by',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Company::class);
    }

    public function rates()
    {
        return $this->hasMany(TaxRate::class)->orderByDesc('effective_date');
    }
}