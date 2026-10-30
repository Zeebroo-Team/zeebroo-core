<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\LeadSource;
use Modules\CRM\Models\Project;
use Modules\Product\Models\Product;

/**
 * The one way a new lead enters the CRM — manual entry (web + desktop), web forms, CSV and,
 * later, Google Forms / Facebook / WhatsApp. It cleans the contact details, checks for a
 * duplicate and puts the lead in the marketing intake pipeline at status "new".
 *
 * Duplicates (same normalised phone or same email in the business):
 * - an open lead exists → no new lead; the enquiry is logged on that lead as an activity and
 *   the lead is flagged "needs review" for Marketing;
 * - only closed (won / lost) leads exist → a new lead is created, flagged as a possible
 *   duplicate of the latest one, so Marketing can merge it or keep it as a new opportunity;
 * - only a customer matches → a new lead linked to that customer, flagged for review.
 *
 * Leads entered for a pipeline no department owns stay in it, as does everything while the
 * business has no marketing intake department with a pipeline. Supplier and employee form
 * submissions (LeadForm::NON_SALES_TYPES) are records, not enquiries: they are created in
 * their own pipeline without a duplicate check.
 */
class LeadIntakeService
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly DuplicateLeadService $duplicates,
        private readonly CrmOrgService $org,
        private readonly LeadAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data       lead fields as LeadService::create() takes them
     * @param  string                $sourceKey  a crm_lead_sources key (manual, web_form, csv, …) or a Lead::SOURCES key
     * @param  Business|Project      $into       the business, or the pipeline the lead was entered in
     */
    public function capture(array $data, string $sourceKey, Business|Project $into, ?int $userId = null): IntakeResult
    {
        $target   = $into instanceof Project ? $into : null;
        $business = $into instanceof Business ? $into : $into->business;

        $data = $this->clean($data);
        $data['source'] = LeadSource::LEAD_SOURCE_ALIASES[$sourceKey] ?? $sourceKey;

        if ($target !== null && $this->isNonSalesForm($target, $data['form_id'] ?? null)) {
            return new IntakeResult($this->leads->create($target, $data, $userId), created: true);
        }

        return DB::transaction(function () use ($data, $business, $target, $userId) {
            $matches = $this->duplicates->matchingLeads($business->id, $data['phone'], $data['email']);

            $open = $matches->first(fn (Lead $lead) => $lead->isOpen());
            if ($open !== null) {
                return new IntakeResult($this->logRepeatEnquiry($open, $data, $userId), created: false);
            }

            $project = $this->pipelineFor($business, $target, $data);
            $routed  = $target === null || $project->id !== $target->id;

            if ($routed) {
                // Stage, assignee and division belong to the pipeline it was entered in; the
                // department that receives it after Marketing forwards it decides those.
                unset($data['stage_id'], $data['assigned_to'], $data['division_id']);
                if ($target?->department_id !== null && (int) $target->department_id !== (int) $project->department_id) {
                    $data['requested_department_id'] = $target->department_id;
                }
            }

            if ($closed = $matches->first()) {
                $data['needs_review']         = true;
                $data['review_reason']        = Lead::REVIEW_POSSIBLE_DUPLICATE;
                $data['duplicate_of_lead_id'] = $closed->id;
                $data['customer_id']          = $closed->customer_id;
            } elseif ($customer = $this->duplicates->matchingCustomers($business->id, $data['phone'], $data['email'])->first()) {
                $data['needs_review']  = true;
                $data['review_reason'] = Lead::REVIEW_EXISTING_CUSTOMER;
                $data['customer_id']   = $customer->id;
            }

            return new IntakeResult($this->leads->create($project, $data, $userId), created: true, routedToIntake: $routed && $target !== null);
        });
    }

    /** The marketing intake department's pipeline, if the business has one. */
    public function intakeProject(Business $business): ?Project
    {
        $department = $this->org->marketingIntakeDepartment($business);

        return $department === null ? null : Project::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->first();
    }

    private function pipelineFor(Business $business, ?Project $target, array $data): Project
    {
        if ($target !== null && $target->department_id === null) {
            return $target;
        }

        $intake = $this->intakeProject($business);
        if ($intake !== null) {
            return $intake;
        }
        if ($target !== null) {
            return $target;
        }

        throw ValidationException::withMessages([
            'project_id' => 'Mark a department as the marketing intake and link a pipeline to it first.',
        ]);
    }

    private function isNonSalesForm(Project $target, mixed $formId): bool
    {
        return filled($formId) && LeadForm::query()
            ->where('project_id', $target->id)
            ->whereKey((int) $formId)
            ->whereIn('type', LeadForm::NON_SALES_TYPES)
            ->exists();
    }

    /** Log the enquiry on the contact's existing lead and flag it for Marketing. */
    private function logRepeatEnquiry(Lead $lead, array $data, ?int $userId): Lead
    {
        Activity::create([
            'business_id'  => $lead->business_id,
            'subject_type' => $lead->getMorphClass(),
            'subject_id'   => $lead->id,
            'type'         => Activity::TYPE_ENQUIRY,
            'body'         => $this->enquirySummary($data),
            'occurred_at'  => now(),
            'created_by'   => $userId,
        ]);

        $lead->update(['needs_review' => true, 'review_reason' => Lead::REVIEW_DUPLICATE_ENQUIRY]);

        $this->audit->recordEvent($lead, LeadAuditLog::EVENT_ENQUIRY, $userId, array_filter([
            'lead_source' => $data['source'],
            'name'        => $data['name'],
            'phone'       => $data['phone'],
            'email'       => $data['email'],
        ]));

        return $lead;
    }

    private function enquirySummary(array $data): string
    {
        $lines = ['New enquiry via ' . (Lead::sourceLabel($data['source']) ?? 'unknown source') . '.'];

        foreach (['name' => 'Name', 'company' => 'Company', 'phone' => 'Phone', 'email' => 'Email'] as $key => $label) {
            if (filled($data[$key] ?? null)) {
                $lines[] = $label . ': ' . $data[$key];
            }
        }

        $productIds = collect($data['products'] ?? [])
            ->map(fn ($item) => (int) (is_array($item) ? ($item['product_id'] ?? $item['id'] ?? 0) : $item))
            ->filter();
        if ($productIds->isNotEmpty()) {
            $names = Product::query()->whereIn('id', $productIds)->pluck('name');
            if ($names->isNotEmpty()) {
                $lines[] = 'Products: ' . $names->implode(', ');
            }
        }

        if (filled($data['product_interest_note'] ?? null)) {
            $lines[] = 'Interest: ' . $data['product_interest_note'];
        }

        $custom = array_filter((array) ($data['custom_fields'] ?? []), fn ($v) => filled($v));
        if ($custom !== []) {
            $labels = LeadCustomField::query()->whereIn('id', array_keys($custom))->pluck('label', 'id');
            foreach ($custom as $fieldId => $value) {
                $lines[] = ($labels[$fieldId] ?? 'Field') . ': ' . (is_array($value) ? implode(', ', $value) : $value);
            }
        }

        if (filled($data['notes'] ?? null)) {
            $lines[] = 'Notes: ' . $data['notes'];
        }

        return mb_substr(implode("\n", $lines), 0, 5000);
    }

    /** Trimmed contact details; a blank value becomes null, the email lower-case. */
    private function clean(array $data): array
    {
        foreach (['name', 'company', 'phone', 'email', 'notes'] as $key) {
            $value      = trim((string) ($data[$key] ?? ''));
            $data[$key] = $value === '' ? null : $value;
        }

        $data['email'] = $this->duplicates->cleanEmail($data['email']);
        $data['name']  = $data['name'] ?? $data['company'] ?? $data['email'] ?? $data['phone'] ?? 'Unnamed lead';

        return $data;
    }
}
