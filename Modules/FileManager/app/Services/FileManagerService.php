<?php

namespace Modules\FileManager\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
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

    /** managed_by values only business admins may see and open here (staff signatures). */
    public const ADMIN_ONLY_MANAGED = ['crm-signature'];

    /** The folder those files live in — hidden from everyone but admins. */
    public const ADMIN_ONLY_FOLDER = ['CRM', 'Signatures'];

    /**
     * @param  bool  $showAdminOnly  true for a business admin: also list the admin-only folder / files
     * @return array{folder: ?FileManagerFolder, folders: Collection<int, FileManagerFolder>, files: Collection<int, FileManagerFile>, breadcrumbs: list<array{id: ?int, name: string}>}
     */
    public function browse(Business $business, ?int $folderId, bool $showAdminOnly = false): array
    {
        $folder = null;
        if ($folderId !== null) {
            $folder = $this->folderForBusiness($business, $folderId);
            abort_unless($folder instanceof FileManagerFolder, 404);
        }
        $hiddenFolderId = $showAdminOnly ? null : $this->adminOnlyFolderId($business);
        abort_if($hiddenFolderId !== null && $folder !== null && $this->isInside($folder, $hiddenFolderId), 404);

        $folders = FileManagerFolder::query()
            ->where('business_id', $business->id)
            ->where('parent_id', $folder?->id)
            ->when($hiddenFolderId !== null, fn ($q) => $q->whereKeyNot($hiddenFolderId))
            ->orderBy('name')
            ->get();

        $files = FileManagerFile::query()
            ->where('business_id', $business->id)
            ->where('folder_id', $folder?->id)
            ->when(! $showAdminOnly, fn ($q) => $this->withoutAdminOnly($q))
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

    /**
     * @param  string       $disk       where to store it; anything but "public" has no public URL
     * @param  string|null  $managedBy  the module that serves and deletes the file itself (e.g. "crm")
     */
    public function storeFile(
        Business $business,
        UploadedFile $file,
        ?int $folderId,
        ?int $uploadedByUserId,
        ?string $notes = null,
        string $disk = 'public',
        ?string $managedBy = null,
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
        $storedPath = $file->storeAs($dir, $filename, $disk);

        return FileManagerFile::query()->create([
            'business_id' => $business->id,
            'folder_id' => $folderId,
            'uploaded_by_user_id' => $uploadedByUserId,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'notes' => filled($notes) ? trim($notes) : null,
        ] + ($disk !== 'public' ? ['disk' => $disk] : []) + ($managedBy !== null ? ['managed_by' => $managedBy] : []));
    }

    public function deleteFile(FileManagerFile $file): void
    {
        $disk = Storage::disk($file->diskName());
        if ($file->stored_path !== '' && $disk->exists($file->stored_path)) {
            $disk->delete($file->stored_path);
        }
        $file->delete();
    }

    /** The folder at this path under the root (e.g. ["CRM", "Leads", "12"]), created as needed. */
    public function ensureFolderPath(Business $business, array $names): ?FileManagerFolder
    {
        $folder = null;
        foreach ($names as $name) {
            $name = trim((string) $name);
            $existing = FileManagerFolder::query()
                ->where('business_id', $business->id)
                ->where('parent_id', $folder?->id)
                ->where('name', $name)
                ->first();

            $folder = $existing ?? $this->createFolder($business, $folder?->id, $name);
        }

        return $folder;
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
            ->where(fn ($q) => $this->withoutAdminOnly($q))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function businessHasAnyItems(Business $business): bool
    {
        return $business->fileManagerFiles()->exists()
            || $business->fileManagerFolders()->exists();
    }

    /** Owner, an "admin" member, or a member whose role grants full access (same rule as the CRM / /pos/me). */
    public function isBusinessAdmin(Business $business, ?int $userId): bool
    {
        if (! $userId) {
            return false;
        }
        if ((int) $business->user_id === $userId) {
            return true;
        }
        $member = BusinessMember::query()->where('business_id', $business->id)->where('user_id', $userId)->first();
        if ($member === null) {
            return false;
        }
        if ($member->role === 'admin') {
            return true;
        }
        $role = BusinessRole::query()->where('business_id', $business->id)->where('slug', $member->role)->first();

        return $role !== null && $role->permissions === null;
    }

    /** The admin-only folder (CRM / Signatures) if it exists — never created here. */
    public function adminOnlyFolderId(Business $business): ?int
    {
        $parentId = null;
        foreach (self::ADMIN_ONLY_FOLDER as $name) {
            $parentId = FileManagerFolder::query()
                ->where('business_id', $business->id)
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->value('id');
            if ($parentId === null) {
                return null;
            }
        }

        return (int) $parentId;
    }

    private function withoutAdminOnly($query)
    {
        return $query->where(fn ($q) => $q->whereNull('managed_by')->orWhereNotIn('managed_by', self::ADMIN_ONLY_MANAGED));
    }

    private function isInside(FileManagerFolder $folder, int $ancestorId): bool
    {
        for ($f = $folder; $f !== null; $f = $f->parent) {
            if ((int) $f->id === $ancestorId) {
                return true;
            }
        }

        return false;
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
