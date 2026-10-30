<?php

namespace Modules\CRM\Services\Timeline;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Models\QuotationDispatch;

/** The lead's quotations: each one made, every approval step and every send to the customer. */
final class QuotationTimelineSource implements TimelineSource
{
    public function key(): string { return 'quotation'; }

    public function label(): string { return 'Quotations'; }

    public function entries(Lead $lead): iterable
    {
        // The Sales module (and its tables) may not be installed
        if (! Schema::hasTable('quotation_approvals')) {
            return;
        }

        $quotations = Quotation::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->with(['approvals.byUser:id,name', 'dispatches.sentBy:id,name'])
            ->get();

        foreach ($quotations as $q) {
            $meta = ['quotation_id' => $q->id, 'quote_number' => $q->quote_number, 'version' => $q->version];

            yield new TimelineEntry('quotation', 'q' . $q->id, $q->created_at,
                ($q->version > 1 ? 'Version ' . $q->version . ' of quotation ' : 'Quotation ') . $q->quote_number . ' created',
                'Total ' . number_format((float) $q->total, 2),
                null, null, 'fa-file-invoice-dollar', '#8b5cf6', $meta);

            foreach ($q->approvals as $a) {
                [$label, $icon, $color] = QuotationApproval::ACTIONS[$a->action] ?? [ucfirst($a->action), 'fa-circle', '#64748b'];
                yield new TimelineEntry('quotation', 'a' . $a->id, $a->created_at,
                    $q->quote_number . ': ' . strtolower($label),
                    $a->comment, $a->by_user_id, $a->byUser?->name, $icon, $color, $meta + ['action' => $a->action]);
            }

            foreach ($q->dispatches as $d) {
                $failed = $d->status === QuotationDispatch::STATUS_FAILED;
                $title  = $q->quote_number . match ($d->status) {
                    QuotationDispatch::STATUS_SENT   => ' sent by ' . $d->channel . ' to ' . $d->to,
                    QuotationDispatch::STATUS_FAILED => ' could not be sent by ' . $d->channel . ' to ' . $d->to,
                    default                          => ' queued for ' . $d->channel . ' to ' . $d->to,
                };
                yield new TimelineEntry('quotation', 'd' . $d->id, $d->sent_at ?? $d->created_at, $title,
                    $failed ? $d->error : $d->subject, $d->sent_by, $d->sentBy?->name,
                    $failed ? 'fa-triangle-exclamation' : 'fa-envelope-circle-check', $failed ? '#ef4444' : '#f59e0b',
                    $meta + ['dispatch_id' => $d->id, 'status' => $d->status]);
            }
        }
    }
}
