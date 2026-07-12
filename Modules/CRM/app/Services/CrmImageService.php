<?php

namespace Modules\CRM\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;

class CrmImageService
{
    public function __construct(
        private readonly FileManagerService $fileManagerService,
        private readonly CrmGeminiImageService $geminiImageService,
    ) {
    }

    public function uploadImage(Business $business, UploadedFile $file, ?int $uploadedByUserId): FileManagerFile
    {
        $folder = $this->fileManagerService->crmFolder($business);

        return $this->fileManagerService->storeFile(
            $business,
            $file,
            $folder->id,
            $uploadedByUserId,
            'CRM form image',
        );
    }

    public function generateWithGemini(Business $business, ?string $subject, ?string $prompt, ?int $uploadedByUserId): FileManagerFile
    {
        return $this->geminiImageService->generateAndStore($business, $subject, $prompt, $uploadedByUserId);
    }

    public function fileForBusiness(Business $business, int $fileId): ?FileManagerFile
    {
        $file = FileManagerFile::query()->find($fileId);
        if (!$file instanceof FileManagerFile) {
            return null;
        }

        return $this->fileManagerService->fileForBusiness($business, $file);
    }
}
