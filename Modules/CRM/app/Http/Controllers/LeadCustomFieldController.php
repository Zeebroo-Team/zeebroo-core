<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadCustomFieldService;

class LeadCustomFieldController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadCustomFieldService $fields,
    ) {}

    public function index(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::leads.custom-fields.index', [
            'business' => $business,
            'project'  => $project,
            'fields'   => $this->fields->listForProject($project),
            'types'    => LeadCustomField::types(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            if ($request->wantsJson()) {
                abort(403);
            }

            return $business;
        }

        $field = $this->fields->create($project, $this->validated($request, $project));

        // Used both by the classic Custom Fields admin page (redirect) and by the
        // form builder's inline "+ Add new field…" flow (JSON, created without
        // leaving the builder).
        if ($request->wantsJson()) {
            return response()->json([
                'id'      => $field->id,
                'label'   => $field->label,
                'type'    => $field->type,
                'options' => $field->optionList(),
            ]);
        }

        return redirect()->route('crm.projects.custom-fields.index', $project)->with('status', 'Field added.');
    }

    public function update(Request $request, Project $project, LeadCustomField $customField): RedirectResponse
    {
        $business = $this->requireField($request, $project, $customField);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->fields->update($customField, $this->validated($request, $project, $customField));

        return redirect()->route('crm.projects.custom-fields.index', $project)->with('status', 'Field updated.');
    }

    public function destroy(Request $request, Project $project, LeadCustomField $customField): RedirectResponse
    {
        $business = $this->requireField($request, $project, $customField);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->fields->delete($customField);

        return redirect()->route('crm.projects.custom-fields.index', $project)->with('status', 'Field removed.');
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            abort(403);
        }

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $this->fields->reorder($project, $ids);

        return response()->json(['success' => true]);
    }

    private function requireField(Request $request, Project $project, LeadCustomField $field): Business|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->fields->fieldForProject($project, $field) instanceof LeadCustomField, 404);

        return $business;
    }

    private function validated(Request $request, Project $project, ?LeadCustomField $field = null): array
    {
        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:100',
                Rule::unique('crm_lead_custom_fields', 'label')
                    ->where(fn ($q) => $q->where('project_id', $project->id))
                    ->ignore($field?->id),
            ],
            'type'        => ['required', 'string', Rule::in(array_keys(LeadCustomField::types()))],
            'options'     => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === LeadCustomField::TYPE_SELECT && !filled($data['options'] ?? '')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Add at least one option for a dropdown field, one per line.']);
        }

        return $data;
    }
}
