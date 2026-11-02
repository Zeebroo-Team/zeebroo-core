<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\Pos\Models\Customer;
use Modules\ProjectManage\Models\Project as PmProject;
use Modules\ProjectManage\Services\ProjectService as PmProjectService;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationItem;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\InvoiceService;
use Modules\Sales\Services\QuotationService;
use Modules\Sales\Services\SalesOrderService;

/**
 * Closing a lead.
 *
 * Won — DECISION: staff choose what to create from it: an invoice or a sales order from the
 * accepted quotation's lines, a ProjectManage project, or nothing. The lead is linked to its
 * customer (the convert logic: the linked customer, else one with the same phone / email, else a
 * new one) and gets closed_at / converted_at. A lead that is already won (e.g. the customer
 * accepted the quotation) can still get its document afterwards. Idempotent: a lead has at most
 * one invoice, one sales order and one project — asking again returns the existing one.
 *
 * Lost — a lost reason is required (crm_lost_reasons) plus an optional note; the lead's pending
 * follow-ups and open tasks are closed (CrmEventSubscriber on LeadClosed).
 *
 * Reopen — the head or an admin only (LeadPolicy::canReopen, checked by the caller), with a
 * reason that goes to the stage log and audit history.
 */
class LeadCloseService
{
    const CREATE_NONE        = 'none';
    const CREATE_INVOICE     = 'invoice';
    const CREATE_SALES_ORDER = 'sales_order';
    const CREATE_PROJECT     = 'project';

    const CREATE_OPTIONS = [
        self::CREATE_NONE        => 'Nothing — just mark it won',
        self::CREATE_INVOICE     => 'Invoice',
        self::CREATE_SALES_ORDER => 'Sales order',
        self::CREATE_PROJECT     => 'Project (ProjectManage)',
    ];

    public function __construct(
        private readonly LeadService $leads,
        private readonly LeadStageService $stages,
        private readonly CustomerService $customers,
        private readonly QuotationService $quotations,
        private readonly InvoiceService $invoices,
        private readonly SalesOrderService $salesOrders,
        private readonly PmProjectService $pmProjects,
    ) {}

    /** Quotation versions a Won can start from: sent or accepted, newest first. @return Collection<int, Quotation> */
    public function wonQuotations(Lead $lead): Collection
    {
        return Quotation::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->whereIn('status', [Quotation::STATUS_SENT, Quotation::STATUS_ACCEPTED])
            ->orderByRaw('case when status = ? then 0 else 1 end', [Quotation::STATUS_ACCEPTED])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The invoice / sales order / project made from the lead, if any.
     *
     * @return list<array{type: string, label: string, id: int, number: string, total: ?float, status: ?string, quotation_id: ?int}>
     */
    public function documents(Lead $lead): array
    {
        $docs = [];
        if ($invoice = $this->existingInvoice($lead)) {
            $docs[] = $this->presentDocument(self::CREATE_INVOICE, $invoice);
        }
        if ($order = $this->existingSalesOrder($lead)) {
            $docs[] = $this->presentDocument(self::CREATE_SALES_ORDER, $order);
        }
        if ($project = $this->existingProject($lead)) {
            $docs[] = $this->presentDocument(self::CREATE_PROJECT, $project);
        }

        return $docs;
    }

    /**
     * @param  array{quotation_id?: int|string|null, create?: ?string, project_name?: ?string, note?: ?string}  $data
     * @return array{lead: Lead, customer: Customer, document: ?array, document_created: bool}
     */
    public function markWon(Lead $lead, array $data, ?int $userId): array
    {
        $create = (string) ($data['create'] ?? self::CREATE_NONE) ?: self::CREATE_NONE;
        if (! array_key_exists($create, self::CREATE_OPTIONS)) {
            throw ValidationException::withMessages(['create' => 'Pick what to create: an invoice, a sales order, a project or nothing.']);
        }

        $quotation = null;
        if (filled($data['quotation_id'] ?? null)) {
            $quotation = Quotation::query()->where('business_id', $lead->business_id)->where('lead_id', $lead->id)->find((int) $data['quotation_id']);
            if ($quotation === null || ! in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_ACCEPTED], true)) {
                throw ValidationException::withMessages(['quotation_id' => 'Pick a quotation that was sent to (or accepted by) the customer.']);
            }
        } elseif (in_array($create, [self::CREATE_INVOICE, self::CREATE_SALES_ORDER], true)) {
            throw ValidationException::withMessages(['quotation_id' => 'An invoice or sales order is made from the accepted quotation — pick it.']);
        }

        return DB::transaction(function () use ($lead, $quotation, $create, $data, $userId) {
            // Serialises two clicks on Won: the second one waits, then finds the document made
            $lead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail()->load(['stage', 'project']);

            if ($lead->isLost()) {
                throw ValidationException::withMessages(['lead' => 'This lead is lost — the department head can reopen it first.']);
            }

            if ($quotation?->status === Quotation::STATUS_SENT) {
                $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
            }

            if ($lead->isOpen()) {
                if ($this->stages->wonStage($lead->project) === null) {
                    throw ValidationException::withMessages(['lead' => 'This pipeline has no won stage.']);
                }
                $lead = $this->leads->convertToCustomer($lead, $userId);
            } else {
                // Already won — make sure it is linked and stamped like a convert
                $customer = $this->customers->forLead($lead);
                $lead->update([
                    'customer_id'  => $customer->id,
                    'converted_at' => $lead->converted_at ?? now(),
                    'closed_at'    => $lead->closed_at ?? now(),
                ]);
                $lead->setRelation('customer', $customer);
            }

            $customer = $lead->customer;
            if ($quotation !== null && $quotation->customer_id === null) {
                $quotation->update(['customer_id' => $customer->id]);
            }

            [$document, $created] = match ($create) {
                self::CREATE_INVOICE     => $this->invoiceFrom($lead, $quotation),
                self::CREATE_SALES_ORDER => $this->salesOrderFrom($lead, $quotation),
                self::CREATE_PROJECT     => $this->projectFrom($lead, $quotation, $customer, $data['project_name'] ?? null, $userId),
                default                  => [null, false],
            };

            return [
                'lead'             => $lead->fresh(['stage', 'customer']),
                'customer'         => $customer,
                'document'         => $document ? $this->presentDocument($create, $document) : null,
                'document_created' => $created,
            ];
        });
    }

    public function markLost(Lead $lead, mixed $lostReasonId, ?string $note, ?int $userId): Lead
    {
        $lead->loadMissing(['stage', 'project']);
        if (! $lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => $lead->isLost() ? 'This lead is already lost.' : 'A won lead cannot be marked lost — the head can reopen it first.']);
        }

        return $this->leads->markLost($lead, $lostReasonId, $note, $userId);
    }

    public function reopen(Lead $lead, ?string $reason, ?int $userId): Lead
    {
        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Say why the lead is reopened.']);
        }

        return $this->leads->reopen($lead->loadMissing('project'), $userId, $reason);
    }

    // ── Documents ────────────────────────────────────────────────────────

    /** @return array{0: Invoice, 1: bool} */
    private function invoiceFrom(Lead $lead, Quotation $quotation): array
    {
        if ($existing = $this->existingInvoice($lead)) {
            return [$existing, false];
        }

        $invoice = $this->invoices->create($lead->business, [
            'branch_id'   => $quotation->branch_id,
            'customer_id' => $lead->customer_id,
            'reference'   => $quotation->quote_number,
            'issue_date'  => now()->toDateString(),
            'notes'       => $quotation->notes,
        ] + $this->quotations->headerAdjustments($quotation), $this->linesOf($quotation));
        $invoice->update(['quotation_id' => $quotation->id, 'lead_id' => $lead->id]);

        return [$invoice, true];
    }

    /** @return array{0: SalesOrder, 1: bool} */
    private function salesOrderFrom(Lead $lead, Quotation $quotation): array
    {
        if ($existing = $this->existingSalesOrder($lead)) {
            return [$existing, false];
        }

        $order = $this->salesOrders->create($lead->business, [
            'branch_id'   => $quotation->branch_id,
            'customer_id' => $lead->customer_id,
            'reference'   => $quotation->quote_number,
            'order_date'  => now()->toDateString(),
            'notes'       => $quotation->notes,
        ] + $this->quotations->headerAdjustments($quotation), $this->linesOf($quotation));
        $order->update(['quotation_id' => $quotation->id, 'lead_id' => $lead->id]);

        return [$order, true];
    }

    /** @return array{0: PmProject, 1: bool} */
    private function projectFrom(Lead $lead, ?Quotation $quotation, Customer $customer, ?string $name, ?int $userId): array
    {
        if ($existing = $this->existingProject($lead)) {
            return [$existing, false];
        }

        $name = trim((string) $name) ?: mb_substr(($lead->company ?: $lead->name) . ($quotation ? ' — ' . $quotation->quote_number : ''), 0, 150);
        $project = $this->pmProjects->create($lead->business, [
            'name'         => $name,
            'description'  => 'Started from CRM lead #' . $lead->id . ($quotation ? ' (quotation ' . $quotation->quote_number . ')' : '') . '.',
            'project_type' => PmProject::TYPE_CUSTOMER,
            'customer_id'  => $customer->id,
            'client_name'  => $customer->name,
            'budget'       => $quotation ? (float) $quotation->total : ($lead->estimated_value !== null ? (float) $lead->estimated_value : null),
            'start_date'   => now()->toDateString(),
        ], (int) $userId);
        $project->update(['quotation_id' => $quotation?->id, 'lead_id' => $lead->id]);

        return [$project, true];
    }

    /** The quotation's lines as invoice / sales order items: same price, line discount and tax. */
    private function linesOf(Quotation $quotation): array
    {
        return $quotation->items()->get()->map(fn (QuotationItem $item) => [
            'item_type'       => $item->product_id ? 'product' : ($item->service_item_id ? 'service' : 'custom'),
            'product_id'      => $item->product_id,
            'service_item_id' => $item->service_item_id,
            'description'     => $item->description,
            'quantity'        => (float) $item->quantity,
            'unit_price'      => (float) $item->unit_price,
            'discount_type'   => 'flat',
            'discount_value'  => (float) $item->discount_amount,
            'tax_pct'         => (float) $item->tax_rate,
            'tax_type'        => 'pct',
        ])->all();
    }

    private function existingInvoice(Lead $lead): ?Invoice
    {
        return Invoice::query()->where('business_id', $lead->business_id)->where('lead_id', $lead->id)->orderBy('id')->first();
    }

    private function existingSalesOrder(Lead $lead): ?SalesOrder
    {
        return SalesOrder::query()->where('business_id', $lead->business_id)->where('lead_id', $lead->id)->orderBy('id')->first();
    }

    private function existingProject(Lead $lead): ?PmProject
    {
        return PmProject::query()->where('business_id', $lead->business_id)->where('lead_id', $lead->id)->orderBy('id')->first();
    }

    private function presentDocument(string $type, Invoice|SalesOrder|PmProject $doc): array
    {
        return [
            'type'         => $type,
            'label'        => self::CREATE_OPTIONS[$type],
            'id'           => (int) $doc->id,
            'number'       => match (true) {
                $doc instanceof Invoice    => (string) ($doc->invoice_number ?? ('#' . $doc->id)),
                $doc instanceof SalesOrder => (string) $doc->order_number,
                default                    => (string) $doc->name,
            },
            'total'        => $doc instanceof PmProject ? ($doc->budget !== null ? (float) $doc->budget : null) : (float) $doc->total,
            'status'       => $doc->status,
            'quotation_id' => $doc->quotation_id !== null ? (int) $doc->quotation_id : null,
        ];
    }
}
