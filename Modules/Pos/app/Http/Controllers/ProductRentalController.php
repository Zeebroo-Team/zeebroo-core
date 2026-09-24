<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\ProductRental;
use Modules\Pos\Services\ProductRentalService;

class ProductRentalController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly ProductRentalService $rentals,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $rentals = $this->rentals->list(
            $business,
            $status,
            $search !== '' ? $search : null,
            25,
        );

        $rows = $rentals->getCollection()->map(fn (ProductRental $rental) => $this->buildRow($rental));

        return view('pos::rentals.index', [
            'business'     => $business,
            'currency'     => $currency,
            'rentals'      => $rentals,
            'rows'         => $rows,
            'status'       => $status,
            'search'       => $search,
            'statusLabels' => ProductRental::statusLabels(),
        ]);
    }

    public function show(Request $request, ProductRental $productRental): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $productRental->business_id === (int) $business->id, 404);

        $productRental->load(['customer', 'product', 'sale', 'saleItem']);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::rentals.show', [
            'business'     => $business,
            'currency'     => $currency,
            'row'          => $this->buildRow($productRental),
            'statusLabels' => ProductRental::statusLabels(),
        ]);
    }

    /**
     * @return array{rental: ProductRental, status: string, duration_days: int, base_total: float, days_late: int, days_remaining: int, late_fee: float, total_amount: float}
     */
    private function buildRow(ProductRental $rental): array
    {
        $today = now()->startOfDay();
        $effectiveStatus = $rental->effectiveStatus();
        $rentedAt = $rental->rented_at;
        $dueAt = $rental->due_at;

        $durationDays = ($rentedAt !== null && $dueAt !== null)
            ? max(1, (int) $rentedAt->diffInDays($dueAt))
            : 1;
        $baseTotal = round((float) $rental->daily_rate * (float) $rental->quantity * $durationDays, 2);

        $daysLate = ($dueAt !== null && $rental->returned_at === null && $today->gt($dueAt)) ? (int) $dueAt->diffInDays($today) : 0;
        $projectedLateFee = $daysLate > 0 ? round($daysLate * (float) $rental->daily_rate * (float) $rental->late_fee_multiplier, 2) : 0.0;
        $effectiveLateFee = $rental->returned_at !== null ? (float) $rental->late_fee : $projectedLateFee;
        $daysRemaining = ($dueAt !== null && $rental->returned_at === null && $today->lte($dueAt)) ? (int) $today->diffInDays($dueAt) : 0;

        return [
            'rental'         => $rental,
            'status'         => $effectiveStatus,
            'duration_days'  => $durationDays,
            'base_total'     => $baseTotal,
            'days_late'      => $daysLate,
            'days_remaining' => $daysRemaining,
            'late_fee'       => $effectiveLateFee,
            'total_amount'   => round($baseTotal + $effectiveLateFee, 2),
        ];
    }

    public function returnRental(Request $request, ProductRental $productRental): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $productRental->business_id === (int) $business->id, 404);

        $this->rentals->markReturned($productRental);

        return back()->with('status', 'Rental marked as returned.');
    }
}
