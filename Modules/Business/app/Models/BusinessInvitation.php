<?php

namespace Modules\Business\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'business_invitations';

    protected $fillable = [
        'business_id',
        'email',
        'role',
        'permissions',
        'token_hash',
        'status',
        'invited_by',
        'accepted_by',
        'expires_at',
        'last_sent_at',
        'accepted_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Pending and not yet expired — the link still works. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }
}
