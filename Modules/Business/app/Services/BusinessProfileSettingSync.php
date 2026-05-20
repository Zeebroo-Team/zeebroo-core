<?php

declare(strict_types=1);

namespace Modules\Business\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Business\Support\BrandCompanyCategoryCatalog;
use Modules\Settings\Services\SettingsService;

final class BusinessProfileSettingSync
{
    public const KEY_CATEGORY_SLUG = 'business.profile.company_category_slug';

    public const KEY_SHORT_DESCRIPTION = 'business.profile.short_description';

    public const KEY_DESCRIPTION = 'business.profile.description';

    public const KEY_BRAND_FEATURES = 'business.profile.brand_features';

    /**
     * Fill missing scoped settings snapshot values from persisted Business columns
     * so Business Settings tabs show profile data until settings rows exist.
     */
    public function hydrateBusinessSettingsUi(Business $business, Collection $settings): Collection
    {
        $fill = [
            self::KEY_CATEGORY_SLUG => $business->company_category_slug,
            self::KEY_SHORT_DESCRIPTION => $business->short_description,
            self::KEY_DESCRIPTION => $business->description,
            self::KEY_BRAND_FEATURES => $business->brand_features,
        ];
        foreach ($fill as $key => $fallback) {
            if ($fallback === null || $fallback === '' || $fallback === []) {
                continue;
            }
            $current = $settings->get($key);
            if (! $this->isVacantScopedValue($current)) {
                continue;
            }
            $settings->put($key, $fallback);
        }

        return $settings;
    }

    /** Mirror authoritative Business columns into scoped settings (after profile saves). */
    public function mirrorModelToSettings(SettingsService $settings, Business $business): void
    {
        $settings->setMany($business, [
            self::KEY_CATEGORY_SLUG => $business->company_category_slug,
            self::KEY_SHORT_DESCRIPTION => $business->short_description,
            self::KEY_DESCRIPTION => $business->description,
            self::KEY_BRAND_FEATURES => $business->brand_features,
        ]);
    }

    /**
     * Persist Business Brand tab saved fields from settings form into the Business model.
     *
     * @param  Collection<string,mixed>  $allFresh  Latest values for this business scope (post-bulk-save).
     */
    public function applyBrandTabFromSettings(Business $business, Collection $allFresh): void
    {
        $slugKeys = array_column(BrandCompanyCategoryCatalog::options(), 'value');
        $validated = Validator::validate(
            [
                'company_category_slug' => $allFresh->get(self::KEY_CATEGORY_SLUG),
                'short_description' => $allFresh->get(self::KEY_SHORT_DESCRIPTION),
                'description' => $allFresh->get(self::KEY_DESCRIPTION),
            ],
            [
                'company_category_slug' => ['required', 'string', Rule::in($slugKeys)],
                'short_description' => ['nullable', 'string', 'max:360'],
                'description' => ['nullable', 'string', 'max:6000'],
            ]
        );

        $slug = trim((string) $validated['company_category_slug']);
        $label = BrandCompanyCategoryCatalog::labelsByValue()[$slug] ?? $slug;
        $short = isset($validated['short_description']) ? trim((string) $validated['short_description']) : '';
        $long = isset($validated['description']) ? trim((string) $validated['description']) : '';

        $business->update([
            'company_category_slug' => $slug,
            'category' => $label,
            'short_description' => $short === '' ? null : $short,
            'description' => $long === '' ? null : $long,
        ]);
    }

    private function isVacantScopedValue(mixed $current): bool
    {
        if ($current === null) {
            return true;
        }
        if ($current === '') {
            return true;
        }

        return is_array($current) && $current === [];
    }
}
