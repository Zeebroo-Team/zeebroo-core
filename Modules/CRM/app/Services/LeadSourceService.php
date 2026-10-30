<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadSource;

class LeadSourceService
{
    public function listForBusiness(Business|int $business, bool $activeOnly = false): Collection
    {
        $businessId = $business instanceof Business ? $business->id : $business;
        $this->ensureDefaults($businessId);

        return LeadSource::query()
            ->where('business_id', $businessId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaults(int $businessId): void
    {
        $existing = LeadSource::query()->where('business_id', $businessId)->pluck('key')->all();

        foreach (LeadSource::DEFAULTS as $key => $label) {
            if (! in_array($key, $existing, true)) {
                LeadSource::create(['business_id' => $businessId, 'key' => $key, 'label' => $label, 'is_active' => true]);
            }
        }
    }

    /**
     * The source_id and crm_leads.source pair for a lead. Callers send either a source id
     * (from crm_lead_sources) or a source key (Lead::SOURCES); the id wins when both are sent.
     *
     * @return array{source_id: ?int, source: ?string}
     */
    public function resolve(int $businessId, ?string $source, mixed $sourceId = null): array
    {
        if (filled($sourceId)) {
            $row = $this->listForBusiness($businessId)->firstWhere('id', (int) $sourceId);
            if ($row !== null) {
                return ['source_id' => $row->id, 'source' => $row->leadSourceKey()];
            }
        }

        if (! filled($source)) {
            return ['source_id' => null, 'source' => null];
        }

        $row = $this->listForBusiness($businessId)->firstWhere('key', LeadSource::keyForLeadSource($source));

        return ['source_id' => $row?->id, 'source' => $source];
    }

    /** @return list<array{id:int,key:string,label:string,lead_source:string,icon:string,color:string,is_active:bool}> */
    public function present(Collection $sources): array
    {
        return $sources->map(fn (LeadSource $s) => [
            'id'          => $s->id,
            'key'         => $s->key,
            'label'       => $s->label,
            'lead_source' => $s->leadSourceKey(),
            'icon'        => Lead::SOURCES[$s->leadSourceKey()][1] ?? 'fa fa-tag',
            'color'       => Lead::SOURCES[$s->leadSourceKey()][2] ?? '#94a3b8',
            'is_active'   => (bool) $s->is_active,
        ])->values()->all();
    }
}
