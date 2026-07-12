<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\ActivityService;
use Modules\Pos\Models\Customer;

class ActivityController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'subject_type' => ['required', 'in:lead,customer'],
            'subject_id'   => ['required', 'integer'],
            'type'         => ['required', 'in:' . implode(',', array_keys(Activity::types()))],
            'body'         => ['nullable', 'string', 'max:5000'],
            'occurred_at'  => ['nullable', 'date'],
        ]);

        $subject = $this->resolveSubject($business, $data['subject_type'], (int) $data['subject_id']);
        abort_unless($subject, 404);

        $this->activityService->create($business, $subject, $data, $request->user()?->id);

        return redirect()->route($data['subject_type'] === 'lead' ? 'crm.leads.show' : 'crm.contacts.show', $subject)
            ->with('status', 'Activity logged.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->activityService->activityForBusiness($business, $activity) instanceof Activity, 404);

        $subject = $activity->subject;
        $subjectType = $activity->subject_type === Customer::class ? 'customer' : 'lead';

        $this->activityService->delete($activity);

        return redirect()->route($subjectType === 'lead' ? 'crm.leads.show' : 'crm.contacts.show', $subject)
            ->with('status', 'Activity removed.');
    }

    private function resolveSubject(\Modules\Business\Models\Business $business, string $type, int $id): Lead|Customer|null
    {
        if ($type === 'lead') {
            $lead = Lead::find($id);
            return $lead && (int) $lead->business_id === (int) $business->id ? $lead : null;
        }

        $customer = Customer::find($id);
        return $customer && (int) $customer->business_id === (int) $business->id ? $customer : null;
    }
}
