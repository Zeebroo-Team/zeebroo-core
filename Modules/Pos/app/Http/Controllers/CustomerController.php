<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\Customer;

class CustomerController extends Controller
{
    use ResolvesPosBusiness;

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $query = Customer::query()->where('business_id', $business->id);

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('phone', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        $customers = $query->withCount('sales')->orderBy('name')->get();

        return view('pos::customers.index', [
            'business'  => $business,
            'customers' => $customers,
            'search'    => $search,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'email'   => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::query()->create(array_merge($data, ['business_id' => $business->id]));

        if ($request->expectsJson()) {
            return response()->json([
                'id'    => $customer->id,
                'name'  => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'label' => $customer->displayLabel(),
            ], 201);
        }

        return redirect()->route('pos.customers.index')->with('status', 'Customer added.');
    }

    public function update(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $customer->business_id === (int) $business->id, 403);

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'email'   => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->update($data);

        if ($request->expectsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $customer->displayLabel()]);
        }

        return redirect()->route('pos.customers.index')->with('status', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $customer->business_id === (int) $business->id, 403);

        $customer->delete();

        return redirect()->route('pos.customers.index')->with('status', 'Customer deleted.');
    }

    public function search(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json([]);
        }

        $q = trim((string) $request->query('q', ''));
        $query = Customer::query()->where('business_id', $business->id);

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($b) use ($like) {
                $b->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get(['id', 'name', 'phone', 'email']);

        return response()->json($customers->map(fn ($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'phone' => $c->phone ?? '',
            'email' => $c->email ?? '',
            'label' => $c->name . ($c->phone ? ' · ' . $c->phone : ''),
        ]));
    }
}
