<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;

class Lead extends Model
{
    protected $table = 'crm_leads';

    /** Where a lead came from: key => [label, Font Awesome icon classes, colour]. */
    public const SOURCES = [
        'manual'      => ['Manual entry', 'fa fa-keyboard',         '#64748b'],
        'facebook'    => ['Facebook',     'fa-brands fa-facebook',  '#1877f2'],
        'instagram'   => ['Instagram',    'fa-brands fa-instagram', '#e1306c'],
        'whatsapp'    => ['WhatsApp',     'fa-brands fa-whatsapp',  '#25d366'],
        'message'     => ['Message / SMS', 'fa fa-comment-sms',     '#8b5cf6'],
        'email'       => ['Email',        'fa fa-envelope',         '#0ea5e9'],
        'phone'       => ['Phone call',   'fa fa-phone',            '#14b8a6'],
        'website'     => ['Website',      'fa fa-globe',            '#2563eb'],
        'public-form' => ['Web form',     'fa fa-file-lines',       '#6366f1'],
        'csv'         => ['CSV import',   'fa fa-file-csv',         '#16a34a'],
        'referral'    => ['Referral',     'fa fa-user-group',       '#f59e0b'],
        'walk-in'     => ['Walk-in',      'fa fa-person-walking',   '#ea580c'],
        'social'      => ['Social media', 'fa fa-hashtag',          '#db2777'],
        'other'       => ['Other',        'fa fa-tag',              '#94a3b8'],
    ];

    public const SOURCE_MANUAL = 'manual';

    public static function sourceLabel(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        return self::SOURCES[$source][0] ?? ucfirst(str_replace('-', ' ', $source));
    }

    /** @return list<array{key:string,label:string,icon:string,color:string}> */
    public static function sourceOptions(): array
    {
        return array_map(
            fn (string $key, array $s) => ['key' => $key, 'label' => $s[0], 'icon' => $s[1], 'color' => $s[2]],
            array_keys(self::SOURCES),
            self::SOURCES,
        );
    }

    protected $fillable = [
        'business_id',
        'project_id',
        'form_id',
        'name',
        'company',
        'email',
        'phone',
        'source',
        'stage_id',
        'estimated_value',
        'expected_close_date',
        'lost_reason',
        'notes',
        'assigned_to',
        'created_by',
        'customer_id',
        'converted_at',
    ];

    protected $casts = [
        'estimated_value'     => 'decimal:2',
        'expected_close_date' => 'date',
        'converted_at'        => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(LeadForm::class, 'form_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'stage_id');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(LeadCustomFieldValue::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->orderByDesc('occurred_at');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject')->orderBy('due_at');
    }

    public function isWon(): bool
    {
        return (bool) $this->stage?->is_won;
    }

    public function isLost(): bool
    {
        return (bool) $this->stage?->is_lost;
    }

    public function isOpen(): bool
    {
        return !$this->isWon() && !$this->isLost();
    }

    public function stageLabel(): string
    {
        return $this->stage?->name ?? 'Unstaged';
    }

    public function stageColor(): string
    {
        return $this->stage?->color ?: '#64748b';
    }
}
