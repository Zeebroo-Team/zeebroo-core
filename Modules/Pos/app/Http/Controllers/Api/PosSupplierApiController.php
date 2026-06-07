<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\Supplier;

class PosSupplierApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'email', 'phone']);

        return response()->json([
            'data' => $suppliers->map(fn (Supplier $s) => $this->format($s))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier = Supplier::create(array_merge($validated, [
            'business_id' => $business->id,
            'is_active'   => true,
        ]));

        return response()->json(['data' => $this->format($supplier)], 201);
    }

    private function format(Supplier $s): array
    {
        return [
            'id'           => $s->id,
            'name'         => $s->name,
            'contact_name' => $s->contact_name,
            'email'        => $s->email,
            'phone'        => $s->phone,
        ];
    }
}
