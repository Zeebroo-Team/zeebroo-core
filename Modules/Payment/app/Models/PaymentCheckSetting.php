<?php

namespace Modules\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-editable config for the scheduled Stripe payment check (single row),
 * plus webhook health — when Stripe last reached us.
 */
class PaymentCheckSetting extends Model
{
    public const DEFAULT_INTERVAL = 20;

    public const MIN_INTERVAL = 5;

    public const MAX_INTERVAL = 1440;

    protected $fillable = [
        'is_enabled',
        'interval_minutes',
        'last_run_at',
        'last_webhook_at',
        'last_webhook_event',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'last_run_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->oldest('id')->first()
            ?? static::query()->create(['is_enabled' => true, 'interval_minutes' => self::DEFAULT_INTERVAL]);
    }

    /** Never lets webhook-health bookkeeping break webhook processing. */
    public static function recordWebhook(string $eventType): void
    {
        try {
            static::current()->update(['last_webhook_at' => now(), 'last_webhook_event' => $eventType]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function isDue(): bool
    {
        return $this->is_enabled
            && ($this->last_run_at === null || $this->last_run_at->copy()->addMinutes($this->interval_minutes)->lte(now()));
    }

    public function nextRunAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->is_enabled) {
            return null;
        }

        return $this->last_run_at?->copy()->addMinutes($this->interval_minutes) ?? now();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
