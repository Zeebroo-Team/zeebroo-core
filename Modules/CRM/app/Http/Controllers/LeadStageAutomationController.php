<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageAutomation;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadStageAutomationService;
use Modules\CRM\Services\LeadStageService;

class LeadStageAutomationController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadStageAutomationService $automations,
        private readonly LeadStageService $stages,
    ) {}

    public function index(Request $request, Project $project, LeadStage $stage): View|RedirectResponse
    {
        $business = $this->requireStage($request, $project, $stage);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::leads.stages.automations', [
            'business'       => $business,
            'project'        => $project,
            'stage'          => $stage,
            'automations'    => $this->automations->listForStage($stage),
            'recipientTypes' => LeadStageAutomation::recipientTypes(),
        ]);
    }

    public function store(Request $request, Project $project, LeadStage $stage): RedirectResponse
    {
        $business = $this->requireStage($request, $project, $stage);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->automations->create($project, $stage, $this->validated($request));

        return redirect()->route('crm.projects.stages.automations.index', [$project, $stage])->with('status', 'Automation added.');
    }

    public function update(Request $request, Project $project, LeadStage $stage, LeadStageAutomation $automation): RedirectResponse
    {
        $business = $this->requireAutomation($request, $project, $stage, $automation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->automations->update($automation, $this->validated($request));

        return redirect()->route('crm.projects.stages.automations.index', [$project, $stage])->with('status', 'Automation updated.');
    }

    public function destroy(Request $request, Project $project, LeadStage $stage, LeadStageAutomation $automation): RedirectResponse
    {
        $business = $this->requireAutomation($request, $project, $stage, $automation);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->automations->delete($automation);

        return redirect()->route('crm.projects.stages.automations.index', [$project, $stage])->with('status', 'Automation removed.');
    }

    private function requireStage(Request $request, Project $project, LeadStage $stage): Business|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->stages->stageForProject($project, $stage) instanceof LeadStage, 404);

        return $business;
    }

    private function requireAutomation(Request $request, Project $project, LeadStage $stage, LeadStageAutomation $automation): Business|RedirectResponse
    {
        $business = $this->requireStage($request, $project, $stage);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->automations->automationForStage($stage, $automation) instanceof LeadStageAutomation, 404);

        return $business;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'recipient_type'  => ['required', 'string', Rule::in(array_keys(LeadStageAutomation::recipientTypes()))],
            'recipient_email' => ['nullable', 'email', 'max:190'],
            'subject'         => ['required', 'string', 'max:200'],
            'body'            => ['required', 'string', 'max:5000'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        if ($data['recipient_type'] === LeadStageAutomation::RECIPIENT_CUSTOM && !filled($data['recipient_email'] ?? '')) {
            throw ValidationException::withMessages(['recipient_email' => 'Enter an email address for a custom recipient.']);
        }

        return $data;
    }
}
