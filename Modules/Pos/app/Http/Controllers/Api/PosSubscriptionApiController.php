<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\CustomerSubscription;
use Modules\Pos\Services\CustomerSubscriptionService;

class PosSubscriptionApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CustomerSubscriptionService $subscriptions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $status     = (string) $request->query('status', 'all');
        $search     = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');

        $page = $this->subscriptions->list(
            $business,
            $status,
            $customerId !== null ? (int) $customerId : null,
            $search !== '' ? $search : null,
            (int) $request->query('per_page', 25),
        );

        return response()->json([
            'data' => collect($page->items())->map(fn (CustomerSubscription $s) => $this->format($s))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);

        $subscription->load(['customer', 'product', 'sale']);

        return response()->json(['data' => $this->format($subscription)]);
    }

    public function forCustomer(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $customer->business_id === (int) $business->id, 404);

        $page = $this->subscriptions->list($business, 'all', $customer->id, null, 100);

        return response()->json([
            'data' => collect($page->items())->map(fn (CustomerSubscription $s) => $this->format($s))->all(),
        ]);
    }

    public function cancel(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);

        $subscription = $this->subscriptions->cancel($subscription);

        return response()->json(['message' => 'Subscription cancelled.', 'data' => $this->format($subscription)]);
    }

    public function pause(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be paused.');

        $subscription = $this->subscriptions->pause($subscription);

        return response()->json(['message' => 'Subscription paused.', 'data' => $this->format($subscription)]);
    }

    public function resume(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be resumed.');

        $subscription = $this->subscriptions->resume($subscription);

        return response()->json(['message' => 'Subscription resumed.', 'data' => $this->format($subscription)]);
    }

    public function renew(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);
        abort_unless($subscription->status !== CustomerSubscription::STATUS_CANCELLED, 422, 'Cancelled subscriptions cannot be renewed.');

        $subscription = $this->subscriptions->renew($subscription);

        return response()->json(['message' => 'Subscription marked as renewed.', 'data' => $this->format($subscription)]);
    }

    public function notify(Request $request, CustomerSubscription $subscription): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $subscription->business_id === (int) $business->id, 404);

        $result = $this->subscriptions->notify($business, $subscription);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Reminder sent.', 'data' => $this->format($subscription->fresh())]);
    }

    private function format(CustomerSubscription $s): array
    {
        return [
            'id'                => $s->id,
            'customer_id'       => $s->pos_customer_id,
            'customer_name'     => $s->customer?->name,
            'customer_phone'    => $s->customer?->phone,
            'customer_email'    => $s->customer?->email,
            'product_id'        => $s->product_id,
            'product_name'      => $s->product?->name,
            'product_sku'       => $s->product?->sku,
            'pos_sale_id'       => $s->pos_sale_id,
            'sale_number'       => $s->sale?->sale_number,
            'recurring_period'  => $s->recurring_period,
            'free_trial'        => (bool) $s->free_trial,
            'price'             => (float) $s->price,
            'quantity'          => (float) $s->quantity,
            'status'            => $s->status,
            'status_label'      => CustomerSubscription::statusLabels()[$s->status] ?? ucfirst($s->status),
            'started_at'        => $s->started_at?->toDateString(),
            'next_billing_at'   => $s->next_billing_at?->toDateString(),
            'last_renewed_at'   => $s->last_renewed_at?->toDateString(),
            'last_notified_at'  => $s->last_notified_at?->toIso8601String(),
            'cancelled_at'      => $s->cancelled_at?->toIso8601String(),
        ];
    }
}
