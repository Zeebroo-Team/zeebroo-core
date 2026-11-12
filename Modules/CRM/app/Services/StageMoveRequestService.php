<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\StageMoveRequest;

/**
 * Moves into a stage that needs approval (LeadStage::needs_approval). Someone who may not
 * approve (LeadPolicy::canApprove) asks; the lead stays where it is and its head is notified.
 * Approving makes the move with what was asked for on it; rejecting tells the person who asked.
 */
class StageMoveRequestService
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly CrmNotifier $notifier,
    ) {}

    /**
     * Ask to move the lead; a request already waiting for this lead is replaced.
     *
     * @param  array<string, mixed>  $context  what was asked for on the move (reason, details, …)
     */
    public function request(Lead $lead, LeadStage $to, ?int $userId, array $context = []): StageMoveRequest
    {
        if ((int) $lead->stage_id === (int) $to->id) {
            throw ValidationException::withMessages(['stage_id' => 'The lead is already in "' . $to->name . '".']);
        }
        // Checklist / no-skipping problems are told now, not when the head approves
        $this->leads->assertMayEnter($lead, $to);

        $request = DB::transaction(function () use ($lead, $to, $userId, $context) {
            StageMoveRequest::query()->where('lead_id', $lead->id)->pending()
                ->update(['status' => StageMoveRequest::STATUS_CANCELLED, 'decided_at' => now()]);

            return StageMoveRequest::create([
                'business_id'   => $lead->business_id,
                'lead_id'       => $lead->id,
                'from_stage_id' => $lead->stage_id,
                'to_stage_id'   => $to->id,
                'requested_by'  => $userId,
                'context'       => array_intersect_key($context, array_flip(['reason', 'lost_reason_id', 'lost_reason', 'follow_up_at', 'expected_close_date', 'estimated_value'])),
                'status'        => StageMoveRequest::STATUS_PENDING,
            ]);
        });

        $by = $userId ? User::query()->whereKey($userId)->value('name') : null;
        $this->notifier->notify($lead, $this->notifier->headUserIdsForLead($lead), CrmNotifier::TYPE_STAGE_APPROVAL,
            'Stage move waiting for your approval',
            ($by ?? 'Someone') . ' wants to move ' . $this->leadName($lead) . ' to "' . $to->name . '".'
                . (filled($context['reason'] ?? null) ? "\nReason: " . $context['reason'] : ''),
            $userId, ['stage_id' => (int) $to->id, 'request_id' => (int) $request->id], 'crm_stage_move', (int) $request->id);

        return $request;
    }

    public function approve(StageMoveRequest $request, ?int $userId): Lead
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $userId) {
            $lead = $request->lead()->firstOrFail();
            $lead = $this->leads->moveStage($lead, (int) $request->to_stage_id, $userId, (array) $request->context);
            $request->update(['status' => StageMoveRequest::STATUS_APPROVED, 'decided_by' => $userId, 'decided_at' => now()]);

            $this->tellRequester($request, $lead, 'approved', $userId);

            return $lead;
        });
    }

    public function reject(StageMoveRequest $request, ?int $userId, ?string $note = null): StageMoveRequest
    {
        $this->assertPending($request);

        $note = filled($note) ? mb_substr(trim($note), 0, 500) : null;
        $request->update(['status' => StageMoveRequest::STATUS_REJECTED, 'decided_by' => $userId, 'decided_at' => now(), 'note' => $note]);
        $this->tellRequester($request, $request->lead, 'rejected', $userId, $note);

        return $request;
    }

    public function pendingForLead(Lead $lead, int $requestId): StageMoveRequest
    {
        $request = StageMoveRequest::query()->where('lead_id', $lead->id)->find($requestId);
        if ($request === null) {
            throw ValidationException::withMessages(['request' => 'That request was not found.']);
        }

        return $request;
    }

    /**
     * The request waiting on each lead, for the board badge.
     *
     * @param  int[]  $leadIds
     * @return array<int, array{id:int,to_stage_id:int,to_stage_name:?string,requested_by_name:?string,requested_at:?string}>
     */
    public function pendingByLead(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        return StageMoveRequest::query()->whereIn('lead_id', $leadIds)->pending()
            ->with(['toStage:id,name', 'requestedBy:id,name'])
            ->get()
            ->mapWithKeys(fn (StageMoveRequest $r) => [(int) $r->lead_id => [
                'id'                => (int) $r->id,
                'to_stage_id'       => (int) $r->to_stage_id,
                'to_stage_name'     => $r->toStage?->name,
                'requested_by_name' => $r->requestedBy?->name,
                'requested_at'      => $r->created_at?->toIso8601String(),
            ]])
            ->all();
    }

    private function assertPending(StageMoveRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'This request was already ' . $request->status . '.']);
        }
    }

    private function tellRequester(StageMoveRequest $request, ?Lead $lead, string $verb, ?int $userId, ?string $note = null): void
    {
        if ($lead === null || ! $request->requested_by) {
            return;
        }
        $stage = $request->toStage?->name ?? 'the stage';
        $this->notifier->notify($lead, [(int) $request->requested_by], CrmNotifier::TYPE_STAGE_APPROVAL_DECIDED,
            'Stage move ' . $verb,
            'Moving ' . $this->leadName($lead) . ' to "' . $stage . '" was ' . $verb . ' by '
                . (($userId ? User::query()->whereKey($userId)->value('name') : null) ?? 'the head') . '.'
                . ($note ? "\n“" . $note . '”' : ''),
            $userId, ['stage_id' => (int) $request->to_stage_id, 'request_id' => (int) $request->id, 'decision' => $verb],
            'crm_stage_move', (int) $request->id);
    }

    private function leadName(Lead $lead): string
    {
        return '"' . ($lead->company ? $lead->name . ' · ' . $lead->company : $lead->name) . '"';
    }
}
