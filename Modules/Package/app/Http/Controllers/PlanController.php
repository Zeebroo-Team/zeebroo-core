<?php

namespace Modules\Package\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Package\Models\Package;
use Modules\Package\Services\PlanChangeService;

/**
 * "Choose your plan" — where a trial business picks its real package, during
 * the trial or after it ran out (EnsureWebSubscriptionSettled redirects here).
 */
class PlanController extends Controller
{
    public function __construct(private readonly PlanChangeService $plans) {}

    public function choose(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business || ! $business->isOnTrial()) {
            return redirect()->route('dashboard');
        }

        return view('package::plan.choose', [
            'business' => $business,
            'isOwner' => $this->plans->canChange($business, $request->user()),
            'packages' => $this->plans->options('web'),
            'expired' => $business->trialHasExpired(),
        ]);
    }

    public function review(Request $request, Package $package): View|RedirectResponse
    {
        $business = $this->ownedTrialBusiness($request);
        if (! $business) {
            return redirect()->route('plan.choose');
        }

        if (! $this->plans->options('web')->contains('id', $package->id)) {
            return redirect()->route('plan.choose')->withErrors(['package_id' => 'That package is no longer available.']);
        }

        return view('package::plan.review', [
            'business' => $business,
            'preview' => $this->plans->preview($business, $package, 'web'),
        ]);
    }

    public function change(Request $request, Package $package): RedirectResponse
    {
        $business = $this->ownedTrialBusiness($request);
        if (! $business) {
            return redirect()->route('plan.choose');
        }

        $preview = $this->plans->preview($business, $package, 'web');

        $validated = $request->validate([
            'keep_members' => ['nullable', 'array'],
            'keep_members.*' => ['integer'],
            'confirm_text' => ['nullable', 'string'],
        ]);

        // Losing features with real data needs an explicit, typed confirmation.
        if ($preview['lost_features'] !== [] && strtoupper(trim((string) ($validated['confirm_text'] ?? ''))) !== 'CONFIRM') {
            throw ValidationException::withMessages(['confirm_text' => 'Type CONFIRM to accept that this data will be hidden.']);
        }

        try {
            $result = $this->plans->start(
                $business,
                $package,
                $request->user(),
                $validated['keep_members'] ?? [],
                'web',
                route('payment.checkout.success'),
                route('payment.checkout.cancel', ['payment' => '__PAYMENT__']),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['payment' => 'We could not start Stripe checkout. Please try again.']);
        }

        if ($result['applied']) {
            return redirect()->route('dashboard')->with('status', 'You are now on the '.$package->name.' plan.');
        }

        return redirect()->away($result['checkout_url']);
    }

    private function ownedTrialBusiness(Request $request): ?Business
    {
        $business = Business::currentForNavbar($request->user());

        return $business && $this->plans->canChange($business, $request->user()) ? $business : null;
    }
}
