<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\Project;

class LeadCustomFieldService
{
    public function listForProject(Project $project): Collection
    {
        return LeadCustomField::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * All fields for a project keyed by id, for looking up a field by its
     * "custom:{id}" reference (e.g. when rendering a form's field blocks).
     */
    public function listKeyedById(Project $project): Collection
    {
        return $this->listForProject($project)->keyBy('id');
    }

    public function create(Project $project, array $data): LeadCustomField
    {
        $nextOrder = (int) LeadCustomField::query()->where('project_id', $project->id)->max('sort_order') + 1;
        $type      = $data['type'] ?? LeadCustomField::TYPE_TEXT;

        return LeadCustomField::create([
            'project_id'  => $project->id,
            'label'       => $data['label'],
            'type'        => $type,
            'options'     => $type === LeadCustomField::TYPE_SELECT ? $this->parseOptions($data['options'] ?? '') : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order'  => $nextOrder,
        ]);
    }

    public function update(LeadCustomField $field, array $data): LeadCustomField
    {
        $type = $data['type'] ?? $field->type;

        $field->update([
            'label'       => $data['label'],
            'type'        => $type,
            'options'     => $type === LeadCustomField::TYPE_SELECT ? $this->parseOptions($data['options'] ?? '') : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
        ]);

        return $field->fresh();
    }

    public function delete(LeadCustomField $field): void
    {
        $field->delete();
    }

    public function reorder(Project $project, array $ids): void
    {
        foreach ($ids as $order => $id) {
            LeadCustomField::query()
                ->where('id', $id)
                ->where('project_id', $project->id)
                ->update(['sort_order' => $order + 1]);
        }
    }

    public function fieldForProject(Project $project, LeadCustomField $field): ?LeadCustomField
    {
        return $field->project_id === $project->id ? $field : null;
    }

    /**
     * @return array<int, string>
     */
    private function parseOptions(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw)), fn ($o) => $o !== ''));
    }
}
