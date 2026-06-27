<?php

namespace Modules\Modification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Property;
use Modules\Account\Services\PropertyService;
use Modules\Business\Models\Business;
use Modules\Modification\Models\Modification;

class ModificationController extends Controller
{
    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);

        $modifications = Modification::query()
            ->where('business_id', $business->id)
            ->withCount([
                'bills' => fn ($q) => $q->where('business_id', $business->id),
            ])
            ->latest()
            ->paginate(12);

        $propertyIdsNeeded = [];
        foreach ($modifications as $mod) {
            if ($mod->assignment_type === 'property' && ctype_digit((string) ($mod->assignment_reference ?? ''))) {
                $propertyIdsNeeded[(string) $mod->assignment_reference] = true;
            }
        }
        $assignmentPropertyLookup = [];
        if ($propertyIdsNeeded !== []) {
            foreach (Property::query()
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->whereIn('id', array_keys($propertyIdsNeeded))
                ->get() as $prop) {
                $assignmentPropertyLookup[(string) $prop->id] = $prop->property_name.' · '.$prop->property_type;
            }
        }

        return view('modification::index', [
            'business' => $business,
            'modifications' => $modifications,
            'assignmentPropertyLookup' => $assignmentPropertyLookup,
            'propertiesForAssignment' => Property::query()
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->orderBy('property_name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);

        return view('modification::create', [
            'business' => $business,
            'propertiesForAssignment' => Property::query()
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->orderBy('property_name')
                ->get(),
        ]);
    }

    public function bills(Request $request, Modification $modification): View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $modification->business_id === (int) $business->id, 404);

        $bills = Bill::query()
            ->where('business_id', $business->id)
            ->where('modification_id', $modification->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('modification::bills', [
            'business' => $business,
            'modification' => $modification,
            'bills' => $bills,
        ]);
    }

    public function show(Request $request, Modification $modification): View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $modification->business_id === (int) $business->id, 404);

        $aref = $modification->assignment_reference;

        $linkedProperty = null;
        $propertyLookup = [];
        if ($modification->assignment_type === 'property' && ctype_digit((string) ($aref ?? ''))) {
            $linkedProperty = Property::query()
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->whereKey((int) $aref)
                ->first();
            if ($linkedProperty) {
                $propertyLookup[(string) $linkedProperty->id] = $linkedProperty->property_name.' · '.$linkedProperty->property_type;
            }
        }

        $modification->loadCount([
            'bills' => fn ($q) => $q->where('business_id', $business->id),
        ]);

        $billsAssigned = Bill::query()
            ->where('business_id', $business->id)
            ->where('modification_id', $modification->id)
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'assigned_bills_page')
            ->withQueryString();

        $activeTab = match ((string) $request->query('tab', 'details')) {
            'bills', 'property' => (string) $request->query('tab'),
            default => 'details',
        };

        return view('modification::show', [
            'business' => $business,
            'modification' => $modification,
            'linkedProperty' => $linkedProperty,
            'billsAssigned' => $billsAssigned,
            'activeTab' => $activeTab,
            'referenceDisplay' => Modification::displayAssignmentReference(
                $modification->assignment_type,
                $modification->assignment_reference,
                $propertyLookup,
            ),
        ]);
    }

    public function quickStoreProperty(Request $request, PropertyService $propertyService): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);

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

        $property = $propertyService->create($request->user(), $business, $payload);

        return response()->json([
            'property' => [
                'id' => $property->id,
                'property_name' => $property->property_name,
                'property_type' => $property->property_type,
            ],
        ]);
    }

    public function destroy(Request $request, Modification $modification): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $modification->business_id === (int) $business->id, 404);

        $modification->delete();

        return redirect()
            ->route('modification.index')
            ->with('status', __('Modification deleted.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_unless(Business::canAccess($request->user(), $business), 403);

        $type = $request->input('assignment_type');
        $assignmentRules = [];
        $workTypeRules = [];
        if ($type === 'property') {
            $assignmentRules['assignment_reference'] = [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(function ($query) use ($business, $request): void {
                    $query->where('business_id', $business->id)->where('user_id', $request->user()->id);
                }),
            ];
            $workTypeRules['property_work_type'] = [
                'required',
                'string',
                Rule::in(array_keys(Modification::propertyWorkTypeLabels())),
            ];
            $workTypeRules['property_work_type_other'] = [
                Rule::requiredIf(fn () => (string) $request->input('property_work_type') === Modification::PROPERTY_WORK_TYPE_OTHER),
                'nullable',
                'string',
                'max:255',
            ];
        } elseif ($type === 'renovation') {
            $assignmentRules['assignment_reference'] = ['required', 'string', 'max:255'];
        } else {
            $assignmentRules['assignment_reference'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', Rule::in(['renovation', 'property', 'other'])],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'duration' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], $assignmentRules, $workTypeRules));

        $refOut = $validated['assignment_reference'] ?? null;
        if ($validated['assignment_type'] === 'property' && $refOut !== null) {
            $refOut = (string) $refOut;
        }

        Modification::query()->create([
            'business_id' => $business->id,
            'created_by_user_id' => $request->user()->id,
            'name' => $validated['name'],
            'assignment_type' => $validated['assignment_type'],
            'assignment_reference' => $refOut,
            'property_work_type' => $validated['assignment_type'] === 'property' ? ($validated['property_work_type'] ?? null) : null,
            'property_work_type_other' => $validated['assignment_type'] === 'property'
                ? (($validated['property_work_type'] ?? null) === Modification::PROPERTY_WORK_TYPE_OTHER ? ($validated['property_work_type_other'] ?? null) : null)
                : null,
            'estimated_cost' => (float) $validated['estimated_cost'],
            'duration' => $validated['duration'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('modification.index')
            ->with('status', __('Modification added successfully.'));
    }
}
