<?php

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

class DemoProductInsertService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductSkuGeneratorService $skuGenerator,
    ) {
    }

    /**
     * @return list<array{name: string, description: string, unit_price: float, stock_quantity: float, unit: string}>
     */
    public function demoDefinitions(): array
    {
        return [
            [
                'name' => 'Office Chair — Ergonomic',
                'description' => 'Demo product. Adjustable height, mesh back.',
                'unit_price' => 18500.00,
                'stock_quantity' => 12.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'A4 Copy Paper (Ream)',
                'description' => 'Demo product. 500 sheets, 80 gsm.',
                'unit_price' => 650.00,
                'stock_quantity' => 240.000,
                'unit' => 'ream',
            ],
            [
                'name' => 'USB-C Cable 2m',
                'description' => 'Demo product. Fast charge compatible.',
                'unit_price' => 890.00,
                'stock_quantity' => 85.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Arabica Coffee Beans 1kg',
                'description' => 'Demo product. Medium roast, whole bean.',
                'unit_price' => 3200.00,
                'stock_quantity' => 36.000,
                'unit' => 'kg',
            ],
            [
                'name' => 'Hand Sanitizer 500ml',
                'description' => 'Demo product. 70% alcohol gel.',
                'unit_price' => 420.00,
                'stock_quantity' => 120.000,
                'unit' => 'bottle',
            ],
            [
                'name' => 'LED Bulb 9W Warm White',
                'description' => 'Demo product. E27 base, 806 lm.',
                'unit_price' => 380.00,
                'stock_quantity' => 200.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Hardcover Notebook A5',
                'description' => 'Demo product. 192 ruled pages.',
                'unit_price' => 550.00,
                'stock_quantity' => 64.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Laser Printer Toner (Black)',
                'description' => 'Demo product. Approx. 2,500 pages yield.',
                'unit_price' => 4500.00,
                'stock_quantity' => 18.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Stainless Water Bottle 750ml',
                'description' => 'Demo product. Insulated, BPA-free.',
                'unit_price' => 1250.00,
                'stock_quantity' => 45.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Wireless Mouse',
                'description' => 'Demo product. 2.4 GHz, 1600 DPI.',
                'unit_price' => 1100.00,
                'stock_quantity' => 55.000,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Packaging Tape 48mm',
                'description' => 'Demo product. Brown, 66 m roll.',
                'unit_price' => 280.00,
                'stock_quantity' => 150.000,
                'unit' => 'roll',
            ],
            [
                'name' => 'Desk Organizer Set',
                'description' => 'Demo product. Pen holder, tray, and clips.',
                'unit_price' => 980.00,
                'stock_quantity' => 28.000,
                'unit' => 'set',
            ],
        ];
    }

    /**
     * @return array{created: int, skipped: int, products: Collection<int, Product>}
     */
    public function insertForBusiness(Business $business, int $limit, bool $dryRun = false): array
    {
        $definitions = array_slice($this->demoDefinitions(), 0, max(1, $limit));
        $existingNames = $business->products()
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->flip();

        $created = 0;
        $skipped = 0;
        $products = collect();

        foreach ($definitions as $definition) {
            $nameKey = mb_strtolower(trim($definition['name']));
            if ($existingNames->has($nameKey)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $created++;

                continue;
            }

            $product = $this->productService->create($business, [
                'name' => $definition['name'],
                'sku' => $this->skuGenerator->generate($business, null, $definition['name']),
                'description' => $definition['description'],
                'unit' => $definition['unit'],
                'unit_price' => $definition['unit_price'],
                'stock_quantity' => $definition['stock_quantity'],
                'is_active' => true,
                'is_bundle' => false,
                'product_category_ids' => [],
                'product_brand_ids' => [],
                'product_unit_id' => null,
                'file_manager_file_ids' => [],
            ]);

            $existingNames->put($nameKey, true);
            $products->push($product);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'products' => $products,
        ];
    }
}
