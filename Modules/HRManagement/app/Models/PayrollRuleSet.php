<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class PayrollRuleSet extends Model
{
    protected $table = 'hr_payroll_rule_sets';

    protected $fillable = [
        'business_id',
        'name',
        'currency',
        'effective_from',
        'effective_to',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PayrollRule::class, 'rule_set_id')->orderBy('sort_order')->orderBy('id');
    }
}
