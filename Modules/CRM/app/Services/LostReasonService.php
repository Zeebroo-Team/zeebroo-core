<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\LostReason;

class LostReasonService
{
    public function listForBusiness(Business|int $business, bool $activeOnly = true): Collection
    {
        $businessId = $business instanceof Business ? $business->id : $business;
        $this->ensureDefaults($businessId);

        return LostReason::query()
            ->where('business_id', $businessId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function ensureDefaults(int $businessId): void
    {
        if (LostReason::query()->where('business_id', $businessId)->exists()) {
            return;
        }

        foreach (LostReason::DEFAULTS as $i => $label) {
            LostReason::create(['business_id' => $businessId, 'label' => $label, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }

    /** An active lost reason of this business, or a validation error. */
    public function requireActive(int $businessId, mixed $lostReasonId): LostReason
    {
        $reason = filled($lostReasonId)
            ? $this->listForBusiness($businessId)->firstWhere('id', (int) $lostReasonId)
            : null;

        if ($reason === null) {
            throw ValidationException::withMessages([
                'lost_reason_id' => filled($lostReasonId) ? 'Pick a valid lost reason.' : 'A lost reason is required to mark this lead as lost.',
            ]);
        }

        return $reason;
    }

    /** @return list<array{id:int,label:string,sort_order:int}> */
    public function present(Collection $reasons): array
    {
        return $reasons->map(fn (LostReason $r) => [
            'id'         => $r->id,
            'label'      => $r->label,
            'sort_order' => (int) $r->sort_order,
        ])->values()->all();
    }
}
