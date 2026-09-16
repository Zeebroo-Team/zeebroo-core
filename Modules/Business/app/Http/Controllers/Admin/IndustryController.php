<?php

declare(strict_types=1);

namespace Modules\Business\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessCategory;

class IndustryController extends Controller
{
    public function index(): View
    {
        $industries = BusinessCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // BusinessCategory is matched to Business by slug (no FK relation), so
        // usage counts are tallied separately here for the delete-guard hint.
        $usageBySlug = Business::query()
            ->whereNotNull('company_category_slug')
            ->selectRaw('company_category_slug, count(*) as aggregate')
            ->groupBy('company_category_slug')
            ->pluck('aggregate', 'company_category_slug');

        return view('business::admin.industries.index', [
            'industries' => $industries,
            'usageBySlug' => $usageBySlug,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateIndustry($request);

        $slug = Str::slug($data['name'], '_');
        $baseSlug = $slug !== '' ? $slug : 'industry';
        $uniqueSlug = $baseSlug;
        $suffix = 2;
        while (BusinessCategory::query()->where('slug', $uniqueSlug)->exists()) {
            $uniqueSlug = $baseSlug . '_' . $suffix;
            $suffix++;
        }

        BusinessCategory::create([
            'slug' => $uniqueSlug,
            'name' => $data['name'],
            'icon' => $data['icon'],
            'color' => $data['color'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()->route('admin.industries.index')
            ->with('success', 'Industry "' . $data['name'] . '" created.');
    }

    public function update(Request $request, BusinessCategory $industry): RedirectResponse
    {
        $data = $this->validateIndustry($request);

        $industry->update([
            'name' => $data['name'],
            'icon' => $data['icon'],
            'color' => $data['color'],
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()->route('admin.industries.index')
            ->with('success', 'Industry "' . $data['name'] . '" updated.');
    }

    public function destroy(BusinessCategory $industry): RedirectResponse
    {
        $inUse = Business::query()->where('company_category_slug', $industry->slug)->exists();

        if ($inUse) {
            return redirect()->route('admin.industries.index')
                ->with('error', 'Cannot delete "' . $industry->name . '" — it is assigned to one or more businesses. Deactivate it instead.');
        }

        $name = $industry->name;
        $industry->delete();

        return redirect()->route('admin.industries.index')
            ->with('success', 'Industry "' . $name . '" deleted.');
    }

    /**
     * @return array{name: string, icon: string, color: string, sort_order: int, is_active: bool}
     */
    private function validateIndustry(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['required', 'string', 'max:64', 'regex:/^fa-[a-z0-9-]+$/'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
