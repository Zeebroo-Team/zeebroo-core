<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Task;
use Modules\Pos\Models\Customer;

class ContactController extends Controller
{
    use ResolvesCrmBusiness;

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

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
            ->selectRaw('subject_id, max(occurred_at) as last_activity_at')
            ->groupBy('subject_id')
            ->pluck('last_activity_at', 'subject_id');

        return view('crm::contacts.index', [
            'business'       => $business,
            'customers'      => $customers,
            'openTaskCounts' => $openTaskCounts,
            'lastActivityAt' => $lastActivityAt,
            'search'         => $search,
        ]);
    }

    public function show(Request $request, Customer $customer): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $customer->business_id === (int) $business->id, 404);

        $customer->setRelation('activities', Activity::query()
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->with('createdBy')
            ->orderByDesc('occurred_at')
            ->get());

        $customer->setRelation('tasks', Task::query()
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->with('assignedTo')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get());

        return view('crm::contacts.show', [
            'business'        => $business,
            'customer'        => $customer,
            'assignableUsers' => $this->assignableUsers($business),
        ]);
    }
}
