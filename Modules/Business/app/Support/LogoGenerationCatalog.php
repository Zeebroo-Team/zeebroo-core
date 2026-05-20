<?php

namespace Modules\Business\Support;

final class LogoGenerationCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function categories(): array
    {
        return [
            ['value' => 'it_software', 'label' => 'IT & Software'],
            ['value' => 'education', 'label' => 'Education'],
            ['value' => 'local_store', 'label' => 'Local Store'],
            ['value' => 'restaurant_food', 'label' => 'Restaurant & Food'],
            ['value' => 'health_wellness', 'label' => 'Health & Wellness'],
            ['value' => 'finance', 'label' => 'Finance & Accounting'],
            ['value' => 'creative_agency', 'label' => 'Creative Agency'],
            ['value' => 'retail_ecommerce', 'label' => 'Retail & E-commerce'],
            ['value' => 'manufacturing', 'label' => 'Manufacturing'],
            ['value' => 'nonprofit', 'label' => 'Non-profit'],
            ['value' => 'real_estate', 'label' => 'Real Estate'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function styles(): array
    {
        return [
            ['value' => 'minimalist', 'label' => 'Minimalist logo'],
            ['value' => '3d_logo', 'label' => '3D logo'],
            ['value' => 'geometric', 'label' => 'Geometric / abstract'],
            ['value' => 'mascot', 'label' => 'Mascot / character'],
            ['value' => 'wordmark', 'label' => 'Wordmark / typography'],
            ['value' => 'vintage', 'label' => 'Vintage / classic'],
            ['value' => 'flat_icon', 'label' => 'Flat icon'],
            ['value' => 'hand_drawn', 'label' => 'Hand-drawn illustrative'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function backgrounds(): array
    {
        return [
            ['value' => 'light', 'label' => 'Light theme'],
            ['value' => 'dark', 'label' => 'Dark theme'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabelsByValue(): array
    {
        return self::collapseValueLabel(self::categories());
    }

    /**
     * @return array<string, string>
     */
    public static function styleLabelsByValue(): array
    {
        return self::collapseValueLabel(self::styles());
    }

    /** @param list<array{value: string, label: string}> $rows */
    private static function collapseValueLabel(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['value']] = $row['label'];
        }

        return $out;
    }
}
