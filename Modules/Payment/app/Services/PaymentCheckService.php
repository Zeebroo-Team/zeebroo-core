<?php

namespace Modules\Payment\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentCheckRun;
use Modules\Payment\Models\PaymentCheckSetting;

/**
 * The "double check" behind the Stripe webhook: re-reads this month's
 * subscription and checkout state straight from Stripe, repairs anything the
 * webhook missed (through the same StripePaymentSyncService the webhook
 * uses), and stores a per-business result the admin panel shows.
 *
 * Runs on the scheduler at the admin-set interval (payment:check) or on
 * demand from Admin › Payment Checks.
 */
class PaymentCheckService
{
    /** Stripe subscription statuses that mean the business owes money. */
    private const OWING_STATUSES = ['past_due', 'unpaid', 'incomplete'];

    private const KEEP_RUNS_DAYS = 30;

    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly StripePaymentSyncService $sync,
    ) {}

    /** Scheduler entry point — runs only when enabled and the interval has elapsed. */
    public function runIfDue(): ?PaymentCheckRun
    {
        return PaymentCheckSetting::current()->isDue()
            ? $this->run(PaymentCheckRun::TRIGGER_SCHEDULED)
            : null;
    }

    /**
     * Returns null when another run is already in progress.
     */
    public function run(string $trigger, ?User $user = null): ?PaymentCheckRun
    {
        $lock = Cache::lock('payment-check:run', 600);
        if (! $lock->get()) {
            return null;
        }

        try {
            @set_time_limit(300);

            PaymentCheckSetting::current()->update(['last_run_at' => now()]);

            $monthStart = now()->startOfMonth();
            $run = PaymentCheckRun::create([
                'trigger' => $trigger,
                'triggered_by' => $user?->id,
                'period' => $monthStart->format('Y-m'),
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                if ((string) config('services.stripe.secret') === '') {
                    throw new \RuntimeException('Stripe secret key is not configured (STRIPE_SECRET).');
                }

                $results = $this->checkSubscriptions($monthStart)
                    ->concat($this->checkCheckouts($monthStart))
                    ->sortBy(fn (array $r) => [array_search($r['outcome'], [PaymentCheckRun::OUTCOME_ERROR, PaymentCheckRun::OUTCOME_ISSUE, PaymentCheckRun::OUTCOME_FIXED, PaymentCheckRun::OUTCOME_OK], true), $r['business_name']])
                    ->values();

                $counts = $results->countBy('outcome');

                $run->update([
                    'status' => 'completed',
                    'results' => $results->all(),
                    'checked_count' => $results->count(),
                    'ok_count' => $counts[PaymentCheckRun::OUTCOME_OK] ?? 0,
                    'fixed_count' => $counts[PaymentCheckRun::OUTCOME_FIXED] ?? 0,
                    'issue_count' => $counts[PaymentCheckRun::OUTCOME_ISSUE] ?? 0,
                    'error_count' => $counts[PaymentCheckRun::OUTCOME_ERROR] ?? 0,
                    'finished_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e);
                $run->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
            }

            $this->pruneOldRuns();

            return $run->refresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * Every Stripe subscription still live, or that closed this month: sync
     * its status and record any of this month's invoices the webhook missed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function checkSubscriptions(Carbon $monthStart): Collection
    {
        $rows = Payment::query()
            ->with('business:id,name')
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->whereNotNull('stripe_subscription_id')
            ->where(fn ($q) => $q
                ->whereNull('stripe_subscription_status')
                ->orWhereNotIn('stripe_subscription_status', ['canceled', 'incomplete_expired'])
                ->orWhere('current_period_end', '>=', $monthStart))
            ->orderByDesc('id')
            ->get()
            ->unique('stripe_subscription_id');

        return $rows->map(fn (Payment $row) => $this->checkSubscription($row, $monthStart))->values();
    }

    /** @return array<string, mixed> */
    private function checkSubscription(Payment $row, Carbon $monthStart): array
    {
        $result = $this->baseResult($row, 'subscription');

        try {
            $subscription = $this->stripe->retrieveSubscription($row->stripe_subscription_id);
            $changed = false;

            $invoices = array_reverse($this->stripe->invoicesForSubscription($row->stripe_subscription_id, $monthStart));
            $paid = null;
            $unpaid = null;

            foreach ($invoices as $invoice) {
                if (($invoice->status ?? null) === 'paid') {
                    $changed = $this->sync->recordInvoicePaid($invoice) || $changed;
                    $paid = $invoice;
                    $unpaid = null; // a later paid invoice supersedes an earlier unpaid one
                } elseif ($this->sync->isUnpaidInvoice($invoice)) {
                    $changed = $this->sync->recordInvoiceFailed($invoice) || $changed;
                    $unpaid = $invoice;
                }
            }

            // After the invoices, so renewal rows created just now get the period end too.
            $changed = $this->sync->syncSubscription($subscription) || $changed;

            $status = (string) ($subscription->status ?? 'canceled');
            $periodEndTs = $subscription->current_period_end ?? ($subscription->items->data[0]->current_period_end ?? null);
            $periodEnd = $periodEndTs ? Carbon::createFromTimestamp($periodEndTs) : null;
            $cancelAtPeriodEnd = (bool) ($subscription->cancel_at_period_end ?? false) || ($subscription->cancel_at ?? null) !== null;

            $result['stripe_status'] = $status;
            $result['period_end'] = $periodEnd?->toIso8601String();
            $result['paid_this_month'] = $paid !== null;
            $result['local_status'] = $this->latestLocalStatus($row);

            if ($paid) {
                $result['amount'] = ($paid->amount_paid ?? 0) / 100;
                $result['currency'] = strtoupper((string) ($paid->currency ?? $row->currency));
            }

            if ($unpaid || in_array($status, self::OWING_STATUSES, true)) {
                $result['outcome'] = PaymentCheckRun::OUTCOME_ISSUE;
                $result['message'] = $unpaid
                    ? sprintf('Renewal invoice unpaid (%s %s, %d attempt%s) — Stripe status: %s.',
                        strtoupper((string) $unpaid->currency), number_format(($unpaid->amount_due ?? 0) / 100, 2),
                        (int) $unpaid->attempt_count, (int) $unpaid->attempt_count === 1 ? '' : 's', $status)
                    : "Stripe reports the subscription as {$status}.";
            } else {
                $result['outcome'] = $changed ? PaymentCheckRun::OUTCOME_FIXED : PaymentCheckRun::OUTCOME_OK;
                $result['message'] = $this->subscriptionMessage($status, $paid, $periodEnd, $cancelAtPeriodEnd, $monthStart);
                if ($changed) {
                    $result['message'] = 'Updated from Stripe (webhook had missed it). '.$result['message'];
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $result['outcome'] = PaymentCheckRun::OUTCOME_ERROR;
            $result['message'] = 'Stripe check failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Signups / resumed payments this month that never got a subscription
     * attached: confirm the Checkout Session with Stripe, or flag them as
     * still unpaid.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function checkCheckouts(Carbon $monthStart): Collection
    {
        $rows = Payment::query()
            ->with('business:id,name')
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->whereNull('stripe_subscription_id')
            ->whereIn('payment_status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING, Payment::STATUS_FAILED, Payment::STATUS_CANCELED])
            ->where(fn ($q) => $q
                ->where('created_at', '>=', $monthStart)
                ->orWhere('updated_at', '>=', $monthStart)
                ->orWhere('due_at', '>=', $monthStart))
            ->orderByDesc('id')
            ->get()
            // One row per business, and skip ones the business has since paid.
            ->unique('business_id')
            ->reject(fn (Payment $p) => Payment::query()
                ->where('business_id', $p->business_id)
                ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
                ->where('payment_status', Payment::STATUS_SUCCEEDED)
                ->where('created_at', '>', $p->created_at)
                ->exists());

        return $rows->map(fn (Payment $row) => $this->checkCheckout($row))->values();
    }

    /** @return array<string, mixed> */
    private function checkCheckout(Payment $row): array
    {
        $result = $this->baseResult($row, 'checkout');
        $result['amount'] = (float) $row->amount;
        $result['currency'] = strtoupper((string) $row->currency);

        if (! $row->stripe_checkout_session_id) {
            $result['outcome'] = PaymentCheckRun::OUTCOME_ISSUE;
            $result['message'] = 'Not paid — the customer has not started Stripe checkout yet'.$this->dueSuffix($row).'.';

            return $result;
        }

        try {
            $session = $this->stripe->retrieveSession($row->stripe_checkout_session_id);
            $result['stripe_status'] = trim(($session->status ?? '').' / '.($session->payment_status ?? ''), ' /');

            if ($this->sync->applyCheckoutSession($session)) {
                $result['outcome'] = PaymentCheckRun::OUTCOME_FIXED;
                $result['paid_this_month'] = true;
                $result['message'] = 'Stripe shows this checkout as paid — payment marked succeeded (webhook had missed it).';
            } elseif (($session->status ?? null) === 'expired') {
                $this->sync->markCanceledIfPending($row, 'Checkout session expired before payment was completed.');
                $result['outcome'] = PaymentCheckRun::OUTCOME_ISSUE;
                $result['message'] = 'Not paid — the checkout session expired'.$this->dueSuffix($row->refresh()).'.';
            } else {
                $result['outcome'] = PaymentCheckRun::OUTCOME_ISSUE;
                $result['message'] = 'Not paid yet — checkout is '.($session->status ?? 'open').$this->dueSuffix($row).'.';
            }

            $result['local_status'] = $row->refresh()->payment_status;
        } catch (\Throwable $e) {
            report($e);
            $result['outcome'] = PaymentCheckRun::OUTCOME_ERROR;
            $result['message'] = 'Stripe check failed: '.$e->getMessage();
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function baseResult(Payment $row, string $kind): array
    {
        return [
            'business_id' => $row->business_id,
            'business_name' => $row->business?->name ?? 'Business #'.$row->business_id,
            'payment_id' => $row->id,
            'kind' => $kind,
            'outcome' => PaymentCheckRun::OUTCOME_OK,
            'local_status' => $row->payment_status,
            'stripe_status' => null,
            'paid_this_month' => false,
            'amount' => null,
            'currency' => null,
            'period_end' => null,
            'message' => '',
        ];
    }

    private function latestLocalStatus(Payment $row): string
    {
        return (string) Payment::query()
            ->where('business_id', $row->business_id)
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->latest('created_at')
            ->orderByDesc('id')
            ->value('payment_status');
    }

    private function subscriptionMessage(string $status, ?object $paid, ?Carbon $periodEnd, bool $cancelAtPeriodEnd, Carbon $monthStart): string
    {
        $end = $periodEnd?->format('d M Y');

        if ($status === 'canceled') {
            return $periodEnd && $periodEnd->isFuture()
                ? "Subscription canceled — access until {$end}."
                : 'Subscription ended this month.';
        }

        $parts = [];
        if ($paid) {
            $paidAt = $this->sync->invoicePaidAt($paid);
            $parts[] = sprintf('Paid %s %s%s', strtoupper((string) $paid->currency), number_format(($paid->amount_paid ?? 0) / 100, 2), $paidAt ? ' on '.$paidAt->format('d M') : '');
        } elseif ($periodEnd && $periodEnd->between($monthStart, $monthStart->copy()->endOfMonth())) {
            $parts[] = "Renews on {$periodEnd->format('d M')} — not charged yet this month";
        } else {
            $parts[] = 'No charge due this month';
        }

        if ($cancelAtPeriodEnd && $end) {
            $parts[] = "set to cancel on {$end}";
        } elseif ($end) {
            $parts[] = "next renewal {$end}";
        }

        return implode('; ', $parts).'.';
    }

    private function dueSuffix(Payment $row): string
    {
        if (! $row->due_at) {
            return '';
        }

        return $row->due_at->isPast()
            ? ' — overdue since '.$row->due_at->format('d M, H:i').' (access locked)'
            : ' — due by '.$row->due_at->format('d M, H:i');
    }

    private function pruneOldRuns(): void
    {
        PaymentCheckRun::query()->where('created_at', '<', now()->subDays(self::KEEP_RUNS_DAYS))->delete();
    }
}
