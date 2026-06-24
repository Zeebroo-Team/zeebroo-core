<?php

declare(strict_types=1);

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Property;
use Modules\Modification\Models\Modification;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseModificationApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('modifications')) {
            return response()->json(['data' => [], 'total_count' => 0, 'total_cost_fmt' => '0.00']);
        }

        $mods = Modification::withCount('bills')
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        $totalCost = $mods->sum(fn (Modification $m) => (float) ($m->estimated_cost ?? 0));

        $propertyIds = $mods
            ->where('assignment_type', 'property')
            ->pluck('assignment_reference')
            ->filter(fn ($v) => ctype_digit((string) ($v ?? '')))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $propertyLookup = [];
        if ($propertyIds !== []) {
            foreach (Property::where('business_id', $business->id)
                ->whereIn('id', $propertyIds)
                ->get(['id', 'property_name', 'property_type']) as $prop) {
                $propertyLookup[(string) $prop->id] = $prop->property_name.' · '.$prop->property_type;
            }
        }

        return response()->json([
            'data'           => $mods->map(fn (Modification $m) => $this->format($m, $propertyLookup))->values(),
            'total_count'    => $mods->count(),
            'total_cost_fmt' => number_format($totalCost, 2, '.', ','),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $type = (string) $request->input('assignment_type', 'renovation');

        $assignmentRules = [];
        $workTypeRules   = [];

        if ($type === 'property') {
            $assignmentRules['assignment_reference'] = [
                'required', 'integer',
                Rule::exists('properties', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ];
            $workTypeRules['property_work_type'] = [
                'required', 'string',
                Rule::in(array_keys(Modification::propertyWorkTypeLabels())),
            ];
            $workTypeRules['property_work_type_other'] = [
                Rule::requiredIf(fn () => (string) $request->input('property_work_type') === Modification::PROPERTY_WORK_TYPE_OTHER),
                'nullable', 'string', 'max:255',
            ];
        } elseif ($type === 'renovation') {
            $assignmentRules['assignment_reference'] = ['required', 'string', 'max:255'];
        } else {
            $assignmentRules['assignment_reference'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate(array_merge([
            'name'            => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', Rule::in(['renovation', 'property', 'other'])],
            'estimated_cost'  => ['required', 'numeric', 'min:0'],
            'duration'        => ['nullable', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:5000'],
        ], $assignmentRules, $workTypeRules));

        $isOtherWorkType = $type === 'property'
            && ($validated['property_work_type'] ?? null) === Modification::PROPERTY_WORK_TYPE_OTHER;

        $modification = Modification::create([
            'business_id'              => $business->id,
            'created_by_user_id'       => $request->user()->id,
            'name'                     => $validated['name'],
            'assignment_type'          => $validated['assignment_type'],
            'assignment_reference'     => $validated['assignment_reference'] ?? null,
            'property_work_type'       => $type === 'property' ? ($validated['property_work_type'] ?? null) : null,
            'property_work_type_other' => $isOtherWorkType ? ($validated['property_work_type_other'] ?? null) : null,
            'estimated_cost'           => (float) $validated['estimated_cost'],
            'duration'                 => $validated['duration'] ?? null,
            'description'              => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Modification created.',
            'data'    => $this->format($modification, []),
        ], 201);
    }

    public function show(Request $request, Modification $modification): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $modification->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Modification not found.'], 404);
        }

        $modification->loadCount('bills');

        $propertyLookup = [];
        if ($modification->assignment_type === 'property'
            && ctype_digit((string) ($modification->assignment_reference ?? ''))
        ) {
            $prop = Property::where('business_id', $business->id)
                ->whereKey((int) $modification->assignment_reference)
                ->first(['id', 'property_name', 'property_type']);
            if ($prop) {
                $propertyLookup[(string) $prop->id] = $prop->property_name.' · '.$prop->property_type;
            }
        }

        $bills          = Bill::where('business_id', $business->id)
            ->where('modification_id', $modification->id)
            ->orderByDesc('id')
            ->get();
        $billPayModes   = Bill::paymentModes();

        return response()->json([
            'data' => array_merge(
                $this->format($modification, $propertyLookup),
                [
                    'bills' => $bills->map(fn (Bill $b) => [
                        'id'                 => $b->id,
                        'name'               => $b->name,
                        'category_label'     => $b->categoryDisplayLabel(),
                        'payment_mode_label' => $billPayModes[$b->payment_mode] ?? $b->payment_mode,
                        'recurring_cost_fmt' => number_format((float) $b->recurring_cost, 2, '.', ','),
                        'due_date'           => $b->due_date?->format('Y-m-d'),
                        'description'        => $b->description,
                    ])->values(),
                ]
            ),
        ]);
    }

    public function destroy(Request $request, Modification $modification): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $modification->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Modification not found.'], 404);
        }

        $modification->delete();

        return response()->json(['message' => 'Modification deleted.']);
    }

    private function format(Modification $modification, array $propertyLookup): array
    {
        $workTypeLabels = Modification::propertyWorkTypeLabels();
        $wtKey          = (string) ($modification->property_work_type ?? '');
        $workTypeLabel  = $workTypeLabels[$wtKey] ?? null;

        if ($wtKey === Modification::PROPERTY_WORK_TYPE_OTHER && $modification->property_work_type_other) {
            $workTypeLabel = $modification->property_work_type_other;
        }

        return [
            'id'                  => $modification->id,
            'name'                => $modification->name,
            'assignment_type'     => $modification->assignment_type,
            'assignment_reference'=> $modification->assignment_reference,
            'assignment_display'  => Modification::displayAssignmentReference(
                $modification->assignment_type,
                $modification->assignment_reference,
                $propertyLookup,
            ),
            'property_work_type'  => $modification->property_work_type,
            'work_type_label'     => $workTypeLabel,
            'estimated_cost'      => (float) ($modification->estimated_cost ?? 0),
            'estimated_cost_fmt'  => number_format((float) ($modification->estimated_cost ?? 0), 2, '.', ','),
            'duration'            => $modification->duration,
            'description'         => $modification->description,
            'bills_count'         => (int) ($modification->bills_count ?? 0),
            'created_at'          => $modification->created_at?->format('Y-m-d'),
        ];
    }
}
