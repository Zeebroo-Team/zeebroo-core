<?php

namespace Modules\Business\Support;

final class BrandCompanyCategoryCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return \Modules\Business\Models\BusinessCategory::optionsForSelect();
    }

    /**
     * @return list<array{value: string, label: string, icon: string, color: string}>
     */
    public static function defaultOptions(): array
    {
        return [
            ['value' => 'education', 'label' => 'Education', 'icon' => 'fa-graduation-cap', 'color' => '#7c3aed'],
            ['value' => 'software_industry', 'label' => 'Software industry', 'icon' => 'fa-laptop-code', 'color' => '#0ea5e9'],
            ['value' => 'local_retail', 'label' => 'Local retail & shops', 'icon' => 'fa-store', 'color' => '#f59e0b'],
            ['value' => 'food_beverage', 'label' => 'Food & beverage', 'icon' => 'fa-utensils', 'color' => '#ef4444'],
            ['value' => 'healthcare', 'label' => 'Healthcare & wellness', 'icon' => 'fa-heart-pulse', 'color' => '#10b981'],
            ['value' => 'finance', 'label' => 'Finance & insurance', 'icon' => 'fa-chart-pie', 'color' => '#3b82f6'],
            ['value' => 'creative_media', 'label' => 'Creative & media', 'icon' => 'fa-palette', 'color' => '#ec4899'],
            ['value' => 'ecommerce', 'label' => 'E-commerce', 'icon' => 'fa-cart-shopping', 'color' => '#8b5cf6'],
            ['value' => 'manufacturing', 'label' => 'Manufacturing', 'icon' => 'fa-industry', 'color' => '#64748b'],
            ['value' => 'real_estate', 'label' => 'Real estate', 'icon' => 'fa-building', 'color' => '#14b8a6'],
            ['value' => 'nonprofit', 'label' => 'Non-profit', 'icon' => 'fa-hand-holding-heart', 'color' => '#22c55e'],
            ['value' => 'professional_services', 'label' => 'Professional services', 'icon' => 'fa-briefcase', 'color' => '#6366f1'],
            ['value' => 'other', 'label' => 'Other', 'icon' => 'fa-ellipsis', 'color' => '#9ca3af'],
        ];
    }

    /**
     * @return array<string, string> value => label
     */
    public static function labelsByValue(): array
    {
        return \Modules\Business\Models\BusinessCategory::labelsBySlug();
    }
}
