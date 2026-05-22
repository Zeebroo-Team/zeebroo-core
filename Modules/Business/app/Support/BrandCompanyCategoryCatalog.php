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
     * @return list<array{value: string, label: string}>
     */
    public static function defaultOptions(): array
    {
        return [
            ['value' => 'education', 'label' => 'Education'],
            ['value' => 'software_industry', 'label' => 'Software industry'],
            ['value' => 'local_retail', 'label' => 'Local retail & shops'],
            ['value' => 'food_beverage', 'label' => 'Food & beverage'],
            ['value' => 'healthcare', 'label' => 'Healthcare & wellness'],
            ['value' => 'finance', 'label' => 'Finance & insurance'],
            ['value' => 'creative_media', 'label' => 'Creative & media'],
            ['value' => 'ecommerce', 'label' => 'E-commerce'],
            ['value' => 'manufacturing', 'label' => 'Manufacturing'],
            ['value' => 'real_estate', 'label' => 'Real estate'],
            ['value' => 'nonprofit', 'label' => 'Non-profit'],
            ['value' => 'professional_services', 'label' => 'Professional services'],
            ['value' => 'other', 'label' => 'Other'],
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
