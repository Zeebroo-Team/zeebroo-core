<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadCustomField extends Model
{
    protected $table = 'crm_lead_custom_fields';

    protected $appends = ['name'];

    const TYPE_TEXT     = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_NUMBER   = 'number';
    const TYPE_DATE     = 'date';
    const TYPE_SELECT   = 'select';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_RADIO    = 'radio';
    const TYPE_CHECKBOX_GROUP = 'checkbox_group';

    /**
     * Field types whose value is chosen from a fixed `options` list rather than
     * typed freely — these are the only types that need the options textarea in
     * every "add/edit custom field" UI, and store multiple values as an array.
     */
    const MULTI_VALUE_TYPES = [self::TYPE_CHECKBOX_GROUP];
    const OPTION_TYPES      = [self::TYPE_SELECT, self::TYPE_RADIO, self::TYPE_CHECKBOX_GROUP];

    protected $fillable = [
        'project_id',
        'label',
        'type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options'     => 'array',
            'is_required' => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(LeadCustomFieldValue::class, 'custom_field_id');
    }

    public static function types(): array
    {
        return [
            self::TYPE_TEXT     => 'Text',
            self::TYPE_TEXTAREA => 'Long text',
            self::TYPE_NUMBER   => 'Number',
            self::TYPE_DATE     => 'Date',
            self::TYPE_SELECT   => 'Dropdown',
            self::TYPE_CHECKBOX => 'Checkbox (Yes/No)',
            self::TYPE_RADIO    => 'Radio buttons',
            self::TYPE_CHECKBOX_GROUP => 'Checkboxes (multiple choice)',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->label;
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ucfirst($this->type);
    }

    public function optionList(): array
    {
        return array_values(array_filter((array) ($this->options ?? []), fn ($o) => trim((string) $o) !== ''));
    }

    public function isMultiValue(): bool
    {
        return in_array($this->type, self::MULTI_VALUE_TYPES, true);
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }

    /**
     * Split a stored comma-joined multi-value (see LeadCustomFieldValue) back into
     * its selected options, e.g. for re-checking boxes when editing a lead.
     *
     * @return array<int, string>
     */
    public static function splitStoredValue(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== ''));
    }
}
