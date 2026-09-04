<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosSettingsService;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Supplier;
use Modules\Purchase\Models\SupplierCategory;

class PosSupplierApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $q          = (string) $request->query('q', '');
        $active     = $request->query('active'); // null = all, 1 = active only
        $categoryId = $request->query('category_id');

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->when($active !== null, fn ($q2) => $q2->where('is_active', (bool) $active))
            ->when($q !== '', fn ($q2) => $q2->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($categoryId !== null && $categoryId !== '', fn ($q2) => $q2->where('supplier_category_id', (int) $categoryId))
            ->withCount('purchases')
            ->with('category')
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
        $supplier->load([
            'category',
            'purchases' => fn ($q) => $q->latest('purchase_date')->latest('id')->limit(10)
                ->select('id', 'supplier_id', 'po_number', 'status', 'purchase_date', 'total'),
        ]);

        return response()->json(['data' => $this->format($supplier, full: true)]);
    }

    public function goodsReceive(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);

        $grns = GoodsReceiveNote::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                    ->orWhereHas('purchase', fn ($q2) => $q2->where('supplier_id', $supplier->id));
            })
            ->latest('received_date')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $grns->map(fn (GoodsReceiveNote $g) => [
                'id'                   => $g->id,
                'grn_number'           => $g->grn_number,
                'received_date'        => $g->received_date?->toDateString(),
                'total'                => $g->total,
                'payment_status'       => $g->paymentStatus(),
                'payment_status_label' => $g->paymentStatusLabel(),
            ])->values(),
        ]);
    }

    public function cheques(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);

        $cheques = ChequePayment::query()
            ->where('business_id', $business->id)
            ->whereHas('goodsReceiveNote', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                    ->orWhereHas('purchase', fn ($q2) => $q2->where('supplier_id', $supplier->id));
            })
            ->latest('due_date')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $cheques->map(fn (ChequePayment $c) => [
                'id'            => $c->id,
                'cheque_number' => $c->cheque_number,
                'due_date'      => $c->due_date?->toDateString(),
                'amount'        => $c->amount,
                'status'        => $c->displayStatus(),
                'status_label'  => $c->displayStatusLabel(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'contact_name'          => ['nullable', 'string', 'max:255'],
            'email'                 => ['nullable', 'email', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'address'               => ['nullable', 'string', 'max:500'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'supplier_category_id'  => [
                'nullable', 'integer',
                Rule::exists('supplier_categories', 'id')->where('business_id', $business->id),
            ],
        ]);

        $supplier = Supplier::create(array_merge($validated, [
            'business_id' => $business->id,
            'is_active'   => true,
        ]));

        $supplier->loadCount('purchases');
        $supplier->load('category');

        return response()->json(['data' => $this->format($supplier)], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $validated = $request->validate([
            'name'                  => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name'          => ['nullable', 'string', 'max:255'],
            'email'                 => ['nullable', 'email', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'address'               => ['nullable', 'string', 'max:500'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'is_active'             => ['sometimes', 'boolean'],
            'supplier_category_id'  => [
                'nullable', 'integer',
                Rule::exists('supplier_categories', 'id')->where('business_id', $business->id),
            ],
        ]);

        $supplier->update($validated);
        $supplier->loadCount('purchases');
        $supplier->load('category');

        return response()->json(['data' => $this->format($supplier)]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $supplier->business_id !== (int) $business->id) abort(403);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $supplier->update(['is_active' => false]);

        return response()->json(['message' => 'Supplier deactivated.']);
    }

    public function import(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_suppliers');

        $request->validate([
            'rows'                => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name'         => ['required', 'string', 'max:255'],
            'rows.*.contact_name' => ['nullable', 'string', 'max:255'],
            'rows.*.phone'        => ['nullable', 'string', 'max:50'],
            'rows.*.email'        => ['nullable', 'email', 'max:255'],
            'rows.*.address'      => ['nullable', 'string', 'max:500'],
            'rows.*.notes'        => ['nullable', 'string', 'max:1000'],
            'rows.*.category'     => ['nullable', 'string', 'max:120'],
        ]);

        $settings = app(PosSettingsService::class)->forBusiness($business);
        $rows     = $request->input('rows');
        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $catCache = [];

        foreach ($rows as $idx => $row) {
            try {
                if (!empty($settings['supplier_require_phone']) && empty($row['phone'])) {
                    throw new \RuntimeException('Phone number is required.');
                }
                if (!empty($settings['supplier_require_email']) && empty($row['email'])) {
                    throw new \RuntimeException('Email is required.');
                }
                if (!empty($settings['supplier_require_address']) && empty($row['address'])) {
                    throw new \RuntimeException('Address is required.');
                }

                $categoryId = null;
                if (!empty($row['category'])) {
                    $catName = trim($row['category']);
                    if (!isset($catCache[$catName])) {
                        $cat = SupplierCategory::firstOrCreate(
                            ['business_id' => $business->id, 'name' => $catName],
                        );
                        $catCache[$catName] = $cat->id;
                    }
                    $categoryId = $catCache[$catName];
                }

                Supplier::create([
                    'business_id'           => $business->id,
                    'name'                  => $row['name'],
                    'contact_name'          => $row['contact_name'] ?? null,
                    'phone'                 => $row['phone'] ?? null,
                    'email'                 => $row['email'] ?? null,
                    'address'               => $row['address'] ?? null,
                    'notes'                 => $row['notes'] ?? null,
                    'supplier_category_id'  => $categoryId,
                    'is_active'             => true,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row'     => $idx + 1,
                    'name'    => $row['name'] ?? '',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    private function format(Supplier $s, bool $full = false): array
    {
        $data = [
            'id'                    => $s->id,
            'name'                  => $s->name,
            'contact_name'          => $s->contact_name,
            'email'                 => $s->email,
            'phone'                 => $s->phone,
            'address'               => $s->address,
            'notes'                 => $s->notes,
            'is_active'             => (bool) $s->is_active,
            'purchases_count'       => $s->purchases_count ?? 0,
            'supplier_category_id'  => $s->supplier_category_id,
            'category_name'         => $s->relationLoaded('category') ? $s->category?->name : null,
        ];

        if ($full && $s->relationLoaded('purchases')) {
            $data['recent_purchase_orders'] = $s->purchases->map(fn ($p) => [
                'id'            => $p->id,
                'po_number'     => $p->po_number,
                'status'        => $p->status,
                'status_label'  => $p->statusLabel(),
                'purchase_date' => $p->purchase_date?->toDateString(),
                'total'         => $p->total,
            ])->values();
        }

        return $data;
    }
}
