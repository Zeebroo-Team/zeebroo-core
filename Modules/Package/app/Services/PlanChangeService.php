<?php

namespace Modules\Package\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Services\UserSeatService;
use Modules\Package\Models\Package;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentProvisioningService;
use Modules\Payment\Services\StripeSubscriptionService;

/**
 * Moving a business off its free trial onto a real package ("Choose your plan").
 *
 * - preview(): what the move costs the business — features it actually used
 *   that the new package lacks (their data gets hidden, never deleted),
 *   packages that would keep those features, and whether some users must be
 *   switched off to fit the new seat limit.
 * - start(): free targets switch at once; paid targets open Stripe Checkout and
 *   switch only when it succeeds (applyPaid(), called from StripePaymentSyncService).
 */
class PlanChangeService
{
    public function __construct(
        private readonly FeatureUsageService $usage,
        private readonly UserSeatService $seats,
        private readonly PaymentProvisioningService $provisioning,
        private readonly StripeSubscriptionService $stripe,
    ) {}

    /** Only the owner of a business that is (or was) on the free trial uses this flow. */
    public function canChange(Business $business, User $user): bool
    {
        return $business->isOnTrial() && (int) $business->user_id === (int) $user->id;
    }

    /** Packages the trial business may move to — never the trial package itself. */
    public function options(string $platform = 'desktop'): Collection
    {
        return Package::query()
            ->where('is_active', true)
            ->where('is_trial', false)
            ->visibleForPlatform($platform)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *   package: Package,
     *   lost_features: list<array{key: string, label: string, total: int, items: list<array{label: string, count: int}>}>,
     *   lost_unused: list<string>,
     *   recommended: Collection<int, Package>,
     *   seats: array{used: int, limit: ?int, must_keep: ?int, needs_selection: bool, members: list<array{id: int, name: string, email: string, role: string}>}
     * }
     */
    public function preview(Business $business, Package $target, string $platform = 'desktop'): array
    {
        $catalog = config('features.list', []);
        $current = $business->effectiveFeatureKeys();
        $targetKeys = array_merge($target->features ?? [], ['account_management']);
        $lostKeys = array_values(array_diff($current, $targetKeys));

        $used = $this->usage->usage($business, $lostKeys);

        $lostFeatures = [];
        $lostUnused = [];
        foreach ($lostKeys as $key) {
            if (isset($used[$key])) {
                $lostFeatures[] = ['key' => $key, 'label' => $catalog[$key] ?? $key] + $used[$key];
            } else {
                $lostUnused[] = $catalog[$key] ?? $key;
            }
        }

        // Packages that keep every feature the business actually has data in,
        // cheapest first — so the customer sees the lowest-priced plan that
        // still covers them. Features of the chosen package they never used
        // don't count, otherwise a cheaper plan that fits gets skipped.
        $recommended = collect();
        if ($lostFeatures !== []) {
            $needed = array_keys($this->usage->usage($business, $current));
            $recommended = $this->options($platform)
                ->reject(fn (Package $p) => $p->id === $target->id)
                ->filter(fn (Package $p) => array_diff($needed, array_merge($p->features ?? [], ['account_management'])) === [])
                ->sortBy([
                    fn (Package $a, Package $b) => $a->effectivePrice() <=> $b->effectivePrice(),
                    fn (Package $a, Package $b) => [$a->sort_order, $a->id] <=> [$b->sort_order, $b->id],
                ])
                ->take(3)
                ->values();
        }

        return [
            'package' => $target,
            'lost_features' => $lostFeatures,
            'lost_unused' => $lostUnused,
            'recommended' => $recommended,
            'seats' => $this->seatPreview($business, $target),
        ];
    }

    /**
     * @param  list<int>  $keepMemberIds  members to keep active when the new package has fewer seats
     * @return array{applied: bool, checkout_url: ?string, payment: ?Payment}
     */
    public function start(
        Business $business,
        Package $target,
        User $user,
        array $keepMemberIds,
        string $platform,
        string $successUrl,
        string $cancelUrl,
    ): array {
        if (! $this->canChange($business, $user)) {
            throw ValidationException::withMessages(['package_id' => ['Only the business owner can change the plan of a trial business.']]);
        }

        $available = $this->options($platform)->contains('id', $target->id);
        if (! $available) {
            throw ValidationException::withMessages(['package_id' => ['The selected package is no longer available.']]);
        }

        $keepMemberIds = $this->validateKeepList($business, $target, $keepMemberIds);

        // Free target: nothing to pay, switch right away.
        if ($target->is_free || $target->effectivePrice() <= 0) {
            $this->apply($business, $target, $keepMemberIds);
            $payment = $this->provisioning->createInitialPayment($business, $target, $user, $platform);

            return ['applied' => true, 'checkout_url' => null, 'payment' => $payment];
        }

        $payment = Payment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'package_id' => $target->id,
            'payment_type' => Payment::TYPE_SUBSCRIPTION,
            'payment_status' => Payment::STATUS_PENDING,
            'billing_cycle' => 'monthly',
            'platform' => $platform,
            'amount' => $target->effectivePrice(),
            'currency' => strtolower($target->currency),
            'due_at' => null, // see Payment::isPlanChange()
            'metadata' => [
                'plan_change' => true,
                'from_package_id' => $business->package_id,
                'keep_member_ids' => $keepMemberIds,
            ],
        ]);

        $session = $this->stripe->createSubscriptionCheckoutSession(
            $payment,
            $business,
            $target,
            (float) $payment->amount,
            $successUrl,
            str_replace('__PAYMENT__', (string) $payment->id, $cancelUrl),
        );

        $payment->update(['stripe_checkout_session_id' => $session->id]);

        return ['applied' => false, 'checkout_url' => $session->url, 'payment' => $payment];
    }

    /** Stripe confirmed a plan-change payment — switch the business now (idempotent). */
    public function applyPaid(Payment $payment): void
    {
        $meta = $payment->metadata ?? [];
        if (! empty($meta['plan_change_applied_at'])) {
            return;
        }

        $business = $payment->business;
        $package = $payment->package;
        if (! $business || ! $package) {
            return;
        }

        $this->apply($business, $package, array_map('intval', (array) ($meta['keep_member_ids'] ?? [])));

        $meta['plan_change_applied_at'] = now()->toIso8601String();
        $payment->forceFill(['metadata' => $meta])->save();
    }

    /**
     * Switches package, features and seats, and ends the trial. Data of
     * features the new package lacks stays in the database — it's only hidden
     * (effectiveFeatureKeys() no longer allows it) and returns on upgrade.
     *
     * @param  list<int>  $keepMemberIds
     */
    public function apply(Business $business, Package $target, array $keepMemberIds): void
    {
        DB::transaction(function () use ($business, $target, $keepMemberIds): void {
            $business->forceFill([
                'package_id' => $target->id,
                'trial_ends_at' => null,
                'trial_reminder_sent_at' => null,
                'trial_package_id' => null,
            ])->save();
            $business->unsetRelation('package');
            $business->unsetRelation('trialPackage');

            $enabled = array_fill_keys($target->features ?? [], true);
            $features = [];
            foreach (array_keys(config('features.list', [])) as $key) {
                $features[$key] = isset($enabled[$key]);
            }
            $features['account_management'] = true;
            $business->setSetting('business.features', $features);

            $this->fitSeats($business, $target, $keepMemberIds);
        });
    }

    /**
     * @return array{used: int, limit: ?int, must_keep: ?int, needs_selection: bool, members: list<array{id: int, name: string, email: string, role: string}>}
     */
    private function seatPreview(Business $business, Package $target): array
    {
        $limit = $this->targetLimit($business, $target);
        $members = $this->activeMembers($business);
        $used = 1 + $members->count();

        return [
            'used' => $used,
            'limit' => $limit,
            // Members that may stay (the owner always keeps one seat).
            'must_keep' => $limit === null ? null : max(0, $limit - 1),
            'needs_selection' => $limit !== null && $used > $limit,
            'members' => $members->map(fn (BusinessMember $m) => [
                'id' => $m->id,
                'name' => (string) $m->user?->name,
                'email' => (string) $m->user?->email,
                'role' => $m->roleLabel(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<int>  $keepMemberIds
     * @return list<int>
     */
    private function validateKeepList(Business $business, Package $target, array $keepMemberIds): array
    {
        $preview = $this->seatPreview($business, $target);
        if (! $preview['needs_selection']) {
            return [];
        }

        $validIds = collect($preview['members'])->pluck('id')->all();
        $keep = array_values(array_unique(array_intersect(array_map('intval', $keepMemberIds), $validIds)));

        if (count($keep) > $preview['must_keep']) {
            throw ValidationException::withMessages([
                'keep_members' => ['The new package allows '.$preview['limit'].' users including you — keep at most '.$preview['must_keep'].' team member(s).'],
            ]);
        }

        return $keep;
    }

    /** @param list<int> $keepMemberIds */
    private function fitSeats(Business $business, Package $target, array $keepMemberIds): void
    {
        $limit = $this->targetLimit($business, $target);
        if ($limit === null) {
            return;
        }

        $members = $this->activeMembers($business);
        if (1 + $members->count() <= $limit) {
            return;
        }

        // Owner's explicit picks first; if they kept none (or the list went
        // stale), fall back to the longest-standing members.
        $keep = $members->whereIn('id', $keepMemberIds)->pluck('id');
        if ($keep->isEmpty()) {
            $keep = $members->sortBy('id')->take(max(0, $limit - 1))->pluck('id');
        }
        $keep = $keep->take(max(0, $limit - 1));

        BusinessMember::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $members->pluck('id')->diff($keep))
            ->update(['status' => 'pending', 'deactivated_at' => now()]);

        // Open invitations would claim seats on accept — cancel them too.
        BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->update(['status' => BusinessInvitation::STATUS_CANCELLED]);
    }

    private function targetLimit(Business $business, Package $target): ?int
    {
        $max = $business->max_users_override ?? $target->max_users;

        return $max === null ? null : (int) $max;
    }

    /** @return Collection<int, BusinessMember> */
    private function activeMembers(Business $business): Collection
    {
        return BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->whereNull('deactivated_at')
            ->whereHas('user')
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get();
    }
}
