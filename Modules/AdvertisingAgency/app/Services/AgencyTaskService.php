<?php

namespace Modules\AdvertisingAgency\Services;

use Illuminate\Support\Collection;
use Modules\AdvertisingAgency\Models\AgencyTask;
use Modules\AdvertisingAgency\Models\Campaign;

class AgencyTaskService
{
    public function listForCampaign(Campaign $campaign, ?string $status = null): Collection
    {
        $query = AgencyTask::where('campaign_id', $campaign->id)
            ->with('assignedTo')
            ->orderByRaw("FIELD(status, 'todo', 'in_progress', 'review', 'done')")
            ->orderBy('due_at')
            ->orderBy('id');

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function create(Campaign $campaign, array $data): AgencyTask
    {
        return AgencyTask::create([
            'campaign_id' => $campaign->id,
            'title'       => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'priority'    => $data['priority'] ?? 'medium',
            'status'      => $data['status'] ?? AgencyTask::STATUS_TODO,
            'assigned_to' => filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null,
            'due_at'      => filled($data['due_at'] ?? null) ? $data['due_at'] : null,
        ]);
    }

    public function update(AgencyTask $task, array $data): AgencyTask
    {
        $wasDone = $task->status !== AgencyTask::STATUS_DONE
            && ($data['status'] ?? '') === AgencyTask::STATUS_DONE;

        $task->update([
            'title'        => trim($data['title']),
            'description'  => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'priority'     => $data['priority'] ?? $task->priority,
            'status'       => $data['status'] ?? $task->status,
            'assigned_to'  => filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null,
            'due_at'       => filled($data['due_at'] ?? null) ? $data['due_at'] : null,
            'completed_at' => $wasDone ? now() : $task->completed_at,
        ]);

        return $task->fresh();
    }

    public function delete(AgencyTask $task): void
    {
        $task->delete();
    }
}
