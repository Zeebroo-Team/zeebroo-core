<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;

class PosCustomerApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $q = (string) $request->query('q', '');

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->withCount('sales')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => collect($customers->items())->map(fn (Customer $c) => $this->format($c))->values(),
            'meta' => [
                'total'        => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);

        $customer->loadCount('sales');
        $customer->load(['sales' => fn ($q) => $q->latest('sold_at')->limit(5)->select('id', 'pos_customer_id', 'sale_number', 'total', 'sold_at', 'payment_method')]);

        return response()->json(['data' => $this->format($customer, full: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::create(array_merge($validated, ['business_id' => $business->id]));
        $customer->loadCount('sales');

        return response()->json(['data' => $this->format($customer)], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);

        $validated = $request->validate([
            'name'    => ['sometimes', 'required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update($validated);
        $customer->loadCount('sales');

        return response()->json(['data' => $this->format($customer)]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);

        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    private function format(Customer $c, bool $full = false): array
    {
        $data = [
            'id'         => $c->id,
            'name'       => $c->name,
            'phone'      => $c->phone,
            'email'      => $c->email,
            'address'    => $c->address,
            'notes'      => $c->notes,
            'sales_count'=> $c->sales_count ?? 0,
        ];

        if ($full && $c->relationLoaded('sales')) {
            $data['recent_sales'] = $c->sales->map(fn ($s) => [
                'id'             => $s->id,
                'sale_number'    => $s->sale_number,
                'total'          => $s->total,
                'payment_method' => $s->payment_method,
                'sold_at'        => $s->sold_at?->toDateTimeString(),
            ])->values();
        }

        return $data;
    }
}
