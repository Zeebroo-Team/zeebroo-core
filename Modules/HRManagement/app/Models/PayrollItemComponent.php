<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemComponent extends Model
{
    protected $table = 'hr_payroll_item_components';

    protected $fillable = [
        'payroll_item_id',
        'rule_id',
        'code',
        'name',
        'component_type',
        'quantity',
        'rate',
        'amount',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PayrollRule::class, 'rule_id');
    }
}
