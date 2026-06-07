<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;

class ProductDummyDataSeeder extends Seeder
{
    private const CATEGORY_COUNT = 50;
    private const BRAND_COUNT    = 50;
    private const PRODUCT_COUNT  = 5000;

    private array $categoryNames = [
        'Electronics', 'Clothing', 'Footwear', 'Furniture', 'Groceries',
        'Beverages', 'Dairy Products', 'Bakery', 'Snacks', 'Frozen Foods',
        'Personal Care', 'Health & Wellness', 'Sports Equipment', 'Outdoor Gear',
        'Books', 'Stationery', 'Toys & Games', 'Baby Products', 'Pet Supplies',
        'Automotive', 'Tools & Hardware', 'Garden & Outdoors', 'Home Appliances',
        'Kitchen Utensils', 'Bedding & Linens', 'Lighting', 'Cleaning Supplies',
        'Office Supplies', 'Arts & Crafts', 'Musical Instruments',
        'Cameras & Photography', 'Mobile Accessories', 'Computers', 'Networking',
        'Audio Equipment', 'Video Games', 'Software', 'Watches', 'Jewellery',
        'Bags & Luggage', 'Sunglasses', 'Cosmetics', 'Fragrances', 'Hair Care',
        'Vitamins & Supplements', 'Medical Devices', 'Safety Equipment',
        'Industrial Supplies', 'Packaging Materials', 'Gift Items',
    ];

    private array $brandNames = [
        'Apex', 'NovaBrand', 'PeakLine', 'CoreTech', 'SkyMark',
        'BrightLeaf', 'IronCrest', 'SilverEdge', 'BluWave', 'GreenRoot',
        'Solaris', 'Velox', 'Stratum', 'Zenith', 'Orion',
        'TerraFirm', 'ArcLight', 'Nexus', 'PrimeCraft', 'Lumina',
        'Cascade', 'Fortis', 'Vertex', 'Halcyon', 'Meridian',
        'CedarCo', 'MapleMark', 'OakBrand', 'StonePeak', 'RiverRun',
        'FrostLine', 'SunPath', 'DuskBrand', 'DawnWave', 'NightOwl',
        'SeaBrand', 'LandMark', 'AirWave', 'FireCore', 'EarthCraft',
        'CosmoBrand', 'GalaxiCo', 'StarMark', 'NebulaTech', 'QuantumCo',
        'BoltBrand', 'SparkLine', 'FluxCo', 'PulseCore', 'EchoBrand',
    ];

    private array $productPrefixes = [
        'Premium', 'Ultra', 'Pro', 'Max', 'Elite', 'Essential',
        'Classic', 'Deluxe', 'Standard', 'Advanced', 'Smart', 'Super',
        'Eco', 'Compact', 'Heavy Duty', 'Portable', 'Wireless', 'Digital',
        'Organic', 'Natural', 'Pure', 'Fresh', 'Lite', 'Plus',
    ];

    private array $productTypes = [
        'Kit', 'Pack', 'Set', 'Bundle', 'Box', 'Unit', 'Combo',
        'Series', 'Edition', 'Collection', 'Model', 'Version',
        'Grade', 'Batch', 'Package', 'Range', 'Line',
    ];

    public function run(): void
    {
        $business = Business::query()->latest()->first();

        if ($business === null) {
            $this->command->error('No business found. Create a business first.');
            return;
        }

        $businessId = $business->id;
        $now = now();

        $this->command->info("Seeding dummy data for business: {$business->name} (ID: {$businessId})");

        // ── Categories ────────────────────────────────────────────────
        $this->command->info('Creating ' . self::CATEGORY_COUNT . ' categories...');

        $categoryRows = [];
        for ($i = 0; $i < self::CATEGORY_COUNT; $i++) {
            $name = $this->categoryNames[$i] ?? "Category {$i}";
            $categoryRows[] = [
                'business_id' => $businessId,
                'parent_id'   => null,
                'name'        => $name,
                'description' => "Dummy description for {$name}",
                'sort_order'  => $i + 1,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('product_categories')->insert($categoryRows);
        $categoryIds = DB::table('product_categories')
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->limit(self::CATEGORY_COUNT)
            ->pluck('id')
            ->toArray();

        // ── Brands ────────────────────────────────────────────────────
        $this->command->info('Creating ' . self::BRAND_COUNT . ' brands...');

        $brandRows = [];
        for ($i = 0; $i < self::BRAND_COUNT; $i++) {
            $name = $this->brandNames[$i] ?? "Brand {$i}";
            $slug = strtolower(str_replace(' ', '-', $name));
            $brandRows[] = [
                'business_id' => $businessId,
                'name'        => $name,
                'description' => "Dummy description for {$name}",
                'website'     => "https://www.{$slug}.example.com",
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('product_brands')->insert($brandRows);
        $brandIds = DB::table('product_brands')
            ->where('business_id', $businessId)
            ->orderByDesc('id')
            ->limit(self::BRAND_COUNT)
            ->pluck('id')
            ->toArray();

        // ── Products ──────────────────────────────────────────────────
        $this->command->info('Creating ' . self::PRODUCT_COUNT . ' products...');

        $chunkSize   = 250;
        $totalChunks = (int) ceil(self::PRODUCT_COUNT / $chunkSize);
        $bar         = $this->command->getOutput()->createProgressBar($totalChunks);

        $productIds = [];
        $skuCounter = 1;

        for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
            $rows  = [];
            $start = $chunk * $chunkSize;
            $end   = min($start + $chunkSize, self::PRODUCT_COUNT);

            for ($i = $start; $i < $end; $i++) {
                $prefix  = $this->productPrefixes[array_rand($this->productPrefixes)];
                $type    = $this->productTypes[array_rand($this->productTypes)];
                $catName = $this->categoryNames[array_rand($this->categoryNames)];
                $name    = "{$prefix} {$catName} {$type} " . ($i + 1);
                $sku     = 'SKU-' . str_pad((string) $skuCounter++, 6, '0', STR_PAD_LEFT);

                $rows[] = [
                    'business_id'        => $businessId,
                    'file_manager_file_id' => null,
                    'product_unit_id'    => null,
                    'name'               => $name,
                    'sku'                => $sku,
                    'description'        => "Dummy product: {$name}",
                    'unit'               => 'pcs',
                    'unit_price'         => round(mt_rand(100, 99900) / 100, 2),
                    'stock_quantity'     => mt_rand(0, 500),
                    'is_active'          => true,
                    'is_bundle'          => false,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            DB::table('products')->insert($rows);

            $inserted = DB::table('products')
                ->where('business_id', $businessId)
                ->orderByDesc('id')
                ->limit(count($rows))
                ->pluck('id')
                ->toArray();

            $productIds = array_merge($productIds, $inserted);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        // ── Category pivot ────────────────────────────────────────────
        $this->command->info('Attaching categories to products...');

        $categoryPivot = [];
        foreach ($productIds as $productId) {
            $count  = mt_rand(1, 3);
            $picked = (array) array_rand(array_flip($categoryIds), min($count, count($categoryIds)));
            foreach ($picked as $catId) {
                $categoryPivot[] = [
                    'product_id'          => $productId,
                    'product_category_id' => $catId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        foreach (array_chunk($categoryPivot, 1000) as $pivotChunk) {
            DB::table('product_product_category')->insert($pivotChunk);
        }

        // ── Brand pivot ───────────────────────────────────────────────
        $this->command->info('Attaching brands to products...');

        $brandPivot = [];
        foreach ($productIds as $productId) {
            $brandId      = $brandIds[array_rand($brandIds)];
            $brandPivot[] = [
                'product_id'       => $productId,
                'product_brand_id' => $brandId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        foreach (array_chunk($brandPivot, 1000) as $pivotChunk) {
            DB::table('product_product_brand')->insert($pivotChunk);
        }

        $this->command->info('Done! Seeded ' . self::CATEGORY_COUNT . ' categories, ' . self::BRAND_COUNT . ' brands, ' . self::PRODUCT_COUNT . ' products.');
    }
}
