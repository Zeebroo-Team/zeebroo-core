<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Customer;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSalesBusiness;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Services\QuotationPrintService;
use Modules\Sales\Services\QuotationService;
use Modules\Service\Models\ServiceItem;

class QuotationController extends Controller
{
    use ResolvesSalesBusiness;

    public function __construct(
        private readonly QuotationService $quotationService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search         = trim((string) $request->query('q', ''));
        $statusFilter   = (string) $request->query('status', 'all');
        $customerFilter = $request->query('customer_id');
        $customerId     = filled($customerFilter) ? (int) $customerFilter : null;

        return view('sales::quotations.index', [
            'business'       => $business,
            'hasQuotations'  => $this->quotationService->businessHasQuotations($business),
            'quotations'     => $this->quotationService->listForBusiness($business, $search, $statusFilter, $customerId),
            'customers'      => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'       => (string) (get_settings('business.currency', '', $business) ?: ''),
            'search'         => $search,
            'statusFilter'   => $statusFilter,
            'customerFilter' => $customerId,
            'statusTabs'     => $this->statusTabs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $quotation = $this->quotationService->create(
                $business,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.quotations.index')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('status', 'Quotation ' . $quotation->quote_number . ' created.');
    }

    public function show(Request $request, Quotation $quotation): View|RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $quotation->load(['customer', 'items.product', 'items.serviceItem.products']);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('sales::quotations.show', [
            'business'  => $business,
            'quotation' => $quotation,
            'currency'  => $currency,
        ]);
    }

    public function edit(Request $request, Quotation $quotation): View|RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$quotation->isEditable()) {
            return redirect()->route('sales.quotations.show', $quotation)
                ->withErrors(['quotation' => 'This quotation can no longer be edited.']);
        }

        $quotation->load(['customer', 'items.product', 'items.serviceItem']);

        return view('sales::quotations.edit', [
            'business'  => $business,
            'quotation' => $quotation,
            'customers' => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'currency'  => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->quotationService->update(
                $quotation,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.quotations.edit', $quotation)
                ->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('status', 'Quotation updated.');
    }

    public function markSent(Request $request, Quotation $quotation): RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->quotationService->markSent($quotation);

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('status', 'Quotation marked as sent.');
    }

    public function markAccepted(Request $request, Quotation $quotation): RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->quotationService->markAccepted($quotation);

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('status', 'Quotation accepted.');
    }

    public function markRejected(Request $request, Quotation $quotation): RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->quotationService->markRejected($quotation);

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('status', 'Quotation rejected.');
    }

    public function printQuotation(Request $request, Quotation $quotation): View|RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('sales::quotations.print', app(QuotationPrintService::class)->viewData($business, $quotation));
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        $business = $this->requireQuotation($request, $quotation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->quotationService->delete($quotation);
        } catch (ValidationException $e) {
            return redirect()->route('sales.quotations.show', $quotation)->withErrors($e->errors());
        }

        return redirect()->route('sales.quotations.index')
            ->with('status', 'Quotation deleted.');
    }

    private function requireQuotation(Request $request, Quotation $quotation): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->quotationService->quotationForBusiness($business, $quotation) instanceof Quotation, 404);

        return $business;
    }

    private function validatedHeader(Request $request, Business $business): array
    {
        return $request->validate([
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'quote_date'              => ['required', 'date'],
            'expiry_date'             => ['nullable', 'date', 'after_or_equal:quote_date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_type'       => ['nullable', 'string', 'in:product,service'],
            'items.*.product_id'      => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.service_item_id' => ['nullable', 'integer', Rule::exists('service_items', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }

    private function statusTabs(): array
    {
        return [
            'all'                    => 'All',
            Quotation::STATUS_DRAFT    => 'Draft',
            Quotation::STATUS_SENT     => 'Sent',
            Quotation::STATUS_ACCEPTED => 'Accepted',
            Quotation::STATUS_REJECTED => 'Rejected',
            Quotation::STATUS_EXPIRED  => 'Expired',
        ];
    }
}
