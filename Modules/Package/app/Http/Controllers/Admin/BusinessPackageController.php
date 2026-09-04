<?php

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Package\Services\BusinessPackageService;

class BusinessPackageController extends Controller
{
    public function __construct(private readonly BusinessPackageService $businessPackages) {}

    public function update(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'package_id'            => ['nullable', 'integer', 'exists:packages,id'],
            'has_unlimited_access'  => ['boolean'],
            'features'              => ['array'],
            'features.*'            => ['string', 'in:' . implode(',', array_keys(config('features.list', [])))],
        ]);

        $this->businessPackages->assign(
            $business,
            $data['package_id'] ?? null,
            $request->boolean('has_unlimited_access'),
            $data['features'] ?? [],
        );

        return redirect()->back()
            ->with('status', 'Package & features updated for "' . $business->name . '".');
    }
}
