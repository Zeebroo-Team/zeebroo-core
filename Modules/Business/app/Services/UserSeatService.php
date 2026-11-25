<?php

namespace Modules\Business\Services;

use Modules\Business\Exceptions\UserLimitReachedException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;

/**
 * User seats of a business: the package's `max_users` plus any extra seats the
 * business has bought (ExtraUserPurchaseService). A seat is taken by the owner,
 * every member, and every invitation still open — an open invitation becomes a
 * member on accept, so it must be counted up front or the cap could be bypassed.
 */
class UserSeatService
{
    public const EXTRA_SEATS_KEY = 'business.extra_user_seats';
    /** Price per extra seat currently billed on the Stripe subscription. */
    public const EXTRA_UNIT_PRICE_KEY = 'business.extra_user_unit_price';

    public function used(Business $business): int
    {
        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->whereHas('user')
            ->count();

        $invitations = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count();

        return 1 + $members + $invitations;
    }

    /**
     * Cap before extra seats: the admin's per-business override when set,
     * else the package's max_users; null = unlimited.
     */
    public function includedSeats(Business $business): ?int
    {
        $max = $business->max_users_override ?? $business->package?->max_users;

        return $max === null ? null : (int) $max;
    }

    public function extraSeats(Business $business): int
    {
        return max(0, (int) $business->getSetting(self::EXTRA_SEATS_KEY, 0));
    }

    public function extraUnitPrice(Business $business): ?float
    {
        $price = $business->getSetting(self::EXTRA_UNIT_PRICE_KEY);

        return $price === null ? null : (float) $price;
    }

    /** Total seats allowed; null = unlimited. */
    public function limit(Business $business): ?int
    {
        $included = $this->includedSeats($business);

        return $included === null ? null : $included + $this->extraSeats($business);
    }

    /** Seats still free; null = unlimited. */
    public function remaining(Business $business): ?int
    {
        $limit = $this->limit($business);

        return $limit === null ? null : max(0, $limit - $this->used($business));
    }

    /** @throws UserLimitReachedException */
    public function assertCanAdd(Business $business): void
    {
        $limit = $this->limit($business);
        if ($limit === null) {
            return;
        }

        $used = $this->used($business);
        if ($used >= $limit) {
            throw new UserLimitReachedException($used, $limit);
        }
    }

    /** Adds bought seats and records the unit price now billed. Returns the new extra-seat total. */
    public function addExtraSeats(Business $business, int $quantity, float $unitPrice): int
    {
        $total = $this->extraSeats($business) + max(0, $quantity);
        $business->setSetting(self::EXTRA_SEATS_KEY, $total);
        $business->setSetting(self::EXTRA_UNIT_PRICE_KEY, $unitPrice);

        return $total;
    }
}
