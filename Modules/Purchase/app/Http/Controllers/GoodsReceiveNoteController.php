<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Purchase\Http\Controllers\Concerns\ResolvesPurchaseBusiness;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\ChequePaymentService;
use Modules\Purchase\Services\GoodsReceiveNoteService;
use Modules\Purchase\Services\GrnPaymentSettlementService;
use Modules\Purchase\Services\PurchaseService;
use Modules\Purchase\Services\SupplierService;

class GoodsReceiveNoteController extends Controller
{
    use ResolvesPurchaseBusiness;

    public function __construct(
        private readonly GoodsReceiveNoteService $grnService,
        private readonly PurchaseService $purchaseService,
        private readonly GrnPaymentSettlementService $paymentSettlement,
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

        $openPurchaseOrders = $business->purchases()
            ->with('supplier')
            ->whereIn('status', [Purchase::STATUS_DRAFT, Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $search = trim((string) $request->query('q', ''));
        $paymentFilter = (string) $request->query('payment', 'all');
        $supplierFilter = $request->query('supplier_id');
        $supplierId = filled($supplierFilter) ? (int) $supplierFilter : null;

        $notes = $this->grnService->listForBusiness($business, $search, $paymentFilter, $supplierId);
        $purchaseGroups = $this->grnService->listGroupedByPurchaseForIndex(
            $business,
            $openPurchaseOrders,
            $search,
            $paymentFilter,
            $supplierId,
        );

        $activeTab = $request->query('view') === 'all' ? 'all' : 'grouped';
        $accounts = $this->accountsForPurchasePayment($business, $request);

        return view('purchase::goods-receive.index', [
            'business' => $business,
            'hasGrns' => $this->grnService->businessHasGrns($business),
            'notes' => $notes,
            'purchaseGroups' => $purchaseGroups,
            'openPurchaseOrders' => $openPurchaseOrders,
            'suppliers' => $this->supplierService->listForBusiness($business)->where('is_active', true)->values(),
            'currency' => $currency,
            'activeTab' => $activeTab,
            'search' => $search,
            'paymentFilter' => $paymentFilter,
            'supplierFilter' => $supplierId,
            'paymentTabs' => $this->grnPaymentFilterTabs(),
            'accounts' => $accounts,
            'hasPaymentAccounts' => $accounts->isNotEmpty(),
            'canPayByCheque' => $this->businessHasCurrentAccount($business, $request),
            'openPayGrnId' => (int) $request->query('pay_grn'),
        ]);
    }

    /** @return array<string, string> */
    private function grnPaymentFilterTabs(): array
    {
        $labels = GrnPaymentSettlementService::paymentStatusLabels();

        return [
            'all' => 'All',
            GrnPaymentSettlementService::STATUS_PAID_FULL => $labels[GrnPaymentSettlementService::STATUS_PAID_FULL],
            GrnPaymentSettlementService::STATUS_PAID_PARTIAL => $labels[GrnPaymentSettlementService::STATUS_PAID_PARTIAL],
            GrnPaymentSettlementService::STATUS_PENDING => $labels[GrnPaymentSettlementService::STATUS_PENDING],
            GrnPaymentSettlementService::STATUS_NO_AMOUNT => $labels[GrnPaymentSettlementService::STATUS_NO_AMOUNT],
        ];
    }

    public function show(Request $request, GoodsReceiveNote $goodsReceiveNote): View|RedirectResponse
    {
        $business = $this->requireGrn($request, $goodsReceiveNote);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $goodsReceiveNote->load([
            'purchase.supplier',
            'items.product',
            'items.purchaseItem',
            'ledgerTransactions.deductAccount.bankType',
            'chequePayments.deductAccount.bankType',
        ]);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = $this->accountsForPurchasePayment($business, $request);

        return view('purchase::goods-receive.show', [
            'business' => $business,
            'grn' => $goodsReceiveNote,
            'currency' => $currency,
            'accounts' => $accounts,
            'grnTotal' => $this->paymentSettlement->grnTotal($goodsReceiveNote),
            'amountPaid' => $this->paymentSettlement->amountPaid($goodsReceiveNote),
            'amountOutstanding' => $this->paymentSettlement->amountOutstanding($goodsReceiveNote),
            'isFullyPaid' => $this->paymentSettlement->isFullyPaid($goodsReceiveNote),
            'hasPayment' => $this->paymentSettlement->hasPayment($goodsReceiveNote),
            'canPayByCheque' => $this->businessHasCurrentAccount($business, $request),
            'hasPaymentAccounts' => $accounts->isNotEmpty(),
            'activeTab' => $this->resolveGrnShowTab($request),
        ]);
    }

    private function resolveGrnShowTab(Request $request): string
    {
        $paymentFields = ['pay_amount', 'deduct_account_id', 'payment_method', 'payment_reference', 'cheque_due_date', 'payment_option'];

        if ($request->session()->get('errors')?->hasAny($paymentFields)) {
            return 'payment';
        }

        $tab = (string) $request->query('tab', 'overview');

        return in_array($tab, ['overview', 'items', 'payment'], true) ? $tab : 'overview';
    }

    public function create(Request $request, Purchase $purchase): View|RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$purchase->canReceiveGoods()) {
            return redirect()
                ->route('purchase.show', $purchase)
                ->withErrors(['purchase' => 'This purchase order cannot receive more goods.']);
        }

        $purchase->load(['supplier', 'items.product', 'items.goodsReceiveNoteItems']);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = $this->accountsForPurchasePayment($business, $request);
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);
        $branchOptions = $branchStockSeparate ? $business->branches()->get() : collect();

        return view('purchase::goods-receive.create', [
            'business'            => $business,
            'purchase'            => $purchase,
            'currency'            => $currency,
            'canPayByCheque'      => $this->businessHasCurrentAccount($business, $request),
            'accounts'            => $accounts,
            'hasPaymentAccounts'  => $accounts->isNotEmpty(),
            'stockSellingMarkupPercent' => (float) get_settings('product.stock_selling_markup_percent', 25, $business),
            'branchStockSeparate' => $branchStockSeparate,
            'branchOptions'       => $branchOptions,
            'purchaseBranchId'    => $purchase->branch_id,
        ]);
    }

    public function store(Request $request, Purchase $purchase): RedirectResponse
    {
        $business = $this->requirePurchase($request, $purchase);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $this->validatedGrnHeader($request, $business);
        $validated['items'] = $request->input('items', []);

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.selling_unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $grn = $this->grnService->createForPurchase(
                $purchase,
                $request->user(),
                $validated,
                $validated['items'],
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('purchase.grn.create', $purchase)
                ->withErrors($e->errors())
                ->withInput();
        }

        $grn->load('chequePayments');

        if (($validated['payment_method'] ?? '') === Purchase::PAYMENT_CHEQUE) {
            $cheque = app(ChequePaymentService::class)->latestOpenChequeForGrn($grn);

            return $this->redirectToChequeAfterRecord(
                $cheque,
                $grn,
                'Goods receive note '.$grn->grn_number.' recorded. Deduct from your account on the cheque page when the cheque is presented.',
            );
        }

        $message = 'Goods receive note '.$grn->grn_number.' recorded.';
        if ($this->paymentSettlement->hasPayment($grn)) {
            $message .= $this->paymentSettlement->isFullyPaid($grn)
                ? ' Payment recorded and account balance updated.'
                : ' Partial payment recorded — outstanding balance remains on this receipt.';
        }

        return redirect()
            ->route('purchase.grn.show', $grn)
            ->with('status', $message);
    }

    public function pay(Request $request, GoodsReceiveNote $goodsReceiveNote): RedirectResponse
    {
        $business = $this->requireGrn($request, $goodsReceiveNote);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($this->paymentSettlement->isFullyPaid($goodsReceiveNote)) {
            return $this->redirectAfterPay($request, $goodsReceiveNote)
                ->withErrors(['payment' => 'This goods receive note is already fully paid.']);
        }

        $paymentOption = (string) $request->input('payment_option', 'full');
        $allowedPayMethods = $this->allowedGrnPayMethods($business, $request);

        if (! $request->filled('payment_method')) {
            $stored = (string) $goodsReceiveNote->payment_method;
            $request->merge([
                'payment_method' => in_array($stored, $allowedPayMethods, true)
                    ? $stored
                    : Purchase::PAYMENT_CASH,
            ]);
        }

        $validated = $request->validate([
            'payment_option' => ['required', 'string', Rule::in(['full', 'partial'])],
            'payment_method' => ['required', 'string', Rule::in($allowedPayMethods)],
            'payment_reference' => [
                Rule::requiredIf((string) $request->input('payment_method') === Purchase::PAYMENT_CHEQUE),
                'nullable',
                'string',
                'max:120',
            ],
            'cheque_due_date' => [
                Rule::requiredIf((string) $request->input('payment_method') === Purchase::PAYMENT_CHEQUE),
                'nullable',
                'date',
            ],
            'deduct_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('user_id', $request->user()->id)),
            ],
            'pay_amount' => [
                Rule::requiredIf($paymentOption === 'partial'),
                'nullable',
                'numeric',
                'min:0.01',
            ],
        ]);

        $payAmount = $paymentOption === 'partial'
            ? round((float) $validated['pay_amount'], 2)
            : null;

        try {
            $ledger = $this->paymentSettlement->settle(
                $goodsReceiveNote,
                $business,
                $request->user(),
                (int) $validated['deduct_account_id'],
                $payAmount,
                $validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $validated['cheque_due_date'] ?? null,
            );
        } catch (ValidationException $e) {
            return $this->redirectAfterPay($request, $goodsReceiveNote, reopenPayModal: true)
                ->withErrors($e->errors())
                ->withInput();
        }

        if ($validated['payment_method'] === Purchase::PAYMENT_CHEQUE) {
            $goodsReceiveNote->update([
                'payment_method' => Purchase::PAYMENT_CHEQUE,
                'payment_reference' => $validated['payment_reference'] ?? null,
                'cheque_due_date' => $validated['cheque_due_date'] ?? null,
            ]);
        }

        $goodsReceiveNote->refresh();

        if ($ledger === null && $validated['payment_method'] === Purchase::PAYMENT_CHEQUE) {
            $goodsReceiveNote->load('chequePayments');
            $cheque = app(ChequePaymentService::class)->latestOpenChequeForGrn($goodsReceiveNote);

            return $this->redirectToChequeAfterRecord(
                $cheque,
                $goodsReceiveNote,
                'Cheque recorded. Deduct from your account when the cheque is presented.',
                $request,
            );
        }

        $accountLabel = $ledger->deductAccount?->deductOptionLabel() ?? 'account';
        $remaining = $this->paymentSettlement->amountOutstanding($goodsReceiveNote);

        $status = 'Payment of '.number_format((float) $ledger->amount, 2).' recorded from '.$accountLabel.'.';
        if ($remaining > 0.005) {
            $status .= ' Outstanding: '.number_format($remaining, 2).'.';
        }

        return $this->redirectAfterPay($request, $goodsReceiveNote)->with('status', $status);
    }

    /**
     * @return list<string>
     */
    private function allowedGrnPayMethods(Business $business, Request $request): array
    {
        $methods = [Purchase::PAYMENT_CASH];
        if ($this->businessHasCurrentAccount($business, $request)) {
            $methods[] = Purchase::PAYMENT_CHEQUE;
        }

        return $methods;
    }

    private function redirectAfterPay(Request $request, GoodsReceiveNote $goodsReceiveNote, bool $reopenPayModal = false): RedirectResponse
    {
        $returnTo = (string) $request->input('return_to', 'show');

        if ($returnTo === 'index') {
            $view = (string) $request->input('return_view', 'grouped');
            $params = [];
            if ($view === 'all') {
                $params['view'] = 'all';
            }
            if ($reopenPayModal) {
                $params['pay_grn'] = $goodsReceiveNote->id;
            }

            return redirect()->route('purchase.grn.index', $params);
        }

        if ($returnTo === 'purchase' && $goodsReceiveNote->purchase_id) {
            return redirect()->route('purchase.show', $goodsReceiveNote->purchase_id);
        }

        $params = ['goodsReceiveNote' => $goodsReceiveNote];
        if ($returnTo !== 'index' && $returnTo !== 'purchase') {
            $params['tab'] = 'payment';
        }

        return redirect()->route('purchase.grn.show', $params);
    }

    private function redirectToChequeAfterRecord(
        ?ChequePayment $cheque,
        GoodsReceiveNote $grn,
        string $message,
        ?Request $request = null,
    ): RedirectResponse {
        if ($cheque instanceof ChequePayment) {
            return redirect()
                ->route('purchase.cheques.show', $cheque)
                ->with('status', $message);
        }

        if ($request instanceof Request) {
            return $this->redirectAfterPay($request, $grn)->with('status', $message);
        }

        return redirect()
            ->route('purchase.grn.show', ['goodsReceiveNote' => $grn, 'tab' => 'payment'])
            ->with('status', $message);
    }

    /**
     * @return array{received_date: string, reference: ?string, notes: ?string, payment_method: string, payment_reference: ?string, deduct_account_id: ?int}
     */
    private function validatedGrnHeader(Request $request, Business $business): array
    {
        $canPayByCheque = $this->businessHasCurrentAccount($business, $request);
        $paymentMethods = [Purchase::PAYMENT_CASH, Purchase::PAYMENT_CREDIT];
        if ($canPayByCheque) {
            $paymentMethods[] = Purchase::PAYMENT_CHEQUE;
        }

        $paymentMethod = (string) $request->input('payment_method');
        $requiresAccount = in_array($paymentMethod, [Purchase::PAYMENT_CASH, Purchase::PAYMENT_CHEQUE], true);
        $paymentOption = (string) $request->input('payment_option', 'full');
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);

        $validated = $request->validate([
            'branch_id' => $branchStockSeparate
                ? ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))]
                : ['nullable'],
            'received_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_method' => ['required', 'string', Rule::in($paymentMethods)],
            'payment_reference' => [
                Rule::requiredIf($paymentMethod === Purchase::PAYMENT_CHEQUE),
                'nullable',
                'string',
                'max:120',
            ],
            'cheque_due_date' => [
                Rule::requiredIf($paymentMethod === Purchase::PAYMENT_CHEQUE),
                'nullable',
                'date',
            ],
            'payment_option' => [
                Rule::requiredIf($requiresAccount),
                'nullable',
                'string',
                Rule::in(['full', 'partial']),
            ],
            'pay_amount' => [
                Rule::requiredIf($requiresAccount && $paymentOption === 'partial'),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'deduct_account_id' => [
                Rule::requiredIf($requiresAccount),
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('user_id', $request->user()->id)),
            ],
        ]);

        if ($requiresAccount) {
            $validated['payment_option'] = $paymentOption === 'partial' ? 'partial' : 'full';
        }

        $validated['deduct_account_id'] = filled($validated['deduct_account_id'] ?? null)
            ? (int) $validated['deduct_account_id']
            : null;

        $validated['branch_id'] = ($branchStockSeparate && !empty($validated['branch_id']))
            ? (int) $validated['branch_id']
            : null;

        if ($requiresAccount && $this->accountsForPurchasePayment($business, $request)->isEmpty()) {
            throw ValidationException::withMessages([
                'deduct_account_id' => 'Add a bank account for this business before paying by cash or cheque.',
            ]);
        }

        return $validated;
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

    private function requireGrn(Request $request, GoodsReceiveNote $grn): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->grnService->grnForBusiness($business, $grn) instanceof GoodsReceiveNote, 404);

        return $business;
    }
}
