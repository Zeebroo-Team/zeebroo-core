<?php

declare(strict_types=1);

namespace Modules\Budget\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetActualEntry extends Model
{
    protected $table = 'budget_actual_entries';

    protected $fillable = [
        'budget_id',
        'category',
        'month',
        'amount',
        'note',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
