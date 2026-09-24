<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Pos\Models\StockAudit;
use Modules\Pos\Models\StockTransfer;
use Modules\Product\Models\Product;
use Modules\Purchase\Models\Purchase;

class InventoryOverviewController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $totalProducts = Product::query()->where('business_id', $business->id)->count();
        $outOfStockProducts = Product::query()
            ->where('business_id', $business->id)
            ->where('is_bundle', false)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $openPurchaseOrders = Purchase::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [Purchase::STATUS_DRAFT, Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])
            ->count();

        $openStockAudits = StockAudit::query()
            ->where('business_id', $business->id)
            ->where('status', StockAudit::STATUS_OPEN)
            ->count();

        $inTransitTransfers = StockTransfer::query()
            ->where('business_id', $business->id)
            ->where('status', StockTransfer::STATUS_IN_TRANSIT)
            ->count();

        return view('product::inventory-overview', [
            'business'            => $business,
            'totalProducts'       => $totalProducts,
            'outOfStockProducts'  => $outOfStockProducts,
            'openPurchaseOrders'  => $openPurchaseOrders,
            'openStockAudits'     => $openStockAudits,
            'inTransitTransfers'  => $inTransitTransfers,
        ]);
    }
}
