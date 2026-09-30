<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Exceptions\SubscriptionActionException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\StripeSubscriptionService;
use Modules\Payment\Services\SubscriptionCancellationService;
use Modules\Payment\Services\SubscriptionSummaryService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosPaymentApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly StripeSubscriptionService $stripe,
        private readonly SubscriptionSummaryService $summary,
        private readonly SubscriptionCancellationService $cancellation,
    ) {}

    /**
     * The business's payment history plus a subscription summary — never
     * includes raw Stripe identifiers or metadata, only what's safe to show
     * the business owner in a billing screen.
     */
    public function history(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        // PosCashier tokens are not App\Models\User — a cashier can see the
        // lock/due state but never pay, so summarize as an anonymous requester.
        $user = $request->user();

        return response()->json([
            'data' => $this->summary->summarize($business, $user instanceof User ? $user : null),
        ]);
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

        try {
            $payment = $this->cancellation->change($business, $request->user(), $cancel);
        } catch (SubscriptionActionException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

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

        return response()->json(['data' => $this->summary->formatPayment($payment->load('package'))]);
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
