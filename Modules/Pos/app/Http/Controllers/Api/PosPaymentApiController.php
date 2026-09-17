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
                && $p->current_period_end !== null
        );

        $overdue = $business->overdueSubscriptionPayment();
        $requester = $request->user();

        return response()->json([
            'data' => [
                'subscription_status' => $activeSubscription?->stripe_subscription_status
                    ?? $business->getSetting('business.subscription_status'),
                'next_renewal_at' => $activeSubscription?->current_period_end?->toIso8601String(),
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
