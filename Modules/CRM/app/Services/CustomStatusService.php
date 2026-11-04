<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CustomStatus;
use Modules\CRM\Models\LeadStage;

/**
 * The business's own statuses (CustomStatus) — made once, offered as a stage template in every
 * pipeline. Deleting one keeps the stages made from it: they simply count as its standard status.
 */
class CustomStatusService
{
    public function __construct(private readonly LeadStageService $stages) {}

    /** @return Collection<int, CustomStatus> */
    public function listForBusiness(Business $business): Collection
    {
        return CustomStatus::query()
            ->where('business_id', $business->id)
            ->withCount('stages')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function statusForBusiness(Business $business, int $id): CustomStatus
    {
        return CustomStatus::query()->where('business_id', $business->id)->findOrFail($id);
    }

    public function create(Business $business, array $data): CustomStatus
    {
        return CustomStatus::create([
            'business_id' => $business->id,
            'sort_order'  => (int) CustomStatus::query()->where('business_id', $business->id)->max('sort_order') + 1,
        ] + $this->attributes($business, $data, null));
    }

    public function update(CustomStatus $status, array $data): CustomStatus
    {
        $status->update($this->attributes($status->business, $data, $status));

        return $status->fresh();
    }

    public function delete(CustomStatus $status): void
    {
        LeadStage::query()->where('custom_status_id', $status->id)->update(['custom_status_id' => null]);
        $status->delete();
    }

    /** @return list<array<string, mixed>> */
    public function present(Collection $statuses): array
    {
        return $statuses->map(fn (CustomStatus $s) => [
            'id'              => $s->id,
            'key'             => 'custom:' . $s->id,
            'label'           => $s->name,
            'color'           => $s->color,
            'icon'            => $s->icon,
            'note'            => $s->description,
            'description'     => $s->description ?:('Your own status — counts as ' . LeadStage::statusLabel($s->base_key) . ' in reports.'),
            'base_key'        => $s->base_key,
            'base_label'      => LeadStage::statusLabel($s->base_key),
            'requires_reason' => (bool) $s->requires_reason,
            'entry_rules'     => (new LeadStage(['entry_rules' => $s->entry_rules]))->entryRules(),
            'stages_count'    => (int) ($s->stages_count ?? 0),
            'custom'          => true,
            'reserved'        => false,
        ])->values()->all();
    }

    private function attributes(Business $business, array $data, ?CustomStatus $status): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $taken = CustomStatus::query()->where('business_id', $business->id)->where('name', $name)
            ->when($status, fn ($q) => $q->where('id', '!=', $status->id))->exists();
        if ($taken || in_array(mb_strtolower($name), array_map('mb_strtolower', array_column(LeadStage::STATUSES, 0)), true)) {
            throw ValidationException::withMessages(['name' => 'A status called "' . $name . '" already exists.']);
        }

        $base = (string) ($data['base_key'] ?? '');
        if (! array_key_exists($base, LeadStage::STATUSES) || in_array($base, LeadStage::RESERVED_STATUSES, true)) {
            throw ValidationException::withMessages(['base_key' => 'Pick the standard status this one counts as.']);
        }

        // A template is shared by every department, so it never names people. Kept as is when not sent.
        $rules = (array) ($data['entry_rules'] ?? []);
        unset($rules['assign_user_ids'], $rules['notify']['user_ids']);
        $rulesSent = $status === null || array_key_exists('entry_rules', $data);

        return ($rulesSent ? ['entry_rules' => $this->stages->cleanEntryRules($rules, null)] : []) + [
            'name'            => $name,
            'color'           => filled($data['color'] ?? null) ? $data['color'] : '#6366f1',
            'icon'            => filled($data['icon'] ?? null) ? $data['icon'] : 'fa-star',
            'description'     => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'base_key'        => $base,
            'requires_reason' => (bool) ($data['requires_reason'] ?? false) || $base === LeadStage::STATUS_LOST,
        ];
    }
}
