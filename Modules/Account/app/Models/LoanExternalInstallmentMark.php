<?php

namespace Modules\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExternalInstallmentMark extends Model
{
    protected $table = 'loan_external_installment_marks';

    protected $fillable = [
        'loan_id',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
