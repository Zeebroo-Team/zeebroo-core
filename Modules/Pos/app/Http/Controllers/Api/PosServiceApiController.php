<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;
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

        // Eager-load project for all requests
        $items->load('project');

        return response()->json([
            'data' => $items->map(fn (ServiceRequest $r) => $this->formatRequest($r)),
        ]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'svc_requests');

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'service_item_id' => ['nullable', 'integer', 'min:1'],
            'customer_id'     => ['nullable', 'integer', 'min:1'],
            'project_id'      => ['nullable', 'integer', 'min:1'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'scheduled_at'    => ['nullable', 'date'],
            'total_price'     => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($validated['project_id'])) {
            $project = Project::where('id', $validated['project_id'])
                              ->where('business_id', $business->id)->first();
            if (! $project) {
                return response()->json(['message' => 'Project not found.'], 422);
            }
        }

        $serviceRequest = $this->requestService->create($business, $validated);
        $serviceRequest->load(['serviceItem', 'customer', 'project']);

        return response()->json([
            'data'    => $this->formatRequest($serviceRequest),
            'message' => 'Request created.',
        ], 201);
    }

    public function updateRequestStatus(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'svc_requests');

        if ((int) $serviceRequest->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $validated = $request->validate([
            'status'      => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
            'project_id'  => ['nullable', 'integer', 'min:1'],
            'create_task' => ['nullable', 'boolean'],
        ]);

        // Assign project if provided
        if (array_key_exists('project_id', $validated)) {
            $projectId = $validated['project_id'] ? (int) $validated['project_id'] : null;
            if ($projectId) {
                $project = Project::where('id', $projectId)->where('business_id', $business->id)->first();
                if ($project) {
                    $serviceRequest->update(['project_id' => $project->id]);
                }
            }
        }

        $serviceRequest->update(['status' => $validated['status']]);
        $serviceRequest->refresh()->load(['serviceItem', 'customer', 'project']);

        // Auto-create PM task when starting a request that belongs to a project
        // create_task defaults to true; set false when the caller already created the task/project
        $shouldCreateTask = (bool) ($validated['create_task'] ?? true);
        $pmTask = null;
        if ($shouldCreateTask && $validated['status'] === ServiceRequest::STATUS_IN_PROGRESS && $serviceRequest->project_id) {
            try {
                $title = $serviceRequest->title;
                if ($serviceRequest->relationLoaded('serviceItem') && $serviceRequest->serviceItem) {
                    $title .= ' — ' . $serviceRequest->serviceItem->name;
                }
                $task = Task::create([
                    'project_id'  => $serviceRequest->project_id,
                    'title'       => $title,
                    'description' => $serviceRequest->notes,
                    'status'      => Task::STATUS_IN_PROGRESS,
                    'priority'    => Task::PRIORITY_NORMAL,
                    'due_date'    => $serviceRequest->scheduled_at?->toDateString(),
                    'sort_order'  => 0,
                ]);
                $pmTask = ['id' => $task->id, 'title' => $task->title];
            } catch (\Throwable) {
                // Task creation failure must not break the status update
            }
        }

        return response()->json([
            'data'    => $this->formatRequest($serviceRequest),
            'pm_task' => $pmTask,
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

        $items = $query->with('imageFile')->orderBy('name')->get();

        return response()->json([
            'data' => $items->map(fn (ServiceItem $i) => [
                'id'             => $i->id,
                'name'           => $i->name,
                'description'    => $i->description,
                'price'          => (float) $i->price,
                'duration_label' => $i->durationLabel(),
                'is_active'      => $i->is_active,
                'has_warranty'   => (bool) $i->has_warranty,
                'categories'     => $i->categories->pluck('name'),
                'image_url'      => $i->imageFile?->publicUrl(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'svc_catalog');

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'barcode'                    => ['nullable', 'string', 'max:120'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'price'                      => ['required', 'numeric', 'min:0'],
            'cost_price'                 => ['nullable', 'numeric', 'min:0'],
            'wholesale_price'            => ['nullable', 'numeric', 'min:0'],
            'duration_minutes'           => ['nullable', 'integer', 'min:0'],
            'is_active'                  => ['boolean'],
            'is_featured'                => ['boolean'],
            'has_warranty'               => ['boolean'],
            'custom_requirement_enabled' => ['boolean'],
            'custom_requirement_fields'          => ['nullable', 'array', 'max:20'],
            'custom_requirement_fields.*.key'    => ['nullable', 'string', 'max:100'],
            'custom_requirement_fields.*.label'  => ['required_with:custom_requirement_fields', 'string', 'max:255'],
            'custom_requirement_fields.*.type'   => ['nullable', 'string', 'in:text,textarea,select,number,date,checkbox,radio'],
            'custom_requirement_fields.*.options'   => ['nullable', 'array'],
            'custom_requirement_fields.*.options.*' => ['nullable', 'string', 'max:120'],
            'service_category_ids'       => ['nullable', 'array'],
            'service_category_ids.*'     => ['integer', 'min:1'],
            'employee_ids'               => ['nullable', 'array'],
            'employee_ids.*'             => ['integer', 'min:1'],
            'product_lines'              => ['nullable', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'min:1'],
            'product_lines.*.qty'        => ['required_with:product_lines', 'numeric', 'min:0.001'],
            'file_manager_file_id'       => ['nullable', 'integer', 'exists:file_manager_files,id'],
        ]);

        $productLinesSync = [];
        foreach ($validated['product_lines'] ?? [] as $line) {
            $productLinesSync[$line['product_id']] = ['qty' => $line['qty']];
        }

        $item = $this->itemService->create($business, [
            'name'                        => $validated['name'],
            'barcode'                     => $validated['barcode'] ?? null,
            'description'                 => $validated['description'] ?? null,
            'price'                       => $validated['price'],
            'cost_price'                  => $validated['cost_price'] ?? null,
            'wholesale_price'             => $validated['wholesale_price'] ?? null,
            'duration_minutes'            => $validated['duration_minutes'] ?? null,
            'is_active'                   => $validated['is_active'] ?? true,
            'is_featured'                 => $validated['is_featured'] ?? false,
            'has_warranty'                => $validated['has_warranty'] ?? false,
            'custom_requirement_enabled'  => $validated['custom_requirement_enabled'] ?? false,
            'custom_requirement_fields'   => $validated['custom_requirement_fields'] ?? [],
            'service_category_ids'        => $validated['service_category_ids'] ?? [],
            'employee_ids'                => $validated['employee_ids'] ?? [],
            'product_lines'               => $productLinesSync,
            'file_manager_file_id'        => $validated['file_manager_file_id'] ?? null,
        ]);

        $item->load('imageFile');

        return response()->json([
            'data'    => $this->formatItem($item),
            'message' => 'Service created.',
        ], 201);
    }

    public function update(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'svc_catalog');

        if ($serviceItem->business_id !== $business->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'barcode'                    => ['nullable', 'string', 'max:120'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'price'                      => ['required', 'numeric', 'min:0'],
            'cost_price'                 => ['nullable', 'numeric', 'min:0'],
            'wholesale_price'            => ['nullable', 'numeric', 'min:0'],
            'duration_minutes'           => ['nullable', 'integer', 'min:0'],
            'is_active'                  => ['boolean'],
            'is_featured'                => ['boolean'],
            'has_warranty'               => ['boolean'],
            'custom_requirement_enabled' => ['boolean'],
            'custom_requirement_fields'          => ['nullable', 'array', 'max:20'],
            'custom_requirement_fields.*.key'    => ['nullable', 'string', 'max:100'],
            'custom_requirement_fields.*.label'  => ['required_with:custom_requirement_fields', 'string', 'max:255'],
            'custom_requirement_fields.*.type'   => ['nullable', 'string', 'in:text,textarea,select,number,date,checkbox,radio'],
            'custom_requirement_fields.*.options'   => ['nullable', 'array'],
            'custom_requirement_fields.*.options.*' => ['nullable', 'string', 'max:120'],
            'service_category_ids'       => ['nullable', 'array'],
            'service_category_ids.*'     => ['integer', 'min:1'],
            'employee_ids'               => ['nullable', 'array'],
            'employee_ids.*'             => ['integer', 'min:1'],
            'product_lines'              => ['nullable', 'array'],
            'product_lines.*.product_id' => ['required_with:product_lines', 'integer', 'min:1'],
            'product_lines.*.qty'        => ['required_with:product_lines', 'numeric', 'min:0.001'],
            'file_manager_file_id'       => ['nullable', 'integer', 'exists:file_manager_files,id'],
        ]);

        $productLinesSync = [];
        foreach ($validated['product_lines'] ?? [] as $line) {
            $productLinesSync[$line['product_id']] = ['qty' => $line['qty']];
        }

        $updateData = [
            'name'                        => $validated['name'],
            'barcode'                     => $validated['barcode'] ?? null,
            'description'                 => $validated['description'] ?? null,
            'price'                       => $validated['price'],
            'cost_price'                  => $validated['cost_price'] ?? null,
            'wholesale_price'             => $validated['wholesale_price'] ?? null,
            'duration_minutes'            => $validated['duration_minutes'] ?? null,
            'is_active'                   => $validated['is_active'] ?? $serviceItem->is_active,
            'is_featured'                 => $validated['is_featured'] ?? $serviceItem->is_featured,
            'has_warranty'                => $validated['has_warranty'] ?? $serviceItem->has_warranty,
            'custom_requirement_enabled'  => $validated['custom_requirement_enabled'] ?? $serviceItem->custom_requirement_enabled,
            'service_category_ids'        => $validated['service_category_ids'] ?? [],
            'employee_ids'                => $validated['employee_ids'] ?? [],
            'product_lines'               => $productLinesSync,
        ];
        if (array_key_exists('custom_requirement_fields', $validated)) {
            $updateData['custom_requirement_fields'] = $validated['custom_requirement_fields'];
        }
        if (array_key_exists('file_manager_file_id', $validated)) {
            $updateData['file_manager_file_id'] = $validated['file_manager_file_id'];
        }

        $item = $this->itemService->update($serviceItem, $updateData);

        $item->load(['categories', 'employees', 'products', 'imageFile']);

        return response()->json([
            'data'    => $this->formatItem($item),
            'message' => 'Service updated.',
        ]);
    }

    public function syncEmployees(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'svc_catalog');
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
        $this->abortUnlessPerm($request, $business, 'svc_catalog');
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
        $this->abortUnlessPerm($request, $business, 'svc_catalog');

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

        $serviceItem->load(['categories', 'employees', 'products', 'imageFile']);

        return response()->json([
            'data' => [
                'id'                    => $serviceItem->id,
                'name'                  => $serviceItem->name,
                'barcode'               => $serviceItem->barcode,
                'description'           => $serviceItem->description,
                'price'                 => (float) $serviceItem->price,
                'cost_price'            => $serviceItem->cost_price !== null ? (float) $serviceItem->cost_price : null,
                'wholesale_price'       => $serviceItem->wholesale_price !== null ? (float) $serviceItem->wholesale_price : null,
                'duration_minutes'      => $serviceItem->duration_minutes,
                'duration_label'        => $serviceItem->durationLabel(),
                'is_active'             => $serviceItem->is_active,
                'is_featured'           => (bool) $serviceItem->is_featured,
                'has_warranty'          => (bool) $serviceItem->has_warranty,
                'custom_requirement_enabled' => (bool) $serviceItem->custom_requirement_enabled,
                'custom_requirement_fields'  => $serviceItem->custom_requirement_fields ?? [],
                'file_manager_file_id'  => $serviceItem->file_manager_file_id,
                'image_url'             => $serviceItem->imageFile?->publicUrl(),
                'categories'            => $serviceItem->categories->map(fn ($c) => [
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
        $this->abortUnlessPerm($request, $business, 'svc_catalog');

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
        $this->abortUnlessPerm($request, $business, 'svc_catalog');

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
            'id'                          => $i->id,
            'name'                        => $i->name,
            'barcode'                     => $i->barcode,
            'description'                 => $i->description,
            'price'                       => (float) $i->price,
            'cost_price'                  => $i->cost_price !== null ? (float) $i->cost_price : null,
            'wholesale_price'             => $i->wholesale_price !== null ? (float) $i->wholesale_price : null,
            'duration_label'              => $i->durationLabel(),
            'is_active'                   => $i->is_active,
            'is_featured'                 => (bool) $i->is_featured,
            'has_warranty'                => (bool) $i->has_warranty,
            'custom_requirement_enabled'  => (bool) $i->custom_requirement_enabled,
            'custom_requirement_fields'   => $i->custom_requirement_fields ?? [],
            'categories'                  => $i->categories->pluck('name'),
            'file_manager_file_id'        => $i->file_manager_file_id,
            'image_url'                   => $i->imageFile?->publicUrl(),
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
            'project'        => $r->project
                ? ['id' => $r->project->id, 'name' => $r->project->name]
                : null,
            'project_id'     => $r->project_id,
            'created_at'     => $r->created_at->toIso8601String(),
        ];
    }
}
