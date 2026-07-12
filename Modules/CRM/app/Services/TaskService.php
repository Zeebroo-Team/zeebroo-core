<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Task;

class TaskService
{
    public function listForBusiness(Business $business, string $filter = 'open'): Collection
    {
        $query = Task::query()
            ->where('business_id', $business->id)
            ->with(['assignedTo', 'subject']);

        match ($filter) {
            'overdue'   => $query->where('status', Task::STATUS_PENDING)->whereNotNull('due_at')->where('due_at', '<', now()),
            'completed' => $query->where('status', Task::STATUS_COMPLETED),
            'open'      => $query->where('status', Task::STATUS_PENDING),
            default     => null,
        };

        return $query->orderByRaw('due_at is null')->orderBy('due_at')->orderByDesc('id')->get();
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
