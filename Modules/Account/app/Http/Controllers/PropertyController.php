<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Account\Models\Property;
use Modules\Account\Services\PropertyService;
use Modules\Business\Models\Business;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertyService $propertyService,
    ) {}

    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        $properties = $business
            ? $this->propertyService->listForBusiness($business)
            : collect();

        return view('account::properties.index', [
            'business' => $business,
            'properties' => $properties,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $validated = $request->validate([
            'property_name' => ['required', 'string', 'max:255'],
            'property_type' => ['required', 'string', Rule::in(array_keys(Property::typeOptions()))],
            'property_type_other' => ['nullable', 'string', 'max:255', 'required_if:property_type,other'],
            'cost' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'has_expiry' => ['nullable', 'boolean'],
            'expire_date' => ['nullable', 'date', 'required_if:has_expiry,1'],
        ]);

        $propertyType = $validated['property_type'] === 'other'
            ? trim((string) ($validated['property_type_other'] ?? ''))
            : $validated['property_type'];

        $payload = [
            'property_name' => $validated['property_name'],
            'property_type' => $propertyType,
            'cost' => $validated['cost'],
            'description' => $validated['description'] ?? null,
            'has_expiry' => (bool) ($validated['has_expiry'] ?? false),
            'expire_date' => ($validated['has_expiry'] ?? false)
                ? ($validated['expire_date'] ?? null)
                : null,
        ];

        $this->propertyService->create($request->user(), $business, $payload);

        return redirect()->route('account.properties.index')->with('status', 'Property saved.');
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        abort_unless($this->propertyService->deleteForUser($request->user(), $property), 403);

        return redirect()->route('account.properties.index')->with('status', 'Property removed.');
    }
}
