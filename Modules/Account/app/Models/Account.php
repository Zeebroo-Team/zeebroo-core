<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'business_id',
        'branch_id',
        'account_name',
        'bank_type_id',
        'bank_id',
        'bank_name',
        'bank_account_number',
        'branch',
        'current_balance',
        'bank_officer_contact',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankType(): BelongsTo
    {
        return $this->belongsTo(BankType::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Business warehouse/site when multi-location mode is enabled (distinct from bank branch field `branch`). */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Plain-text label for selects (loan deduct-from-account, etc.): name, bank, type, balance.
     */
    public function deductOptionLabel(): string
    {
        $segments = [];

        $name = trim((string) $this->account_name);
        if ($name !== '') {
            $segments[] = $name;
        }

        $bankName = trim((string) ($this->bank?->name ?: $this->bank_name ?: ''));
        if ($bankName !== '') {
            $segments[] = $bankName;
        }

        $typeName = trim((string) ($this->bankType?->name ?? ''));
        if ($typeName !== '') {
            $segments[] = $typeName;
        }

        $balance = number_format((float) $this->current_balance, 2);
        $segments[] = 'Balance '.$balance;

        $loc = trim((string) ($this->warehouse?->name ?? ''));
        if ($loc !== '') {
            $segments[] = $loc;
        }

        return implode(' · ', $segments);
    }
}
