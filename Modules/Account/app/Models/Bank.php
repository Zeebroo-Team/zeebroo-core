<?php

namespace Modules\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
