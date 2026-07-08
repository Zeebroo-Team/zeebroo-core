<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Service\Models\ServiceCategory;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Models\ServiceRequest;
use Modules\Service\Services\ServiceItemService;
use Modules\Service\Services\ServiceRequestService;

class PosServiceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ServiceRequestService $requestService,
        private readonly ServiceItemService $itemService,
    ) {}

    public function requests(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q      = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $items = $this->requestService->listForBusiness(
            $business,
            filled($q)      ? $q      : null,
            filled($status) ? $status : null,
        );

        return response()->json([
            'data' => $items->map(fn (ServiceRequest $r) => $this->formatRequest($r)),
        ]);
    }

    public function updateRequestStatus(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $serviceRequest->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
        ]);

        $serviceRequest->update(['status' => $validated['status']]);
        $serviceRequest->refresh()->load(['serviceItem', 'customer']);

        return response()->json([
            'data'    => $this->formatRequest($serviceRequest),
            'message' => 'Status updated.',
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $q        = trim((string) $request->query('q', ''));

        $query = ServiceItem::query()
            ->where('business_id', $business->id)
            ->with('categories');

        if (filled($q)) {
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('name')->get();

        return response()->json([
            'data' => $items->map(fn (ServiceItem $i) => [
                'id'             => $i->id,
                'name'           => $i->name,
                'description'    => $i->description,
                'price'          => (float) $i->price,
                'duration_label' => $i->durationLabel(),
                'is_active'      => $i->is_active,
                'categories'     => $i->categories->pluck('name'),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'price'                      => ['required', 'numeric', 'min:0'],
            'duration_minutes'           => ['nullable', 'integer', 'min:0'],
            'is_active'                  => ['boolean'],
            'is_featured'                => ['boolean'],
            'service_category_ids'       => ['nullable', 'array'],
            'service_category_ids.*'     => ['integer', 'min:1'],
            'employee_ids'               => ['nullable', 'array'],
            'employee_ids.*'             => ['integer', 'min:1'],
            'product_lines'              => ['nullable', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'min:1'],
            'product_lines.*.qty'        => ['required_with:product_lines', 'numeric', 'min:0.001'],
        ]);

        $productLinesSync = [];
        foreach ($validated['product_lines'] ?? [] as $line) {
            $productLinesSync[$line['product_id']] = ['qty' => $line['qty']];
        }

        $item = $this->itemService->create($business, [
            'name'                 => $validated['name'],
            'description'          => $validated['description'] ?? null,
            'price'                => $validated['price'],
            'duration_minutes'     => $validated['duration_minutes'] ?? null,
            'is_active'            => $validated['is_active'] ?? true,
            'is_featured'          => $validated['is_featured'] ?? false,
            'service_category_ids' => $validated['service_category_ids'] ?? [],
            'employee_ids'         => $validated['employee_ids'] ?? [],
            'product_lines'        => $productLinesSync,
        ]);

        return response()->json([
            'data'    => $this->formatItem($item),
            'message' => 'Service created.',
        ], 201);
    }

    public function update(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ($serviceItem->business_id !== $business->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'price'                      => ['required', 'numeric', 'min:0'],
            'duration_minutes'           => ['nullable', 'integer', 'min:0'],
            'is_active'                  => ['boolean'],
            'is_featured'                => ['boolean'],
            'service_category_ids'       => ['nullable', 'array'],
            'service_category_ids.*'     => ['integer', 'min:1'],
            'employee_ids'               => ['nullable', 'array'],
            'employee_ids.*'             => ['integer', 'min:1'],
            'product_lines'              => ['nullable', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'min:1'],
            'product_lines.*.qty'        => ['required_with:product_lines', 'numeric', 'min:0.001'],
        ]);

        $productLinesSync = [];
        foreach ($validated['product_lines'] ?? [] as $line) {
            $productLinesSync[$line['product_id']] = ['qty' => $line['qty']];
        }

        $item = $this->itemService->update($serviceItem, [
            'name'                 => $validated['name'],
            'description'          => $validated['description'] ?? null,
            'price'                => $validated['price'],
            'duration_minutes'     => $validated['duration_minutes'] ?? null,
            'is_active'            => $validated['is_active'] ?? $serviceItem->is_active,
            'is_featured'          => $validated['is_featured'] ?? $serviceItem->is_featured,
            'service_category_ids' => $validated['service_category_ids'] ?? [],
            'employee_ids'         => $validated['employee_ids'] ?? [],
            'product_lines'        => $productLinesSync,
        ]);

        $item->load(['categories', 'employees', 'products']);

        return response()->json([
            'data'    => $this->formatItem($item),
            'message' => 'Service updated.',
        ]);
    }

    public function syncEmployees(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ($serviceItem->business_id !== $business->id) abort(404);

        $validated = $request->validate([
            'employee_ids'   => ['present', 'array'],
            'employee_ids.*' => ['integer', 'min:1'],
        ]);

        $serviceItem->employees()->sync($validated['employee_ids']);

        $serviceItem->load('employees');
        return response()->json([
            'data' => $serviceItem->employees->map(fn ($e) => [
                'id'        => $e->id,
                'name'      => $e->full_name,
                'job_title' => $e->jobTitle?->name ?? null,
            ]),
        ]);
    }

    public function syncProducts(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ($serviceItem->business_id !== $business->id) abort(404);

        $validated = $request->validate([
            'product_lines'              => ['present', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'min:1'],
            'product_lines.*.qty'        => ['required_with:product_lines', 'numeric', 'min:0.001'],
        ]);

        $sync = [];
        foreach ($validated['product_lines'] as $line) {
            $sync[$line['product_id']] = ['qty' => $line['qty']];
        }
        $serviceItem->products()->sync($sync);

        $serviceItem->load('products');
        return response()->json([
            'data' => $serviceItem->products->map(fn ($p) => [
                'id'   => $p->id,
                'name' => $p->name,
                'qty'  => (float) $p->pivot->qty,
                'unit' => $p->unit ?? null,
            ]),
        ]);
    }

    public function destroy(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ($serviceItem->business_id !== $business->id) {
            abort(404);
        }

        $serviceItem->delete();

        return response()->json(['message' => 'Service deleted.']);
    }

    public function show(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ($serviceItem->business_id !== $business->id) {
            abort(404);
        }

        $serviceItem->load(['categories', 'employees', 'products']);

        return response()->json([
            'data' => [
                'id'               => $serviceItem->id,
                'name'             => $serviceItem->name,
                'description'      => $serviceItem->description,
                'price'            => (float) $serviceItem->price,
                'duration_minutes' => $serviceItem->duration_minutes,
                'duration_label'   => $serviceItem->durationLabel(),
                'is_active'        => $serviceItem->is_active,
                'is_featured'      => (bool) $serviceItem->is_featured,
                'categories'       => $serviceItem->categories->map(fn ($c) => [
                    'id'   => $c->id,
                    'name' => $c->name,
                ]),
                'employees'        => $serviceItem->employees->map(fn ($e) => [
                    'id'        => $e->id,
                    'name'      => $e->full_name,
                    'job_title' => $e->jobTitle?->name ?? null,
                ]),
                'products'         => $serviceItem->products->map(fn ($p) => [
                    'id'   => $p->id,
                    'name' => $p->name,
                    'qty'  => (float) $p->pivot->qty,
                    'unit' => $p->unit ?? null,
                ]),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $items = ServiceCategory::query()
            ->where('business_id', $business->id)
            ->withCount('serviceItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $items->map(fn (ServiceCategory $c) => $this->formatCategory($c)),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $category = ServiceCategory::create([
            'business_id' => $business->id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
            'sort_order'  => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'data'    => $this->formatCategory($category->loadCount('serviceItems')),
            'message' => 'Category created.',
        ], 201);
    }

    public function destroyCategory(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ($serviceCategory->business_id !== $business->id) {
            abort(404);
        }

        $serviceCategory->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function formatCategory(ServiceCategory $c): array
    {
        return [
            'id'                  => $c->id,
            'name'                => $c->name,
            'description'         => $c->description,
            'is_active'           => $c->is_active,
            'sort_order'          => $c->sort_order,
            'service_items_count' => (int) ($c->service_items_count ?? 0),
        ];
    }

    private function formatItem(ServiceItem $i): array
    {
        return [
            'id'             => $i->id,
            'name'           => $i->name,
            'description'    => $i->description,
            'price'          => (float) $i->price,
            'duration_label' => $i->durationLabel(),
            'is_active'      => $i->is_active,
            'is_featured'    => (bool) $i->is_featured,
            'categories'     => $i->categories->pluck('name'),
        ];
    }

    private function formatRequest(ServiceRequest $r): array
    {
        return [
            'id'             => $r->id,
            'request_number' => $r->request_number,
            'title'          => $r->title,
            'status'         => $r->status,
            'status_label'   => $r->statusLabel(),
            'status_color'   => $r->statusColor(),
            'total_price'    => $r->total_price !== null ? (float) $r->total_price : null,
            'scheduled_at'   => $r->scheduled_at?->toIso8601String(),
            'notes'          => $r->notes,
            'customer'       => $r->customer
                ? ['id' => $r->customer->id, 'name' => $r->customer->name]
                : null,
            'service_item'   => $r->serviceItem
                ? ['id' => $r->serviceItem->id, 'name' => $r->serviceItem->name]
                : null,
            'created_at'     => $r->created_at->toIso8601String(),
        ];
    }
}
