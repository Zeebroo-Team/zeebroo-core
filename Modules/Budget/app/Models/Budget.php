<?php

declare(strict_types=1);

namespace Modules\Budget\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;

class Budget extends Model
{
    protected $table = 'budgets';

    public const TYPE_MONTHLY = 'monthly';

    public const TYPE_YEARLY = 'yearly';

    public const VIEW_DAILY = 'daily';

    public const VIEW_MONTHLY = 'monthly';

    public const VIEW_YEARLY = 'yearly';

    public const CATEGORIES = [
        'purchasing',
        'sales',
        'renovation',
        'payroll',
        'bill',
        'loans',
        'marketing',
        'rental',
    ];

    protected $fillable = [
        'business_id',
        'created_by_user_id',
        'name',
        'type',
        'start_date',
        'end_date',
        'view_period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function actualEntries(): HasMany
    {
        return $this->hasMany(BudgetActualEntry::class);
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_MONTHLY => __('Monthly'),
            self::TYPE_YEARLY => __('Yearly'),
        ];
    }

    /** @return array<string, string> */
    public static function viewPeriodLabels(): array
    {
        return [
            self::VIEW_DAILY => __('Daily'),
            self::VIEW_MONTHLY => __('Monthly'),
            self::VIEW_YEARLY => __('Yearly'),
        ];
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return [
            'purchasing' => __('Purchasing'),
            'sales' => __('Sales'),
            'renovation' => __('Renovation'),
            'payroll' => __('Payroll'),
            'bill' => __('Bill'),
            'loans' => __('Loans'),
            'marketing' => __('Marketing'),
            'rental' => __('Rental'),
        ];
    }

    /**
     * The budget's end date is derived from its type and start date:
     * a monthly budget spans one calendar month, a yearly budget one calendar year.
     */
    public static function computeEndDate(string $type, \DateTimeInterface|string $startDate): Carbon
    {
        $start = Carbon::parse($startDate);

        return $type === self::TYPE_YEARLY
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonth()->subDay();
    }
}
