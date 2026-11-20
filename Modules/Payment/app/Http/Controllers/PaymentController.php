<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentCheckSetting;
use Modules\Payment\Services\StripePaymentSyncService;
use Modules\Payment\Services\StripeSubscriptionService;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly StripePaymentSyncService $sync,
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
            return redirect()->route('business.platform-choice')->withErrors(['payment' => 'Missing Stripe session reference.']);
        }

        $payment = $this->settleFromSessionId($sessionId);

        if (! $payment) {
            return redirect()->route('business.platform-choice')->withErrors(['payment' => 'We could not confirm your payment. Please contact support.']);
        }

        if ($payment->isSucceeded()) {
            return redirect()->route('business.platform-choice')->with('status', 'Payment successful — your monthly subscription is now active.');
        }

        return redirect()->route('business.platform-choice')->withErrors(['payment' => 'Payment was not completed.']);
    }

    /**
     * Stripe redirects here when the customer abandons Checkout (cancel_url).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $payment = Payment::find($request->query('payment'));
        $this->markCanceledIfPending($payment);

        return redirect()->route('business.platform-choice')->withErrors(['payment' => 'Payment was canceled']);
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
            'platform' => $payment?->platform ?? 'desktop',
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
            'platform' => $payment?->platform ?? 'desktop',
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

        $payment = $this->sync->paymentForSession($session);
        if (! $payment) {
            return null;
        }

        $this->sync->applyCheckoutSession($session);

        return $payment->refresh();
    }

    /**
     * Shared by cancel() and desktopCancel().
     */
    private function markCanceledIfPending(?Payment $payment): void
    {
        $this->sync->markCanceledIfPending($payment, 'Checkout was canceled before payment was completed.');
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
            return redirect()->back()->withErrors(['payment' => 'This package is no longer available.']);
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

            return redirect()->back()->withErrors(['payment' => 'Could not start Stripe checkout. Please try again later.']);
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
     * renewal charges, cancellations from the Stripe dashboard, etc). The
     * scheduled payment check (payment:check) re-verifies the same state
     * against Stripe in case a delivery is ever missed.
     */
    public function webhook(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');

        // Without a signing secret anyone could POST a fake
        // "checkout.session.completed" and unlock a business for free — only
        // tolerate unsigned events on a local/testing machine.
        if ($secret === '' && ! app()->environment(['local', 'testing'])) {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured.');

            return response('Webhook secret not configured', 500);
        }

        try {
            $event = $secret !== ''
                ? $this->stripe->constructWebhookEvent($payload, $sigHeader, $secret)
                : \Stripe\Event::constructFrom(json_decode($payload, true) ?? []);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        PaymentCheckSetting::recordWebhook((string) $event->type);

        $object = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->sync->applyCheckoutSession($object);
                break;

            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                $this->sync->markCanceledIfPending(
                    $this->sync->paymentForSession($object),
                    $event->type === 'checkout.session.expired'
                        ? 'Checkout session expired before payment was completed.'
                        : 'The payment method was declined after checkout.',
                );
                break;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                $this->sync->recordInvoicePaid($object);
                break;

            case 'invoice_payment.paid':
                // Newer API versions report renewal success on this InvoicePayment
                // object, which only references its parent Invoice by ID.
                try {
                    $invoice = $this->stripe->retrieveInvoice($object->invoice);
                } catch (\Throwable $e) {
                    report($e);
                    break;
                }
                $this->sync->recordInvoicePaid($invoice);
                break;

            case 'invoice.payment_failed':
                $this->sync->recordInvoiceFailed($object);
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->sync->syncSubscription($object);
                break;
        }

        return response('OK', 200);
    }
}
