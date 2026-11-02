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
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\MarketingInboxService;

/** The marketing inbox: verify, clean up, forward and de-duplicate new leads. */
class MarketingInboxController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly MarketingInboxService $inbox,
        private readonly LeadIntakeService $intake,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        abort_unless($access->usesInbox(), 403);

        $filter = array_key_exists((string) $request->query('filter'), MarketingInboxService::FILTERS) ? (string) $request->query('filter') : 'all';
        $search = trim((string) $request->query('q', ''));

        $leads = $this->inbox->list($business, $access, $filter, $search);

        return view('crm::inbox.index', [
            'business'      => $business,
            'leads'         => $leads,
            'rows'          => $leads->map(fn (Lead $lead) => $this->inbox->present($lead, $access, withDuplicates: (bool) $lead->needs_review))->keyBy('id'),
            'filter'        => $filter,
            'filters'       => MarketingInboxService::FILTERS,
            'counts'        => $this->inbox->counts($business, $access),
            'search'        => $search,
            'targets'       => $this->inbox->forwardTargets($business),
            'intakeProject' => $this->intake->intakeProject($business),
            'sources'       => Lead::sourceOptions(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireInboxLead($request, $lead, 'canEdit');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'company'               => ['nullable', 'string', 'max:255'],
            'email'                 => ['nullable', 'email', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'source'                => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
            'notes'                 => ['nullable', 'string', 'max:5000'],
            'product_interest_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $this->inbox->update($lead, $data, $request->user()?->id), 'Lead details saved.');
    }

    public function verify(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireInboxLead($request, $lead, 'canEdit');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->inbox->verify($lead, $request->user()?->id);

        return $this->back('Lead "' . $lead->name . '" verified.');
    }

    public function review(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireInboxLead($request, $lead, 'canReview');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->inbox->clearReview($lead, $request->user()?->id);

        return $this->back('Review flag cleared for "' . $lead->name . '".');
    }

    public function forward(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireInboxLead($request, $lead, 'canForward');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'division_id'   => ['nullable', 'integer'],
            'note'          => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $this->inbox->forward(
            $lead,
            (int) $data['department_id'],
            isset($data['division_id']) ? (int) $data['division_id'] : null,
            $data['note'] ?? null,
            $request->user()?->id,
        ), 'Lead "' . $lead->name . '" forwarded.');
    }

    public function merge(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireInboxLead($request, $lead, 'canReview');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data   = $request->validate(['target_lead_id' => ['required', 'integer']]);
        $target = Lead::query()->where('business_id', $business->id)->find($data['target_lead_id']);
        if ($target === null) {
            return $this->back(null, ['target_lead_id' => 'That lead was not found.']);
        }

        return $this->attempt(fn () => $this->inbox->merge($lead, $target, $request->user()?->id), 'Lead merged into "' . $target->name . '".');
    }

    /** A lead of the current business the user may see in the inbox (404) and act on with $ability (403). */
    private function requireInboxLead(Request $request, Lead $lead, string $ability): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $lead->business_id === (int) $business->id, 404);

        $access = $this->crmAccess($request, $business);
        abort_unless($access->usesInbox() && $this->leadPolicy()->canView($access, $lead), 404);
        abort_unless($this->leadPolicy()->{$ability}($access, $lead), 403);

        return $business;
    }

    private function attempt(callable $action, string $status): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->back(null, $e->errors());
        }

        return $this->back($status);
    }

    private function back(?string $status, array $errors = []): RedirectResponse
    {
        $redirect = redirect()->route('crm.inbox.index', request()->only(['filter', 'q']));

        return $errors !== [] ? $redirect->withErrors($errors)->withInput() : $redirect->with('status', $status);
    }
}
