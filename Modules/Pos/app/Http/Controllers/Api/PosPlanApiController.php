<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Package\Services\PlanChangeService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Desktop counterpart of the web "Choose your plan" page (Modules\Package\Http\Controllers\PlanController):
 * a trial business picks its real package. Exempt from EnsureSubscriptionSettled
 * so it keeps working once the trial has expired.
 */
class PosPlanApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PlanChangeService $plans) {}

    /** GET auth/plan — trial state + packages the business can move to. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->resolveBusinessForApi($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        return response()->json([
            'data' => [
                'trial' => $this->trialPayload($business),
                'current_package' => $business->package ? [
                    'id' => $business->package->id,
                    'name' => $business->package->name,
                    'is_trial' => (bool) $business->package->is_trial,
                    'feature_labels' => $business->package->featureLabels(),
                ] : null,
                'can_change' => $this->plans->canChange($business, $request->user()),
                'packages' => $business->isOnTrial()
                    ? $this->plans->options($this->platform($request))->map(fn (Package $p) => $this->packagePayload($p))->values()
                    : [],
            ],
        ]);
    }

    /** GET auth/plan/{package}/preview — features with data that would be hidden, recommendations, seats. */
    public function preview(Request $request, Package $package): JsonResponse
    {
        $business = $this->ownedTrialBusiness($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        if (! $this->plans->options($this->platform($request))->contains('id', $package->id)) {
            return response()->json(['message' => 'That package is no longer available.'], 422);
        }

        $preview = $this->plans->preview($business, $package, $this->platform($request));

        return response()->json([
            'data' => [
                'package' => $this->packagePayload($package),
                'lost_features' => $preview['lost_features'],
                'lost_unused' => $preview['lost_unused'],
                'recommended' => $preview['recommended']->map(fn (Package $p) => $this->packagePayload($p))->values(),
                'seats' => $preview['seats'],
                'requires_confirmation' => $preview['lost_features'] !== [],
                'requires_payment' => ! ($package->is_free || $package->effectivePrice() <= 0),
            ],
        ]);
    }

    /** POST auth/plan/{package} — switch now (free) or return a Stripe Checkout URL (paid). */
    public function change(Request $request, Package $package): JsonResponse
    {
        $business = $this->ownedTrialBusiness($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $validated = $request->validate([
            'keep_member_ids' => ['nullable', 'array'],
            'keep_member_ids.*' => ['integer'],
            'confirm_text' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
        ]);

        $preview = $this->plans->preview($business, $package, $this->platform($request));
        if ($preview['lost_features'] !== [] && strtoupper(trim((string) ($validated['confirm_text'] ?? ''))) !== 'CONFIRM') {
            throw ValidationException::withMessages(['confirm_text' => ['Type CONFIRM to accept that this data will be hidden.']]);
        }

        try {
            $result = $this->plans->start(
                $business,
                $package,
                $request->user(),
                $validated['keep_member_ids'] ?? [],
                $this->platform($request),
                route('payment.desktop.checkout.success'),
                route('payment.desktop.checkout.cancel', ['payment' => '__PAYMENT__']),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not start Stripe checkout. Please try again later.'], 502);
        }

        return response()->json([
            'data' => [
                'applied' => $result['applied'],
                'payment_id' => $result['payment']?->id,
                'checkout_url' => $result['checkout_url'],
            ],
        ]);
    }

    private function ownedTrialBusiness(Request $request): Business|JsonResponse
    {
        $business = $this->resolveBusinessForApi($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        if (! $this->plans->canChange($business, $request->user())) {
            return response()->json(['message' => 'Only the owner of a business on the free trial can change its plan here.'], 403);
        }

        return $business;
    }

    private function platform(Request $request): string
    {
        return $request->input('platform') === 'pos_lite' ? 'pos_lite' : 'desktop';
    }

    /** @return array<string, mixed> */
    private function trialPayload(Business $business): array
    {
        return [
            'active' => $business->isOnTrial(),
            'expired' => $business->trialHasExpired(),
            'ends_at' => $business->trial_ends_at?->toIso8601String(),
            'days_left' => $business->trialDaysLeft(),
        ];
    }

    /** @return array<string, mixed> */
    private function packagePayload(Package $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'price' => (float) $package->price,
            'discounted_price' => $package->discounted_price !== null ? (float) $package->discounted_price : null,
            'effective_price' => $package->effectivePrice(),
            'currency' => $package->currency,
            'currency_symbol' => $package->currencySymbol(),
            'is_free' => (bool) $package->is_free,
            'max_users' => $package->max_users,
            'user_limit_label' => $package->userLimitLabel(),
            'features' => $package->features ?? [],
            'feature_labels' => $package->featureLabels(),
        ];
    }
}
