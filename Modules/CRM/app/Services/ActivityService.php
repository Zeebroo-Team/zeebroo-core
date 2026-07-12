<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;

class ActivityService
{
    public function create(Business $business, Model $subject, array $data, ?int $userId): Activity
    {
        return Activity::create([
            'business_id' => $business->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id'   => $subject->getKey(),
            'type'        => $data['type'] ?? Activity::TYPE_NOTE,
            'body'        => filled($data['body'] ?? '') ? $data['body'] : null,
            'occurred_at' => filled($data['occurred_at'] ?? '') ? $data['occurred_at'] : now(),
            'created_by'  => $userId,
        ]);
    }

    public function delete(Activity $activity): void
    {
        $activity->delete();
    }

    public function activityForBusiness(Business $business, Activity $activity): ?Activity
    {
        return $activity->business_id === $business->id ? $activity : null;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function recentForProject(Project $project, int $limit = 20): Collection
    {
        $leadIds = Lead::query()->where('project_id', $project->id)->pluck('id');

        return Activity::query()
            ->where('subject_type', Lead::class)
            ->whereIn('subject_id', $leadIds)
            ->with(['subject', 'createdBy'])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }
}
