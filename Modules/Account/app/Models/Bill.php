<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\Department;
use Modules\Modification\Models\Modification;
use Modules\Transaction\Models\LedgerTransaction;

class Bill extends Model
{
    public const RECURRING_PER_DAY = 'per_day';

    public const RECURRING_PER_MONTH = 'per_month';

    public const RECURRING_PER_YEAR = 'per_year';

    public const PAYMENT_MODE_RECURRING = 'recurring';

    public const PAYMENT_MODE_ONE_TIME = 'one_time';

    public const CATEGORY_WATER = 'water';

    public const CATEGORY_ELECTRICITY = 'electricity';

    public const CATEGORY_TELEPHONE = 'telephone';

    public const CATEGORY_INTERNET = 'internet';

    public const CATEGORY_GAS = 'gas';

    public const CATEGORY_WASTE = 'waste';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'business_id',
        'rental_property_related',
        'rental_id',
        'property_id',
        'employee_id',
        'modification_id',
        'branch_id',
        'department_id',
        'name',
        'payment_mode',
        'bill_category',
        'bill_category_other',
        'description',
        'agreement_valid_until_year',
        'deduct_account_id',
        'recurring_cost',
        'recurring_type',
        'amount_varies_by_usage',
        'allow_split_payment',
        'remind_before_days',
        'due_date',
        'first_installment_due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'agreement_valid_until_year' => 'integer',
            'recurring_cost' => 'decimal:2',
            'remind_before_days' => 'integer',
            'due_date' => 'date',
            'first_installment_due_date' => 'date',
            'rental_property_related' => 'boolean',
            'amount_varies_by_usage' => 'boolean',
            'allow_split_payment' => 'boolean',
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

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function modification(): BelongsTo
    {
        return $this->belongsTo(Modification::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function deductAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deduct_account_id');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable')
            ->orderByDesc('occurrence_date');
    }

    public function isOneTime(): bool
    {
        return $this->payment_mode === self::PAYMENT_MODE_ONE_TIME;
    }

    /** @return array<string, string> */
    public static function billCategories(): array
    {
        return [
            self::CATEGORY_WATER => 'Water',
            self::CATEGORY_ELECTRICITY => 'Electricity',
            self::CATEGORY_TELEPHONE => 'Telephone',
            self::CATEGORY_INTERNET => 'Internet',
            self::CATEGORY_GAS => 'Gas',
            self::CATEGORY_WASTE => 'Waste / sanitation',
            self::CATEGORY_OTHER => 'Other (specify)',
        ];
    }

    public function categoryDisplayLabel(): string
    {
        $map = self::billCategories();
        if ($this->bill_category === self::CATEGORY_OTHER) {
            $custom = trim((string) $this->bill_category_other);

            return $custom !== '' ? $custom : ($map[self::CATEGORY_OTHER] ?? 'Other');
        }

        return $map[$this->bill_category] ?? (string) $this->bill_category;
    }

    /** @return array<string, string> */
    public static function paymentModes(): array
    {
        return [
            self::PAYMENT_MODE_RECURRING => 'Recurring',
            self::PAYMENT_MODE_ONE_TIME => 'One-time payment',
        ];
    }

    /** @return array<string, string> */
    public static function recurringTypes(): array
    {
        return [
            self::RECURRING_PER_DAY => 'Per day',
            self::RECURRING_PER_MONTH => 'Per month',
            self::RECURRING_PER_YEAR => 'Per year',
        ];
    }
}
