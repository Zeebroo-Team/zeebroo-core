<?php

namespace Modules\CRM\Listeners;

use App\Models\User;
use Illuminate\Events\Dispatcher;
use Modules\AutomationEditor\Services\AutomationRunnerService;
use Modules\CRM\Events\FollowUpDue;
use Modules\CRM\Events\FollowUpOverdue;
use Modules\CRM\Events\LeadAssigned;
use Modules\CRM\Events\LeadClosed;
use Modules\CRM\Events\LeadEnteredInbox;
use Modules\CRM\Events\LeadForwarded;
use Modules\CRM\Events\QuotationDecided;
use Modules\CRM\Events\QuotationSent;
use Modules\CRM\Events\QuotationSubmitted;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\CrmNotifier;
use Modules\CRM\Services\FollowUpService;
use Modules\Sales\Models\QuotationApproval;

/**
 * What happens after the CRM workflow events (Modules/CRM/app/Events): who is notified
 * (CrmNotifier), follow-ups planned on sending and cancelled on closing, and the
 * AutomationEditor triggers crm.quotation.sent / crm.followup.overdue. A failure here is
 * reported but never undoes the action that raised the event.
 *
 *   new lead in the inbox        → marketing users
 *   forwarded                    → the receiving department / division head
 *   assigned / re-assigned       → the staff added (and the new primary)
 *   quotation submitted          → the head ("discount approval needed" when over the limit)
 *   approved / returned/rejected → whoever submitted it
 *   follow-up due                → its assignee
 *   follow-up overdue            → its assignee and the head
 *   won / lost                   → the head
 */
class CrmEventSubscriber
{
    public function __construct(
        private readonly CrmNotifier $notifier,
        private readonly FollowUpService $followUps,
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            LeadEnteredInbox::class   => 'onLeadEnteredInbox',
            LeadForwarded::class      => 'onLeadForwarded',
            LeadAssigned::class       => 'onLeadAssigned',
            QuotationSubmitted::class => 'onQuotationSubmitted',
            QuotationDecided::class   => 'onQuotationDecided',
            QuotationSent::class      => 'onQuotationSent',
            FollowUpDue::class        => 'onFollowUpDue',
            FollowUpOverdue::class    => 'onFollowUpOverdue',
            LeadClosed::class         => 'onLeadClosed',
        ];
    }

    public function onLeadEnteredInbox(LeadEnteredInbox $e): void
    {
        $this->guard(function () use ($e) {
            $lead = $e->lead;
            $this->notifier->notify($lead, $this->notifier->marketingUserIds($lead->business), CrmNotifier::TYPE_LEAD_NEW,
                'New lead in the inbox',
                $this->leadName($lead) . ($lead->source ? ' (' . Lead::sourceLabel($lead->source) . ')' : '') . ' is waiting to be verified and forwarded.',
                $e->byUserId);
        });
    }

    public function onLeadForwarded(LeadForwarded $e): void
    {
        $this->guard(function () use ($e) {
            $lead = $e->lead->fresh(['department', 'division']);
            $this->notifier->notify($lead, [$this->notifier->headUserIdForLead($lead)], CrmNotifier::TYPE_LEAD_FORWARDED,
                'Lead forwarded to ' . ($lead->division?->name ?? $lead->department?->name ?? 'your department'),
                $this->actor($e->byUserId, 'Marketing') . ' forwarded ' . $this->leadName($lead) . ' — assign someone to work it.'
                    . ($e->note ? "\nNote: " . $e->note : ''),
                $e->byUserId);
        });
    }

    public function onLeadAssigned(LeadAssigned $e): void
    {
        $this->guard(function () use ($e) {
            $lead = $e->lead;
            $added = $e->addedUserIds;
            if ($added !== []) {
                $this->notifier->notify($lead, $added, CrmNotifier::TYPE_LEAD_ASSIGNED,
                    'Lead assigned to you',
                    $this->actor($e->byUserId, 'Someone') . ' assigned you to ' . $this->leadName($lead) . ' in ' . ($lead->project?->name ?? 'the pipeline') . '.',
                    $e->byUserId);
            }
            if ($e->newPrimaryUserId !== null && ! in_array($e->newPrimaryUserId, $added, true)) {
                $this->notifier->notify($lead, [$e->newPrimaryUserId], CrmNotifier::TYPE_LEAD_ASSIGNED,
                    'You are now the primary on a lead',
                    $this->actor($e->byUserId, 'Someone') . ' made you the primary assignee of ' . $this->leadName($lead) . '.',
                    $e->byUserId);
            }
        });
    }

    public function onQuotationSubmitted(QuotationSubmitted $e): void
    {
        $this->guard(function () use ($e) {
            $q = $e->quotation;
            $over = (bool) ($e->discount['over_limit'] ?? false);
            $this->notifier->notify($e->lead, [$this->notifier->headUserIdForLead($e->lead)], CrmNotifier::TYPE_QUOTATION_SUBMITTED,
                $over ? 'Discount approval needed' : 'Quotation waiting for your approval',
                $this->actor($e->byUserId, 'Staff') . ' submitted ' . $q->quote_number . ($q->version > 1 ? ' v' . $q->version : '')
                    . ' for ' . $this->leadName($e->lead) . ' (' . number_format((float) $q->total, 2) . ')'
                    . ($over ? ' with a ' . $this->pct($e->discount['percent']) . '% discount, over the ' . $this->pct($e->discount['limit']) . '% limit.' : '.'),
                $e->byUserId, ['quotation_id' => (int) $q->id], 'crm_quotation', (int) $q->id);
        });
    }

    public function onQuotationDecided(QuotationDecided $e): void
    {
        $this->guard(function () use ($e) {
            $q = $e->quotation;
            $verb = match ($e->action) {
                QuotationApproval::ACTION_APPROVED => 'approved',
                QuotationApproval::ACTION_RETURNED => 'returned for changes',
                default                            => 'rejected',
            };
            $this->notifier->notify($e->lead, [(int) $q->submitted_by], CrmNotifier::TYPE_QUOTATION_DECIDED,
                'Quotation ' . $verb,
                $this->actor($e->byUserId, 'The head') . ' ' . $verb . ' ' . $q->quote_number . ' for ' . $this->leadName($e->lead) . '.'
                    . ($e->comment ? "\n“" . $e->comment . '”' : '')
                    . ($e->action === QuotationApproval::ACTION_APPROVED ? ' It can be sent to the customer now.' : ''),
                $e->byUserId, ['quotation_id' => (int) $q->id, 'decision' => $e->action], 'crm_quotation', (int) $q->id);
        });
    }

    public function onQuotationSent(QuotationSent $e): void
    {
        $this->guard(fn () => $this->followUps->scheduleForQuotation($e->lead, $e->quotation, $e->byUserId));
        $this->guard(fn () => app(AutomationRunnerService::class)->dispatch('crm.quotation.sent', $e->lead->business, [
            'event'     => 'crm.quotation.sent',
            'lead'      => $this->leadPayload($e->lead),
            'quotation' => [
                'id'           => (int) $e->quotation->id,
                'quote_number' => $e->quotation->quote_number,
                'version'      => (int) $e->quotation->version,
                'total'        => (float) $e->quotation->total,
                'sent_via'     => $e->quotation->sent_via,
            ],
        ]));
    }

    public function onFollowUpDue(FollowUpDue $e): void
    {
        $this->guard(function () use ($e) {
            $f = $e->followUp;
            $lead = $f->lead;
            if ($lead === null) {
                return;
            }
            $this->notifier->notify($lead, [(int) $f->user_id], CrmNotifier::TYPE_FOLLOW_UP_DUE,
                'Follow-up due',
                'Follow up with ' . $this->leadName($lead) . ($lead->phone ? ' (' . $lead->phone . ')' : '') . ' — due ' . $f->due_at->format('M j, g:i A') . '.'
                    . ($f->notes ? "\n" . $f->notes : ''),
                null, ['follow_up_id' => (int) $f->id], 'crm_follow_up', (int) $f->id);
        });
    }

    public function onFollowUpOverdue(FollowUpOverdue $e): void
    {
        $f = $e->followUp;
        $lead = $f->lead;
        if ($lead === null) {
            return;
        }

        $this->guard(function () use ($f, $lead) {
            $assignee = $f->user_id ? User::query()->whereKey($f->user_id)->value('name') : null;
            $this->notifier->notify($lead, [(int) $f->user_id, $this->notifier->headUserIdForLead($lead)], CrmNotifier::TYPE_FOLLOW_UP_OVERDUE,
                'Follow-up overdue',
                'The follow-up with ' . $this->leadName($lead) . ' was due ' . $f->due_at->format('M j') . ($assignee ? ' (' . $assignee . ')' : '') . ' and is not done yet.',
                null, ['follow_up_id' => (int) $f->id], 'crm_follow_up', (int) $f->id);
        });
        $this->guard(fn () => app(AutomationRunnerService::class)->dispatch('crm.followup.overdue', $lead->business, [
            'event'     => 'crm.followup.overdue',
            'lead'      => $this->leadPayload($lead),
            'follow_up' => ['id' => (int) $f->id, 'due_at' => $f->due_at?->toIso8601String(), 'user_id' => $f->user_id, 'quotation_id' => $f->quotation_id],
        ]));
    }

    public function onLeadClosed(LeadClosed $e): void
    {
        $lead = $e->lead;

        // Nothing left to chase; a lost lead's open tasks are closed too
        $this->guard(fn () => $this->followUps->cancelPending($lead));
        if ($e->outcome === LeadClosed::LOST) {
            $this->guard(fn () => Task::query()
                ->where('subject_type', $lead->getMorphClass())
                ->where('subject_id', $lead->id)
                ->where('status', Task::STATUS_PENDING)
                ->get()
                ->each(fn (Task $t) => $t->update(['status' => Task::STATUS_COMPLETED, 'completed_at' => now()])));
        }

        $this->guard(function () use ($e, $lead) {
            $lead->loadMissing('lostReason');
            $won = $e->outcome === LeadClosed::WON;
            $this->notifier->notify($lead, [$this->notifier->headUserIdForLead($lead)], CrmNotifier::TYPE_LEAD_CLOSED,
                $won ? 'Lead won' : 'Lead lost',
                $this->leadName($lead) . ' was ' . ($won ? 'won' : 'lost') . ' by ' . $this->actor($e->byUserId, 'the team')
                    . (! $won && $lead->lostReason ? ' — ' . $lead->lostReason->label . ($lead->lost_reason ? ': ' . $lead->lost_reason : '') : '')
                    . ($won && $lead->estimated_value ? ' (' . number_format((float) $lead->estimated_value, 2) . ')' : '') . '.',
                $e->byUserId, ['outcome' => $e->outcome]);
        });
    }

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function leadName(Lead $lead): string
    {
        return '"' . ($lead->company ? $lead->name . ' · ' . $lead->company : $lead->name) . '"';
    }

    private function actor(?int $userId, string $fallback): string
    {
        return ($userId ? User::query()->whereKey($userId)->value('name') : null) ?? $fallback;
    }

    private function pct(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function leadPayload(Lead $lead): array
    {
        return [
            'id'         => (int) $lead->id,
            'name'       => $lead->name,
            'email'      => $lead->email,
            'phone'      => $lead->phone,
            'project_id' => (int) $lead->project_id,
            'stage_id'   => $lead->stage_id,
            'assigned_to' => $lead->assigned_to,
        ];
    }
}
