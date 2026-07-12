<?php

namespace Modules\FileManager\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Models\FileManagerFolder;

class FileManagerService
{
    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip', 'rar', '7z',
    ];

    public const MAX_FILE_KB = 20480;

    /**
     * @return array{folder: ?FileManagerFolder, folders: Collection<int, FileManagerFolder>, files: Collection<int, FileManagerFile>, breadcrumbs: list<array{id: ?int, name: string}>}
     */
    public function browse(Business $business, ?int $folderId): array
    {
        $folder = null;
        if ($folderId !== null) {
            $folder = $this->folderForBusiness($business, $folderId);
            abort_unless($folder instanceof FileManagerFolder, 404);
        }

        $folders = FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->where('parent_id', $folder?->id)
            ->orderBy('name')
            ->get();

        $files = FileManagerFile::query()
            ->where('business_id', $business->id)
            ->where('folder_id', $folder?->id)
            ->orderByDesc('created_at')
            ->get();

        return [
            'folder' => $folder,
            'folders' => $folders,
            'files' => $files,
            'breadcrumbs' => $this->breadcrumbs($folder),
        ];
    }

    public function createFolder(Business $business, ?int $parentId, string $name): FileManagerFolder
    {
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->folderForBusiness($business, $parentId);
            abort_unless($parent instanceof FileManagerFolder, 404);
        }

        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Folder name is required.']);
        }

        $exists = FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->where('parent_id', $parent?->id)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'A folder with this name already exists here.']);
        }

        return FileManagerFolder::query()->create([
            'business_id' => $business->id,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    public function storeBinary(
        Business $business,
        string $binary,
        string $mimeType,
        string $originalFilename,
        string $storedFilename,
        ?int $folderId,
        ?int $uploadedByUserId,
        ?string $notes = null,
    ): FileManagerFile {
        if ($folderId !== null) {
            abort_unless($this->folderForBusiness($business, $folderId) instanceof FileManagerFolder, 404);
        }

        $dir = 'business-files/'.$business->id.'/'.($folderId ?? 'root');
        $storedPath = $dir.'/'.ltrim($storedFilename, '/');

        Storage::disk('public')->put($storedPath, $binary);

        return FileManagerFile::query()->create([
            'business_id' => $business->id,
            'folder_id' => $folderId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'original_filename' => Str::limit($originalFilename, 255, ''),
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($binary) ?: null,
            'notes' => filled($notes) ? trim($notes) : null,
        ]);
    }

    public function storeFile(
        Business $business,
        UploadedFile $file,
        ?int $folderId,
        ?int $uploadedByUserId,
        ?string $notes = null,
    ): FileManagerFile {
        if ($folderId !== null) {
            abort_unless($this->folderForBusiness($business, $folderId) instanceof FileManagerFolder, 404);
        }

        $dir = 'business-files/'.$business->id.'/'.($folderId ?? 'root');
        $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        if ($safeBase === '') {
            $safeBase = 'file';
        }
        $ext = $file->getClientOriginalExtension();
        $filename = $safeBase.'-'.Str::lower(Str::random(8)).($ext !== '' ? '.'.$ext : '');
        $storedPath = $file->storeAs($dir, $filename, 'public');

        return FileManagerFile::query()->create([
            'business_id' => $business->id,
            'folder_id' => $folderId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'notes' => filled($notes) ? trim($notes) : null,
        ]);
    }

    public function deleteFile(FileManagerFile $file): void
    {
        if ($file->stored_path !== '' && Storage::disk('public')->exists($file->stored_path)) {
            Storage::disk('public')->delete($file->stored_path);
        }
        $file->delete();
    }

    public function deleteFolder(FileManagerFolder $folder): void
    {
        if ($folder->children()->exists()) {
            throw ValidationException::withMessages(['folder' => 'Remove subfolders first.']);
        }
        if ($folder->files()->exists()) {
            throw ValidationException::withMessages(['folder' => 'Remove files in this folder first.']);
        }

        $folder->delete();
    }

    public function fileForBusiness(Business $business, FileManagerFile $file): ?FileManagerFile
    {
        if ((int) $file->business_id !== (int) $business->id) {
            return null;
        }

        return $file;
    }

    public function folderForBusiness(Business $business, int $folderId): ?FileManagerFolder
    {
        return FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->whereKey($folderId)
            ->first();
    }

    public function productsFolder(Business $business): FileManagerFolder
    {
        $existing = FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->whereNull('parent_id')
            ->where('name', 'Products')
            ->first();

        if ($existing instanceof FileManagerFolder) {
            return $existing;
        }

        return $this->createFolder($business, null, 'Products');
    }

    public function crmFolder(Business $business): FileManagerFolder
    {
        $existing = FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->whereNull('parent_id')
            ->where('name', 'CRM')
            ->first();

        if ($existing instanceof FileManagerFolder) {
            return $existing;
        }

        return $this->createFolder($business, null, 'CRM');
    }

    /**
     * @return Collection<int, FileManagerFile>
     */
    public function listImagesForBusiness(Business $business, int $limit = 60): Collection
    {
        return FileManagerFile::query()
            ->where('business_id', $business->id)
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function businessHasAnyItems(Business $business): bool
    {
        return $business->fileManagerFiles()->exists()
            || $business->fileManagerFolders()->exists();
    }

    /**
     * @return list<array{id: ?int, name: string}>
     */
    private function breadcrumbs(?FileManagerFolder $folder): array
    {
        $crumbs = [['id' => null, 'name' => 'All files']];
        if (!$folder) {
            return $crumbs;
        }

        $chain = [];
        $current = $folder;
        while ($current) {
            $chain[] = ['id' => $current->id, 'name' => $current->name];
            if ($current->parent_id && !$current->relationLoaded('parent')) {
                $current->load('parent');
            }
            $current = $current->parent;
        }

        return array_merge($crumbs, array_reverse($chain));
    }
}
