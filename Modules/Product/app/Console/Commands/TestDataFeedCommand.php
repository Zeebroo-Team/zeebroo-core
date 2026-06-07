<?php

namespace Modules\Product\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;

class TestDataFeedCommand extends Command
{
    protected $signature = 'test-data:feed
        {username   : User email address}
        {business   : Business name (exact match, case-insensitive)}
        {--categories=50 : Number of categories to create}
        {--brands=50     : Number of brands to create}
        {--products=5000 : Number of products to create}
        {--fresh         : Delete existing test-seeded data for this business first}';

    protected $description = 'Seed test categories, brands and products for a specific user and business';

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
        'Confectionery', 'Seafood', 'Meat & Poultry', 'Fruits & Vegetables',
        'Spices & Condiments', 'Oils & Fats', 'Cereals & Pulses', 'Beverages Hot',
        'Beverages Cold', 'Instant Foods', 'Canned Goods', 'Dry Goods',
        'Frozen Desserts', 'Organic Products', 'Gluten-Free', 'Vegan Products',
        'Craft Supplies', 'Party Supplies', 'Seasonal Items', 'Holiday Decor',
        'Home Decor', 'Wall Art', 'Rugs & Mats', 'Curtains & Blinds',
        'Storage & Organisation', 'Bath Accessories', 'Shower Products',
        'Oral Care', 'Eye Care', 'Skin Care', 'Men\'s Grooming',
        'Women\'s Fashion', 'Men\'s Fashion', 'Kids\' Fashion', 'Sportswear',
        'Workwear', 'Formal Wear', 'Casual Wear', 'Accessories', 'Hats & Caps',
        'Socks & Hosiery', 'Underwear', 'Swimwear', 'Activewear', 'Rain Gear',
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
        'TitanMark', 'GlideCo', 'SwiftLine', 'BoldBrand', 'ClearTech',
        'DeepBlue', 'HighCrest', 'SharpEdge', 'SoftWave', 'RapidCo',
        'PureCore', 'FreshBrand', 'NatureLine', 'EcoMark', 'GreenTech',
        'BlueStar', 'RedPeak', 'YellowLine', 'WhiteCore', 'BlackCraft',
        'SilkBrand', 'WoolMark', 'CottonCo', 'LinenLine', 'VelvetPeak',
    ];

    private array $productPrefixes = [
        'Premium', 'Ultra', 'Pro', 'Max', 'Elite', 'Essential',
        'Classic', 'Deluxe', 'Standard', 'Advanced', 'Smart', 'Super',
        'Eco', 'Compact', 'Heavy Duty', 'Portable', 'Wireless', 'Digital',
        'Organic', 'Natural', 'Pure', 'Fresh', 'Lite', 'Plus', 'Turbo',
        'Rapid', 'Flex', 'Bold', 'Clear', 'Slim', 'Wide', 'Long', 'Short',
    ];

    private array $productTypes = [
        'Kit', 'Pack', 'Set', 'Bundle', 'Box', 'Unit', 'Combo',
        'Series', 'Edition', 'Collection', 'Model', 'Version',
        'Grade', 'Batch', 'Package', 'Range', 'Line', 'Assortment',
        'Selection', 'Mix', 'Variety', 'Special',
    ];

    public function handle(): int
    {
        // ── Resolve user ───────────────────────────────────────────────
        $username = $this->argument('username');
        $user = User::query()->where('email', $username)->first();

        if ($user === null) {
            $this->error("User not found with email: {$username}");
            return self::FAILURE;
        }

        // ── Resolve business ───────────────────────────────────────────
        $businessName = $this->argument('business');
        $business = Business::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($businessName)])
            ->first();

        if ($business === null) {
            $this->error("Business '{$businessName}' not found for user {$username}.");
            $this->line('Available businesses for this user:');
            $businesses = Business::query()->where('user_id', $user->id)->pluck('name');
            if ($businesses->isEmpty()) {
                $this->line('  (none)');
            } else {
                $businesses->each(fn ($n) => $this->line("  - {$n}"));
            }
            return self::FAILURE;
        }

        $categoryCount = max(1, (int) $this->option('categories'));
        $brandCount    = max(1, (int) $this->option('brands'));
        $productCount  = max(1, (int) $this->option('products'));

        $this->info("User     : {$user->name} <{$user->email}>");
        $this->info("Business : {$business->name} (ID: {$business->id})");
        $this->info("Seeding  : {$categoryCount} categories · {$brandCount} brands · {$productCount} products");
        $this->newLine();

        if ($this->option('fresh')) {
            $this->wipePreviousData($business->id);
        }

        $businessId = $business->id;
        $now        = now();

        // ── Categories ─────────────────────────────────────────────────
        $this->line('  Creating categories...');
        $categoryRows = [];
        for ($i = 0; $i < $categoryCount; $i++) {
            $name           = $this->categoryNames[$i % count($this->categoryNames)];
            $name           = $categoryCount > count($this->categoryNames) ? "{$name} " . ($i + 1) : $name;
            $categoryRows[] = [
                'business_id' => $businessId,
                'parent_id'   => null,
                'name'        => $name,
                'description' => "Test category: {$name}",
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
            ->limit($categoryCount)
            ->pluck('id')
            ->toArray();
        $this->line("  <fg=green>✓</> {$categoryCount} categories created.");

        // ── Brands ─────────────────────────────────────────────────────
        $this->line('  Creating brands...');
        $brandRows = [];
        for ($i = 0; $i < $brandCount; $i++) {
            $name       = $this->brandNames[$i % count($this->brandNames)];
            $name       = $brandCount > count($this->brandNames) ? "{$name} " . ($i + 1) : $name;
            $slug       = strtolower(str_replace([' ', "'"], ['-', ''], $name));
            $brandRows[] = [
                'business_id' => $businessId,
                'name'        => $name,
                'description' => "Test brand: {$name}",
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
            ->limit($brandCount)
            ->pluck('id')
            ->toArray();
        $this->line("  <fg=green>✓</> {$brandCount} brands created.");

        // ── Products ───────────────────────────────────────────────────
        $this->line("  Creating {$productCount} products...");
        $bar        = $this->output->createProgressBar((int) ceil($productCount / 250));
        $productIds = [];
        $skuBase    = DB::table('products')->where('business_id', $businessId)->count();

        for ($chunk = 0; $chunk * 250 < $productCount; $chunk++) {
            $rows  = [];
            $start = $chunk * 250;
            $end   = min($start + 250, $productCount);

            for ($i = $start; $i < $end; $i++) {
                $prefix  = $this->productPrefixes[array_rand($this->productPrefixes)];
                $type    = $this->productTypes[array_rand($this->productTypes)];
                $catName = $this->categoryNames[array_rand($this->categoryNames)];
                $num     = $skuBase + $i + 1;
                $rows[]  = [
                    'business_id'          => $businessId,
                    'file_manager_file_id' => null,
                    'product_unit_id'      => null,
                    'name'                 => "{$prefix} {$catName} {$type} {$num}",
                    'sku'                  => 'TEST-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT),
                    'description'          => "Test product #{$num} for {$business->name}",
                    'unit'                 => 'pcs',
                    'unit_price'           => round(mt_rand(100, 99900) / 100, 2),
                    'stock_quantity'       => mt_rand(0, 500),
                    'is_active'            => true,
                    'is_bundle'            => false,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            DB::table('products')->insert($rows);

            $inserted   = DB::table('products')
                ->where('business_id', $businessId)
                ->orderByDesc('id')
                ->limit(count($rows))
                ->pluck('id')
                ->toArray();
            $productIds = array_merge($productIds, $inserted);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // ── Pivots ─────────────────────────────────────────────────────
        $this->line('  Attaching categories...');
        $catPivot = [];
        foreach ($productIds as $pid) {
            $picked = (array) array_rand(array_flip($categoryIds), min(mt_rand(1, 3), count($categoryIds)));
            foreach ($picked as $cid) {
                $catPivot[] = ['product_id' => $pid, 'product_category_id' => $cid, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($catPivot, 1000) as $ch) {
            DB::table('product_product_category')->insert($ch);
        }

        $this->line('  Attaching brands...');
        $brandPivot = [];
        foreach ($productIds as $pid) {
            $brandPivot[] = ['product_id' => $pid, 'product_brand_id' => $brandIds[array_rand($brandIds)], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($brandPivot, 1000) as $ch) {
            DB::table('product_product_brand')->insert($ch);
        }

        $this->newLine();
        $this->info("All done! Test data seeded for [{$business->name}]:");
        $this->table(
            ['Type', 'Count'],
            [
                ['Categories', $categoryCount],
                ['Brands',     $brandCount],
                ['Products',   $productCount],
            ]
        );

        return self::SUCCESS;
    }

    private function wipePreviousData(int $businessId): void
    {
        $this->line('  <fg=yellow>--fresh: removing existing products, categories and brands...</>');

        $productIds = DB::table('products')->where('business_id', $businessId)->pluck('id');
        DB::table('product_product_category')->whereIn('product_id', $productIds)->delete();
        DB::table('product_product_brand')->whereIn('product_id', $productIds)->delete();
        DB::table('products')->where('business_id', $businessId)->delete();
        DB::table('product_categories')->where('business_id', $businessId)->delete();
        DB::table('product_brands')->where('business_id', $businessId)->delete();

        $this->line('  <fg=green>✓</> Existing data cleared.');
    }
}
