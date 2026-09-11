<?php

declare(strict_types=1);

namespace Modules\Budget\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    protected $table = 'budget_items';

    protected $fillable = [
        'budget_id',
        'category',
        'label',
        'monthly_amount',
        'yearly_amount',
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'yearly_amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
