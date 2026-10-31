<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Task;

class TaskService
{
    /** With an access, tasks on leads the user cannot see are left out; other tasks stay. */
    public function listForBusiness(Business $business, string $filter = 'open', ?CrmAccess $access = null): Collection
    {
        return $this->filtered($business, $filter, $access)
            ->with(['assignedTo', 'subject'])
            ->orderByRaw('due_at is null')->orderBy('due_at')->orderByDesc('id')
            ->get();
    }

    /** How many tasks listForBusiness() would return, without loading them. */
    public function countForBusiness(Business $business, string $filter = 'open', ?CrmAccess $access = null): int
    {
        return $this->filtered($business, $filter, $access)->count();
    }

    private function filtered(Business $business, string $filter, ?CrmAccess $access): Builder
    {
        $query = Task::query()->where('business_id', $business->id);

        if ($access !== null) {
            $leadType = (new Lead)->getMorphClass();
            $query->where(fn ($q) => $q->whereNull('subject_type')
                ->orWhere('subject_type', '!=', $leadType)
                ->orWhereIn('subject_id', Lead::query()->visibleWith($access)->select('crm_leads.id')));
        }

        match ($filter) {
            'overdue'   => $query->where('status', Task::STATUS_PENDING)->whereNotNull('due_at')->where('due_at', '<', now()),
            'completed' => $query->where('status', Task::STATUS_COMPLETED),
            'open'      => $query->where('status', Task::STATUS_PENDING),
            default     => null,
        };

        return $query;
    }

    public function businessHasTasks(Business $business): bool
    {
        return Task::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data, ?Model $subject, ?int $userId): Task
    {
        return Task::create([
            'business_id'  => $business->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'title'        => $data['title'],
            'description'  => filled($data['description'] ?? '') ? $data['description'] : null,
            'due_at'       => filled($data['due_at'] ?? '') ? $data['due_at'] : null,
            'status'       => Task::STATUS_PENDING,
            'assigned_to'  => $this->nullableInt($data['assigned_to'] ?? null),
            'created_by'   => $userId,
        ]);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update([
            'title'       => $data['title'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'due_at'      => filled($data['due_at'] ?? '') ? $data['due_at'] : null,
            'assigned_to' => $this->nullableInt($data['assigned_to'] ?? null),
        ]);

        return $task->fresh();
    }

    public function complete(Task $task): Task
    {
        $task->update([
            'status'       => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $task;
    }

    public function reopen(Task $task): Task
    {
        $task->update([
            'status'       => Task::STATUS_PENDING,
            'completed_at' => null,
        ]);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function taskForBusiness(Business $business, Task $task): ?Task
    {
        return $task->business_id === $business->id ? $task : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
