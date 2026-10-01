<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Coupon;
use Modules\Pos\Models\CouponRedemption;
use Modules\Pos\Models\Sale;

class CouponService
{
    private const MONEY_TOLERANCE = 0.005;

    // No 0/O/1/I — codes are read aloud and typed by cashiers.
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const MAX_QUANTITY = 100000;

    public function list(Business $business, string $q, string $status): Collection
    {
        $query = Coupon::query()
            ->where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(fn ($sq) => $sq
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', '%'.$this->normalizeCode($q).'%'));
        }

        $coupons = $query->get();

        return $status !== ''
            ? $coupons->filter(fn (Coupon $c) => $c->status() === $status)->values()
            : $coupons;
    }

    public function forBusiness(Business $business, Coupon $coupon): ?Coupon
    {
        return (int) $coupon->business_id === (int) $business->id ? $coupon : null;
    }

    public function findByCode(Business $business, string $code): ?Coupon
    {
        return Coupon::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->first();
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    /** A new code unique within the business, formatted CP-XXXXXX. */
    public function generateCode(Business $business): string
    {
        do {
            $chunk = '';
            for ($i = 0; $i < 6; $i++) {
                $chunk .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'CP-'.$chunk;
        } while ($this->codeTaken($business, $code));

        return $code;
    }

    public function codeTaken(Business $business, string $code, ?int $exceptId = null): bool
    {
        return Coupon::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function create(Business $business, ?User $user, array $data): Coupon
    {
        $code = filled($data['code'] ?? null) ? $this->normalizeCode($data['code']) : $this->generateCode($business);
        if ($this->codeTaken($business, $code)) {
            throw ValidationException::withMessages(['code' => 'This coupon code is already in use.']);
        }

        return Coupon::query()->create([
            'business_id'    => $business->id,
            'name'           => $data['name'],
            'code'           => $code,
            'discount_type'  => $data['discount_type'],
            'discount_value' => round((float) $data['discount_value'], 2),
            'quantity'       => (int) $data['quantity'],
            'used_count'     => 0,
            'valid_from'     => $data['valid_from'] ?? null,
            'expires_at'     => $data['expires_at'] ?? null,
            'is_active'      => $data['is_active'] ?? true,
            'notes'          => $data['notes'] ?? null,
            'created_by'     => $user?->id,
        ]);
    }

    public function update(Business $business, Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($business, $coupon, $data) {
            $coupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('code', $data)) {
                $code = filled($data['code']) ? $this->normalizeCode($data['code']) : $coupon->code;
                if ($code !== $coupon->code && $this->codeTaken($business, $code, $coupon->id)) {
                    throw ValidationException::withMessages(['code' => 'This coupon code is already in use.']);
                }
                $data['code'] = $code;
            }

            if (array_key_exists('quantity', $data) && (int) $data['quantity'] < (int) $coupon->used_count) {
                throw ValidationException::withMessages([
                    'quantity' => "This coupon has already been used {$coupon->used_count} times — the number of coupons can't be lower than that.",
                ]);
            }

            if (array_key_exists('discount_value', $data)) {
                $data['discount_value'] = round((float) $data['discount_value'], 2);
            }

            $coupon->update($data);

            return $coupon->refresh();
        });
    }

    public function delete(Coupon $coupon): void
    {
        if ($coupon->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has been used in sales and cannot be deleted. Disable it instead.',
            ]);
        }

        $coupon->delete();
    }

    /**
     * Apply the coupon to a sale: works out the discount on $amount and uses
     * up one redemption. Must run inside the checkout transaction — the row
     * lock keeps two tills from both taking the last remaining use.
     *
     * @return array{0: Coupon, 1: float} the coupon and the discount given
     */
    public function redeemForSale(Business $business, string $code, float $amount, Sale $sale, ?User $user): array
    {
        $coupon = Coupon::query()
            ->where('business_id', $business->id)
            ->where('code', $this->normalizeCode($code))
            ->lockForUpdate()
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon not found.']);
        }

        $this->assertRedeemable($coupon);

        $discount = $coupon->discountFor($amount);

        $coupon->increment('used_count');
        $coupon->redemptions()->create([
            'pos_sale_id'     => $sale->id,
            'discount_amount' => $discount,
            'user_id'         => $user?->id,
        ]);

        return [$coupon->refresh(), $discount];
    }

    /** Hand a voided sale's coupon use back so the code can be used again. */
    public function reverseForSale(Sale $sale): void
    {
        if ($sale->pos_coupon_id === null) {
            return;
        }

        $redemption = CouponRedemption::query()
            ->where('pos_coupon_id', $sale->pos_coupon_id)
            ->where('pos_sale_id', $sale->id)
            ->whereNull('reversed_at')
            ->lockForUpdate()
            ->first();

        if ($redemption === null) {
            return;
        }

        $redemption->update(['reversed_at' => now()]);
        Coupon::query()->whereKey($sale->pos_coupon_id)->where('used_count', '>', 0)->decrement('used_count');
    }

    public function assertRedeemable(Coupon $coupon): void
    {
        $message = match ($coupon->status()) {
            Coupon::STATUS_DISABLED  => 'This coupon is disabled.',
            Coupon::STATUS_EXPIRED   => 'This coupon expired on '.$coupon->expires_at?->toDateString().'.',
            Coupon::STATUS_SCHEDULED => 'This coupon is not valid until '.$coupon->valid_from?->toDateString().'.',
            Coupon::STATUS_USED      => 'This coupon has been fully used — no uses left.',
            default                  => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['coupon_code' => $message]);
        }
    }

    public function format(Coupon $coupon, bool $withRedemptions = false): array
    {
        $data = [
            'id'             => (int) $coupon->id,
            'name'           => $coupon->name,
            'code'           => $coupon->code,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => round((float) $coupon->discount_value, 2),
            'quantity'       => (int) $coupon->quantity,
            'used_count'     => (int) $coupon->used_count,
            'remaining'      => $coupon->remaining(),
            'valid_from'     => $coupon->valid_from?->toDateString(),
            'expires_at'     => $coupon->expires_at?->toDateString(),
            'is_active'      => (bool) $coupon->is_active,
            'status'         => $coupon->status(),
            'is_redeemable'  => $coupon->isRedeemable(),
            'notes'          => $coupon->notes,
            'created_at'     => $coupon->created_at?->toIso8601String(),
        ];

        if ($withRedemptions) {
            $data['total_discount'] = round((float) $coupon->redemptions->whereNull('reversed_at')->sum('discount_amount'), 2);
            $data['redemptions']    = $coupon->redemptions->map(fn (CouponRedemption $r) => [
                'id'              => (int) $r->id,
                'discount_amount' => round((float) $r->discount_amount, 2),
                'reversed'        => $r->reversed_at !== null,
                'sale_id'         => $r->pos_sale_id !== null ? (int) $r->pos_sale_id : null,
                'sale_number'     => $r->sale?->sale_number,
                'user_name'       => $r->user?->name,
                'created_at'      => $r->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
