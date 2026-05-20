<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\HRManagement\Models\Employee;

final class EmployeeProfilePhotoService
{
    public function store(Employee $employee, UploadedFile $file): void
    {
        $path = $file->store("hr-employees/{$employee->business_id}/{$employee->id}", 'public');
        $previous = $employee->profile_photo_path;
        $employee->profile_photo_path = $path;
        $employee->save();

        if ($previous !== null && $previous !== '' && $previous !== $path && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }
    }

    public function delete(Employee $employee): void
    {
        $path = $employee->profile_photo_path;
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        if ($employee->profile_photo_path !== null) {
            $employee->profile_photo_path = null;
            $employee->save();
        }
    }
}
