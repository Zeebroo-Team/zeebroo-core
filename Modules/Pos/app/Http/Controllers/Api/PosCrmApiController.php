<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageAutomation;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadStageAutomationService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\ProjectService;
use Modules\CRM\Services\TaskService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;

class PosCrmApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProjectService              $projects,
        private readonly LeadService                 $leads,
        private readonly LeadStageService            $stages,
        private readonly TaskService                 $tasks,
        private readonly LeadStageAutomationService  $automations,
        private readonly LeadFormService             $forms,
        private readonly LeadCustomFieldService      $customFields,
    ) {}

    // ── Projects ─────────────────────────────────────────────────────────

    public function projects(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $rows = Project::query()
            ->where('business_id', $business->id)
            ->where('status', Project::STATUS_ACTIVE)
            ->withCount('leads')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status']);

        return response()->json(['data' => $rows]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = $this->projects->create($business, $validated);

        return response()->json(['data' => $project], 201);
    }

    // ── Pipeline ─────────────────────────────────────────────────────────

    public function pipeline(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $project = Project::where('business_id', $business->id)->findOrFail($projectId);

        $allStages = $this->stages->listForProject($project);

        $leads = Lead::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get(['id', 'stage_id', 'name', 'company', 'email', 'phone', 'estimated_value', 'expected_close_date', 'notes']);

        $groupedLeads = $leads->groupBy('stage_id');

        $columns = $allStages->map(function (LeadStage $stage) use ($groupedLeads) {
            $stageLeads = $groupedLeads->get($stage->id, collect());
            return [
                'id'         => $stage->id,
                'name'       => $stage->name,
                'color'      => $stage->color,
                'is_won'     => $stage->is_won,
                'is_lost'    => $stage->is_lost,
                'leads_count'  => $stageLeads->count(),
                'value_total'  => (float) $stageLeads->sum('estimated_value'),
                'leads'      => $stageLeads->values(),
            ];
        });

        return response()->json([
            'data' => [
                'project' => ['id' => $project->id, 'name' => $project->name],
                'columns' => $columns,
                'stages'  => $allStages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color]),
            ],
        ]);
    }

    // ── Leads ─────────────────────────────────────────────────────────────

    public function createLead(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $project = Project::where('business_id', $business->id)->findOrFail($projectId);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'company'             => ['nullable', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'stage_id'            => ['nullable', 'integer'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->leads->create($project, $validated, $request->user()?->id);

        return response()->json(['data' => $lead->load('stage')], 201);
    }

    public function updateLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'company'             => ['nullable', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'stage_id'            => ['nullable', 'integer'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->leads->update($lead, $validated, $request->user()?->id);

        return response()->json(['data' => $lead->load('stage')]);
    }

    public function moveLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);

        $validated = $request->validate([
            'stage_id' => ['required', 'integer', 'exists:crm_lead_stages,id'],
        ]);

        // Ensure stage belongs to the same project
        $stage = LeadStage::where('project_id', $lead->project_id)->findOrFail($validated['stage_id']);

        $lead = $this->leads->moveStage($lead, $stage->id, $request->user()?->id);

        return response()->json(['data' => $lead->load('stage')]);
    }

    public function deleteLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);

        $this->leads->delete($lead);

        return response()->json(['message' => 'Lead deleted.']);
    }

    // ── Contacts ──────────────────────────────────────────────────────────

    public function contacts(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $customerIds = $customers->pluck('id');

        $openTaskCounts = Task::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Customer::class)
            ->whereIn('subject_id', $customerIds)
            ->where('status', Task::STATUS_PENDING)
            ->selectRaw('subject_id, count(*) as open_tasks')
            ->groupBy('subject_id')
            ->pluck('open_tasks', 'subject_id');

        $lastActivityAt = Activity::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Customer::class)
            ->whereIn('subject_id', $customerIds)
            ->selectRaw('subject_id, max(occurred_at) as last_at')
            ->groupBy('subject_id')
            ->pluck('last_at', 'subject_id');

        $data = $customers->map(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'phone'         => $c->phone,
            'email'         => $c->email,
            'open_tasks'    => (int) ($openTaskCounts[$c->id] ?? 0),
            'last_activity' => $lastActivityAt[$c->id] ?? null,
        ]);

        return response()->json(['data' => $data]);
    }

    // ── Tasks ─────────────────────────────────────────────────────────────

    public function tasksList(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filter = in_array($request->query('filter'), ['open', 'overdue', 'completed'], true)
            ? $request->query('filter')
            : 'open';

        $taskRows = $this->tasks->listForBusiness($business, $filter);

        $data = $taskRows->map(fn (Task $t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'description' => $t->description,
            'due_at'      => $t->due_at?->toIso8601String(),
            'status'      => $t->status,
            'is_overdue'  => $t->isOverdue(),
            'subject_type' => $t->subject_type,
            'subject_id'   => $t->subject_id,
            'subject_label' => $this->taskSubjectLabel($t),
        ]);

        return response()->json(['data' => $data]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $task = $this->tasks->create($business, $validated, null, $request->user()?->id);

        return response()->json(['data' => $task], 201);
    }

    public function completeTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);

        $this->tasks->complete($task);

        return response()->json(['message' => 'Task completed.']);
    }

    public function reopenTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);

        $this->tasks->reopen($task);

        return response()->json(['message' => 'Task reopened.']);
    }

    public function deleteTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);

        $this->tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }

    // ── Stages ───────────────────────────────────────────────────────────

    public function stages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $stages = $this->stages->listForProject($project)->loadCount('leads');

        return response()->json(['data' => $stages->map(fn ($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'color'       => $s->color,
            'is_won'      => (bool) $s->is_won,
            'is_lost'     => (bool) $s->is_lost,
            'sort_order'  => $s->sort_order,
            'leads_count' => (int) $s->leads_count,
        ])]);
    }

    public function createStage(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:60'],
            'color'   => ['nullable', 'string', 'max:20'],
            'is_won'  => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ]);

        $stage = $this->stages->create($project, $validated);

        return response()->json(['data' => $stage], 201);
    }

    public function updateStage(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:60'],
            'color'   => ['nullable', 'string', 'max:20'],
            'is_won'  => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ]);

        $stage = $this->stages->update($stage, $validated);

        return response()->json(['data' => $stage]);
    }

    public function deleteStage(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);

        try {
            $this->stages->delete($stage);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['stage'][0] ?? 'Cannot delete stage.'], 422);
        }

        return response()->json(['message' => 'Stage deleted.']);
    }

    public function reorderStages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $this->stages->reorder($project, $ids);

        return response()->json(['message' => 'Reordered.']);
    }

    // ── Stage Automations ─────────────────────────────────────────────────

    public function automations(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);

        $rows = $this->automations->listForStage($stage);

        return response()->json(['data' => $rows->map(fn ($a) => [
            'id'              => $a->id,
            'is_active'       => (bool) $a->is_active,
            'recipient_type'  => $a->recipient_type,
            'recipient_email' => $a->recipient_email,
            'recipient_label' => $a->recipientLabel(),
            'subject'         => $a->subject,
            'body'            => $a->body,
        ])]);
    }

    public function createAutomation(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);

        $validated = $this->validateAutomation($request);

        $automation = $this->automations->create($project, $stage, $validated);

        return response()->json(['data' => $automation], 201);
    }

    public function updateAutomation(Request $request, int $projectId, int $stageId, int $automationId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $project    = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage      = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $automation = LeadStageAutomation::where('stage_id', $stage->id)->findOrFail($automationId);

        $validated = $this->validateAutomation($request);

        $automation = $this->automations->update($automation, $validated);

        return response()->json(['data' => $automation]);
    }

    public function deleteAutomation(Request $request, int $projectId, int $stageId, int $automationId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $project    = Project::where('business_id', $business->id)->findOrFail($projectId);
        $stage      = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $automation = LeadStageAutomation::where('stage_id', $stage->id)->findOrFail($automationId);

        $this->automations->delete($automation);

        return response()->json(['message' => 'Automation deleted.']);
    }

    private function validateAutomation(Request $request): array
    {
        $data = $request->validate([
            'recipient_type'  => ['required', 'string', Rule::in(array_keys(LeadStageAutomation::recipientTypes()))],
            'recipient_email' => ['nullable', 'email', 'max:190'],
            'subject'         => ['required', 'string', 'max:200'],
            'body'            => ['required', 'string', 'max:5000'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        if ($data['recipient_type'] === LeadStageAutomation::RECIPIENT_CUSTOM && !filled($data['recipient_email'] ?? '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recipient_email' => ['Enter an email address for a custom recipient.'],
            ]);
        }

        return $data;
    }

    // ── Forms ─────────────────────────────────────────────────────────────

    public function forms(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $rows = $this->forms->listForProject($project);

        return response()->json(['data' => $rows->map(fn (LeadForm $f) => [
            'id'                 => $f->id,
            'name'               => $f->name,
            'is_published'       => $f->is_published,
            'public_url'         => $f->publicUrl(),
            'submit_button_text' => $f->submit_button_text,
            'success_message'    => $f->success_message,
            'blocks_count'       => count($f->blocks ?? []),
        ])]);
    }

    public function createForm(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'template' => ['nullable', 'string', Rule::in(LeadFormService::templateKeys())],
        ]);

        $form = $this->forms->create($project, $validated);

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'is_published'       => $form->is_published,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks,
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
        ]], 201);
    }

    public function getForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $customFields = $this->customFields->listForProject($project);

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'is_published'       => $form->is_published,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks ?? [],
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
            'custom_fields'      => $customFields->map(fn ($cf) => [
                'id'    => $cf->id,
                'name'  => $cf->name,
                'type'  => $cf->type,
            ]),
            'templates'          => $this->forms->templateChoices(),
        ]]);
    }

    public function updateForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $validated = $request->validate([
            'name'               => ['nullable', 'string', 'max:255'],
            'blocks'             => ['nullable', 'array'],
            'style'              => ['nullable', 'array'],
            'submit_button_text' => ['nullable', 'string', 'max:60'],
            'success_message'    => ['nullable', 'string', 'max:500'],
        ]);

        $form = $this->forms->update($form, $validated);

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'is_published'       => $form->is_published,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks ?? [],
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
        ]]);
    }

    public function publishForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->publish($form);

        return response()->json(['message' => 'Form published.', 'is_published' => true]);
    }

    public function unpublishForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->unpublish($form);

        return response()->json(['message' => 'Form unpublished.', 'is_published' => false]);
    }

    public function deleteForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->delete($form);

        return response()->json(['message' => 'Form deleted.']);
    }

    public function formTemplates(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->forms->templateChoices()]);
    }

    // ── Custom Fields ─────────────────────────────────────────────────────

    public function customFieldsList(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $fields = $this->customFields->listForProject($project);

        return response()->json(['data' => $fields->map(fn ($cf) => [
            'id'         => $cf->id,
            'name'       => $cf->name,
            'type'       => $cf->type,
            'sort_order' => $cf->sort_order,
        ])]);
    }

    public function createCustomField(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->findOrFail($projectId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', Rule::in(LeadCustomField::types())],
        ]);

        $field = $this->customFields->create($project, $validated);

        return response()->json(['data' => [
            'id'   => $field->id,
            'name' => $field->name,
            'type' => $field->type,
        ]], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function taskSubjectLabel(Task $task): ?string
    {
        if ($task->subject_type === null) {
            return null;
        }

        if ($task->subject_type === Customer::class || str_ends_with($task->subject_type, 'Customer')) {
            return $task->subject instanceof Customer ? $task->subject->name : null;
        }

        if ($task->subject_type === Lead::class || str_ends_with($task->subject_type, 'Lead')) {
            return $task->subject instanceof Lead ? $task->subject->name : null;
        }

        return null;
    }
}
