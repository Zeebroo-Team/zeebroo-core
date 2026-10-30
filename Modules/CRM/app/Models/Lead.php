<?php

namespace Modules\CRM\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\PosCashier;
use Modules\Product\Models\Product;

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
        'google-form' => ['Google Form',  'fa-brands fa-google',    '#ea4335'],
        'csv'         => ['CSV import',   'fa fa-file-csv',         '#16a34a'],
        'referral'    => ['Referral',     'fa fa-user-group',       '#f59e0b'],
        'walk-in'     => ['Walk-in',      'fa fa-person-walking',   '#ea580c'],
        'social'      => ['Social media', 'fa fa-hashtag',          '#db2777'],
        'other'       => ['Other',        'fa fa-tag',              '#94a3b8'],
    ];

    public const SOURCE_MANUAL = 'manual';

    /** Manual hot / warm / cold rating: key => [label, colour]. */
    public const SCORES = [
        'hot'  => ['Hot',  '#ef4444'],
        'warm' => ['Warm', '#f59e0b'],
        'cold' => ['Cold', '#3b82f6'],
    ];

    /** Why a lead is flagged for Marketing to look at: key => label. */
    public const REVIEW_REASONS = [
        'duplicate_enquiry'  => 'Repeat enquiry',
        'existing_customer'  => 'Existing customer',
        'possible_duplicate' => 'Possible duplicate',
    ];

    public const REVIEW_DUPLICATE_ENQUIRY  = 'duplicate_enquiry';
    public const REVIEW_EXISTING_CUSTOMER  = 'existing_customer';
    public const REVIEW_POSSIBLE_DUPLICATE = 'possible_duplicate';

    public static function reviewReasonLabel(?string $reason): ?string
    {
        return $reason !== null ? (self::REVIEW_REASONS[$reason] ?? ucfirst(str_replace('_', ' ', $reason))) : null;
    }

    public static function scoreLabel(?string $score): ?string
    {
        return $score !== null ? (self::SCORES[$score][0] ?? null) : null;
    }

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
        'department_id',
        'division_id',
        'form_id',
        'name',
        'company',
        'email',
        'phone',
        'normalized_phone',
        'source',
        'source_id',
        'external_id',
        'stage_id',
        'estimated_value',
        'score',
        'expected_close_date',
        'lost_reason',
        'lost_reason_id',
        'notes',
        'product_interest_note',
        'assigned_to',
        'created_by',
        'customer_id',
        'converted_at',
        'verified_at',
        'verified_by',
        'first_response_at',
        'closed_at',
        'on_hold_from_stage_id',
        'needs_review',
        'review_reason',
        'duplicate_of_lead_id',
        'requested_department_id',
        'forwarded_at',
        'forwarded_by',
    ];

    protected $casts = [
        'estimated_value'     => 'decimal:2',
        'expected_close_date' => 'date',
        'converted_at'        => 'datetime',
        'verified_at'         => 'datetime',
        'first_response_at'   => 'datetime',
        'closed_at'           => 'datetime',
        'forwarded_at'        => 'datetime',
        'needs_review'        => 'boolean',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class, 'lost_reason_id');
    }

    public function onHoldFromStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'on_hold_from_stage_id');
    }

    /** Products the lead is interested in, with an optional quantity (crm_lead_products). */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'crm_lead_products', 'lead_id', 'product_id')
            ->withPivot('quantity')
            ->withTimestamps();
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function forwardedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'forwarded_by');
    }

    public function requestedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requested_department_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_lead_id');
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(LeadAssignmentLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->orderByDesc('occurred_at');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject')->orderBy('due_at');
    }

    /** Leads of this business the user may see (see LeadPolicy). */
    public function scopeVisibleTo(Builder $query, Authenticatable|PosCashier|null $user, Business $business): Builder
    {
        return $query->where('crm_leads.business_id', $business->id)
            ->visibleWith(app(CrmPermissionService::class)->accessFor($business, $user));
    }

    /** Same as visibleTo() for a caller that already resolved the user's CRM access. */
    public function scopeVisibleWith(Builder $query, CrmAccess $access): Builder
    {
        return app(LeadPolicy::class)->scopeVisible($query, $access);
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

    public function isOnHold(): bool
    {
        return (bool) $this->stage?->isOnHold();
    }

    /** The standard status (LeadStage::STATUSES key) of the lead's stage. */
    public function statusKey(): ?string
    {
        return $this->stage?->system_key;
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
