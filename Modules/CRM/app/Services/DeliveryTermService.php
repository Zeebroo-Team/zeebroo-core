<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\DeliveryTerm;

/** The business's delivery terms — the quotation editor's Delivery dropdown. */
class DeliveryTermService
{
    public function listForBusiness(Business|int $business, bool $activeOnly = true): Collection
    {
        $businessId = $business instanceof Business ? (int) $business->id : $business;
        $this->ensureDefaults($businessId);

        return DeliveryTerm::query()
            ->where('business_id', $businessId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaults(int $businessId): void
    {
        if (DeliveryTerm::query()->where('business_id', $businessId)->exists()) {
            return;
        }

        foreach (DeliveryTerm::DEFAULTS as $i => $label) {
            DeliveryTerm::create(['business_id' => $businessId, 'label' => $label, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }

    public function termForBusiness(Business $business, int $termId): DeliveryTerm
    {
        $term = DeliveryTerm::query()->where('business_id', $business->id)->find($termId);
        if ($term === null) {
            throw (new ModelNotFoundException())->setModel(DeliveryTerm::class, [$termId]);
        }

        return $term;
    }

    /** @param array{label: string, is_active?: bool} $data — added at the end of the list */
    public function create(Business $business, array $data): DeliveryTerm
    {
        $this->ensureDefaults((int) $business->id);
        $label = $this->uniqueLabel($business, $data['label']);
        $last  = (int) DeliveryTerm::query()->where('business_id', $business->id)->max('sort_order');

        return DeliveryTerm::create([
            'business_id' => $business->id,
            'label'       => $label,
            'sort_order'  => $last + 1,
            'is_active'   => $data['is_active'] ?? true,
        ]);
    }

    /** @param array{label?: string, is_active?: bool} $data */
    public function update(DeliveryTerm $term, array $data): DeliveryTerm
    {
        if (array_key_exists('label', $data)) {
            $term->label = $this->uniqueLabel($term->business_id, $data['label'], (int) $term->id);
        }
        if (array_key_exists('is_active', $data)) {
            $term->is_active = (bool) $data['is_active'];
        }
        $term->save();

        return $term;
    }

    /** Quotations already saved keep their text — the term is only a choice for new lines. */
    public function delete(DeliveryTerm $term): void
    {
        $term->delete();
    }

    /** @param list<int> $ids the business's terms in their new order; others keep their place after them */
    public function reorder(Business $business, array $ids): void
    {
        $terms = $this->listForBusiness($business, false)->keyBy('id');
        $order = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $terms->has($id))));
        foreach ($terms->keys() as $id) {
            if (! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        foreach ($order as $i => $id) {
            $term = $terms[$id];
            if ((int) $term->sort_order !== $i + 1) {
                $term->update(['sort_order' => $i + 1]);
            }
        }
    }

    /** @return list<array{id: int, label: string, sort_order: int, is_active: bool}> */
    public function present(Collection $terms): array
    {
        return $terms->map(fn (DeliveryTerm $t) => [
            'id'         => (int) $t->id,
            'label'      => $t->label,
            'sort_order' => (int) $t->sort_order,
            'is_active'  => (bool) $t->is_active,
        ])->values()->all();
    }

    private function uniqueLabel(Business|int $business, string $label, ?int $exceptId = null): string
    {
        $label      = trim(preg_replace('/\s+/u', ' ', $label));
        $businessId = $business instanceof Business ? (int) $business->id : $business;

        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Type the delivery term.']);
        }

        $taken = DeliveryTerm::query()
            ->where('business_id', $businessId)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['label' => 'This delivery term is already in the list.']);
        }

        return $label;
    }
}
