<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\Design;
use Modules\Purchase\Http\Controllers\Concerns\ResolvesPurchaseBusiness;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseService;
use Modules\Purchase\Services\SupplierService;

class PurchaseController extends Controller
{
    use ResolvesPurchaseBusiness;

    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly SupplierService $supplierService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);
        $branchOptions = $branchStockSeparate ? $business->branches()->get() : collect();

        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');
        $supplierFilter = $request->query('supplier_id');
        $supplierId = filled($supplierFilter) ? (int) $supplierFilter : null;

        $suppliers = $this->supplierService->listForBusiness($business)->where('is_active', true)->values();

        return view('purchase::purchases.index', [
            'business'            => $business,
            'hasPurchases'        => $this->purchaseService->businessHasPurchases($business),
            'purchases'           => $this->purchaseService->listForBusiness($business, $search, $statusFilter, $supplierId),
            'suppliers'           => $suppliers,
            'products'            => $business->products()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
            'currency'            => $currency,
            'search'              => $search,
            'statusFilter'        => $statusFilter,
            'supplierFilter'      => $supplierId,
            'statusTabs'          => $this->purchaseStatusFilterTabs(),
            'branchStockSeparate' => $branchStockSeparate,
            'branchOptions'       => $branchOptions,
        ]);
    }

    /** @return array<string, string> */
    private function purchaseStatusFilterTabs(): array
    {
        return [
            'all' => 'All',
            Purchase::STATUS_DRAFT => 'Draft',
            Purchase::STATUS_ORDERED => 'Ordered',
            Purchase::STATUS_PARTIALLY_RECEIVED => 'Partially received',
            Purchase::STATUS_RECEIVED => 'Received',
            Purchase::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $purchase = $this->purchaseService->create(
                $business,
                $this->validatedPurchaseHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('purchase.index')->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('purchase.show', $purchase)
            ->with('status', 'Purchase order '.$purchase->po_number.' created.');
    }

    public function show(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $purchase->load([
            'supplier',
            'items.product.productUnit',
            'items.goodsReceiveNoteItems',
            'goodsReceiveNotes' => fn ($query) => $query->withSum('ledgerTransactions as ledger_paid_total', 'amount'),
        ]);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = $this->accountsForPurchasePayment($business, $request);

        return view('purchase::purchases.show', [
            'business' => $business,
            'purchase' => $purchase,
            'currency' => $currency,
            'accounts' => $accounts,
            'hasPaymentAccounts' => $accounts->isNotEmpty(),
            'canPayByCheque' => $this->businessHasCurrentAccount($business, $request),
        ]);
    }

    public function printInvoice(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $purchase->load(['supplier', 'items.product.productUnit']);

        $currency    = (string) (get_settings('business.currency', '', $business) ?: '');
        $mainBranch  = $business->branches()->first();
        $lhLinks     = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet'], $business);
        $letterhead  = in_array('po', $lhLinks) ? Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first() : null;

        // Extract accent colour + raw canvas JSON for client-side rendering.
        // canvas_json is stored as either:
        //   (a) direct Fabric.js JSON  {"version":"5.3.0","objects":[...]}
        //   (b) multi-page wrapper     [{"json":"...fabric json..."}]
        $accentColor          = '#3B82F6';
        $letterheadCanvasJson = null;
        $objs                 = [];
        if ($letterhead) {
            try {
                $raw     = (string) $letterhead->canvas_json;
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    if (isset($decoded['objects']) && is_array($decoded['objects'])) {
                        // Format (a): direct Fabric.js JSON
                        $letterheadCanvasJson = $raw;
                        $objs                 = $decoded['objects'];
                    } elseif (!empty($decoded[0]['json'])) {
                        // Format (b): multi-page wrapper
                        $letterheadCanvasJson = $decoded[0]['json'];
                        $objs                 = json_decode((string) $decoded[0]['json'], true)['objects'] ?? [];
                    }
                }
                // Find the thin top accent strip (rect, near top, height ≤ 8)
                foreach ($objs as $obj) {
                    if (($obj['type'] ?? '') === 'rect'
                        && (float) ($obj['top'] ?? 99) < 4
                        && (float) ($obj['height'] ?? 99) <= 8
                        && !empty($obj['fill'])
                        && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $obj['fill'])) {
                        $accentColor = $obj['fill'];
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        return view('purchase::purchases.print', [
            'business'            => $business,
            'purchase'            => $purchase,
            'currency'            => $currency,
            'mainBranch'          => $mainBranch,
            'accentColor'         => $accentColor,
            'letterheadCanvasJson' => $letterheadCanvasJson,
        ]);
    }

    public function edit(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$purchase->isEditable()) {
            return redirect()
                ->route('purchase.show', $purchase)
                ->withErrors(['purchase' => 'This purchase order can no longer be edited.']);
        }

        $purchase->load(['supplier', 'items.product']);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);
        $branchOptions = $branchStockSeparate ? $business->branches()->get() : collect();

        return view('purchase::purchases.edit', [
            'business'            => $business,
            'purchase'            => $purchase,
            'suppliers'           => $this->supplierService->listForBusiness($business)->where('is_active', true)->values(),
            'products'            => $business->products()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
            'currency'            => $currency,
            'branchStockSeparate' => $branchStockSeparate,
            'branchOptions'       => $branchOptions,
        ]);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->purchaseService->update(
                $purchase,
                $this->validatedPurchaseHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('purchase.edit', $purchase)->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('purchase.show', $purchase)
            ->with('status', 'Purchase order updated.');
    }

    public function placeOrder(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->purchaseService->markOrdered($purchase);
        } catch (ValidationException $e) {
            return redirect()->route('purchase.show', $purchase)->withErrors($e->errors());
        }

        return redirect()->route('purchase.show', $purchase)->with('status', 'Purchase order placed with supplier.');
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $grn = $this->purchaseService->markReceived($purchase, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('purchase.show', $purchase)->withErrors($e->errors());
        }

        return redirect()
            ->route('purchase.grn.show', $grn)
            ->with('status', 'All remaining goods received ('.$grn->grn_number.'). Record payment on this receipt if needed.');
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->purchaseService->cancel($purchase);
        } catch (ValidationException $e) {
            return redirect()->route('purchase.show', $purchase)->withErrors($e->errors());
        }

        return redirect()->route('purchase.show', $purchase)->with('status', 'Purchase order cancelled.');
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->purchaseService->delete($purchase);
        } catch (ValidationException $e) {
            return redirect()->route('purchase.index')->withErrors($e->errors());
        }

        return redirect()->route('purchase.index')->with('status', 'Purchase order removed.');
    }

    private function requirePurchase(Request $request, Purchase $purchase): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->purchaseService->purchaseForBusiness($business, $purchase) instanceof Purchase, 404);

        return $business;
    }

    /**
     * @return array{supplier_id: ?int, reference: ?string, purchase_date: string, expected_delivery_date: ?string, status: string, notes: ?string}
     */
    private function validatedPurchaseHeader(Request $request, Business $business): array
    {
        $supplierId = $request->input('supplier_id');
        $supplierId = ($supplierId === null || $supplierId === '' || $supplierId === '0') ? null : (int) $supplierId;

        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);

        $validated = $request->validate([
            'branch_id' => $branchStockSeparate
                ? ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))]
                : ['nullable'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'reference' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status' => ['required', 'string', Rule::in([Purchase::STATUS_DRAFT, Purchase::STATUS_ORDERED])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $validated['supplier_id'] = $supplierId;
        $validated['branch_id'] = ($branchStockSeparate && !empty($validated['branch_id']))
            ? (int) $validated['branch_id']
            : null;
        $validated['expected_delivery_date'] = filled($validated['expected_delivery_date'] ?? null)
            ? $validated['expected_delivery_date']
            : null;

        return $validated;
    }
}
