<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\CustomerSubscription;
use Modules\Pos\Services\CustomerSubscriptionService;

class SubscriptionController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly CustomerSubscriptionService $subscriptions,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $subscriptions = $this->subscriptions->list(
            $business,
            $status,
            null,
            $search !== '' ? $search : null,
            25,
        );

        return view('pos::subscriptions.index', [
            'business'      => $business,
            'currency'      => $currency,
            'subscriptions' => $subscriptions,
            'status'        => $status,
            'search'        => $search,
            'statusLabels'  => CustomerSubscription::statusLabels(),
        ]);
    }

    public function cancel(Request $request, CustomerSubscription $subscription): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);

        $this->subscriptions->cancel($subscription);

        return back()->with('status', 'Subscription cancelled.');
    }

    public function pause(Request $request, CustomerSubscription $subscription): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be paused.');

        $this->subscriptions->pause($subscription);

        return back()->with('status', 'Subscription paused.');
    }

    public function resume(Request $request, CustomerSubscription $subscription): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be resumed.');

        $this->subscriptions->resume($subscription);

        return back()->with('status', 'Subscription resumed.');
    }

    public function renew(Request $request, CustomerSubscription $subscription): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be renewed.');

        $this->subscriptions->renew($subscription);

        return back()->with('status', 'Subscription marked as renewed.');
    }

    public function notify(Request $request, CustomerSubscription $subscription): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);

        $result = $this->subscriptions->notify($business, $subscription);

        if (!$result['success']) {
            return back()->withErrors(['subscription' => $result['error']]);
        }

        return back()->with('status', 'Reminder sent.');
    }
}
