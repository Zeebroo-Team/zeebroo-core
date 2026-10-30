<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignmentLog;
use Modules\CRM\Models\LeadAttachment;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadMessage;
use Modules\CRM\Models\LeadCustomFieldValue;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\CRM\Policies\LeadPolicy;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Models\Customer;

/**
 * The marketing inbox: new leads in the intake pipeline and leads flagged "needs review",
 * where Marketing cleans the details, verifies, forwards to a department / division, clears
 * review flags and merges duplicates. Who may do each of these is decided by LeadPolicy;
 * callers check it before calling in here.
 */
class MarketingInboxService
{
    public const FILTERS = [
        'all'        => 'All',
        'review'     => 'Needs review',
        'unverified' => 'Not verified',
        'verified'   => 'Verified',
    ];

    public function __construct(
        private readonly LeadService $leads,
        private readonly LeadIntakeService $intake,
        private readonly DuplicateLeadService $duplicates,
        private readonly CrmOrgService $org,
        private readonly LeadAuditService $audit,
        private readonly LeadPolicy $policy,
        private readonly LeadAssignmentService $assignments,
    ) {}

    // ── Reading ──────────────────────────────────────────────────────────

    /** @return Collection<int, Lead> flagged first, then newest */
    public function list(Business $business, CrmAccess $access, string $filter = 'all', ?string $search = null, int $limit = 200): Collection
    {
        return $this->query($business, $access, $filter)
            ->when(filled($search), fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where('crm_leads.name', 'like', "%{$search}%")
                  ->orWhere('crm_leads.company', 'like', "%{$search}%")
                  ->orWhere('crm_leads.email', 'like', "%{$search}%")
                  ->orWhere('crm_leads.phone', 'like', "%{$search}%");
            }))
            ->with(['stage', 'project:id,name,department_id', 'customer:id,name', 'createdBy:id,name', 'verifiedBy:id,name',
                'requestedDepartment:id,name', 'duplicateOf.stage', 'duplicateOf.project:id,name'])
            ->orderByDesc('crm_leads.needs_review')
            ->orderByDesc('crm_leads.id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> filter key => number of leads */
    public function counts(Business $business, CrmAccess $access): array
    {
        return collect(array_keys(self::FILTERS))
            ->mapWithKeys(fn (string $f) => [$f => $this->query($business, $access, $f)->count()])
            ->all();
    }

    /** Inbox leads: "new" in the intake pipeline, plus every flagged lead the user can see. */
    private function query(Business $business, CrmAccess $access, string $filter): Builder
    {
        $intakeId = $this->intakeProjectId($business->id);
        $isNew    = fn (Builder $q) => $q->where('crm_leads.project_id', $intakeId ?? 0)
            ->whereHas('stage', fn (Builder $s) => $s->where('system_key', LeadStage::STATUS_NEW));

        return Lead::query()
            ->where('crm_leads.business_id', $business->id)
            ->visibleWith($access)
            ->where(fn (Builder $q) => $q->where('crm_leads.needs_review', true)->orWhere($isNew))
            ->when($filter === 'review', fn (Builder $q) => $q->where('crm_leads.needs_review', true))
            ->when($filter === 'unverified', fn (Builder $q) => $q->whereNull('crm_leads.verified_at'))
            ->when($filter === 'verified', fn (Builder $q) => $q->whereNotNull('crm_leads.verified_at'));
    }

    /**
     * The contact's other leads and matching customers.
     *
     * @return array{leads: Collection<int, Lead>, customers: Collection<int, Customer>}
     */
    public function duplicatesOf(Lead $lead): array
    {
        return [
            'leads'     => $this->duplicates->matchingLeads($lead->business_id, $lead->phone, $lead->email, $lead->id),
            'customers' => $this->duplicates->matchingCustomers($lead->business_id, $lead->phone, $lead->email),
        ];
    }

    /**
     * Departments a lead can be forwarded to: those with a pipeline, except the intake one.
     *
     * @return list<array{id:int,name:string,pipeline:array{id:int,name:string},divisions:list<array{id:int,name:string}>}>
     */
    public function forwardTargets(Business $business): array
    {
        return collect($this->org->departments($business))
            ->filter(fn (array $d) => $d['pipeline'] !== null && ! $d['settings']['is_marketing_intake'])
            ->map(fn (array $d) => [
                'id'        => $d['id'],
                'name'      => $d['name'],
                'pipeline'  => ['id' => $d['pipeline']['id'], 'name' => $d['pipeline']['name']],
                'divisions' => collect($d['divisions'])->where('is_active', true)
                    ->map(fn (array $v) => ['id' => $v['id'], 'name' => $v['name']])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** One inbox row for the desktop / JSON, with the per-lead actions the user may take. */
    public function present(Lead $lead, CrmAccess $access, bool $withDuplicates = false): array
    {
        $row = [
            'id'                    => $lead->id,
            'name'                  => $lead->name,
            'company'               => $lead->company,
            'email'                 => $lead->email,
            'phone'                 => $lead->phone,
            'normalized_phone'      => $lead->normalized_phone,
            'source'                => $lead->source,
            'source_label'          => Lead::sourceLabel($lead->source),
            'notes'                 => $lead->notes,
            'product_interest_note' => $lead->product_interest_note,
            'project'               => $lead->project ? ['id' => $lead->project->id, 'name' => $lead->project->name] : null,
            'stage'                 => $lead->stage ? ['id' => $lead->stage->id, 'name' => $lead->stage->name, 'color' => $lead->stage->color, 'system_key' => $lead->stage->system_key] : null,
            'in_intake'             => $lead->project_id === $this->intakeProjectId($lead->business_id),
            'requested_department'  => $lead->requestedDepartment ? ['id' => $lead->requestedDepartment->id, 'name' => $lead->requestedDepartment->name] : null,
            'customer'              => $lead->customer ? ['id' => $lead->customer->id, 'name' => $lead->customer->name] : null,
            'needs_review'          => (bool) $lead->needs_review,
            'review_reason'         => $lead->review_reason,
            'review_reason_label'   => Lead::reviewReasonLabel($lead->review_reason),
            'duplicate_of'          => $lead->duplicateOf ? $this->presentMatch($lead->duplicateOf) : null,
            'verified_at'           => $lead->verified_at?->toIso8601String(),
            'verified_by_name'      => $lead->verifiedBy?->name,
            'created_by_name'       => $lead->createdBy?->name,
            'created_at'            => $lead->created_at?->toIso8601String(),
            'can'                   => [
                'edit'    => $this->policy->canEdit($access, $lead),
                'forward' => $this->policy->canForward($access, $lead) && $lead->isOpen(),
                'review'  => $this->policy->canReview($access, $lead),
            ],
        ];

        if ($withDuplicates) {
            $found = $this->duplicatesOf($lead);
            $row['duplicates'] = [
                'leads'     => $found['leads']->map(fn (Lead $l) => $this->presentMatch($l))->values()->all(),
                'customers' => $found['customers']->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email])->values()->all(),
            ];
        }

        return $row;
    }

    /** @var array<int, ?int> business id => intake pipeline id */
    private array $intakeProjectIds = [];

    private function intakeProjectId(int $businessId): ?int
    {
        if (! array_key_exists($businessId, $this->intakeProjectIds)) {
            $business = Business::find($businessId);
            $this->intakeProjectIds[$businessId] = $business ? $this->intake->intakeProject($business)?->id : null;
        }

        return $this->intakeProjectIds[$businessId];
    }

    /** A matching lead, as much as Marketing needs to decide on a merge. */
    public function presentMatch(Lead $lead): array
    {
        return [
            'id'           => $lead->id,
            'name'         => $lead->name,
            'phone'        => $lead->phone,
            'email'        => $lead->email,
            'project_name' => $lead->project?->name,
            'stage_name'   => $lead->stage?->name,
            'is_open'      => $lead->isOpen(),
            'created_at'   => $lead->created_at?->toIso8601String(),
        ];
    }

    // ── Actions ──────────────────────────────────────────────────────────

    /**
     * Clean up the contact details. A changed phone or email that now matches another lead
     * flags this one as a possible duplicate.
     */
    public function update(Lead $lead, array $data, ?int $userId): Lead
    {
        $keep = fn (string $key) => array_key_exists($key, $data) ? $data[$key] : $lead->{$key};

        $changes = [
            'name'                  => $data['name'] ?? $lead->name,
            'company'               => $keep('company'),
            'email'                 => $this->duplicates->cleanEmail($keep('email')),
            'phone'                 => $keep('phone'),
            'notes'                 => $keep('notes'),
            'product_interest_note' => $keep('product_interest_note'),
            'score'                 => $keep('score'),
        ];
        // The source only changes when one is sent
        foreach (['source', 'source_id'] as $key) {
            if (filled($data[$key] ?? null)) {
                $changes[$key] = $data[$key];
            }
        }

        $lead = $this->leads->update($lead, $changes, $userId);

        if (! $lead->needs_review && ($match = $this->duplicates->matchingLeads($lead->business_id, $lead->phone, $lead->email, $lead->id)->first())) {
            $lead->update([
                'needs_review'         => true,
                'review_reason'        => Lead::REVIEW_POSSIBLE_DUPLICATE,
                'duplicate_of_lead_id' => $match->id,
            ]);
        }

        return $lead;
    }

    public function verify(Lead $lead, ?int $userId): Lead
    {
        if ($lead->verified_at === null) {
            $lead->update(['verified_at' => now(), 'verified_by' => $userId]);
            $this->audit->recordEvent($lead, LeadAuditLog::EVENT_VERIFIED, $userId);
        }

        return $lead;
    }

    /** Marketing has looked at the flag: keep the lead as it is. */
    public function clearReview(Lead $lead, ?int $userId): Lead
    {
        if ($lead->needs_review) {
            $reason = $lead->review_reason;
            $lead->update(['needs_review' => false, 'review_reason' => null, 'duplicate_of_lead_id' => null]);
            $this->audit->recordEvent($lead, LeadAuditLog::EVENT_REVIEWED, $userId, array_filter(['reason' => Lead::reviewReasonLabel($reason)]));
        }

        return $lead;
    }

    /**
     * Send the lead to a department's pipeline (stage "new"), optionally into one of its
     * divisions. Forwarding also verifies the lead and clears its review flag; the history
     * goes to crm_lead_assignment_logs.
     */
    public function forward(Lead $lead, int $departmentId, ?int $divisionId, ?string $note, ?int $userId): Lead
    {
        $lead->loadMissing(['stage', 'project']);
        if (! $lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => 'Only open leads can be forwarded.']);
        }

        $department = Department::query()->where('business_id', $lead->business_id)->find($departmentId);
        if ($department === null) {
            throw ValidationException::withMessages(['department_id' => 'Pick a department of this business.']);
        }

        $project = Project::query()
            ->where('business_id', $lead->business_id)
            ->where('department_id', $department->id)
            ->first();
        if ($project === null) {
            throw ValidationException::withMessages(['department_id' => $department->name . ' has no pipeline yet. Link one under CRM → Org Structure.']);
        }
        if ((int) $project->id === (int) $lead->project_id) {
            throw ValidationException::withMessages(['department_id' => 'The lead is already in the ' . $department->name . ' pipeline.']);
        }

        $division = null;
        if ($divisionId !== null) {
            $division = Division::query()
                ->where('business_id', $lead->business_id)
                ->where('department_id', $department->id)
                ->where('is_active', true)
                ->find($divisionId);
            if ($division === null) {
                throw ValidationException::withMessages(['division_id' => 'Pick an active division of ' . $department->name . '.']);
            }
        }

        $note = filled($note) ? mb_substr(trim($note), 0, 2000) : null;

        return DB::transaction(function () use ($lead, $department, $division, $project, $note, $userId) {
            LeadAssignmentLog::create([
                'business_id'        => $lead->business_id,
                'lead_id'            => $lead->id,
                'action'             => LeadAssignmentLog::ACTION_FORWARDED,
                'from_department_id' => $lead->department_id,
                'to_department_id'   => $department->id,
                'from_division_id'   => $lead->division_id,
                'to_division_id'     => $division?->id,
                'by_user_id'         => $userId,
                'note'               => $note,
            ]);

            // The receiving department's head picks who works it
            $this->assignments->clear($lead);

            $lead = $this->leads->moveToProject($lead, $project, $userId, [
                'division_id'          => $division?->id,
                'needs_review'         => false,
                'review_reason'        => null,
                'duplicate_of_lead_id' => null,
                'forwarded_at'         => now(),
                'forwarded_by'         => $userId,
                'verified_at'          => $lead->verified_at ?? now(),
                'verified_by'          => $lead->verified_at ? $lead->verified_by : $userId,
            ], array_filter([
                'to_department' => $department->name,
                'to_division'   => $division?->name,
                'note'          => $note,
            ]));

            if ($note !== null) {
                Activity::create([
                    'business_id'  => $lead->business_id,
                    'subject_type' => $lead->getMorphClass(),
                    'subject_id'   => $lead->id,
                    'type'         => Activity::TYPE_NOTE,
                    'body'         => 'Forwarded to ' . $department->name . ($division ? ' / ' . $division->name : '') . ': ' . $note,
                    'occurred_at'  => now(),
                    'created_by'   => $userId,
                ]);
            }

            return $lead;
        });
    }

    /**
     * Merge $source into $target and delete $source. Activities, tasks and assignment history
     * move to the target; details the target is missing are filled in from the source, its
     * notes appended, its products and custom field values added.
     */
    public function merge(Lead $source, Lead $target, ?int $userId): Lead
    {
        if ((int) $source->business_id !== (int) $target->business_id) {
            throw ValidationException::withMessages(['target_lead_id' => 'That lead belongs to another business.']);
        }
        if ((int) $source->id === (int) $target->id) {
            throw ValidationException::withMessages(['target_lead_id' => 'A lead cannot be merged into itself.']);
        }

        return DB::transaction(function () use ($source, $target, $userId) {
            $morph = $source->getMorphClass();

            Activity::query()->where('subject_type', $morph)->where('subject_id', $source->id)->update(['subject_id' => $target->id]);
            Task::query()->where('subject_type', $morph)->where('subject_id', $source->id)->update(['subject_id' => $target->id]);
            LeadAssignmentLog::query()->where('lead_id', $source->id)->update(['lead_id' => $target->id]);
            LeadMessage::query()->where('lead_id', $source->id)->update(['lead_id' => $target->id]);
            LeadAttachment::query()->where('lead_id', $source->id)->update(['lead_id' => $target->id]);

            $existingFields = LeadCustomFieldValue::query()->where('lead_id', $target->id)->pluck('custom_field_id');
            LeadCustomFieldValue::query()
                ->where('lead_id', $source->id)
                ->whereNotIn('custom_field_id', $existingFields)
                ->update(['lead_id' => $target->id]);

            $products = $source->products()->get()->mapWithKeys(fn ($p) => [$p->id => ['quantity' => $p->pivot->quantity]]);
            if ($products->isNotEmpty()) {
                $target->products()->syncWithoutDetaching($products->except($target->products()->pluck('products.id')->all())->all());
            }

            $blank = fn (string $key) => blank($target->{$key}) && filled($source->{$key}) ? $source->{$key} : $target->{$key};
            $notes = $target->notes;
            if (filled($source->notes) && trim((string) $source->notes) !== trim((string) $target->notes)) {
                $notes = trim(($notes ? $notes . "\n\n" : '') . '[From merged lead "' . $source->name . '"] ' . $source->notes);
            }

            $target = $this->leads->update($target, [
                'name'                  => $target->name,
                'company'               => $blank('company'),
                'email'                 => $blank('email'),
                'phone'                 => $blank('phone'),
                'notes'                 => $notes,
                'estimated_value'       => $blank('estimated_value'),
                'expected_close_date'   => $blank('expected_close_date'),
                'product_interest_note' => $blank('product_interest_note'),
                'score'                 => $blank('score'),
            ], $userId);

            $extra = array_filter([
                'customer_id' => $target->customer_id ?: $source->customer_id,
                'verified_at' => $target->verified_at ?? $source->verified_at,
                'verified_by' => $target->verified_at ? $target->verified_by : $source->verified_by,
            ]);
            // The flag that pointed at the target is settled by the merge
            if ((int) $target->duplicate_of_lead_id === (int) $source->id) {
                $extra += ['needs_review' => false, 'review_reason' => null, 'duplicate_of_lead_id' => null];
            }
            if ($extra !== []) {
                $target->update($extra);
            }

            Activity::create([
                'business_id'  => $target->business_id,
                'subject_type' => $morph,
                'subject_id'   => $target->id,
                'type'         => Activity::TYPE_NOTE,
                'body'         => 'Merged lead "' . $source->name . '" (#' . $source->id
                    . ($source->source ? ', ' . Lead::sourceLabel($source->source) : '')
                    . ', created ' . $source->created_at?->format('Y-m-d') . ') into this lead.',
                'occurred_at'  => now(),
                'created_by'   => $userId,
            ]);

            $this->audit->recordEvent($target, LeadAuditLog::EVENT_MERGED, $userId, [
                'merged_lead_id'   => $source->id,
                'merged_lead_name' => $source->name,
            ]);
            $this->leads->delete($source, $userId, ['merged_into' => $target->id]);

            return $target->fresh();
        });
    }
}
