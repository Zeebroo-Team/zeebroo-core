<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Payment\Services\SubscriptionSummaryService;

/**
 * Secondary pages reachable from the Get Started pill navbar.
 */
class GetStartedController extends Controller
{
    public function __construct(
        private readonly SubscriptionSummaryService $subscriptionSummary,
    ) {}

    public function community(): ViewContract
    {
        return view('business::get-started.community', [
            'support' => config('support'),
        ]);
    }

    public function support(Request $request): ViewContract
    {
        return view('business::get-started.support', [
            'support' => config('support'),
            'business' => Business::currentForNavbar($request->user()),
        ]);
    }

    /**
     * Get Started–styled billing page. Same data as the in-app billing page
     * (SubscriptionSummaryService); cancel/resume/receipt reuse its routes.
     */
    public function billing(Request $request): ViewContract|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());

        // Billing belongs to the owner. Team members also land here when the
        // subscription is overdue (EnsureWebSubscriptionSettled), so point them
        // at the owner instead of showing a 403.
        if ($business && (int) $business->user_id !== (int) $request->user()->id) {
            return redirect()->route('business.platform-choice')->with(
                'status',
                $business->overdueSubscriptionPayment() || $business->subscriptionHasEnded()
                    ? 'This workspace\'s subscription needs attention. Please ask the business owner to settle it.'
                    : 'Billing is managed by the business owner.'
            );
        }

        return view('business::get-started.billing', [
            'business' => $business,
            'package' => $business?->package,
            'summary' => $business ? $this->subscriptionSummary->summarize($business, $request->user()) : null,
        ]);
    }
}
