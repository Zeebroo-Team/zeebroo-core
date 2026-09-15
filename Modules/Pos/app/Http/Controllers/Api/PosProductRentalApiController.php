<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\ProductRental;
use Modules\Pos\Services\ProductRentalService;

class PosProductRentalApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProductRentalService $rentals,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $page = $this->rentals->list(
            $business,
            $status,
            $search !== '' ? $search : null,
            (int) $request->query('per_page', 25),
        );

        return response()->json([
            'data' => collect($page->items())->map(fn (ProductRental $r) => $this->format($r))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, ProductRental $productRental): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $productRental->business_id === (int) $business->id, 404);

        $productRental->load(['customer', 'product', 'sale']);

        return response()->json(['data' => $this->format($productRental)]);
    }

    public function return(Request $request, ProductRental $productRental): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $productRental->business_id === (int) $business->id, 404);

        $productRental = $this->rentals->markReturned($productRental);

        return response()->json(['message' => 'Rental marked as returned.', 'data' => $this->format($productRental)]);
    }

    private function format(ProductRental $r): array
    {
        $status = $r->effectiveStatus();
        $today = now()->startOfDay();
        $dueAt = $r->due_at;

        return [
            'id'               => $r->id,
            'customer_id'      => $r->pos_customer_id,
            'customer_name'    => $r->customer?->name,
            'customer_phone'   => $r->customer?->phone,
            'customer_email'   => $r->customer?->email,
            'product_id'       => $r->product_id,
            'product_name'     => $r->product?->name,
            'product_sku'      => $r->product?->sku,
            'pos_sale_id'      => $r->pos_sale_id,
            'sale_number'      => $r->sale?->sale_number,
            'branch_id'        => $r->branch_id,
            'daily_rate'       => (float) $r->daily_rate,
            'quantity'         => (float) $r->quantity,
            'rented_at'        => $r->rented_at?->toDateString(),
            'due_at'           => $dueAt?->toDateString(),
            'returned_at'      => $r->returned_at?->toDateString(),
            'late_fee'         => (float) $r->late_fee,
            'late_fee_multiplier' => (float) $r->late_fee_multiplier,
            'status'           => $status,
            'status_label'     => ProductRental::statusLabels()[$status] ?? ucfirst($status),
            'days_late'        => ($dueAt !== null && $r->returned_at === null && $today->gt($dueAt)) ? (int) $dueAt->diffInDays($today) : 0,
            'days_remaining'   => ($dueAt !== null && $r->returned_at === null && $today->lte($dueAt)) ? (int) $today->diffInDays($dueAt) : 0,
        ];
    }
}
