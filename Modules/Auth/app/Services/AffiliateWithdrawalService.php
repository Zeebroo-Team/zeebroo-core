<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\AffiliateWithdrawal;

/**
 * Commission withdrawals: an affiliate requests a payout from their available
 * balance, an admin approves it, pays it by bank transfer and marks it paid
 * (or rejects it with a reason). The affiliate may cancel a request while it
 * is still in review.
 *
 * Available balance = commission earned (LKR) minus every request that still
 * holds money (in review, approved or paid). Rejected and cancelled requests
 * give their amount back.
 */
class AffiliateWithdrawalService
{
    public const CURRENCY = 'LKR';

    /** Smallest amount that can be requested (bank transfers cost a fee). */
    public const MIN_AMOUNT = 1000.0;

    public function __construct(
        private readonly AffiliateCommissionService $commissions,
        private readonly AffiliateProfileService $profiles,
    ) {}

    /**
     * @return array{earned: float, in_review: float, paid_out: float, available: float}
     */
    public function balance(User $affiliate): array
    {
        $earned = (float) ($this->commissions->totals($this->commissions->entries($affiliate))[self::CURRENCY] ?? 0);

        $sums = AffiliateWithdrawal::query()
            ->where('user_id', $affiliate->id)
            ->whereIn('status', AffiliateWithdrawal::HOLDING_STATUSES)
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (float) $v);

        $paidOut = $sums[AffiliateWithdrawal::STATUS_PAID] ?? 0.0;
        $inReview = ($sums[AffiliateWithdrawal::STATUS_PENDING] ?? 0.0) + ($sums[AffiliateWithdrawal::STATUS_APPROVED] ?? 0.0);

        return [
            'earned' => round($earned, 2),
            'in_review' => round($inReview, 2),
            'paid_out' => round($paidOut, 2),
            // Never below zero: a refunded payment can lower what was earned after a payout.
            'available' => round(max(0.0, $earned - $inReview - $paidOut), 2),
        ];
    }

    /** The request still waiting on the admin, if any (one at a time). */
    public function openRequest(User $affiliate): ?AffiliateWithdrawal
    {
        return AffiliateWithdrawal::query()
            ->where('user_id', $affiliate->id)
            ->whereIn('status', AffiliateWithdrawal::OPEN_STATUSES)
            ->latest()
            ->first();
    }

    public function history(User $affiliate, int $perPage = 15): LengthAwarePaginator
    {
        return AffiliateWithdrawal::query()
            ->where('user_id', $affiliate->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Why the affiliate cannot ask for a withdrawal right now, or null when they can.
     *
     * @return array{reason: string, message: string}|null
     */
    public function blocker(User $affiliate, ?array $balance = null): ?array
    {
        $completion = $this->profiles->completion($affiliate);
        if (in_array(false, $completion, true)) {
            return ['reason' => 'settings', 'message' => __('Complete your Settings (contact details, payout bank and ID) before asking for a withdrawal.')];
        }

        if ($this->openRequest($affiliate) !== null) {
            return ['reason' => 'open', 'message' => __('You already have a withdrawal request in progress. You can ask for another once it is paid, rejected or cancelled.')];
        }

        $balance ??= $this->balance($affiliate);
        if ($balance['available'] < self::MIN_AMOUNT) {
            return ['reason' => 'balance', 'message' => __('You can withdraw once your available balance reaches :min.', [
                'min' => self::CURRENCY.' '.number_format(self::MIN_AMOUNT, 2),
            ])];
        }

        return null;
    }

    /**
     * Creates a request for $amount, paid to the bank account in the affiliate's
     * Settings. Locked per affiliate so a double submit cannot create two.
     */
    public function request(User $affiliate, float $amount, ?string $note = null): AffiliateWithdrawal
    {
        return Cache::lock('affiliate-withdrawal:'.$affiliate->id, 10)->block(5, function () use ($affiliate, $amount, $note) {
            return DB::transaction(function () use ($affiliate, $amount, $note): AffiliateWithdrawal {
                $balance = $this->balance($affiliate);

                if ($blocker = $this->blocker($affiliate, $balance)) {
                    throw ValidationException::withMessages(['amount' => $blocker['message']]);
                }

                $amount = round($amount, 2);
                if ($amount < self::MIN_AMOUNT) {
                    throw ValidationException::withMessages(['amount' => __('The smallest withdrawal is :min.', ['min' => self::CURRENCY.' '.number_format(self::MIN_AMOUNT, 2)])]);
                }
                if ($amount > $balance['available']) {
                    throw ValidationException::withMessages(['amount' => __('You can withdraw up to :max.', ['max' => self::CURRENCY.' '.number_format($balance['available'], 2)])]);
                }

                $profile = $this->profiles->profileFor($affiliate);
                $note = trim((string) $note);

                return AffiliateWithdrawal::query()->create([
                    'user_id' => $affiliate->id,
                    'amount' => $amount,
                    'currency' => self::CURRENCY,
                    'status' => AffiliateWithdrawal::STATUS_PENDING,
                    'note' => $note !== '' ? $note : null,
                    'bank_name' => $profile->bank_name,
                    'bank_branch' => $profile->bank_branch,
                    'account_holder_name' => $profile->account_holder_name,
                    'account_number' => $profile->account_number,
                ]);
            });
        });
    }

    public function cancel(AffiliateWithdrawal $withdrawal): AffiliateWithdrawal
    {
        $this->expectStatus($withdrawal, [AffiliateWithdrawal::STATUS_PENDING], __('Only a request that is still in review can be cancelled.'));

        $withdrawal->forceFill(['status' => AffiliateWithdrawal::STATUS_CANCELLED, 'cancelled_at' => now()])->save();

        return $withdrawal;
    }

    public function approve(AffiliateWithdrawal $withdrawal, User $admin, ?string $note = null): AffiliateWithdrawal
    {
        $this->expectStatus($withdrawal, [AffiliateWithdrawal::STATUS_PENDING], __('Only a request that is in review can be approved.'));

        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_APPROVED,
            'approved_at' => now(),
            'reviewed_by' => $admin->id,
            'admin_note' => $this->cleanNote($note) ?? $withdrawal->admin_note,
        ])->save();

        return $withdrawal;
    }

    public function reject(AffiliateWithdrawal $withdrawal, User $admin, string $reason): AffiliateWithdrawal
    {
        $this->expectStatus($withdrawal, AffiliateWithdrawal::OPEN_STATUSES, __('Only a request that is in review or approved can be rejected.'));

        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_REJECTED,
            'rejected_at' => now(),
            'reviewed_by' => $admin->id,
            'admin_note' => $this->cleanNote($reason),
        ])->save();

        return $withdrawal;
    }

    public function markPaid(AffiliateWithdrawal $withdrawal, User $admin, string $reference, ?Carbon $paidAt = null, ?string $note = null): AffiliateWithdrawal
    {
        $this->expectStatus($withdrawal, [AffiliateWithdrawal::STATUS_APPROVED], __('Approve the request before marking it as paid.'));

        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_PAID,
            'paid_at' => $paidAt ?? now(),
            'payment_reference' => trim($reference),
            'reviewed_by' => $admin->id,
            'admin_note' => $this->cleanNote($note) ?? $withdrawal->admin_note,
        ])->save();

        return $withdrawal;
    }

    /**
     * Admin list, newest first, filtered by status ("all" for every status),
     * one affiliate, and/or a name or email search.
     */
    public function adminList(string $status = AffiliateWithdrawal::STATUS_PENDING, ?int $affiliateId = null, ?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return AffiliateWithdrawal::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($affiliateId, fn ($q) => $q->where('user_id', $affiliateId))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))))
            ->orderByRaw($status === AffiliateWithdrawal::STATUS_PENDING ? 'created_at asc' : 'created_at desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, int> count per status, plus "all" */
    public function statusCounts(?int $affiliateId = null): array
    {
        $counts = AffiliateWithdrawal::query()
            ->when($affiliateId, fn ($q) => $q->where('user_id', $affiliateId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $out = [];
        foreach (array_keys(AffiliateWithdrawal::STATUS_LABELS) as $status) {
            $out[$status] = $counts[$status] ?? 0;
        }
        $out['all'] = array_sum($out);

        return $out;
    }

    private function expectStatus(AffiliateWithdrawal $withdrawal, array $allowed, string $message): void
    {
        if (! in_array($withdrawal->status, $allowed, true)) {
            throw ValidationException::withMessages(['withdrawal' => $message]);
        }
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note !== '' ? $note : null;
    }
}
