<?php

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Account\Models\Account;

class Branch extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'description',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'branch_id');
    }
}
