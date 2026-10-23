<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Slide;

class SlideService
{
    public function list(Business|int $business, bool $activeOnly = false): Collection
    {
        $id = $business instanceof Business ? $business->id : $business;

        return Slide::where('business_id', $id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function create(Business $business, array $data): Slide
    {
        $data['business_id'] = $business->id;
        $data['sort_order']  = (int) Slide::where('business_id', $business->id)->max('sort_order') + 1;

        return Slide::create($data);
    }

    public function update(Slide $slide, array $data): Slide
    {
        $slide->fill($data)->save();

        return $slide->fresh();
    }

    /** @param int[] $ids slide ids in their new display order */
    public function reorder(Business $business, array $ids): void
    {
        DB::transaction(function () use ($business, $ids) {
            foreach (array_values($ids) as $i => $id) {
                Slide::where('business_id', $business->id)->where('id', (int) $id)->update(['sort_order' => $i + 1]);
            }
        });
    }

    public function format(Slide $s): array
    {
        return [
            'id'          => $s->id,
            'image_url'   => $s->image_url,
            'title'       => $s->title,
            'subtitle'    => $s->subtitle,
            'button_text' => $s->button_text,
            'button_link' => $s->button_link,
            'sort_order'  => $s->sort_order,
            'is_active'   => $s->is_active,
        ];
    }
}
