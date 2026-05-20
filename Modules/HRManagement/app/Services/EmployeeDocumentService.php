<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\EmployeeDocument;

final class EmployeeDocumentService
{
    public function store(Employee $employee, UploadedFile $file, string $category, ?int $uploadedByUserId): EmployeeDocument
    {
        $dir = 'hr-employees/'.$employee->business_id.'/'.$employee->id.'/documents';
        $storedPath = $file->store($dir, 'public');

        return EmployeeDocument::query()->create([
            'business_id' => $employee->business_id,
            'employee_id' => $employee->id,
            'category' => $category,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);
    }

    public function delete(EmployeeDocument $document): void
    {
        if ($document->stored_path !== '' && Storage::disk('public')->exists($document->stored_path)) {
            Storage::disk('public')->delete($document->stored_path);
        }
        $document->delete();
    }
}
