<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class Investment extends Model
{
    public const PAYMENT_MODE_RECURRING = 'recurring';

    public const PAYMENT_MODE_ONE_TIME = 'one_time';

    public const RECURRING_PER_DAY = 'per_day';

    public const RECURRING_PER_MONTH = 'per_month';

    public const RECURRING_PER_YEAR = 'per_year';

    public const TYPE_OTHER = 'other';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MATURED = 'matured';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'business_id',
        'name',
        'investment_type',
        'investment_type_other',
        'provider',
        'reference_number',
        'description',
        'payment_mode',
        'recurring_type',
        'contribution_amount',
        'schedule_valid_until_year',
        'start_date',
        'maturity_date',
        'expected_return_rate',
        'target_amount',
        'deduct_account_id',
        'remind_before_days',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contribution_amount' => 'decimal:2',
            'schedule_valid_until_year' => 'integer',
            'start_date' => 'date',
            'maturity_date' => 'date',
            'expected_return_rate' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'remind_before_days' => 'integer',
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
    public static function investmentTypes(): array
    {
        return [
            'bank_investment' => 'Bank investment',
            'fixed_deposit' => 'Fixed deposit',
            'savings_plan' => 'Savings plan',
            'insurance' => 'Insurance',
            'retirement' => 'Retirement / pension fund',
            'shares' => 'Shares / stocks',
            'unit_trust' => 'Unit trust / mutual fund',
            'gold' => 'Gold / precious metals',
            'real_estate' => 'Real estate / land',
            self::TYPE_OTHER => 'Other (specify)',
        ];
    }

    public function typeDisplayLabel(): string
    {
        $map = self::investmentTypes();
        if ($this->investment_type === self::TYPE_OTHER) {
            $custom = trim((string) $this->investment_type_other);

            return $custom !== '' ? $custom : $map[self::TYPE_OTHER];
        }

        return $map[$this->investment_type] ?? (string) $this->investment_type;
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_MATURED => 'Matured',
            self::STATUS_CLOSED => 'Closed',
        ];
    }
}
