<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAttachment;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\Timeline\TimelineEntry;
use Modules\CRM\Services\Timeline\TimelineService;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\Sale;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\SalesOrder;

/**
 * The 360° customer page (web ContactController@show and GET /api/pos/crm/customers/{id}):
 * the customer's leads, quotations, files, follow-ups, invoices / orders and one timeline.
 *
 * Leads (and so their files, tasks and timeline) are limited to the ones the user may see.
 */
class CustomerProfileService
{
    /** How much of each list the page shows. */
    private const LIMIT = 100;

    private const ACTIVITY_ICONS = [
        Activity::TYPE_NOTE    => ['fa-note-sticky', '#64748b'],
        Activity::TYPE_CALL    => ['fa-phone', '#14b8a6'],
        Activity::TYPE_EMAIL   => ['fa-envelope', '#0ea5e9'],
        Activity::TYPE_MEETING => ['fa-handshake', '#8b5cf6'],
        Activity::TYPE_ENQUIRY => ['fa-inbox', '#f59e0b'],
    ];

    public function __construct(
        private readonly TimelineService $timeline,
        private readonly LeadAttachmentService $attachments,
    ) {}

    public function build(Customer $customer, CrmAccess $access): array
    {
        $leads      = $this->leads($customer, $access);
        $quotations = $this->quotations($customer);
        $orders     = $this->orders($customer);
        $tasks      = $this->tasks($customer, $leads);

        return [
            'customer'   => [
                'id'            => $customer->id,
                'name'          => $customer->name,
                'phone'         => $customer->phone,
                'email'         => $customer->email,
                'address'       => $customer->address,
                'notes'         => $customer->notes,
                'customer_type' => $customer->customer_type ?? 'retail',
                'created_at'    => $customer->created_at?->toIso8601String(),
            ],
            'summary'    => [
                'leads'           => $leads->count(),
                'open_leads'      => $leads->filter(fn (Lead $l) => $l->isOpen())->count(),
                'quotations'      => count($quotations),
                'open_quotations' => count(array_filter($quotations, fn ($q) => in_array($q['status'], [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT], true))),
                'open_tasks'      => count(array_filter($tasks, fn ($t) => $t['status'] === Task::STATUS_PENDING)),
                'overdue_tasks'   => count(array_filter($tasks, fn ($t) => $t['is_overdue'])),
                'orders'          => count($orders),
                'total_sales'     => round(array_sum(array_map(fn ($o) => $o['counts'] ? $o['total'] : 0, $orders)), 2),
            ],
            'leads'      => $leads->map(fn (Lead $l) => $this->presentLead($l))->values()->all(),
            'quotations' => $quotations,
            'documents'  => $this->documents($leads),
            'tasks'      => $tasks,
            'orders'     => $orders,
            'timeline'   => $this->timelineFor($customer, $leads, $quotations, $orders),
        ];
    }

    /** @return Collection<int, Lead> linked leads the user may see, newest first */
    private function leads(Customer $customer, CrmAccess $access): Collection
    {
        return Lead::query()
            ->where('crm_leads.business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->visibleWith($access)
            ->with(['stage', 'project:id,name'])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    private function presentLead(Lead $lead): array
    {
        return [
            'id'              => $lead->id,
            'name'            => $lead->name,
            'company'         => $lead->company,
            'relation'        => $lead->project?->name,
            'project_id'      => $lead->project_id,
            'stage'           => $lead->stage?->name,
            'status_key'      => $lead->statusKey(),
            'status_label'    => LeadStage::statusLabel($lead->statusKey()) ?? $lead->stage?->name,
            'is_open'         => $lead->isOpen(),
            'estimated_value' => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'source'          => $lead->source,
            'created_at'      => $lead->created_at?->toIso8601String(),
            'converted_at'    => $lead->converted_at?->toIso8601String(),
        ];
    }

    /** Every quotation of the customer, newest first — revisions are separate quotations, so all show. */
    private function quotations(Customer $customer): array
    {
        return Quotation::query()
            ->where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->withCount('items')
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Quotation $q) => [
                'id'           => $q->id,
                'number'       => $q->quote_number,
                'reference'    => $q->reference,
                'status'       => $q->status,
                'status_label' => $q->statusLabel(),
                'date'         => $q->quote_date?->toDateString(),
                'expiry_date'  => $q->expiry_date?->toDateString(),
                'items_count'  => (int) $q->items_count,
                'total'        => round((float) $q->total, 2),
                'created_at'   => $q->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Invoices, sales orders and POS sales in one list, newest first. `counts` = part of the sales total. */
    private function orders(Customer $customer): array
    {
        $invoices = Invoice::query()
            ->where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->latest('issue_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Invoice $i) => [
                'type' => 'invoice', 'type_label' => 'Invoice', 'id' => $i->id, 'number' => $i->invoice_number,
                'date' => $i->issue_date?->toDateString(), 'status' => $i->status, 'status_label' => $i->statusLabel(),
                'total' => round((float) $i->total, 2), 'counts' => ! in_array($i->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true),
                'at' => $i->created_at,
            ]);

        $salesOrders = SalesOrder::query()
            ->where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->latest('order_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SalesOrder $o) => [
                'type' => 'order', 'type_label' => 'Sales order', 'id' => $o->id, 'number' => $o->order_number,
                'date' => $o->order_date?->toDateString(), 'status' => $o->status, 'status_label' => $o->statusLabel(),
                // Orders are invoiced separately; counting them too would double the total
                'total' => round((float) $o->total, 2), 'counts' => false,
                'at' => $o->created_at,
            ]);

        $posSales = Sale::query()
            ->where('business_id', $customer->business_id)
            ->where('pos_customer_id', $customer->id)
            ->latest('sold_at')
            ->limit(self::LIMIT)
            ->get(['id', 'sale_number', 'status', 'payment_method', 'total', 'sold_at', 'created_at'])
            ->map(fn (Sale $s) => [
                'type' => 'pos_sale', 'type_label' => 'POS sale', 'id' => $s->id, 'number' => $s->sale_number,
                'date' => $s->sold_at?->toDateString(), 'status' => $s->status, 'status_label' => ucfirst((string) $s->status),
                'total' => round((float) $s->total, 2), 'counts' => $s->status === Sale::STATUS_COMPLETED,
                'at' => $s->sold_at ?? $s->created_at,
            ]);

        return $invoices->concat($salesOrders)->concat($posSales)
            ->sortByDesc(fn ($o) => [$o['date'] ?? '', $o['at']?->getTimestamp() ?? 0])
            ->map(function ($o) {
                $o['at'] = $o['at']?->toIso8601String();

                return $o;
            })
            ->values()
            ->all();
    }

    /** Files uploaded on the customer's leads. */
    private function documents(Collection $leads): array
    {
        if ($leads->isEmpty()) {
            return [];
        }

        $names = $leads->pluck('name', 'id');

        return LeadAttachment::query()
            ->whereIn('lead_id', $leads->pluck('id'))
            ->with(['file', 'uploadedBy:id,name'])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (LeadAttachment $a) => $this->attachments->present($a, false) + [
                'lead' => ['id' => $a->lead_id, 'name' => $names[$a->lead_id] ?? null],
            ])
            ->all();
    }

    /** Follow-ups on the customer and on its leads: open ones first by due date, then done ones. */
    private function tasks(Customer $customer, Collection $leads): array
    {
        $leadNames = $leads->pluck('name', 'id');

        return Task::query()
            ->where('business_id', $customer->business_id)
            ->where(function ($q) use ($customer, $leads) {
                $q->where(fn ($q) => $q->where('subject_type', Customer::class)->where('subject_id', $customer->id));
                if ($leads->isNotEmpty()) {
                    $q->orWhere(fn ($q) => $q->where('subject_type', Lead::class)->whereIn('subject_id', $leads->pluck('id')));
                }
            })
            ->with('assignedTo:id,name')
            ->orderByRaw('status = ? desc', [Task::STATUS_PENDING])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Task $t) => [
                'id'           => $t->id,
                'title'        => $t->title,
                'description'  => $t->description,
                'due_at'       => $t->due_at?->toIso8601String(),
                'status'       => $t->status,
                'is_overdue'   => $t->isOverdue(),
                'completed_at' => $t->completed_at?->toIso8601String(),
                'assigned_to'  => $t->assignedTo ? ['id' => $t->assignedTo->id, 'name' => $t->assignedTo->name] : null,
                'lead'         => $t->subject_type === Lead::class
                    ? ['id' => (int) $t->subject_id, 'name' => $leadNames[$t->subject_id] ?? null]
                    : null,
            ])
            ->all();
    }

    /** The customer's own activities, each lead's timeline, and the quotations and sales, newest first. */
    private function timelineFor(Customer $customer, Collection $leads, array $quotations, array $orders, int $limit = 300): array
    {
        $entries = [];

        $activities = Activity::query()
            ->where('business_id', $customer->business_id)
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->with('createdBy:id,name')
            ->get();
        foreach ($activities as $a) {
            [$icon, $color] = self::ACTIVITY_ICONS[$a->type] ?? ['fa-circle', '#64748b'];
            $entries[] = (new TimelineEntry('activity', $a->id, $a->occurred_at ?? $a->created_at, $a->typeLabel(), $a->body,
                $a->created_by, $a->createdBy?->name, $icon, $color, ['activity_type' => $a->type]))->toArray() + ['lead' => null];
        }

        foreach ($leads as $lead) {
            foreach ($this->timeline->forLead($lead, null, $limit) as $entry) {
                $entries[] = $entry + ['lead' => ['id' => $lead->id, 'name' => $lead->name]];
            }
        }

        foreach ($quotations as $q) {
            $entries[] = (new TimelineEntry('quotation', $q['id'], $this->date($q['created_at']), "Quotation {$q['number']} · {$q['status_label']}",
                number_format($q['total'], 2), null, null, 'fa-file-invoice', '#6366f1', ['quotation_id' => $q['id']]))->toArray() + ['lead' => null];
        }

        foreach ($orders as $o) {
            $entries[] = (new TimelineEntry($o['type'], $o['id'], $this->date($o['at']), "{$o['type_label']} {$o['number']} · {$o['status_label']}",
                number_format($o['total'], 2), null, null, $o['type'] === 'pos_sale' ? 'fa-cash-register' : 'fa-receipt', '#10b981', [$o['type'] . '_id' => $o['id']]))->toArray() + ['lead' => null];
        }

        usort($entries, fn ($a, $b) => [$b['at'] ?? '', $b['key']] <=> [$a['at'] ?? '', $a['key']]);

        return array_slice($entries, 0, $limit);
    }

    private function date(?string $iso): ?Carbon
    {
        return $iso !== null ? Carbon::parse($iso) : null;
    }
}
