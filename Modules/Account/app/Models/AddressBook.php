<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressBook extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'street_address',
        'bank_account_details',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'address_book_id');
    }
}
