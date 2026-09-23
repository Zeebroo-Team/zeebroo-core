<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Modules\Pos\Models\Customer;
use Modules\Pos\Services\SaleStockConsumptionService;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceCategory;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceRequestService;

class ServicePosController extends Controller
{
    use ResolvesServiceBusiness;

    public function __construct(
        private readonly ServiceRequestService $requests,
        private readonly SaleStockConsumptionService $stock,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $services = ServiceItem::where('business_id', $business->id)
            ->where('is_active', true)
            ->with('categories')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        $categories = ServiceCategory::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('service::pos.index', [
            'business'   => $business,
            'services'   => $services,
            'categories' => $categories,
            'customers'  => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'   => (string) (get_settings('business.currency', '', $business) ?: ''),
            'receipt'    => session('service_pos_receipt'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

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
            $item = ServiceItem::where('business_id', $business->id)
                ->where('id', (int) $line['service_item_id'])
                ->with('products')
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
            ];

            $receiptItems[] = [
                'name'  => $item->name,
                'qty'   => $qty,
                'price' => $unitPrice,
                'total' => $lineTotal,
            ];
        }

        if (empty($created)) {
            return redirect()->route('service.pos.index')->withErrors(['pos' => 'No valid service items found.']);
        }

        $grandTotal  = round(array_sum(array_column($receiptItems, 'total')), 2);
        $methodLabel = match ($validated['payment_method']) {
            'card'   => 'Card payment',
            'credit' => 'Credit',
            default  => 'Cash',
        };

        $receipt = [
            'request_numbers'      => array_column($created, 'request_number'),
            'sold_at'               => now(),
            'payment_method_label'  => $methodLabel,
            'total'                 => $grandTotal,
            'items'                 => $receiptItems,
        ];

        return redirect()->route('service.pos.index')
            ->with('status', count($created) . ' service ' . (count($created) === 1 ? 'request' : 'requests') . ' billed.')
            ->with('service_pos_receipt', $receipt);
    }
}
