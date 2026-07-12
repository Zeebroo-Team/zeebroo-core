<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\TaskService;
use Modules\Pos\Models\Customer;

class TaskController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $filter = (string) $request->query('filter', 'open');

        return view('crm::tasks.index', [
            'business'        => $business,
            'hasTasks'        => $this->taskService->businessHasTasks($business),
            'tasks'           => $this->taskService->listForBusiness($business, $filter),
            'filter'          => $filter,
            'filterTabs'      => ['open' => 'Open', 'overdue' => 'Overdue', 'completed' => 'Completed', 'all' => 'All'],
            'assignableUsers' => $this->assignableUsers($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validated($request);

        $subject = $this->resolveSubject($business, (string) $request->input('subject_type', ''), (int) $request->input('subject_id', 0));

        $this->taskService->create($business, $data, $subject, $request->user()?->id);

        return redirect()->back()->with('status', 'Task added.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->update($task, $this->validated($request));

        return redirect()->back()->with('status', 'Task updated.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->complete($task);

        return redirect()->back()->with('status', 'Task completed.');
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->reopen($task);

        return redirect()->back()->with('status', 'Task reopened.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->delete($task);

        return redirect()->back()->with('status', 'Task removed.');
    }

    private function requireTask(Request $request, Task $task): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->taskService->taskForBusiness($business, $task) instanceof Task, 404);

        return $business;
    }

    private function resolveSubject(Business $business, string $type, int $id): Lead|Customer|null
    {
        if ($type === 'lead' && $id > 0) {
            $lead = Lead::find($id);
            return $lead && (int) $lead->business_id === (int) $business->id ? $lead : null;
        }

        if ($type === 'customer' && $id > 0) {
            $customer = Customer::find($id);
            return $customer && (int) $customer->business_id === (int) $business->id ? $customer : null;
        }

        return null;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at'      => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }
}
