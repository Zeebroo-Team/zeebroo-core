<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Services\ProductCategoryService;

class ProductCategoryController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(private readonly ProductCategoryService $categoryService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search       = trim((string) $request->query('q', ''));
        $filterStatus = in_array($request->query('status'), ['active', 'inactive'], true)
            ? (string) $request->query('status')
            : null;
        $isFiltering  = $search !== '' || $filterStatus !== null;

        $categories     = $this->categoryService->listTreeFlatForBusiness($business);
        $totalCount     = $categories->count();
        $categoryTree   = $isFiltering ? collect() : $this->categoryService->categoryTreeForIndex($business);
        $searchResults  = $isFiltering
            ? $this->categoryService->searchFlatForBusiness($business, $search, $filterStatus)
            : null;

        return view('product::categories.index', [
            'business'      => $business,
            'categories'    => $categories,
            'totalCount'    => $totalCount,
            'categoryTree'  => $categoryTree,
            'searchResults' => $searchResults,
            'isFiltering'   => $isFiltering,
            'search'        => $search,
            'filterStatus'  => $filterStatus,
            'parentOptions' => $this->categoryService->parentOptionsForForm($business),
            'presetParentId' => $request->integer('parent') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedCategory($request, $business);
        $this->categoryService->create($business, $data);

        return redirect()->route('product.categories.index')->with('status', 'Category added.');
    }

    public function edit(Request $request, ProductCategory $category): View|RedirectResponse
    {
        $business = $this->requireCategory($request, $category);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $category->load('parent');

        return view('product::categories.edit', [
            'business' => $business,
            'category' => $category,
            'parentOptions' => $this->categoryService->parentOptionsForForm($business, $category),
        ]);
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $business = $this->requireCategory($request, $category);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedCategory($request, $business, $category);
        $this->categoryService->update($category, $data);

        return redirect()->route('product.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, ProductCategory $category): RedirectResponse
    {
        $business = $this->requireCategory($request, $category);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($category->products()->exists()) {
            return redirect()->route('product.categories.index')->withErrors([
                'category' => 'Cannot delete a category that still has products assigned.',
            ]);
        }

        try {
            $this->categoryService->delete($category);
        } catch (ValidationException $e) {
            return redirect()->route('product.categories.index')->withErrors($e->errors());
        }

        return redirect()->route('product.categories.index')->with('status', 'Category removed.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        if ($request->has('tree')) {
            $validated = $request->validate([
                'tree' => ['required', 'array', 'min:1'],
            ]);

            try {
                $this->categoryService->applyTree($business, $validated['tree']);
            } catch (ValidationException $e) {
                return response()->json([
                    'error' => $e->validator->errors()->first() ?: 'Could not save categories.',
                ], 422);
            }

            return response()->json(['ok' => true, 'reload' => true]);
        }

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        try {
            $this->categoryService->reorder($business, $validated['order']);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->validator->errors()->first() ?: 'Could not save order.',
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    private function requireCategory(Request $request, ProductCategory $category): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->categoryService->categoryForBusiness($business, $category) instanceof ProductCategory, 404);

        return $business;
    }

    /**
     * @return array{name: string, description: ?string, parent_id: ?int, sort_order: int, is_active: bool}
     */
    private function validatedCategory(Request $request, Business $business, ?ProductCategory $ignore = null): array
    {
        $parentId = $request->input('parent_id');
        $parentId = ($parentId === null || $parentId === '' || $parentId === '0') ? null : (int) $parentId;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)
                        ->when(
                            $parentId === null,
                            fn ($q) => $q->whereNull('parent_id'),
                            fn ($q) => $q->where('parent_id', $parentId),
                        ))
                    ->ignore($ignore?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['parent_id'] = $parentId;
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
