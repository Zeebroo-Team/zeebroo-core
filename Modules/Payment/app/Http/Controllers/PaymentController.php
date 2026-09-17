<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\StripeSubscriptionService;
use Modules\Pos\Services\PosNotificationService;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly PosNotificationService $notifications,
    ) {}

    /**
     * Stripe redirects here after a successful Checkout (success_url).
     * Verifies the session server-side before marking the payment paid —
     * the redirect alone is not trusted, only what Stripe confirms.
     */
    public function success(Request $request): RedirectResponse
    {
        $sessionId = (string) $request->query('session_id');
        if ($sessionId === '') {
            return redirect()->route('dashboard')->withErrors(['payment' => 'Missing Stripe session reference.']);
        }

        $payment = $this->settleFromSessionId($sessionId);

        if (! $payment) {
            return redirect()->route('dashboard')->withErrors(['payment' => 'We could not confirm your payment. Please contact support.']);
        }

        if ($payment->isSucceeded()) {
            return redirect()->route('dashboard')->with('status', 'Payment successful — your monthly subscription is now active.');
        }

        return redirect()->route('dashboard')->withErrors(['payment' => 'Payment was not completed.']);
    }

    /**
     * Stripe redirects here when the customer abandons Checkout (cancel_url).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $payment = Payment::find($request->query('payment'));
        $this->markCanceledIfPending($payment);

        return redirect()->route('dashboard')->withErrors(['payment' => 'Payment was canceled. Your business setup is saved — complete payment to activate your subscription.']);
    }

    /**
     * Desktop counterpart of success() — reached by the system browser after
     * the Electron app opens Stripe Checkout via `shell.openExternal()`. No
     * Laravel session exists for that browser, so this route carries no
     * `auth` middleware; authorization instead comes entirely from Stripe's
     * unguessable session_id, verified server-side exactly as success() does.
     */
    public function desktopSuccess(Request $request): View
    {
        $sessionId = (string) $request->query('session_id');
        $payment = $sessionId !== '' ? $this->settleFromSessionId($sessionId) : null;

        return view('payment::desktop-return', [
            'status' => ($payment?->isSucceeded() ?? false) ? 'success' : 'failed',
            'paymentId' => $payment?->id,
        ]);
    }

    /**
     * Desktop counterpart of cancel().
     */
    public function desktopCancel(Request $request): View
    {
        $payment = Payment::find($request->query('payment'));
        $this->markCanceledIfPending($payment);

        return view('payment::desktop-return', [
            'status' => 'cancel',
            'paymentId' => $payment?->id,
        ]);
    }

    /**
     * Verifies a Stripe Checkout session and marks the matching Payment
     * succeeded when Stripe confirms it as paid — shared by the web and
     * desktop success routes. Returns null when the session can't be
     * retrieved or no matching Payment record exists.
     */
    private function settleFromSessionId(string $sessionId): ?Payment
    {
        try {
            $session = $this->stripe->retrieveSession($sessionId);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session retrieval failed', ['error' => $e->getMessage()]);

            return null;
        }

        $paymentId = $session->metadata['payment_id'] ?? $session->client_reference_id ?? null;
        $payment = $paymentId ? Payment::find($paymentId) : null;

        if (! $payment) {
            return null;
        }

        if ($session->payment_status === 'paid' || $session->status === 'complete') {
            // retrieveSession() expands subscription/payment_intent into full Stripe
            // objects — store only their IDs, not the object (which would otherwise
            // stringify into a JSON dump via Stripe\StripeObject::__toString()).
            $subscriptionId = is_object($session->subscription) ? $session->subscription->id : $session->subscription;
            $paymentIntentId = is_object($session->payment_intent) ? $session->payment_intent->id : $session->payment_intent;

            $this->markSucceeded($payment, $session->id, $session->customer, $subscriptionId, $paymentIntentId);
        }

        return $payment;
    }

    /**
     * Shared by cancel() and desktopCancel().
     */
    private function markCanceledIfPending(?Payment $payment): void
    {
        if ($payment && $payment->payment_status === Payment::STATUS_PENDING) {
            $payment->update([
                'payment_status' => Payment::STATUS_CANCELED,
                'failure_reason' => 'Checkout was canceled before payment was completed.',
            ]);
        }
    }

    /**
     * Re-opens Stripe Checkout for a payment that was canceled or failed —
     * the original Checkout Session is single-use, so a fresh one is
     * created against the same pending Payment row.
     */
    public function resume(Request $request, Payment $payment): RedirectResponse
    {
        $business = $payment->business;
        if (! $business || ! Business::canAccess($request->user(), $business)) {
            abort(403);
        }

        if ($payment->payment_type !== Payment::TYPE_SUBSCRIPTION || $payment->isSucceeded()) {
            return redirect()->route('dashboard');
        }

        $package = $payment->package;
        if (! $package) {
            return redirect()->route('dashboard')->withErrors(['payment' => 'This package is no longer available.']);
        }

        try {
            $session = $this->stripe->createSubscriptionCheckoutSession(
                $payment,
                $business,
                $package,
                (float) $payment->amount,
                route('payment.checkout.success'),
                route('payment.checkout.cancel', ['payment' => $payment->id]),
            );
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session creation failed', ['error' => $e->getMessage()]);

            return redirect()->route('dashboard')->withErrors(['payment' => 'Could not start Stripe checkout. Please try again later.']);
        }

        $payment->update([
            'payment_status' => Payment::STATUS_PENDING,
            'stripe_checkout_session_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }

    /**
     * Stripe webhook — authoritative source of truth for subscription state
     * changes that can happen outside the redirect flow (renewals, failed
     * renewal charges, cancellations from the Stripe dashboard, etc).
     */
    public function webhook(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');

        try {
            $event = $secret !== ''
                ? $this->stripe->constructWebhookEvent($payload, $sigHeader, $secret)
                : \Stripe\Event::constructFrom(json_decode($payload, true));
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        $object = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
                $paymentId = $object->metadata['payment_id'] ?? $object->client_reference_id ?? null;
                $payment = $paymentId ? Payment::find($paymentId) : null;
                if ($payment && ! $payment->isSucceeded()) {
                    $this->markSucceeded($payment, $object->id, $object->customer, $object->subscription, $object->payment_intent);
                }
                break;

            case 'invoice.payment_failed':
                $payment = Payment::query()->where('stripe_subscription_id', $object->subscription)->latest()->first();
                if ($payment) {
                    $payment->update([
                        'payment_status' => Payment::STATUS_FAILED,
                        'failure_reason' => 'Stripe invoice payment failed for subscription renewal.',
                        'due_at' => now()->addDays(Payment::GRACE_PERIOD_DAYS),
                    ]);

                    if ($payment->business) {
                        $this->notifications->notifyPaymentFailed($payment->business, $payment);
                    }
                }
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $currentPeriodEnd = isset($object->current_period_end)
                    ? Carbon::createFromTimestamp($object->current_period_end)
                    : null;

                Payment::query()->where('stripe_subscription_id', $object->id)
                    ->update([
                        'stripe_subscription_status' => $object->status ?? 'canceled',
                        'current_period_end' => $currentPeriodEnd,
                    ]);
                break;
        }

        return response('OK', 200);
    }

    private function markSucceeded(Payment $payment, ?string $sessionId, ?string $customerId, ?string $subscriptionId, ?string $paymentIntentId): void
    {
        $payment->update([
            'payment_status' => Payment::STATUS_SUCCEEDED,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_subscription_status' => 'active',
            'paid_at' => now(),
        ]);

        $payment->business?->setSetting('business.subscription_status', 'active');

        if ($payment->business) {
            $this->notifications->notifyPaymentSucceeded($payment->business, $payment->refresh());
        }
    }
}
