<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStage extends Model
{
    protected $table = 'crm_lead_stages';

    public const STATUS_NEW                 = 'new';
    public const STATUS_ASSIGNED            = 'assigned';
    public const STATUS_CONTACTED           = 'contacted';
    public const STATUS_IN_PROCESS          = 'in_process';
    public const STATUS_QUOTATION_PREPARING = 'quotation_preparing';
    public const STATUS_PENDING_APPROVAL    = 'pending_approval';
    public const STATUS_QUOTATION_SENT      = 'quotation_sent';
    public const STATUS_NEGOTIATION         = 'negotiation';
    public const STATUS_ON_HOLD             = 'on_hold';
    public const STATUS_WON                 = 'won';
    public const STATUS_LOST                = 'lost';

    /**
     * The standard statuses every department shares, in pipeline order: key => [label, colour].
     * Each pipeline stage carries one of these keys; extra stages map onto one of them.
     */
    public const STATUSES = [
        self::STATUS_NEW                 => ['New',                 '#64748b'],
        self::STATUS_ASSIGNED            => ['Assigned',            '#0ea5e9'],
        self::STATUS_CONTACTED           => ['Contacted',           '#3b82f6'],
        self::STATUS_IN_PROCESS          => ['In Process',          '#6366f1'],
        self::STATUS_QUOTATION_PREPARING => ['Quotation Preparing', '#8b5cf6'],
        self::STATUS_PENDING_APPROVAL    => ['Pending Approval',    '#a855f7'],
        self::STATUS_QUOTATION_SENT      => ['Quotation Sent',      '#f59e0b'],
        self::STATUS_NEGOTIATION         => ['Negotiation',         '#f97316'],
        self::STATUS_ON_HOLD             => ['On Hold',             '#94a3b8'],
        self::STATUS_WON                 => ['Won',                 '#10b981'],
        self::STATUS_LOST                => ['Lost',                '#ef4444'],
    ];

    /** Set only by the quotation workflow, never by moving a lead by hand. */
    public const RESERVED_STATUSES = [self::STATUS_PENDING_APPROVAL, self::STATUS_QUOTATION_SENT];

    protected $fillable = [
        'project_id',
        'name',
        'system_key',
        'color',
        'is_won',
        'is_lost',
        'requires_reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_won'          => 'boolean',
            'is_lost'         => 'boolean',
            'requires_reason' => 'boolean',
            'sort_order'      => 'integer',
        ];
    }

    /** @return list<array{key:string,label:string,color:string,reserved:bool}> */
    public static function statusOptions(): array
    {
        return array_map(
            fn (string $key, array $s) => [
                'key'      => $key,
                'label'    => $s[0],
                'color'    => $s[1],
                'reserved' => in_array($key, self::RESERVED_STATUSES, true),
            ],
            array_keys(self::STATUSES),
            self::STATUSES,
        );
    }

    public static function statusLabel(?string $key): ?string
    {
        return $key !== null ? (self::STATUSES[$key][0] ?? null) : null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    public function isWonStage(): bool
    {
        return $this->is_won || $this->system_key === self::STATUS_WON;
    }

    public function isLostStage(): bool
    {
        return $this->is_lost || $this->system_key === self::STATUS_LOST;
    }

    public function isOnHold(): bool
    {
        return $this->system_key === self::STATUS_ON_HOLD;
    }

    public function isReserved(): bool
    {
        return in_array($this->system_key, self::RESERVED_STATUSES, true);
    }
}
