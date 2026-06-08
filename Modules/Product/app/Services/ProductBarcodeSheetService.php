<?php

namespace Modules\Product\Services;

use Modules\Business\Models\Business;
use Modules\Product\Models\ProductBarcodeSheet;

class ProductBarcodeSheetService
{
    public function create(Business $business, array $data): ProductBarcodeSheet
    {
        return $business->productBarcodeSheets()->create($data);
    }

    public function update(ProductBarcodeSheet $sheet, array $data): void
    {
        $sheet->update($data);
    }

    public function delete(ProductBarcodeSheet $sheet): void
    {
        $sheet->delete();
    }

    public function sheetForBusiness(Business $business, ProductBarcodeSheet $sheet): ?ProductBarcodeSheet
    {
        return $business->productBarcodeSheets()->find($sheet->id);
    }
}
