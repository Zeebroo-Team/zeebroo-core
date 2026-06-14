<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Branch;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseBillAssignmentApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $branches = $this->safe(fn () => Branch::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->values());

        $departments = $this->safe(function () use ($business) {
            if (! Schema::hasTable('hr_departments')) {
                return collect();
            }
            return \Modules\HRManagement\Models\Department::where('business_id', $business->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
                ->values();
        });

        $properties = $this->safe(function () use ($business, $user) {
            if (! Schema::hasTable('properties')) {
                return collect();
            }
            return \Modules\Account\Models\Property::where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->orderBy('property_name')
                ->get(['id', 'property_name', 'property_type'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->property_name . ' · ' . $p->property_type])
                ->values();
        });

        $employees = $this->safe(function () use ($business) {
            if (! Schema::hasTable('hr_employees')) {
                return collect();
            }
            return \Modules\HRManagement\Models\Employee::where('business_id', $business->id)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_id'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name . ($e->employee_id ? '  #' . $e->employee_id : '')])
                ->values();
        });

        $modifications = $this->safe(function () use ($business) {
            if (! Schema::hasTable('modifications')) {
                return collect();
            }
            return \Modules\Modification\Models\Modification::where('business_id', $business->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
                ->values();
        });

        $rentals = $this->safe(function () use ($business, $user) {
            if (! Schema::hasTable('rentals')) {
                return collect();
            }
            return \Modules\Account\Models\Rental::where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->orderBy('property_type')
                ->get(['id', 'property_type', 'purpose'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->property_type . ($r->purpose ? '  ·  ' . $r->purpose : '')])
                ->values();
        });

        return response()->json([
            'data' => [
                'branches'      => $branches,
                'departments'   => $departments,
                'properties'    => $properties,
                'employees'     => $employees,
                'modifications' => $modifications,
                'rentals'       => $rentals,
            ],
        ]);
    }

    private function safe(callable $fn): Collection
    {
        try {
            return $fn() ?? collect();
        } catch (\Throwable) {
            return collect();
        }
    }
}
