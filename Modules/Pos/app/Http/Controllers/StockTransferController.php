<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\StockTransfer;
use Modules\Pos\Services\StockTransferService;
use Modules\Product\Services\ProductBundleService;

class StockTransferController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly StockTransferService $service,
        private readonly ProductBundleService $productCatalog,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $transfers = $this->service->listForBusiness($business, $search !== '' ? $search : null);

        return view('pos::stock-transfers.index', compact('business', 'transfers', 'search'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $branches = $business->branches()->orderBy('name')->get();
        $catalog = $this->productCatalog->pickerCatalogForBusiness($business);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::stock-transfers.create', compact('business', 'branches', 'catalog', 'currency'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'from_branch_id'     => ['required', 'integer'],
            'to_branch_id'       => ['required', 'integer'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'lines'              => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity'   => ['required', 'numeric', 'min:0.001'],
        ]);

        $transfer = $this->service->create($business, $data, $request->user());

        return redirect()->route('pos.stock-transfers.show', $transfer)
            ->with('status', "Transfer {$transfer->transfer_number} created.");
    }

    public function show(Request $request, StockTransfer $stockTransfer): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $transfer->load(['lines', 'fromBranch', 'toBranch', 'transferredBy', 'receivedBy', 'cancelledBy']);

        return view('pos::stock-transfers.show', compact('business', 'transfer'));
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $this->service->receive($transfer, $request->user());

        return redirect()->route('pos.stock-transfers.show', $transfer)
            ->with('status', "Transfer {$transfer->transfer_number} received — stock updated.");
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $transfer = $this->service->transferForBusiness($business, $stockTransfer);
        $this->service->cancel($transfer, $request->user());

        return redirect()->route('pos.stock-transfers.show', $transfer)
            ->with('status', "Transfer {$transfer->transfer_number} cancelled.");
    }
}
