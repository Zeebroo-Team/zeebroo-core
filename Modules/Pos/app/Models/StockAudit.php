<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class StockAudit extends Model
{
    public const STATUS_OPEN      = 'open';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'business_id', 'branch_id', 'audit_number', 'audit_date',
        'status', 'notes', 'finalized_at', 'finalized_by', 'created_by',
    ];

    protected $casts = [
        'audit_date'   => 'date',
        'finalized_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockAuditLine::class)->orderBy('sort_order')->orderBy('product_name');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_FINALIZED => 'Finalized',
            default                => 'Open',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_FINALIZED => '#10b981',
            default                => '#3b82f6',
        };
    }

    public function countedLinesCount(): int
    {
        return $this->lines->filter(fn ($l) => $l->counted_qty !== null)->count();
    }

    public function totalLinesCount(): int
    {
        return $this->lines->count();
    }

    public function varianceLinesCount(): int
    {
        return $this->lines->filter(fn ($l) => $l->counted_qty !== null && round($l->variance(), 3) !== 0.0)->count();
    }
}
