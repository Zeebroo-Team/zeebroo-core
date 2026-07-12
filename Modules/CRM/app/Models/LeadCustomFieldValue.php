<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadCustomFieldValue extends Model
{
    protected $table = 'crm_lead_custom_field_values';

    protected $fillable = [
        'lead_id',
        'custom_field_id',
        'value',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(LeadCustomField::class, 'custom_field_id');
    }

    public function displayValue(): string
    {
        $field = $this->customField;
        if (!$field) {
            return (string) $this->value;
        }

        return match ($field->type) {
            LeadCustomField::TYPE_CHECKBOX => $this->value ? 'Yes' : 'No',
            LeadCustomField::TYPE_DATE     => $this->value ? \Illuminate\Support\Carbon::parse($this->value)->format('M j, Y') : '',
            default                        => (string) $this->value,
        };
    }
}
