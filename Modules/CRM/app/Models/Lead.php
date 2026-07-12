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

    protected $fillable = [
        'business_id',
        'project_id',
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
