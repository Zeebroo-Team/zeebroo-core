<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Account\Models\Account;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleReturn;
use Modules\Pos\Services\SaleReturnService;
use Modules\Pos\Services\SaleService;
use Modules\Product\Models\Product;

class SaleController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly SaleService $sales,
        private readonly SaleReturnService $saleReturns,
    ) {
    }

    public function createReturn(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $mode        = $request->query('mode') === 'open' ? 'open' : 'ref';
        $saleNumber  = trim((string) $request->query('sale', ''));
        $sale        = null;
        $returnedQtys = [];
        $accounts    = collect();
        $saleNotFound = false;
        $products    = collect();

        if ($mode === 'open') {
            $accounts = Account::query()
                ->where('business_id', $business->id)
                ->orderBy('account_name')
                ->get();
            $products = Product::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->where('is_bundle', false)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit_price']);
        } elseif (filled($saleNumber)) {
            $sale = Sale::query()
                ->where('business_id', $business->id)
                ->where('sale_number', $saleNumber)
                ->with(['items.product', 'returns.items'])
                ->first();

            if ($sale === null) {
                $saleNotFound = true;
            } elseif ($sale->isCompleted()) {
                $returnedQtys = $this->saleReturns->returnedQuantitiesForSale($sale);
                $accounts = Account::query()
                    ->where('business_id', $business->id)
                    ->orderBy('account_name')
                    ->get();
            }
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::sales.create-return', compact(
            'business', 'currency', 'mode', 'saleNumber', 'sale',
            'returnedQtys', 'accounts', 'saleNotFound', 'products'
        ));
    }

    public function storeOpenReturn(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'min:1'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'refund_method'          => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'          => ['nullable', 'string', 'max:100'],
            'credit_account_id'      => ['nullable', 'integer', 'min:1'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processOpenReturn(
            $business,
            $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('pos.returns.index')
            ->with('status', "Return {$ret->return_number} processed successfully.");
    }

    public function returnsIndex(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search   = (string) $request->query('q', '');
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $returns = SaleReturn::query()
            ->where('business_id', $business->id)
            ->with(['sale:id,sale_number,sold_at', 'user:id,name', 'items'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('return_number', 'like', '%'.$search.'%')
                      ->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('returned_at')
            ->paginate(50);

        $hasReturns = SaleReturn::query()->where('business_id', $business->id)->exists();

        return view('pos::sales.returns', compact('business', 'currency', 'search', 'returns', 'hasReturns'));
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
        $sale->load(['items.product', 'creditAccount', 'user', 'branch', 'ledgerTransactions.deductAccount', 'returns.items', 'returns.user', 'returns.creditAccount']);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $returnedQtys = $this->saleReturns->returnedQuantitiesForSale($sale);
        $accounts = Account::query()
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('pos::sales.show', [
            'business'     => $business,
            'currency'     => $currency,
            'sale'         => $sale,
            'returnedQtys' => $returnedQtys,
            'accounts'     => $accounts,
        ]);
    }

    public function storeReturn(Request $request, Sale $sale): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sale = $this->saleForBusiness($business, $sale);

        $validated = $request->validate([
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.sale_item_id'     => ['required', 'integer', 'min:1'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'refund_method'            => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'            => ['nullable', 'string', 'max:100'],
            'credit_account_id'        => ['nullable', 'integer', 'min:1'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processReturn(
            $sale,
            $business,
            $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('pos.sales.show', $sale)
            ->with('status', "Return {$ret->return_number} processed successfully.");
    }

    public function saleLookup(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $saleNumber = trim((string) $request->query('sale', ''));
        if (! filled($saleNumber)) {
            return response()->json(['error' => 'Sale number required'], 422);
        }

        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where('sale_number', $saleNumber)
            ->with(['items', 'returns.items'])
            ->first();

        if ($sale === null) {
            return response()->json(['found' => false]);
        }

        $returnedQtys = $this->saleReturns->returnedQuantitiesForSale($sale);

        $items = $sale->items->map(function ($item) use ($returnedQtys) {
            $retQty     = round((float) ($returnedQtys[$item->id] ?? 0), 3);
            $returnable = round((float) $item->quantity - $retQty, 3);

            return [
                'id'              => $item->id,
                'product_name'    => $item->product_name,
                'sku'             => $item->sku ?? '',
                'quantity'        => round((float) $item->quantity, 3),
                'returned'        => $retQty,
                'returnable'      => $returnable,
                'unit_sell_price' => round((float) $item->unit_sell_price, 2),
            ];
        })->values();

        return response()->json([
            'found'        => true,
            'is_void'      => $sale->isVoid(),
            'all_returned' => $items->every(fn ($i) => $i['returnable'] <= 0),
            'sale'         => [
                'id'                   => $sale->id,
                'sale_number'          => $sale->sale_number,
                'sold_at'              => $sale->sold_at?->format('M j, Y g:i A') ?? '—',
                'payment_method_label' => $sale->paymentMethodLabel(),
                'total'                => round((float) $sale->total, 2),
            ],
            'items' => $items,
        ]);
    }

    public function onlineModalReturn(Request $request, Sale $sale): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $sale = $this->saleForBusiness($business, $sale);

        $validated = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.001'],
            'refund_method'        => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'        => ['nullable', 'string', 'max:100'],
            'credit_account_id'    => ['nullable', 'integer', 'min:1'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processReturn(
            $sale, $business, $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('pos.online')
            ->with('status', "Return {$ret->return_number} processed successfully.");
    }

    public function onlineModalReturnOpen(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'refund_method'      => ['required', 'string', 'in:cash,credit,none'],
            'refund_reason'      => ['nullable', 'string', 'max:100'],
            'credit_account_id'  => ['nullable', 'integer', 'min:1'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ]);

        $ret = $this->saleReturns->processOpenReturn(
            $business, $request->user(),
            $validated['items'],
            $validated['refund_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            $validated['notes'] ?? null,
            $validated['refund_reason'] ?? null,
        );

        return redirect()
            ->route('pos.online')
            ->with('status', "Return {$ret->return_number} processed successfully.");
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
