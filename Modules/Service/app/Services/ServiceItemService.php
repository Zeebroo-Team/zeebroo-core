<?php

namespace Modules\Service\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Service\Models\ServiceItem;

class ServiceItemService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = ServiceItem::query()
            ->with('categories')
            ->where('business_id', $business->id);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('categories', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->orderBy('name')->get();
    }

    public function businessHasItems(Business $business): bool
    {
        return ServiceItem::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): ServiceItem
    {
        $categoryIds  = $data['service_category_ids'] ?? [];
        $employeeIds  = $data['employee_ids'] ?? [];
        $productLines = $data['product_lines'] ?? [];

        $item = ServiceItem::create([
            'business_id'                 => $business->id,
            'name'                        => $data['name'],
            'barcode'                     => filled($data['barcode'] ?? '') ? $data['barcode'] : null,
            'description'                 => filled($data['description'] ?? '') ? $data['description'] : null,
            'tags'                        => $this->normalizeTags($data['tags'] ?? []),
            'price'                       => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'cost_price'                  => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'wholesale_price'             => isset($data['wholesale_price']) && $data['wholesale_price'] !== '' ? (float) $data['wholesale_price'] : null,
            'duration_minutes'            => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'is_active'                   => (bool) ($data['is_active'] ?? true),
            'is_featured'                 => (bool) ($data['is_featured'] ?? false),
            'has_warranty'                => (bool) ($data['has_warranty'] ?? false),
            'custom_requirement_enabled'  => (bool) ($data['custom_requirement_enabled'] ?? false),
            'custom_requirement_fields'   => $this->sanitizeCustomRequirementFields($data['custom_requirement_fields'] ?? []),
            'file_manager_file_id'        => $data['file_manager_file_id'] ?? null,
        ]);

        $item->categories()->sync($categoryIds);
        $item->employees()->sync($employeeIds);
        $item->products()->sync($productLines);

        return $item->load(['categories', 'employees', 'products']);
    }

    public function update(ServiceItem $item, array $data): ServiceItem
    {
        $categoryIds  = $data['service_category_ids'] ?? null;
        $employeeIds  = $data['employee_ids'] ?? null;
        $productLines = $data['product_lines'] ?? null;

        $item->update([
            'name'                        => $data['name'],
            'barcode'                     => filled($data['barcode'] ?? '') ? $data['barcode'] : null,
            'description'                 => filled($data['description'] ?? '') ? $data['description'] : null,
            'tags'                        => array_key_exists('tags', $data) ? $this->normalizeTags($data['tags'] ?? []) : $item->tags,
            'price'                       => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            'cost_price'                  => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'wholesale_price'             => isset($data['wholesale_price']) && $data['wholesale_price'] !== '' ? (float) $data['wholesale_price'] : null,
            'duration_minutes'            => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int) $data['duration_minutes'] : null,
            'is_active'                   => (bool) ($data['is_active'] ?? true),
            'is_featured'                 => (bool) ($data['is_featured'] ?? $item->is_featured),
            'has_warranty'                => (bool) ($data['has_warranty'] ?? $item->has_warranty),
            'custom_requirement_enabled'  => (bool) ($data['custom_requirement_enabled'] ?? $item->custom_requirement_enabled),
            'custom_requirement_fields'   => array_key_exists('custom_requirement_fields', $data)
                ? $this->sanitizeCustomRequirementFields($data['custom_requirement_fields'] ?? [])
                : $item->custom_requirement_fields,
            'file_manager_file_id'        => array_key_exists('file_manager_file_id', $data) ? $data['file_manager_file_id'] : $item->file_manager_file_id,
        ]);

        if ($categoryIds !== null)  $item->categories()->sync($categoryIds);
        if ($employeeIds !== null)  $item->employees()->sync($employeeIds);
        if ($productLines !== null) $item->products()->sync($productLines);

        return $item->fresh()->load(['categories', 'employees', 'products']);
    }

    public function delete(ServiceItem $item): void
    {
        $item->delete();
    }

    public function itemForBusiness(Business $business, ServiceItem $item): ?ServiceItem
    {
        return $item->business_id === $business->id ? $item : null;
    }

    /**
     * Trim, drop blanks, and dedupe (case-insensitive) a client-submitted tags payload.
     *
     * @param  array<int, mixed>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $seen = [];
        $out  = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $tag;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return list<array{key: string, label: string, type: string, options: list<string>}>
     */
    private function sanitizeCustomRequirementFields(array $fields): array
    {
        $allowedTypes = ['text', 'textarea', 'select', 'number', 'date', 'checkbox', 'radio'];
        $usedKeys     = [];
        $sanitized    = [];

        foreach (array_slice($fields, 0, 20) as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $type = in_array($field['type'] ?? null, $allowedTypes, true) ? $field['type'] : 'text';

            $key = trim((string) ($field['key'] ?? ''));
            $key = $key !== '' ? Str::slug($key, '_') : Str::slug($label, '_');
            $key = $key !== '' ? $key : 'field_' . (count($sanitized) + 1);
            $baseKey = $key;
            $suffix  = 1;
            while (in_array($key, $usedKeys, true)) {
                $key = $baseKey . '_' . (++$suffix);
            }
            $usedKeys[] = $key;

            $options = [];
            if ($type === 'select' || $type === 'radio') {
                foreach ((array) ($field['options'] ?? []) as $opt) {
                    $opt = trim((string) $opt);
                    if ($opt !== '') {
                        $options[] = mb_substr($opt, 0, 120);
                    }
                }
            }

            $sanitized[] = [
                'key'     => mb_substr($key, 0, 100),
                'label'   => mb_substr($label, 0, 255),
                'type'    => $type,
                'options' => $options,
            ];
        }

        return $sanitized;
    }
}
