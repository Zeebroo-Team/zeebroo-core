<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Payment\Exceptions\SubscriptionActionException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\SubscriptionCancellationService;
use Modules\Payment\Services\SubscriptionSummaryService;

/**
 * Web-session counterpart of Modules\Pos\Http\Controllers\Api\PosPaymentApiController
 * — same subscription status, payment history, and cancel/resume flow the
 * desktop app's "Billing & Payments" screen shows, reached from the browser
 * instead of the Electron app.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionSummaryService $summary,
        private readonly SubscriptionCancellationService $cancellation,
    ) {}

    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());

        return view('payment::billing', [
            'business' => $business,
            'summary' => $business ? $this->summary->summarize($business, $request->user()) : null,
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        return $this->changeCancellation($request, true);
    }

    public function resume(Request $request): RedirectResponse
    {
        return $this->changeCancellation($request, false);
    }

    private function changeCancellation(Request $request, bool $cancel): RedirectResponse
    {
        // The Get Started billing page posts here too and wants to land back on itself.
        $returnRoute = $request->input('return_to') === 'get-started'
            ? 'business.get-started.billing'
            : 'payment.billing.index';

        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route($returnRoute);
        }

        try {
            $this->cancellation->change($business, $request->user(), $cancel);
        } catch (SubscriptionActionException $e) {
            return redirect()->route($returnRoute)->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route($returnRoute)->with('status', $cancel
            ? 'Your subscription is set to cancel at the end of the current billing period.'
            : 'Your subscription will now renew as normal — the scheduled cancellation was undone.');
    }

    /**
     * PDF receipt for a succeeded payment — same document the desktop app downloads.
     */
    public function receipt(Request $request, Payment $payment): Response
    {
        $business = Business::currentForNavbar($request->user());
        abort_unless($business && (int) $payment->business_id === (int) $business->id, 403);
        abort_unless($payment->isSucceeded(), 404);

        $pdf = Pdf::loadView('payment::receipt', [
            'business' => $business->load('user'),
            'payment' => $payment->load('package'),
        ]);

        return $pdf->download("receipt-{$payment->id}.pdf");
    }
}
