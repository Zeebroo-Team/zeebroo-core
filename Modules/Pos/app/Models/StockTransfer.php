<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;

class StockTransfer extends Model
{
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'business_id', 'from_branch_id', 'to_branch_id', 'transfer_number',
        'status', 'notes',
        'transferred_by', 'transferred_at',
        'received_by', 'received_at',
        'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'received_at'    => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default                => 'In Transit',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => '#10b981',
            self::STATUS_CANCELLED => '#ef4444',
            default                => '#3b82f6',
        };
    }
}
