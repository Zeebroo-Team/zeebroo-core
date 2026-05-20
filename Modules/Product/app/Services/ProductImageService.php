<?php

namespace Modules\Product\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use Modules\Product\Models\Product;

class ProductImageService
{
    public function __construct(
        private readonly FileManagerService $fileManagerService,
        private readonly ProductGeminiImageService $geminiImageService,
    ) {
    }

    public function generateWithGemini(
        Business $business,
        ?string $productName,
        ?string $prompt,
        ?int $uploadedByUserId,
    ): FileManagerFile {
        return $this->geminiImageService->generateAndStore(
            $business,
            $productName,
            $prompt,
            $uploadedByUserId,
        );
    }

    public function uploadImage(Business $business, UploadedFile $file, ?int $uploadedByUserId): FileManagerFile
    {
        $folder = $this->fileManagerService->productsFolder($business);

        return $this->fileManagerService->storeFile(
            $business,
            $file,
            $folder->id,
            $uploadedByUserId,
            'Product image',
        );
    }

    public function resolveImageFileId(Business $business, mixed $fileId, bool $removeImage): ?int
    {
        if ($removeImage) {
            return null;
        }

        if ($fileId === null || $fileId === '') {
            return null;
        }

        $file = $this->fileManagerService->fileForBusiness($business, FileManagerFile::query()->find((int) $fileId));
        if (!$file instanceof FileManagerFile) {
            throw ValidationException::withMessages([
                'file_manager_file_id' => 'Selected image was not found for this business.',
            ]);
        }

        if (!$file->isImage()) {
            throw ValidationException::withMessages([
                'file_manager_file_id' => 'Only image files can be used as a product photo.',
            ]);
        }

        return (int) $file->id;
    }

    /**
     * @param  list<mixed>  $fileIds
     * @return list<int>
     */
    public function resolveImageFileIds(Business $business, array $fileIds, bool $removeAll): array
    {
        if ($removeAll) {
            return [];
        }

        $resolved = [];
        foreach ($fileIds as $fileId) {
            if ($fileId === null || $fileId === '') {
                continue;
            }
            $id = $this->resolveImageFileId($business, $fileId, false);
            if ($id !== null) {
                $resolved[] = $id;
            }
        }

        return array_values(array_unique($resolved));
    }

    public function syncProductImages(Product $product, array $fileIds): void
    {
        $product->productImages()->delete();

        foreach (array_values($fileIds) as $index => $fileId) {
            $product->productImages()->create([
                'file_manager_file_id' => $fileId,
                'sort_order' => $index,
            ]);
        }

        $product->file_manager_file_id = $fileIds[0] ?? null;
        $product->save();
    }
}
