<?php

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Package\Services\BusinessPackageService;

class BusinessPackageController extends Controller
{
    public function __construct(private readonly BusinessPackageService $businessPackages) {}

    public function update(Request $request, Business $business): RedirectResponse
    {
        $manual = $request->input('package_id') === 'manual';
        if ($manual) {
            $request->merge(['package_id' => null]);
        }

        $data = $request->validate([
            'package_id'            => ['nullable', 'integer', 'exists:packages,id'],
            'has_unlimited_access'  => ['boolean'],
            'features'              => ['array'],
            'features.*'            => ['string', 'in:' . implode(',', array_keys(config('features.list', [])))],
            // Blank = fall back to the platform default (config/subscription.php).
            'payment_grace_days'    => ['nullable', 'integer', 'min:0', 'max:' . (int) config('subscription.max_grace_period_days', 365)],
            // Blank = use the package's Max Users. Bought extra users are added on top.
            'max_users_override'    => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        DB::transaction(function () use ($business, $data, $request, $manual): void {
            $this->businessPackages->assign(
                $business,
                $data['package_id'] ?? null,
                $request->boolean('has_unlimited_access'),
                $data['features'] ?? [],
                $manual,
            );

            if ($request->has('payment_grace_days')) {
                $this->businessPackages->setGraceDays($business, $data['payment_grace_days'] ?? null);
            }

            if ($request->has('max_users_override')) {
                $this->businessPackages->setMaxUsersOverride($business, $data['max_users_override'] ?? null);
            }
        });

        return redirect()->back()
            ->with('status', 'Package & features updated for "' . $business->name . '".');
    }
}
