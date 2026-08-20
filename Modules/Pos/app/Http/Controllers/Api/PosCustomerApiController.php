<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;
use Modules\Pos\Services\PosSettingsService;

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
            ->with('category:id,name')
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
        $customer->load([
            'sales' => fn ($q) => $q->latest('sold_at')->limit(5)->select('id', 'pos_customer_id', 'sale_number', 'total', 'sold_at', 'payment_method'),
            'category:id,name',
        ]);

        return response()->json(['data' => $this->format($customer, full: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $validated = $request->validate($this->rules($business));

        $customer = Customer::create(array_merge($validated, ['business_id' => $business->id]));
        $customer->loadCount('sales');
        $customer->load('category:id,name');

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('customer.created', $business, [
                'event'    => 'customer.created',
                'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'created_at' => $customer->created_at?->toIso8601String()],
            ]);
        } catch (\Throwable) {}

        return response()->json(['data' => $this->format($customer)], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $rules = $this->rules($business);
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validated = $request->validate($rules);

        $customer->update($validated);
        $customer->loadCount('sales');
        $customer->load('category:id,name');

        return response()->json(['data' => $this->format($customer)]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $customer->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(Business $business): array
    {
        $settings = app(PosSettingsService::class)->forBusiness($business);

        return [
            'name'                 => ['required', 'string', 'max:255'],
            'phone'                => [!empty($settings['customer_require_phone']) ? 'required' : 'nullable', 'string', 'max:50'],
            'email'                => [!empty($settings['customer_require_email']) ? 'required' : 'nullable', 'email', 'max:255'],
            'address'              => [!empty($settings['customer_require_address']) ? 'required' : 'nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'customer_type'        => ['nullable', 'string', 'in:retail,wholesale'],
            'customer_category_id' => [
                'nullable', 'integer',
                Rule::exists('pos_customer_categories', 'id')->where('business_id', $business->id),
            ],
        ];
    }

    private function format(Customer $c, bool $full = false): array
    {
        $data = [
            'id'                   => $c->id,
            'name'                 => $c->name,
            'phone'                => $c->phone,
            'email'                => $c->email,
            'address'              => $c->address,
            'notes'                => $c->notes,
            'customer_type'        => $c->customer_type ?? 'retail',
            'customer_category_id' => $c->customer_category_id,
            'category_name'        => $c->relationLoaded('category') ? $c->category?->name : null,
            'sales_count'          => $c->sales_count ?? 0,
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
