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
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;

class LeadFormController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadFormService $forms,
        private readonly LeadCustomFieldService $customFieldService,
    ) {}

    public function index(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::leads.forms.index', [
            'business'  => $business,
            'project'   => $project,
            'forms'     => $this->forms->listForProject($project),
            'templates' => $this->forms->templateChoices(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($this->forms->listForProject($project)->isNotEmpty()) {
            return redirect()->route('crm.projects.forms.index', $project)
                ->withErrors(['name' => 'This project already has a lead form — only one form is allowed per project.']);
        }

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('crm_lead_forms', 'name')->where(fn ($q) => $q->where('project_id', $project->id)),
            ],
            'template' => ['nullable', 'string', Rule::in(LeadFormService::templateKeys())],
        ]);

        $form = $this->forms->create($project, $data);

        return redirect()->route('crm.projects.forms.builder', [$project, $form])->with('status', 'Form created.');
    }

    public function builder(Request $request, Project $project, LeadForm $form): View|RedirectResponse
    {
        $business = $this->requireForm($request, $project, $form);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::leads.forms.builder', [
            'business'     => $business,
            'project'      => $project,
            'form'         => $form,
            'customFields' => $this->customFieldService->listForProject($project),
            'style'        => $form->styleSettings(),
        ]);
    }

    public function update(Request $request, Project $project, LeadForm $form): JsonResponse
    {
        $business = $this->requireForm($request, $project, $form);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $data = $request->validate([
            'name'                     => ['nullable', 'string', 'max:150'],
            'blocks'                   => ['nullable', 'array'],
            'style'                    => ['nullable', 'array'],
            'style.layout'             => ['nullable', 'string', Rule::in(array_keys(LeadForm::layouts()))],
            'style.accent_color'       => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.background_color'  => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'submit_button_text'       => ['nullable', 'string', 'max:60'],
            'success_message'          => ['nullable', 'string', 'max:1000'],
        ]);

        $this->forms->update($form, $data);

        return response()->json(['success' => true]);
    }

    public function publish(Request $request, Project $project, LeadForm $form): RedirectResponse
    {
        $business = $this->requireForm($request, $project, $form);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->forms->publish($form);

        return redirect()->route('crm.projects.forms.builder', [$project, $form])->with('status', 'Form published.');
    }

    public function unpublish(Request $request, Project $project, LeadForm $form): RedirectResponse
    {
        $business = $this->requireForm($request, $project, $form);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->forms->unpublish($form);

        return redirect()->route('crm.projects.forms.builder', [$project, $form])->with('status', 'Form unpublished.');
    }

    public function destroy(Request $request, Project $project, LeadForm $form): RedirectResponse
    {
        $business = $this->requireForm($request, $project, $form);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->forms->delete($form);

        return redirect()->route('crm.projects.forms.index', $project)->with('status', 'Form deleted.');
    }

    private function requireForm(Request $request, Project $project, LeadForm $form): Business|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->forms->formForProject($project, $form) instanceof LeadForm, 404);

        return $business;
    }
}
