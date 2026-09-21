<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentProvisioningService;
use Modules\Payment\Services\StripeSubscriptionService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosPaymentApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly PaymentProvisioningService $provisioning,
    ) {}

    /**
     * The business's payment history plus a subscription summary — never
     * includes raw Stripe identifiers or metadata, only what's safe to show
     * the business owner in a billing screen.
     */
    public function history(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->backfillMissingPayment($business);

        $payments = $business->payments()->with('package')->limit(50)->get();
        $this->backfillMissingDueDate($payments);

        $activeSubscription = $payments->first(
            fn (Payment $p) => $p->payment_type === Payment::TYPE_SUBSCRIPTION
                && $p->isSucceeded()
                && $p->stripe_subscription_status === 'active'
        );
        if ($activeSubscription) {
            $this->syncPeriodFromStripe($activeSubscription);
        }

        $overdue = $business->overdueSubscriptionPayment();
        $requester = $request->user();

        return response()->json([
            'data' => [
                'subscription_status' => $activeSubscription?->stripe_subscription_status
                    ?? $business->getSetting('business.subscription_status'),
                'next_renewal_at' => $activeSubscription?->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => (bool) $activeSubscription?->cancel_at_period_end,
                'access_until' => $activeSubscription?->cancel_at_period_end
                    ? $activeSubscription->current_period_end?->toIso8601String()
                    : null,
                'can_manage_subscription' => $activeSubscription !== null
                    && $requester instanceof User
                    && (int) $activeSubscription->user_id === (int) $requester->id,
                'subscription_ended' => $business->subscriptionHasEnded(),
                'overdue' => $overdue ? [
                    'payment_id' => $overdue->id,
                    'due_at' => $overdue->due_at?->toIso8601String(),
                    'can_pay' => $requester instanceof User && (int) $overdue->user_id === (int) $requester->id,
                ] : null,
                'items' => $payments->map(fn (Payment $p) => $this->formatPayment($p))->all(),
            ],
        ]);
    }

    /**
     * Rows created before period tracking (or before the webhook arrived) have
     * no renewal date — pull it from Stripe once so the billing screen and the
     * cancel flow have a real "access until" date.
     */
    private function syncPeriodFromStripe(Payment $payment): void
    {
        if ($payment->current_period_end !== null || ! $payment->stripe_subscription_id) {
            return;
        }

        try {
            $sub = $this->stripe->retrieveSubscription($payment->stripe_subscription_id);
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        $end = $sub->current_period_end ?? ($sub->items->data[0]->current_period_end ?? null);

        Payment::query()->where('stripe_subscription_id', $payment->stripe_subscription_id)->update([
            'current_period_end' => $end !== null ? \Illuminate\Support\Carbon::createFromTimestamp($end) : null,
            'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false) || ($sub->cancel_at ?? null) !== null,
        ]);
        $payment->refresh();
    }

    /**
     * Cancel at the end of the paid period: the business keeps full access
     * until `current_period_end` and is not charged again. Reversible via resume().
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        return $this->changeCancellation($request, true);
    }

    /** Undo a scheduled cancellation while the paid period is still running. */
    public function resumeSubscription(Request $request): JsonResponse
    {
        return $this->changeCancellation($request, false);
    }

    private function changeCancellation(Request $request, bool $cancel): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $payment = $business->payments()
            ->where('payment_type', Payment::TYPE_SUBSCRIPTION)
            ->where('payment_status', Payment::STATUS_SUCCEEDED)
            ->where('stripe_subscription_status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->latest('paid_at')
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'There is no active subscription to change.'], 422);
        }

        abort_unless((int) $payment->user_id === (int) $request->user()->id, 403);

        if ($payment->cancel_at_period_end === $cancel) {
            return response()->json(['message' => $cancel
                ? 'This subscription is already set to cancel.'
                : 'This subscription is not scheduled to cancel.'], 422);
        }

        try {
            $this->stripe->setCancelAtPeriodEnd($payment->stripe_subscription_id, $cancel);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not update your subscription. Please try again later.'], 502);
        }

        // Every billing row of this Stripe subscription shares the flag.
        Payment::query()
            ->where('stripe_subscription_id', $payment->stripe_subscription_id)
            ->update(['cancel_at_period_end' => $cancel]);

        return response()->json([
            'data' => [
                'cancel_at_period_end' => $cancel,
                'access_until' => $payment->current_period_end?->toIso8601String(),
            ],
        ]);
    }

    /**
     * A single payment's masked detail — same shape as one `history()` item.
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $payment->business_id === (int) $business->id, 403);

        return response()->json(['data' => $this->formatPayment($payment->load('package'))]);
    }

    /**
     * PDF receipt for a succeeded payment — never exposes Stripe identifiers.
     */
    public function receipt(Request $request, Payment $payment): Response
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $payment->business_id === (int) $business->id, 403);
        abort_unless($payment->isSucceeded(), 404);

        $pdf = Pdf::loadView('payment::receipt', [
            'business' => $business->load('user'),
            'payment' => $payment->load('package'),
        ]);

        return $pdf->download("receipt-{$payment->id}.pdf");
    }

    /**
     * Businesses created before the Payment module shipped (or otherwise
     * missing their initial billing record) never got a Payment row, so they
     * show as neither paid nor due. Back-fill it here, on first read, using
     * the same provisioning logic that runs at signup — this only ever
     * creates the missing row once, since it's guarded on having none at all.
     */
    private function backfillMissingPayment(Business $business): void
    {
        if ($business->payments()->exists()) {
            return;
        }

        $package = $business->package;
        $owner = $business->user;
        if (! $package || ! $owner) {
            return;
        }

        $this->provisioning->createInitialPayment($business, $package, $owner);
    }

    /**
     * Payments left pending/failed/canceled from before `due_at` tracking
     * existed have no deadline at all, so they could never lock or show a
     * real countdown. Give them a fresh grace period from now — we don't know
     * their true original due date, but "never enforceable" is worse than a
     * slightly generous one.
     *
     * @param \Illuminate\Support\Collection<int, Payment> $payments
     */
    private function backfillMissingDueDate($payments): void
    {
        $actionable = [Payment::STATUS_PENDING, Payment::STATUS_FAILED, Payment::STATUS_CANCELED];

        foreach ($payments as $payment) {
            if ($payment->payment_type === Payment::TYPE_SUBSCRIPTION
                && in_array($payment->payment_status, $actionable, true)
                && $payment->due_at === null
            ) {
                $payment->update(['due_at' => now()->addDays(Payment::GRACE_PERIOD_DAYS)]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'plan' => $payment->package?->name,
            'payment_type' => $payment->payment_type,
            'payment_status' => $payment->payment_status,
            'billing_cycle' => $payment->billing_cycle,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'current_period_end' => $payment->current_period_end?->toIso8601String(),
            'failure_reason' => $payment->failure_reason,
            'due_at' => $payment->due_at?->toIso8601String(),
            'is_overdue' => $payment->isOverdue(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * Starts (or restarts) Stripe Checkout for a pending subscription payment
     * created during desktop registration — returns the Checkout URL for the
     * Electron app to open in the system browser (each Checkout Session is
     * single-use, so this can be called again to retry after a cancel).
     */
    public function checkoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'integer'],
        ]);

        $payment = Payment::query()
            ->where('user_id', $request->user()->id)
            ->find($validated['payment_id']);

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_id' => ['Payment not found.'],
            ]);
        }

        if ($payment->payment_type !== Payment::TYPE_SUBSCRIPTION || $payment->isSucceeded()) {
            throw ValidationException::withMessages([
                'payment_id' => ['This payment does not require checkout.'],
            ]);
        }

        $business = $payment->business;
        $package = $payment->package;

        if (! $business || ! $package) {
            return response()->json(['message' => 'This package or business is no longer available.'], 422);
        }

        try {
            $session = $this->stripe->createSubscriptionCheckoutSession(
                $payment,
                $business,
                $package,
                (float) $payment->amount,
                route('payment.desktop.checkout.success'),
                route('payment.desktop.checkout.cancel', ['payment' => $payment->id]),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not start Stripe checkout. Please try again later.'], 502);
        }

        $payment->update([
            'payment_status' => Payment::STATUS_PENDING,
            'stripe_checkout_session_id' => $session->id,
        ]);

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'checkout_url' => $session->url,
            ],
        ]);
    }

    /**
     * Polled by the desktop wizard after it receives the `socibiz://payment`
     * deep link — the deep link itself is untrusted UI signal, so the app
     * confirms the real status here before letting the user into the app.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'payment_status' => $payment->payment_status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
        ]);
    }
}
