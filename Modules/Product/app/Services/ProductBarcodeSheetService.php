<?php

namespace Modules\Product\Services;

use Modules\Business\Models\Business;
use Modules\Product\Models\ProductBarcodeSheet;

class ProductBarcodeSheetService
{
    public function create(Business $business, array $data): ProductBarcodeSheet
    {
        $sheet = $business->productBarcodeSheets()->create($data);
        $sheet->loadMissing('product');

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('barcode.sheet.created', $business, [
                'event'  => 'barcode.sheet.created',
                'sheet'  => [
                    'id'             => $sheet->id,
                    'name'           => $sheet->name,
                    'total_quantity' => $sheet->total_quantity,
                    'label_type'     => $sheet->label_type,
                    'encode_type'    => $sheet->encode_type,
                    'created_at'     => $sheet->created_at?->toIso8601String(),
                ],
                'product' => $sheet->product ? ['id' => $sheet->product->id, 'name' => $sheet->product->name, 'sku' => $sheet->product->sku] : [],
            ]);
        } catch (\Throwable) {}

        return $sheet;
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
