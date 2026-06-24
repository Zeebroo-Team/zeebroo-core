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
        $q        = (string) $request->query('q', '');
        $active   = $request->query('active'); // null = all, 1 = active only

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->when($active !== null, fn ($q2) => $q2->where('is_active', (bool) $active))
            ->when($q !== '', fn ($q2) => $q2->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->withCount('purchases')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => collect($suppliers->items())->map(fn (Supplier $s) => $this->format($s))->values(),
            'meta' => [
                'total'        => $suppliers->total(),
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);

        $supplier->loadCount('purchases');

        return response()->json(['data' => $this->format($supplier, full: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:500'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier = Supplier::create(array_merge($validated, [
            'business_id' => $business->id,
            'is_active'   => true,
        ]));

        $supplier->loadCount('purchases');

        return response()->json(['data' => $this->format($supplier)], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);

        $validated = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:500'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $supplier->update($validated);
        $supplier->loadCount('purchases');

        return response()->json(['data' => $this->format($supplier)]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);

        $supplier->update(['is_active' => false]);

        return response()->json(['message' => 'Supplier deactivated.']);
    }

    private function format(Supplier $s, bool $full = false): array
    {
        $data = [
            'id'             => $s->id,
            'name'           => $s->name,
            'contact_name'   => $s->contact_name,
            'email'          => $s->email,
            'phone'          => $s->phone,
            'address'        => $s->address ?? null,
            'notes'          => $s->notes,
            'is_active'      => (bool) $s->is_active,
            'purchases_count'=> $s->purchases_count ?? 0,
        ];

        return $data;
    }
}
