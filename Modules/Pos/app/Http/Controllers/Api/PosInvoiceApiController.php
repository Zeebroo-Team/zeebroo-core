<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Services\InvoiceService;

class PosInvoiceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $search     = trim((string) $request->query('q', ''));
        $status     = (string) $request->query('status', 'all');
        $customerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        $list = $this->invoices->listForBusiness(
            $business,
            $search !== '' ? $search : null,
            $status !== 'all' ? $status : null,
            $customerId,
        );

        return response()->json([
            'data' => $list->map(fn ($i) => $this->formatListItem($i))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $this->validateHeader($request, $business);

        try {
            $invoice = $this->invoices->create(
                $business,
                $validated,
                $request->input('items', []),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $invoice->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Invoice ' . $invoice->invoice_number . ' created.',
            'data'    => $this->formatDetail($invoice),
        ], 201);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        $invoice->load(['customer', 'items.product']);

        return response()->json(['data' => $this->formatDetail($invoice)]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        $validated = $this->validateHeader($request, $business);

        try {
            $invoice = $this->invoices->update($invoice, $validated, $request->input('items', []));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $invoice->load(['customer', 'items.product']);

        return response()->json([
            'message' => 'Invoice updated.',
            'data'    => $this->formatDetail($invoice),
        ]);
    }

    public function markSent(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        $this->invoices->markSent($invoice);

        return response()->json(['message' => 'Marked as sent.', 'status' => $invoice->fresh()->status]);
    }

    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        try {
            $this->invoices->markPaid($invoice);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Marked as paid.', 'status' => $invoice->fresh()->status]);
    }

    public function markOverdue(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        $this->invoices->markOverdue($invoice);

        return response()->json(['message' => 'Marked as overdue.', 'status' => $invoice->fresh()->status]);
    }

    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        $this->invoices->cancel($invoice);

        return response()->json(['message' => 'Invoice cancelled.', 'status' => $invoice->fresh()->status]);
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless((int) $invoice->business_id === (int) $business->id, 404);

        try {
            $this->invoices->delete($invoice);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Invoice deleted.']);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatListItem(Invoice $i): array
    {
        return [
            'id'             => (int) $i->id,
            'invoice_number' => $i->invoice_number,
            'status'         => $i->status,
            'status_label'   => $i->statusLabel(),
            'status_color'   => $i->statusColor(),
            'customer_name'  => $i->customer?->name,
            'reference'      => $i->reference,
            'issue_date'     => $i->issue_date?->toDateString(),
            'due_date'       => $i->due_date?->toDateString(),
            'is_overdue'     => $i->isPaymentDue(),
            'total'          => round((float) $i->total, 2),
        ];
    }

    private function formatDetail(Invoice $i): array
    {
        return [
            ...$this->formatListItem($i),
            'subtotal'        => round((float) $i->subtotal, 2),
            'discount_amount' => round((float) $i->discount_amount, 2),
            'tax_amount'      => round((float) $i->tax_amount, 2),
            'notes'           => $i->notes,
            'customer_id'     => $i->customer_id,
            'is_editable'     => $i->isEditable(),
            'items'           => $i->items->map(fn ($item) => [
                'id'          => (int) $item->id,
                'product_id'  => $item->product_id,
                'item_type'   => $item->product_id ? 'product' : ($item->service_item_id ? 'service' : 'custom'),
                'description' => $item->description,
                'quantity'    => round((float) $item->quantity, 3),
                'unit_price'  => round((float) $item->unit_price, 2),
                'line_total'  => round((float) $item->line_total, 2),
            ])->values()->all(),
        ];
    }

    private function validateHeader(Request $request, \Modules\Business\Models\Business $business): array
    {
        return $request->validate([
            'customer_id'             => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'reference'               => ['nullable', 'string', 'max:120'],
            'issue_date'              => ['required', 'date'],
            'due_date'                => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.item_type'       => ['nullable', 'string', 'in:product,service,custom'],
            'items.*.product_id'      => ['nullable', 'integer'],
            'items.*.service_item_id' => ['nullable', 'integer'],
            'items.*.description'     => ['nullable', 'string', 'max:255'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
        ]);
    }
}
