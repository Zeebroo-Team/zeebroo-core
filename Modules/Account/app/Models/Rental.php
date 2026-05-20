<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class Rental extends Model
{
    public const RECURRING_PER_DAY = 'per_day';

    public const RECURRING_PER_MONTH = 'per_month';

    public const RECURRING_PER_YEAR = 'per_year';

    protected $fillable = [
        'user_id',
        'business_id',
        'branch_id',
        'address_book_id',
        'property_type',
        'purpose',
        'key_money',
        'agreement_valid_until_year',
        'deduct_account_id',
        'recurring_cost',
        'recurring_type',
        'remind_before_days',
        'notes',
        'due_date',
        'first_installment_due_date',
    ];

    protected function casts(): array
    {
        return [
            'key_money' => 'decimal:2',
            'recurring_cost' => 'decimal:2',
            'agreement_valid_until_year' => 'integer',
            'remind_before_days' => 'integer',
            'due_date' => 'date',
            'first_installment_due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function deductAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deduct_account_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(AddressBook::class, 'address_book_id');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable')
            ->orderByDesc('occurrence_date');
    }

    public static function recurringTypes(): array
    {
        return [
            self::RECURRING_PER_DAY => 'Per day',
            self::RECURRING_PER_MONTH => 'Per month',
            self::RECURRING_PER_YEAR => 'Per year',
        ];
    }
}
