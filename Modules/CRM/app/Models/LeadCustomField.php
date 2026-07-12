<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadCustomField extends Model
{
    protected $table = 'crm_lead_custom_fields';

    const TYPE_TEXT     = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_NUMBER   = 'number';
    const TYPE_DATE     = 'date';
    const TYPE_SELECT   = 'select';
    const TYPE_CHECKBOX = 'checkbox';

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
            self::TYPE_CHECKBOX => 'Yes / No',
        ];
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ucfirst($this->type);
    }

    public function optionList(): array
    {
        return array_values(array_filter((array) ($this->options ?? []), fn ($o) => trim((string) $o) !== ''));
    }
}
