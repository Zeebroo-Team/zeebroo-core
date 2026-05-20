<?php

namespace Modules\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'slug',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
