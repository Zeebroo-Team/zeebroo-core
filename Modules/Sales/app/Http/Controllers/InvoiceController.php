<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\Design;
use Modules\Pos\Models\Customer;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSalesBusiness;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Services\InvoiceService;

class InvoiceController extends Controller
{
    use ResolvesSalesBusiness;

    public function __construct(
        private readonly InvoiceService $invoiceService,
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

        return view('sales::invoices.index', [
            'business'       => $business,
            'hasInvoices'    => $this->invoiceService->businessHasInvoices($business),
            'invoices'       => $this->invoiceService->listForBusiness($business, $search, $statusFilter, $customerId),
            'customers'      => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'products'       => $business->products()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
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
            $invoice = $this->invoiceService->create(
                $business,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.invoices.index')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice ' . $invoice->invoice_number . ' created.');
    }

    public function show(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $invoice->load(['customer', 'items.product']);
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('sales::invoices.show', [
            'business' => $business,
            'invoice'  => $invoice,
            'currency' => $currency,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if (!$invoice->isEditable()) {
            return redirect()->route('sales.invoices.show', $invoice)
                ->withErrors(['invoice' => 'This invoice can no longer be edited.']);
        }

        $invoice->load(['customer', 'items.product']);

        return view('sales::invoices.edit', [
            'business'  => $business,
            'invoice'   => $invoice,
            'customers' => Customer::where('business_id', $business->id)->orderBy('name')->get(),
            'products'  => $business->products()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'unit_price']),
            'currency'  => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->invoiceService->update(
                $invoice,
                $this->validatedHeader($request, $business),
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return redirect()->route('sales.invoices.edit', $invoice)
                ->withErrors($e->errors())->withInput();
        }

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice updated.');
    }

    public function markSent(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->invoiceService->markSent($invoice);

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice marked as sent.');
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->invoiceService->markPaid($invoice);

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice marked as paid.');
    }

    public function markOverdue(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->invoiceService->markOverdue($invoice);

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice marked as overdue.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->invoiceService->cancel($invoice);

        return redirect()->route('sales.invoices.show', $invoice)
            ->with('status', 'Invoice cancelled.');
    }

    public function printInvoice(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $invoice->load(['customer', 'items.product']);

        $currency   = (string) (get_settings('business.currency', '', $business) ?: '');
        $mainBranch = $business->branches()->first();
        $lhLinks    = (array) get_settings('design_studio.lh_links', ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation', 'sales_invoice'], $business);
        $letterhead = in_array('sales_invoice', $lhLinks) ? Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first() : null;

        $accentColor          = '#3B82F6';
        $letterheadCanvasJson = null;
        if ($letterhead) {
            try {
                $raw     = (string) $letterhead->canvas_json;
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    if (isset($decoded['objects'])) {
                        $letterheadCanvasJson = $raw;
                        $objs                 = $decoded['objects'];
                    } elseif (!empty($decoded[0]['json'])) {
                        $letterheadCanvasJson = $decoded[0]['json'];
                        $objs                 = json_decode((string) $decoded[0]['json'], true)['objects'] ?? [];
                    }
                }
                foreach ($objs ?? [] as $obj) {
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

        return view('sales::invoices.print', [
            'business'             => $business,
            'invoice'              => $invoice,
            'currency'             => $currency,
            'mainBranch'           => $mainBranch,
            'accentColor'          => $accentColor,
            'letterheadCanvasJson' => $letterheadCanvasJson,
        ]);
    }

    public function toggleShare(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($invoice->isPublic()) {
            $this->invoiceService->disableShare($invoice);
            $msg = 'Public link disabled.';
        } else {
            $this->invoiceService->enableShare($invoice);
            $msg = 'Public link enabled.';
        }

        return redirect()->route('sales.invoices.show', $invoice)->with('status', $msg);
    }

    public function publicView(string $token): View
    {
        $invoice = Invoice::query()
            ->where('share_token', $token)
            ->with(['customer', 'items.product', 'business.branches'])
            ->firstOrFail();

        $business   = $invoice->business;
        $mainBranch = $business->branches()->first();
        $currency   = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('sales::invoices.public', [
            'invoice'    => $invoice,
            'business'   => $business,
            'mainBranch' => $mainBranch,
            'currency'   => $currency,
        ]);
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->requireInvoice($request, $invoice);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->invoiceService->delete($invoice);
        } catch (ValidationException $e) {
            return redirect()->route('sales.invoices.show', $invoice)->withErrors($e->errors());
        }

        return redirect()->route('sales.invoices.index')
            ->with('status', 'Invoice deleted.');
    }

    private function requireInvoice(Request $request, Invoice $invoice): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->invoiceService->invoiceForBusiness($business, $invoice) instanceof Invoice, 404);

        return $business;
    }

    private function validatedHeader(Request $request, Business $business): array
    {
        return $request->validate([
            'customer_id'     => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'       => ['nullable', 'string', 'max:120'],
            'issue_date'      => ['required', 'date'],
            'due_date'        => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tax_amount'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }

    private function statusTabs(): array
    {
        return [
            'all'                     => 'All',
            Invoice::STATUS_DRAFT     => 'Draft',
            Invoice::STATUS_SENT      => 'Sent',
            Invoice::STATUS_PAID      => 'Paid',
            Invoice::STATUS_OVERDUE   => 'Overdue',
            Invoice::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
