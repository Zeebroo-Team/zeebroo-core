<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\SaleService;

class SaleController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly SaleService $sales,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = (string) $request->query('q', '');
        $sales = $this->sales->listForBusiness($business, $search !== '' ? $search : null);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::sales.index', [
            'business' => $business,
            'currency' => $currency,
            'search' => $search,
            'sales' => $sales,
            'hasSales' => $this->sales->businessHasSales($business),
        ]);
    }

    public function show(Request $request, Sale $sale): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sale = $this->saleForBusiness($business, $sale);
        $sale->load(['items.product', 'creditAccount', 'user', 'ledgerTransactions.deductAccount']);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::sales.show', [
            'business' => $business,
            'currency' => $currency,
            'sale' => $sale,
        ]);
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sale = $this->saleForBusiness($business, $sale);
        $this->sales->void($sale, $business);

        return redirect()
            ->route('pos.sales.show', $sale)
            ->with('status', 'Sale '.$sale->sale_number.' has been voided and stock restored.');
    }
}
