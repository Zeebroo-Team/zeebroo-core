<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\GiftCard;
use Modules\Pos\Models\GiftCardGroup;
use Modules\Pos\Models\GiftCardTransaction;
use Modules\Pos\Models\Sale;

class GiftCardService
{
    private const MONEY_TOLERANCE = 0.005;

    // No 0/O/1/I — codes are read aloud and typed by cashiers.
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function list(Business $business, string $q, string $status): Collection
    {
        $today = now()->startOfDay();

        $query = GiftCard::query()
            ->where('business_id', $business->id)
            ->with('customer')
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->where(fn ($sq) => $sq
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', '%'.$this->normalizeCode($q).'%'));
        }

        match ($status) {
            GiftCard::STATUS_ACTIVE => $query->where('is_active', true)
                ->where('balance', '>', self::MONEY_TOLERANCE)
                ->where(fn ($sq) => $sq->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($sq) => $sq->whereNull('expires_at')->orWhere('expires_at', '>=', $today)),
            GiftCard::STATUS_EXPIRED  => $query->where('is_active', true)->where('expires_at', '<', $today),
            GiftCard::STATUS_USED     => $query->where('balance', '<=', self::MONEY_TOLERANCE),
            GiftCard::STATUS_DISABLED => $query->where('is_active', false),
            default                   => null,
        };

        return $query->get();
    }

    public function forBusiness(Business $business, GiftCard $card): ?GiftCard
    {
        return (int) $card->business_id === (int) $business->id ? $card : null;
    }

    public function findByCode(Business $business, string $code): ?GiftCard
    {
        return GiftCard::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->first();
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    /** A new code unique within the business, formatted GC-XXXX-XXXX-XXXX. */
    public function generateCode(Business $business): string
    {
        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $chunk = '';
                for ($i = 0; $i < 4; $i++) {
                    $chunk .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
                }
                $groups[] = $chunk;
            }
            $code = 'GC-'.implode('-', $groups);
        } while ($this->codeTaken($business, $code));

        return $code;
    }

    public function codeTaken(Business $business, string $code, ?int $exceptId = null): bool
    {
        return GiftCard::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public const MAX_BATCH = 500;

    /**
     * Create a gift card group and `quantity` cards in it, each with its own
     * unique code. A custom code is only honoured when creating a single card.
     */
    public function create(Business $business, ?User $user, array $data): GiftCardGroup
    {
        return DB::transaction(function () use ($business, $user, $data) {
            $quantity = max(1, min(self::MAX_BATCH, (int) ($data['quantity'] ?? 1)));

            $customCode = null;
            if ($quantity === 1 && filled($data['code'] ?? null)) {
                $customCode = $this->normalizeCode($data['code']);
                if ($this->codeTaken($business, $customCode)) {
                    throw ValidationException::withMessages(['code' => 'This gift card code is already in use.']);
                }
            }

            $group = GiftCardGroup::query()->create([
                'business_id'   => $business->id,
                'name'          => $data['name'],
                'initial_value' => round((float) $data['initial_value'], 2),
                'valid_from'    => $data['valid_from'] ?? null,
                'expires_at'    => $data['expires_at'] ?? null,
                'is_active'     => $data['is_active'] ?? true,
                'notes'         => $data['notes'] ?? null,
                'created_by'    => $user?->id,
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                $this->issueCard($business, $group, $user, $customCode, $data['pos_customer_id'] ?? null);
            }

            return $group;
        });
    }

    /** Generate more cards in an existing group, using the group's value and dates. */
    public function addCards(Business $business, GiftCardGroup $group, ?User $user, int $quantity): GiftCardGroup
    {
        $quantity = max(1, min(self::MAX_BATCH, $quantity));

        DB::transaction(function () use ($business, $group, $user, $quantity) {
            for ($i = 0; $i < $quantity; $i++) {
                $this->issueCard($business, $group, $user);
            }
        });

        return $group;
    }

    private function issueCard(Business $business, GiftCardGroup $group, ?User $user, ?string $code = null, ?int $customerId = null): GiftCard
    {
        $value = round((float) $group->initial_value, 2);

        $card = GiftCard::query()->create([
            'business_id'            => $business->id,
            'pos_gift_card_group_id' => $group->id,
            'pos_customer_id'        => $customerId,
            'name'                   => $group->name,
            'code'                   => $code ?? $this->generateCode($business),
            'initial_value'          => $value,
            'balance'                => $value,
            'valid_from'             => $group->valid_from,
            'expires_at'             => $group->expires_at,
            'is_active'              => $group->is_active,
            'notes'                  => $group->notes,
            'created_by'             => $user?->id,
        ]);

        $card->transactions()->create([
            'type'          => GiftCardTransaction::TYPE_ISSUE,
            'amount'        => $value,
            'balance_after' => $value,
            'notes'         => 'Gift card issued',
            'user_id'       => $user?->id,
        ]);

        return $card;
    }

    /**
     * Groups with their cards. `q` matches the group name (all its cards) or a
     * card code (just those cards); `status` keeps only cards in that status.
     */
    public function listGroups(Business $business, string $q, string $status): Collection
    {
        $groups = GiftCardGroup::query()
            ->where('business_id', $business->id)
            ->with(['cards.customer'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $needle = $this->normalizeCode($q);

        return $groups->map(function (GiftCardGroup $group) use ($q, $needle, $status) {
            $cards = $group->cards;
            if ($q !== '' && stripos($group->name, $q) === false) {
                $cards = $cards->filter(fn (GiftCard $c) => str_contains($c->code, $needle));
            }
            if ($status !== '') {
                $cards = $cards->filter(fn (GiftCard $c) => $c->status() === $status);
            }
            $group->setRelation('cards', $cards->values());

            return $group;
        })->filter(fn (GiftCardGroup $g) => $g->cards->isNotEmpty() || ($q === '' && $status === ''))->values();
    }

    public function groupForBusiness(Business $business, GiftCardGroup $group): ?GiftCardGroup
    {
        return (int) $group->business_id === (int) $business->id ? $group : null;
    }

    /** Name, dates and active flag apply to every card in the group. */
    public function updateGroup(GiftCardGroup $group, array $data): GiftCardGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update($data);

            $cascade = array_intersect_key($data, array_flip(['name', 'valid_from', 'expires_at', 'is_active']));
            if ($cascade !== []) {
                $group->cards()->update($cascade);
            }

            return $group->refresh();
        });
    }

    public function deleteGroup(GiftCardGroup $group): void
    {
        $used = GiftCardTransaction::query()
            ->where('type', GiftCardTransaction::TYPE_REDEEM)
            ->whereIn('pos_gift_card_id', $group->cards()->select('id'))
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'gift_card' => 'Some cards in this group have been used in sales, so it cannot be deleted. Disable it instead.',
            ]);
        }

        $group->delete();
    }

    public function formatGroup(GiftCardGroup $group): array
    {
        $cards = $group->cards;
        $counts = $cards->countBy(fn (GiftCard $c) => $c->status());

        return [
            'id'            => (int) $group->id,
            'name'          => $group->name,
            'initial_value' => round((float) $group->initial_value, 2),
            'valid_from'    => $group->valid_from?->toDateString(),
            'expires_at'    => $group->expires_at?->toDateString(),
            'is_active'     => (bool) $group->is_active,
            'notes'         => $group->notes,
            'card_count'    => $cards->count(),
            'total_value'   => round($cards->sum(fn ($c) => (float) $c->initial_value), 2),
            'total_balance' => round($cards->sum(fn ($c) => (float) $c->balance), 2),
            'status_counts' => $counts->all(),
            'created_at'    => $group->created_at?->toIso8601String(),
            'cards'         => $cards->map(fn (GiftCard $c) => $this->format($c))->values()->all(),
        ];
    }

    public function update(Business $business, GiftCard $card, ?User $user, array $data): GiftCard
    {
        return DB::transaction(function () use ($business, $card, $user, $data) {
            $card = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('code', $data)) {
                $code = filled($data['code']) ? $this->normalizeCode($data['code']) : $card->code;
                if ($code !== $card->code && $this->codeTaken($business, $code, $card->id)) {
                    throw ValidationException::withMessages(['code' => 'This gift card code is already in use.']);
                }
                $data['code'] = $code;
            }

            // Changing the face value shifts the balance by the same delta, so
            // amounts already spent stay spent. It can't drop below what's used.
            if (array_key_exists('initial_value', $data)) {
                $newValue = round((float) $data['initial_value'], 2);
                $delta    = round($newValue - (float) $card->initial_value, 2);
                if (abs($delta) > self::MONEY_TOLERANCE) {
                    $newBalance = round((float) $card->balance + $delta, 2);
                    if ($newBalance < -self::MONEY_TOLERANCE) {
                        $spent = round((float) $card->initial_value - (float) $card->balance, 2);
                        throw ValidationException::withMessages([
                            'initial_value' => "Value can't be lower than the amount already spent ({$spent}).",
                        ]);
                    }
                    $data['balance'] = max(0, $newBalance);
                    $card->transactions()->create([
                        'type'          => GiftCardTransaction::TYPE_ADJUST,
                        'amount'        => $delta,
                        'balance_after' => $data['balance'],
                        'notes'         => 'Gift card value changed',
                        'user_id'       => $user?->id,
                    ]);
                }
                $data['initial_value'] = $newValue;
            }

            $card->update($data);

            return $card->refresh();
        });
    }

    public function delete(GiftCard $card): void
    {
        if ($card->transactions()->where('type', GiftCardTransaction::TYPE_REDEEM)->exists()) {
            throw ValidationException::withMessages([
                'gift_card' => 'This gift card has been used in sales and cannot be deleted. Disable it instead.',
            ]);
        }

        DB::transaction(function () use ($card) {
            $groupId = $card->pos_gift_card_group_id;
            $card->delete();

            // Don't leave an empty group behind.
            if ($groupId !== null && ! GiftCard::query()->where('pos_gift_card_group_id', $groupId)->exists()) {
                GiftCardGroup::query()->whereKey($groupId)->delete();
            }
        });
    }

    /**
     * Spend $amount from the card for a sale. Must run inside the checkout
     * transaction — the row lock keeps two tills from double-spending a card.
     */
    public function redeemForSale(Business $business, string $code, float $amount, Sale $sale, ?User $user): GiftCard
    {
        $card = GiftCard::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->lockForUpdate()
            ->first();

        if ($card === null) {
            throw ValidationException::withMessages(['gift_card_code' => 'Gift card not found.']);
        }

        $this->assertRedeemable($card);

        $amount = round($amount, 2);
        if ($amount > (float) $card->balance + self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'gift_card_amount' => 'Gift card balance is only '.number_format((float) $card->balance, 2).'.',
            ]);
        }

        $newBalance = round(max(0, (float) $card->balance - $amount), 2);
        $card->update(['balance' => $newBalance]);

        $card->transactions()->create([
            'pos_sale_id'   => $sale->id,
            'type'          => GiftCardTransaction::TYPE_REDEEM,
            'amount'        => -$amount,
            'balance_after' => $newBalance,
            'notes'         => 'Redeemed on sale '.$sale->sale_number,
            'user_id'       => $user?->id,
        ]);

        return $card;
    }

    /** Put a voided sale's gift card spend back on the card. */
    public function refundForSale(Sale $sale, ?User $user = null): void
    {
        if ($sale->pos_gift_card_id === null || (float) $sale->gift_card_amount <= self::MONEY_TOLERANCE) {
            return;
        }

        $card = GiftCard::query()->whereKey($sale->pos_gift_card_id)->lockForUpdate()->first();
        if ($card === null) {
            return;
        }

        $amount     = round((float) $sale->gift_card_amount, 2);
        $newBalance = round((float) $card->balance + $amount, 2);
        $card->update(['balance' => $newBalance]);

        $card->transactions()->create([
            'pos_sale_id'   => $sale->id,
            'type'          => GiftCardTransaction::TYPE_REFUND,
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'notes'         => 'Sale '.$sale->sale_number.' voided',
            'user_id'       => $user?->id,
        ]);
    }

    public function assertRedeemable(GiftCard $card): void
    {
        $message = match ($card->status()) {
            GiftCard::STATUS_DISABLED  => 'This gift card is disabled.',
            GiftCard::STATUS_EXPIRED   => 'This gift card expired on '.$card->expires_at?->toDateString().'.',
            GiftCard::STATUS_SCHEDULED => 'This gift card is not valid until '.$card->valid_from?->toDateString().'.',
            GiftCard::STATUS_USED      => 'This gift card has no remaining balance.',
            default                    => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['gift_card_code' => $message]);
        }
    }

    public function format(GiftCard $card, bool $withTransactions = false): array
    {
        $initial = round((float) $card->initial_value, 2);
        $balance = round((float) $card->balance, 2);

        $data = [
            'id'              => (int) $card->id,
            'group_id'        => $card->pos_gift_card_group_id !== null ? (int) $card->pos_gift_card_group_id : null,
            'name'            => $card->name,
            'code'            => $card->code,
            'initial_value'   => $initial,
            'balance'         => $balance,
            'used_amount'     => round(max(0, $initial - $balance), 2),
            'valid_from'      => $card->valid_from?->toDateString(),
            'expires_at'      => $card->expires_at?->toDateString(),
            'is_active'       => (bool) $card->is_active,
            'status'          => $card->status(),
            'is_redeemable'   => $card->isRedeemable(),
            'notes'           => $card->notes,
            'pos_customer_id' => $card->pos_customer_id !== null ? (int) $card->pos_customer_id : null,
            'customer_name'   => $card->customer?->name,
            'created_at'      => $card->created_at?->toIso8601String(),
        ];

        if ($withTransactions) {
            $data['transactions'] = $card->transactions->map(fn (GiftCardTransaction $t) => [
                'id'            => (int) $t->id,
                'type'          => $t->type,
                'amount'        => round((float) $t->amount, 2),
                'balance_after' => round((float) $t->balance_after, 2),
                'notes'         => $t->notes,
                'sale_id'       => $t->pos_sale_id !== null ? (int) $t->pos_sale_id : null,
                'sale_number'   => $t->sale?->sale_number,
                'user_name'     => $t->user?->name,
                'created_at'    => $t->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
