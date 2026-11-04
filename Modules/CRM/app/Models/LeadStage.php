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

    /**
     * Icon (Font Awesome name), a plain-language note on what each standard status means, and
     * what the system does with a lead in a stage of that status (shown when picking a status).
     */
    public const STATUS_GUIDE = [
        self::STATUS_NEW                 => ['fa-inbox',          'Fresh lead that nobody has picked up yet. Every new lead lands here first.', [
            'Every new lead starts here — added by hand, imported or from the website',
            'Waits in the Marketing inbox until someone picks it up',
            'Counts as an open lead in reports',
        ]],
        self::STATUS_ASSIGNED            => ['fa-user-check',     'A team member now owns the lead but has not reached out yet.', [
            'A lead in "New" moves here by itself when someone is assigned',
            'Can also be moved here by hand',
            'Counts as an open lead in reports',
        ]],
        self::STATUS_CONTACTED           => ['fa-phone',          'First call, message or email done — the conversation has started.', [
            'Moved here by hand after the first call, message or email',
            'Nothing automatic — it marks progress',
            'Counts as an open lead in reports',
        ]],
        self::STATUS_IN_PROCESS          => ['fa-gears',          'Actively working the lead: meetings, site visits, gathering needs.', [
            'Moved here by hand while the work goes on',
            'Nothing automatic — use it for meetings, visits, demos',
            'Counts as an open lead in reports',
        ]],
        self::STATUS_QUOTATION_PREPARING => ['fa-file-pen',       'A quotation is being drafted for this lead.', [
            'Lead moves here by itself when a quotation is created or revised',
            'Comes back here when a manager returns or rejects the quotation',
            'Counts as an open lead in reports',
        ]],
        self::STATUS_PENDING_APPROVAL    => ['fa-hourglass-half', 'Automatic: the quotation waits for a manager to approve it.', [
            'Lead moves here by itself when a quotation is sent for approval',
            'Managers see it in their approval queue',
            'Cannot be moved here by hand (unless you switch that off)',
        ]],
        self::STATUS_QUOTATION_SENT      => ['fa-paper-plane',    'Automatic: the approved quotation has gone to the customer.', [
            'Lead moves here by itself when the approved quotation is sent',
            'Lead score goes up — it shows as a warmer lead',
            'Cannot be moved here by hand (unless you switch that off)',
        ]],
        self::STATUS_NEGOTIATION         => ['fa-handshake',      'Discussing price, terms or changes before the customer decides.', [
            'Lead moves here by itself when the customer asks for a discount',
            'Can also be moved here by hand',
            'Lead score goes up — it shows as a warmer lead',
        ]],
        self::STATUS_ON_HOLD             => ['fa-circle-pause',   'Paused for now — the customer asked to wait or is not ready.', [
            'Turns on the "Put on hold" button on each lead',
            'Remembers the stage it came from — "Resume" takes it back there',
            'Still an open lead — not counted as lost',
        ]],
        self::STATUS_WON                 => ['fa-trophy',         'Deal closed. Counts as a win in reports and conversion rates.', [
            'Closes the lead — it leaves the open list',
            'Counts as a win; its deal value adds to won revenue',
            'Can be reopened later if needed',
        ]],
        self::STATUS_LOST                => ['fa-circle-xmark',   'Deal did not happen. A lost reason is always asked for.', [
            'Closes the lead — it leaves the open list',
            'Asks for a reason from your lost-reason list (can be made optional)',
            'Counts as a loss in reports; can be reopened later',
        ]],
    ];

    /**
     * What a "Custom" stage (is_custom) can count as: it stays open, closes the lead as won or
     * lost, or pauses it. Its other behaviour comes from its ticks (needs_approval, locks_lead, …).
     */
    public const CUSTOM_KEYS = [self::STATUS_IN_PROCESS, self::STATUS_WON, self::STATUS_LOST, self::STATUS_ON_HOLD];

    /** Set only by the quotation workflow, never by moving a lead by hand. */
    public const RESERVED_STATUSES = [self::STATUS_PENDING_APPROVAL, self::STATUS_QUOTATION_SENT];

    /**
     * What a stage does by itself because of its standard status: key => [label, note, statuses
     * it comes with, can be switched off]. Switched-off keys are kept in disabled_behaviours.
     */
    public const BUILT_IN_BEHAVIOURS = [
        'lands_new'        => ['New leads land here', 'Every new lead starts in this stage, so it cannot be deleted while it is the only one.', [self::STATUS_NEW], false],
        'auto_from_assign' => ['Moves here when someone is assigned', 'A lead still in "New" moves here by itself as soon as someone is assigned to it.', [self::STATUS_ASSIGNED], true],
        'workflow_only'    => ['Set by the quotation workflow only', 'Leads cannot be moved here by hand — the quotation workflow moves them. Switch off to allow moving by hand too.', self::RESERVED_STATUSES, true],
        'remember_from'    => ['Remembers where the lead paused', 'Keeps the stage the lead came from, so "Resume" takes it back there.', [self::STATUS_ON_HOLD], true],
        'closes_won'       => ['Closes the lead as won', 'Counts as a win in reports and conversion rates.', [self::STATUS_WON], false],
        'closes_lost'      => ['Closes the lead as lost', 'Counts as a loss in reports.', [self::STATUS_LOST], false],
        'lost_reason'      => ['Asks for a lost reason', 'A reason from your lost-reason list must be picked, so reports can group losses. Switch off to make it optional.', [self::STATUS_LOST], true],
    ];

    /** Lead details a stage can ask for when a lead is moved into it by hand: key => label. */
    public const ENTRY_FIELDS = [
        'follow_up_at'        => 'Follow-up date',
        'expected_close_date' => 'Expected close date',
        'estimated_value'     => 'Deal value',
    ];

    /** Empty entry rules: the stage does nothing beyond its standard status. */
    public const ENTRY_RULES_DEFAULT = [
        'require'   => [],                                           // keys of ENTRY_FIELDS
        'task'      => null,                                         // {title, due_days}
        'notify'    => ['owner' => false, 'head' => false, 'user_ids' => []],
        'assign_user_ids' => [],                                     // people added to the lead
        'max_days'  => null,                                         // flag leads that stay longer
    ];

    protected $fillable = [
        'project_id',
        'name',
        'system_key',
        'custom_status_id',
        'is_custom',
        'needs_approval',
        'locks_lead',
        'exit_checklist',
        'no_skip',
        'disabled_behaviours',
        'color',
        'icon',
        'description',
        'is_won',
        'is_lost',
        'requires_reason',
        'entry_rules',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_won'          => 'boolean',
            'is_lost'         => 'boolean',
            'requires_reason' => 'boolean',
            'is_custom'       => 'boolean',
            'needs_approval'  => 'boolean',
            'locks_lead'      => 'boolean',
            'exit_checklist'  => 'array',
            'no_skip'         => 'boolean',
            'disabled_behaviours' => 'array',
            'entry_rules'     => 'array',
            'sort_order'      => 'integer',
        ];
    }

    /**
     * What happens when a lead enters this stage, every key present (ENTRY_RULES_DEFAULT shape).
     *
     * @return array{require: list<string>, task: ?array{title:string,due_days:int}, notify: array{owner:bool,head:bool,user_ids:list<int>}, assign_user_ids: list<int>, max_days: ?int}
     */
    public function entryRules(): array
    {
        $rules = (array) ($this->entry_rules ?? []);
        // Older rules held a single "assign_to" user id
        $assign = (array) ($rules['assign_user_ids'] ?? (! empty($rules['assign_to']) ? [$rules['assign_to']] : []));

        return [
            'require'   => array_values(array_intersect(array_keys(self::ENTRY_FIELDS), (array) ($rules['require'] ?? []))),
            'task'      => ! empty($rules['task']['title'])
                ? ['title' => (string) $rules['task']['title'], 'due_days' => max(0, (int) ($rules['task']['due_days'] ?? 0))]
                : null,
            'notify'    => [
                'owner'    => (bool) ($rules['notify']['owner'] ?? false),
                'head'     => (bool) ($rules['notify']['head'] ?? false),
                'user_ids' => array_values(array_map('intval', (array) ($rules['notify']['user_ids'] ?? []))),
            ],
            'assign_user_ids' => array_values(array_unique(array_filter(array_map('intval', $assign)))),
            'max_days'  =>! empty($rules['max_days']) ? (int) $rules['max_days'] : null,
        ];
    }

    /** False when the stage's built-in behaviour (BUILT_IN_BEHAVIOURS key) was switched off. */
    public function behaviourOn(string $key): bool
    {
        return ! in_array($key, (array) ($this->disabled_behaviours ?? []), true);
    }

    /** Keys of BUILT_IN_BEHAVIOURS that come with a status and can be switched off. @return list<string> */
    public static function switchableBehaviours(?string $statusKey): array
    {
        return array_keys(array_filter(self::BUILT_IN_BEHAVIOURS, fn (array $b) => $b[3] && in_array($statusKey, $b[2], true)));
    }

    /**
     * The built-in behaviours this stage has through its standard status, and whether each is on.
     *
     * @return list<array{key:string,label:string,description:string,switchable:bool,on:bool}>
     */
    public function builtInBehaviours(): array
    {
        $out = [];
        foreach (self::BUILT_IN_BEHAVIOURS as $key => [$label, $note, $statuses, $switchable]) {
            if (in_array($this->system_key, $statuses, true)) {
                $out[] = ['key' => $key, 'label' => $label, 'description' => $note, 'switchable' => $switchable, 'on' => ! $switchable || $this->behaviourOn($key)];
            }
        }

        return $out;
    }

    /** Items to finish before a lead may leave this stage. @return list<string> */
    public function exitChecklist(): array
    {
        return array_values(array_filter(array_map(fn ($i) => trim((string) $i), (array) ($this->exit_checklist ?? [])), 'strlen'));
    }

    /** True when entering the stage does something (asks for details, creates a task, notifies, assigns). */
    public function hasEntryActions(): bool
    {
        $r = $this->entryRules();

        return $r['require'] !== [] || $r['task'] !== null || $r['assign_user_ids'] !== []
            || $r['notify']['owner'] || $r['notify']['head'] || $r['notify']['user_ids'] !== [];
    }

    /** @return list<array{key:string,label:string,color:string,icon:string,description:string,effects:list<string>,reserved:bool}> */
    public static function statusOptions(): array
    {
        return array_map(
            fn (string $key, array $s) => [
                'key'         => $key,
                'label'       => $s[0],
                'color'       => $s[1],
                'icon'        => self::statusIcon($key),
                'description' => self::STATUS_GUIDE[$key][1],
                'effects'     => self::STATUS_GUIDE[$key][2],
                'reserved'    => in_array($key, self::RESERVED_STATUSES, true),
            ],
            array_keys(self::STATUSES),
            self::STATUSES,
        );
    }

    public static function statusLabel(?string $key): ?string
    {
        return $key !== null ? (self::STATUSES[$key][0] ?? null) : null;
    }

    public static function statusIcon(?string $key): string
    {
        return self::STATUS_GUIDE[$key ?? ''][0] ?? 'fa-circle';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The business's own status this stage was made from, if any (it still counts as system_key). */
    public function customStatus(): BelongsTo
    {
        return $this->belongsTo(CustomStatus::class, 'custom_status_id');
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

    /** Only the quotation workflow moves leads here (unless that was switched off for the stage). */
    public function isReserved(): bool
    {
        return in_array($this->system_key, self::RESERVED_STATUSES, true) && $this->behaviourOn('workflow_only');
    }
}
