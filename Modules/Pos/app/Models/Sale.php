<?php

namespace Modules\Pos\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Account\Models\Account;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class Sale extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CARD = 'card';

    public const PAYMENT_CREDIT = 'credit';

    public const PAYMENT_CHEQUE = 'cheque';

    public const CHANNEL_RETAIL = 'retail';

    public const CHANNEL_ONLINE = 'online';

    protected $table = 'pos_sales';

    protected $fillable = [
        'business_id',
        'branch_id',
        'pos_counter_id',
        'user_id',
        'sale_number',
        'client_uuid',
        'offline_ref',
        'status',
        'payment_method',
        'channel',
        'credit_account_id',
        'pos_customer_id',
        'pos_gift_card_id',
        'pos_coupon_id',
        'credit_due_date',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'coupon_discount',
        'total',
        'amount_paid',
        'gift_card_amount',
        'amount_tendered',
        'change_amount',
        'notes',
        'sold_at',
        'is_settled',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'gift_card_amount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'credit_due_date' => 'date',
            'sold_at' => 'datetime',
            'is_settled' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'pos_customer_id');
    }

    public function creditPayments(): HasMany
    {
        return $this->hasMany(CreditPayment::class, 'pos_sale_id');
    }

    /** Unpaid balance of a credit sale (0 for cash/card sales). */
    public function creditDue(): float
    {
        if ($this->payment_method !== self::PAYMENT_CREDIT) {
            return 0.0;
        }

        return max(round((float) $this->total - (float) $this->amount_paid, 2), 0.0);
    }

    /** A credit sale with money still owed whose due date has passed. */
    public function isCreditOverdue(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->creditDue() > 0.005
            && $this->credit_due_date !== null
            && $this->credit_due_date->lt(now()->startOfDay());
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class, 'pos_gift_card_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'pos_coupon_id');
    }

    /** Amount paid through payment_method itself — i.e. the total less any gift card portion. */
    public function netPaymentAmount(): float
    {
        return round(max(0, (float) $this->total - (float) $this->gift_card_amount), 2);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'pos_sale_id')->orderBy('sort_order')->orderBy('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class, 'pos_sale_id')->orderBy('returned_at');
    }

    public function revert(): HasOne
    {
        return $this->hasOne(SaleRevert::class, 'pos_sale_id');
    }

    public function cheque(): HasOne
    {
        return $this->hasOne(SaleCheque::class, 'pos_sale_id');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable');
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** @return array<string, string> */
    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_CASH => 'Cash',
            self::PAYMENT_CARD => 'Card payment',
            self::PAYMENT_CREDIT => 'Credit payment',
            self::PAYMENT_CHEQUE => 'Cheque',
        ];
    }

    public function paymentMethodLabel(): string
    {
        return self::paymentMethodLabels()[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    /** @return array<string, string> */
    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_RETAIL => 'Retail register',
            self::CHANNEL_ONLINE => 'Online POS',
        ];
    }

    public function channelLabel(): string
    {
        return self::channelLabels()[$this->channel] ?? ucfirst((string) $this->channel);
    }
}
