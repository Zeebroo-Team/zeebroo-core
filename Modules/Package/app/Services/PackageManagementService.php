<?php

namespace Modules\Package\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Package\Models\Package;

class PackageManagementService
{
    private const IMAGE_DIR = 'packages';

    public function create(array $data, ?UploadedFile $image): Package
    {
        $data['slug'] = $this->uniqueSlug($data['name']);

        if ($image) {
            $data['image'] = $this->storeImage($image);
        }

        return Package::create($data);
    }

    public function update(Package $package, array $data, ?UploadedFile $image): Package
    {
        if ($package->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $package->id);
        }

        if ($image) {
            $this->deleteImage($package->image);
            $data['image'] = $this->storeImage($image);
        }

        $package->update($data);

        return $package;
    }

    public function delete(Package $package): void
    {
        $this->deleteImage($package->image);
        $package->delete();
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store(self::IMAGE_DIR, 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Package::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
