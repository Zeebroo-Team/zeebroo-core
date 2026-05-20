<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class PayrollCycle extends Model
{
    protected $table = 'hr_payroll_cycles';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPUTED = 'computed';

    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'business_id',
        'rule_set_id',
        'name',
        'year',
        'month',
        'period_start',
        'period_end',
        'status',
        'computed_at',
        'finalized_at',
        'finalized_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'computed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(PayrollRuleSet::class, 'rule_set_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_cycle_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable')
            ->orderByDesc('occurrence_date');
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
