<?php

namespace App\Domain\Accounting\Models;

use App\Support\Concerns\HasAuditFields;
use App\Support\Enums\AccountType;
use App\Support\Enums\NormalBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $table = 'accounts';

    protected $fillable = [
        'company_id', 'code', 'name', 'name_bn', 'type', 'parent_id', 'level',
        'normal_balance', 'is_system', 'is_active', 'is_postable',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_postable' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Domain\Company\Models\Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function typeValue(): AccountType
    {
        return AccountType::from($this->type);
    }

    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    /**
     * All descendant ids, including nested children (used to prevent cycles).
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return array_values(array_unique($ids));
    }

    public function fullCodePath(): string
    {
        $codes = [];
        $account = $this;

        while ($account) {
            $codes[] = $account->code;
            $account = $account->parent;
        }

        return implode('.', array_reverse($codes));
    }
}