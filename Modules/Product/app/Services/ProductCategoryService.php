<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\ProductCategory;

class ProductCategoryService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->productCategories()
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Depth-first flat list for simple tables / exports.
     */
    public function listTreeFlatForBusiness(Business $business): Collection
    {
        $all = $business->productCategories()
            ->with('parent')
            ->withCount(['products', 'children'])
            ->get();

        $roots = $all->whereNull('parent_id')->sortBy([
            ['sort_order', 'asc'],
            ['name', 'asc'],
        ]);

        $flat = new Collection();
        foreach ($roots as $root) {
            $this->appendCategoryAndDescendants($flat, $root, $all);
        }

        return $flat;
    }

    /**
     * Root categories with nested children loaded to any depth.
     *
     * @return Collection<int, ProductCategory>
     */
    public function categoryTreeForIndex(Business $business): Collection
    {
        $roots = $business->productCategories()
            ->whereNull('parent_id')
            ->withCount(['products', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $this->loadChildrenRecursively($roots);

        return $roots;
    }

    /**
     * All categories that may be chosen as a parent (excludes self and descendants when editing).
     *
     * @return SupportCollection<int, object{id: int, name: string, label: string, depth: int}>
     */
    public function parentOptionsForForm(Business $business, ?ProductCategory $exclude = null): SupportCollection
    {
        $all = $business->productCategories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $excludeIds = $exclude instanceof ProductCategory
            ? $this->selfAndDescendantIds($exclude, $all)
            : [];

        return $all
            ->reject(fn (ProductCategory $row) => in_array((int) $row->id, $excludeIds, true))
            ->map(function (ProductCategory $row) use ($all) {
                $depth = $this->depthOf($row, $all);

                return (object) [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'label' => $this->breadcrumbLabel($row, $all),
                    'depth' => $depth,
                ];
            })
            ->sortBy([
                ['depth', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    public function create(Business $business, array $data): ProductCategory
    {
        $parentId = $this->normalizeParentId($data['parent_id'] ?? null);
        $data['parent_id'] = $parentId;
        $this->assertValidParent($business, $parentId, null);

        if (!array_key_exists('sort_order', $data) || (int) $data['sort_order'] === 0) {
            $data['sort_order'] = $this->nextSortOrder($business, $parentId);
        }

        return $business->productCategories()->create($data);
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function reorder(Business $business, array $orderedIds): void
    {
        $ids = array_values(array_unique(array_map(static fn ($id) => (int) $id, $orderedIds)));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'order' => 'Category order is required.',
            ]);
        }

        $rows = $business->productCategories()->whereIn('id', $ids)->get();
        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'order' => 'One or more categories are invalid for this business.',
            ]);
        }

        $parentKeys = $rows->map(static fn (ProductCategory $row) => $row->parent_id === null ? '' : (string) $row->parent_id)->unique();
        if ($parentKeys->count() > 1) {
            throw ValidationException::withMessages([
                'order' => 'Reorder categories within the same level only.',
            ]);
        }

        foreach ($ids as $index => $id) {
            ProductCategory::query()
                ->where('business_id', $business->id)
                ->whereKey($id)
                ->update(['sort_order' => $index]);
        }
    }

    /**
     * Apply full category tree from drag-and-drop (unlimited nesting).
     *
     * @param  list<array{id: int|string, children?: list<array{id: int|string, children?: array}>}>  $tree
     */
    public function applyTree(Business $business, array $tree): void
    {
        if ($tree === []) {
            throw ValidationException::withMessages([
                'tree' => 'Category tree is required.',
            ]);
        }

        $expectedCount = $business->productCategories()->count();
        $seenIds = [];

        DB::transaction(function () use ($business, $tree, $expectedCount, &$seenIds): void {
            $this->walkTreeNodes($business, $tree, null, $seenIds);

            if (count(array_unique($seenIds)) !== $expectedCount) {
                throw ValidationException::withMessages([
                    'tree' => 'Every category must appear once in the tree.',
                ]);
            }
        });
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $business = $category->business;
        if (!$business instanceof Business) {
            throw ValidationException::withMessages(['parent_id' => 'Business not found for category.']);
        }

        $parentId = array_key_exists('parent_id', $data)
            ? $this->normalizeParentId($data['parent_id'])
            : $category->parent_id;

        $data['parent_id'] = $parentId;
        $this->assertValidParent($business, $parentId, $category);

        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function delete(ProductCategory $category): bool
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Remove or reassign subcategories before deleting this category.',
            ]);
        }

        return (bool) $category->delete();
    }

    public function categoryForBusiness(Business $business, ProductCategory $category): ?ProductCategory
    {
        if ((int) $category->business_id !== (int) $business->id) {
            return null;
        }

        return $category;
    }

    public function displayLabel(ProductCategory $category): string
    {
        $business = $category->business;
        if (!$business instanceof Business) {
            return $category->name;
        }

        $indexed = $business->productCategories()->get()->keyBy('id');

        return $this->breadcrumbLabel($category, $indexed);
    }

    /**
     * @param  Collection<int|string, ProductCategory>  $indexed
     */
    public function breadcrumbLabel(ProductCategory $category, ?Collection $indexed = null): string
    {
        if (!$indexed instanceof Collection) {
            $business = $category->business;
            $indexed = $business instanceof Business
                ? $business->productCategories()->get()->keyBy('id')
                : collect();
        }

        $parts = [];
        $current = $category;
        $guard = 0;
        while ($current instanceof ProductCategory && $guard < 64) {
            array_unshift($parts, $current->name);
            $current = $current->parent_id ? $indexed->get($current->parent_id) : null;
            $guard++;
        }

        return implode(' › ', $parts);
    }

    /**
     * @param  Collection<int|string, ProductCategory>  $indexed
     */
    private function depthOf(ProductCategory $category, Collection $indexed): int
    {
        $depth = 0;
        $current = $category;
        $guard = 0;
        while ($current->parent_id && $guard < 64) {
            $parent = $indexed->get($current->parent_id);
            if (!$parent instanceof ProductCategory) {
                break;
            }
            $depth++;
            $current = $parent;
            $guard++;
        }

        return $depth;
    }

    /**
     * @param  Collection<int|string, ProductCategory>  $indexed
     * @return list<int>
     */
    private function selfAndDescendantIds(ProductCategory $category, Collection $indexed): array
    {
        $ids = [(int) $category->id];
        $queue = [(int) $category->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);
            foreach ($indexed as $row) {
                if ((int) $row->parent_id === $parentId && !in_array((int) $row->id, $ids, true)) {
                    $ids[] = (int) $row->id;
                    $queue[] = (int) $row->id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function loadChildrenRecursively(Collection $categories): void
    {
        if ($categories->isEmpty()) {
            return;
        }

        $categories->load([
            'children' => static function ($query): void {
                $query->withCount(['products', 'children'])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },
        ]);

        foreach ($categories as $category) {
            if ($category->children->isNotEmpty()) {
                $this->loadChildrenRecursively($category->children);
            }
        }
    }

    /**
     * @param  Collection<int, ProductCategory>  $flat
     * @param  Collection<int|string, ProductCategory>  $all
     */
    private function appendCategoryAndDescendants(Collection $flat, ProductCategory $category, Collection $all): void
    {
        $flat->push($category);
        $children = $all->where('parent_id', $category->id)->sortBy([
            ['sort_order', 'asc'],
            ['name', 'asc'],
        ]);
        foreach ($children as $child) {
            $this->appendCategoryAndDescendants($flat, $child, $all);
        }
    }

    /**
     * @param  list<array{id: int|string, children?: array}>  $nodes
     * @param  list<int>  $seenIds
     */
    private function walkTreeNodes(Business $business, array $nodes, ?int $parentId, array &$seenIds): void
    {
        foreach ($nodes as $index => $node) {
            $categoryId = (int) ($node['id'] ?? 0);
            if ($categoryId <= 0) {
                throw ValidationException::withMessages(['tree' => 'Invalid category in tree.']);
            }

            $seenIds[] = $categoryId;
            $category = $business->productCategories()->whereKey($categoryId)->first();
            if (!$category instanceof ProductCategory) {
                throw ValidationException::withMessages(['tree' => 'Category not found for this business.']);
            }

            $this->assignTreePosition($business, $category, $parentId, $index);

            $children = $node['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->walkTreeNodes($business, $children, $category->id, $seenIds);
            }
        }
    }

    private function normalizeParentId(mixed $parentId): ?int
    {
        if ($parentId === null || $parentId === '' || $parentId === '0' || $parentId === 0) {
            return null;
        }

        return (int) $parentId;
    }

    private function nextSortOrder(Business $business, ?int $parentId): int
    {
        $query = $business->productCategories();
        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    private function assignTreePosition(Business $business, ProductCategory $category, ?int $parentId, int $sortOrder): void
    {
        $this->assertValidParent($business, $parentId, $category);

        $category->parent_id = $parentId;
        $category->sort_order = $sortOrder;
        $category->save();
    }

    private function assertValidParent(Business $business, ?int $parentId, ?ProductCategory $ignore): void
    {
        if ($parentId === null) {
            return;
        }

        if ($ignore && (int) $ignore->id === $parentId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $parent = $business->productCategories()->whereKey($parentId)->first();
        if (!$parent instanceof ProductCategory) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selected parent category was not found.',
            ]);
        }

        if ($ignore && $this->isDescendantOf($parent, (int) $ignore->id, $business)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be placed under one of its own subcategories.',
            ]);
        }
    }

    private function isDescendantOf(ProductCategory $node, int $ancestorId, Business $business): bool
    {
        $indexed = $business->productCategories()->get()->keyBy('id');
        $current = $node;
        $guard = 0;

        while ($current instanceof ProductCategory && $guard < 64) {
            if ((int) $current->id === $ancestorId) {
                return true;
            }
            $current = $current->parent_id ? $indexed->get($current->parent_id) : null;
            $guard++;
        }

        return false;
    }
}
