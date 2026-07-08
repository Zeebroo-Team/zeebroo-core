<?php

namespace Modules\Service\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\SaleStockConsumptionService;
use Modules\Service\Models\ServiceCategory;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceRequestService;

class ServicePosController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ServiceRequestService $requests,
        private readonly SaleStockConsumptionService $stock,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q     = trim((string) $request->query('q', ''));
        $catId = (int) $request->query('category', 0);

        $query = ServiceItem::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with('categories');

        if (filled($q)) {
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($catId > 0) {
            $query->whereHas('categories', fn ($sq) => $sq->where('service_categories.id', $catId));
        }

        $items = $query->orderByDesc('is_featured')->orderBy('name')->get();

        $categories = ServiceCategory::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => [
                'services'   => $items->map(fn ($i) => [
                    'id'               => $i->id,
                    'name'             => $i->name,
                    'description'      => $i->description,
                    'price'            => (float) $i->price,
                    'duration_minutes' => $i->duration_minutes,
                    'duration_label'   => $i->durationLabel(),
                    'is_featured'      => (bool) $i->is_featured,
                    'categories'       => $i->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
                ]),
                'categories' => $categories,
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.service_item_id' => ['required', 'integer', 'min:1'],
            'items.*.qty'             => ['required', 'numeric', 'min:1'],
            'items.*.price'           => ['required', 'numeric', 'min:0'],
            'customer_id'             => ['nullable', 'integer', 'min:1'],
            'scheduled_at'            => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'payment_method'          => ['required', 'string', 'in:cash,card,credit'],
        ]);

        $created      = [];
        $receiptItems = [];

        foreach ($validated['items'] as $line) {
            $item = ServiceItem::query()
                ->where('business_id', $business->id)
                ->where('id', (int) $line['service_item_id'])
                ->with('products') // load assigned products for stock deduction
                ->first();

            if (! $item) {
                continue;
            }

            $qty       = max(1, (int) round((float) $line['qty']));
            $unitPrice = round((float) $line['price'], 2);
            $lineTotal = round($unitPrice * $qty, 2);

            $req = DB::transaction(function () use ($business, $item, $qty, $unitPrice, $lineTotal, $validated) {
                $req = $this->requests->create($business, [
                    'service_item_id' => $item->id,
                    'customer_id'     => $validated['customer_id'] ?? null,
                    'title'           => $item->name,
                    'notes'           => $validated['notes'] ?? null,
                    'scheduled_at'    => $validated['scheduled_at'] ?? null,
                    'total_price'     => $lineTotal,
                ]);

                // Deduct stock for each product assigned to this service item
                foreach ($item->products as $product) {
                    $consumeQty = round((float) $product->pivot->qty * $qty, 3);
                    if ($consumeQty <= 0) {
                        continue;
                    }

                    try {
                        $this->stock->consumeFifo($product, $consumeQty);
                    } catch (\Throwable $e) {
                        Log::warning("Service POS: could not deduct stock for product #{$product->id} ({$product->name}): {$e->getMessage()}");
                    }
                }

                return $req;
            });

            $created[] = [
                'id'             => $req->id,
                'request_number' => $req->request_number,
                'title'          => $req->title,
                'total_price'    => (float) $req->total_price,
                'status'         => $req->status,
            ];

            $receiptItems[] = [
                'product_name'    => $item->name,
                'quantity'        => $qty,
                'unit_sell_price' => $unitPrice,
                'line_total'      => $lineTotal,
            ];
        }

        if (empty($created)) {
            return response()->json(['message' => 'No valid service items found.'], 422);
        }

        $grandTotal  = round(array_sum(array_column($receiptItems, 'line_total')), 2);
        $methodLabel = match ($validated['payment_method']) {
            'card'   => 'Card payment',
            'credit' => 'Credit',
            default  => 'Cash',
        };
        $count = count($created);

        $receipt = [
            'sale_number'          => count($created) === 1
                ? $created[0]['request_number']
                : implode(', ', array_column($created, 'request_number')),
            'sold_at'              => now()->toIso8601String(),
            'payment_method'       => $validated['payment_method'],
            'payment_method_label' => $methodLabel,
            'subtotal'             => $grandTotal,
            'total'                => $grandTotal,
            'amount_paid'          => $grandTotal,
            'items'                => $receiptItems,
        ];

        return response()->json([
            'data'    => [
                'requests' => $created,
                'receipt'  => $receipt,
            ],
            'message' => "{$count} service " . ($count === 1 ? 'request' : 'requests') . ' created.',
        ], 201);
    }
}
