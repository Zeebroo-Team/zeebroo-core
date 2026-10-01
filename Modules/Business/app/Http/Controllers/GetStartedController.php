<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
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
    public function billing(Request $request): ViewContract
    {
        $business = Business::currentForNavbar($request->user());

        return view('business::get-started.billing', [
            'business' => $business,
            'package' => $business?->package,
            'summary' => $business ? $this->subscriptionSummary->summarize($business, $request->user()) : null,
        ]);
    }
}
