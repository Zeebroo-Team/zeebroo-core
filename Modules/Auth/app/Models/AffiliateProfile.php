<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An affiliate's contact, address, payout bank account and ID details. */
class AffiliateProfile extends Model
{
    public const ID_NIC = 'nic';

    public const ID_PASSPORT = 'passport';

    public const ID_TYPES = [
        self::ID_NIC => 'National ID card (NIC)',
        self::ID_PASSPORT => 'Passport',
    ];

    protected $fillable = [
        'user_id',
        'phone', 'phone_alt',
        'address_line1', 'address_line2', 'city', 'district', 'postal_code', 'country',
        'bank_name', 'bank_branch', 'account_holder_name', 'account_number',
        'id_type', 'id_number',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'id_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasContact(): bool
    {
        return filled($this->phone) && filled($this->address_line1) && filled($this->city);
    }

    public function hasPayout(): bool
    {
        return filled($this->bank_name) && filled($this->bank_branch)
            && filled($this->account_holder_name) && filled($this->account_number);
    }

    /** One line for lists: "No. 12, Main Street, Kandy, Central 20000, Sri Lanka". */
    public function addressLine(): string
    {
        $cityLine = trim(implode(' ', array_filter([$this->district, $this->postal_code])));

        return implode(', ', array_filter([
            $this->address_line1, $this->address_line2, $this->city, $cityLine ?: null, $this->country,
        ]));
    }

    /** "**** **** 4821" style, for places that should not show the whole number. */
    public function maskedAccountNumber(): ?string
    {
        $number = preg_replace('/\s+/', '', (string) $this->account_number);
        if ($number === '') {
            return null;
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
