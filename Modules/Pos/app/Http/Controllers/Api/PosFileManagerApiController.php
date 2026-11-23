<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Models\FileManagerFolder;
use Modules\FileManager\Services\FileManagerService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosFileManagerApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly FileManagerService $service) {}

    public function browse(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $folderId  = $request->query('folder');
        $folderId  = is_numeric($folderId) ? (int) $folderId : null;
        $imgOnly   = $request->boolean('images_only', false);

        // Staff signatures (CRM / Signatures) are listed for business admins only
        $isAdmin = $this->service->isBusinessAdmin($business, (int) $request->user()?->id);
        $data  = $this->service->browse($business, $folderId, $isAdmin);
        $files = $data['files'];

        if ($imgOnly) {
            // Image pickers never offer signatures
            $files = $files->filter(fn ($f) => $f->isImage() && ! $f->isAdminOnly());
        }

        return response()->json([
            'folders'     => $data['folders']->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->values(),
            'files'       => $files->map(fn ($f) => $this->filePayload($f))->values(),
            'breadcrumbs' => $data['breadcrumbs'],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $request->validate([
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => [
                'file',
                'max:'.FileManagerService::MAX_FILE_KB,
                'mimes:'.implode(',', FileManagerService::ALLOWED_MIMES),
            ],
        ]);

        $folderId = $request->input('folder_id') ? (int) $request->input('folder_id') : null;
        $this->abortIfAdminOnlyFolder($request, $business, $folderId);
        $stored   = [];

        foreach ($request->file('files', []) as $uploaded) {
            $file     = $this->service->storeFile($business, $uploaded, $folderId, (int) $request->user()->id);
            $stored[] = $this->filePayload($file);
        }

        return response()->json(['data' => $stored], 201);
    }

    public function destroy(Request $request, FileManagerFile $file): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless($this->service->fileForBusiness($business, $file) instanceof FileManagerFile, 404);
        // A module-owned file (e.g. a CRM lead attachment) is only removed through that module
        abort_if($file->isManaged(), 403, 'This file belongs to '.strtoupper((string) $file->managed_by).'. Remove it from there.');

        $this->service->deleteFile($file);

        return response()->json(['message' => 'File removed.']);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parentId = !empty($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $this->abortIfAdminOnlyFolder($request, $business, $parentId);
        $folder   = $this->service->createFolder($business, $parentId, $validated['name']);

        return response()->json(['data' => ['id' => $folder->id, 'name' => $folder->name]], 201);
    }

    public function destroyFolder(Request $request, FileManagerFolder $folder): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless($this->service->folderForBusiness($business, $folder->id) instanceof FileManagerFolder, 404);
        abort_if((int) $folder->id === $this->service->adminOnlyFolderId($business), 403, 'This folder is managed by the CRM.');

        $this->service->deleteFolder($folder);

        return response()->json(['message' => 'Folder removed.']);
    }

    /** Nobody but an admin uploads into / adds folders under the admin-only folder (CRM / Signatures). */
    private function abortIfAdminOnlyFolder(Request $request, $business, ?int $folderId): void
    {
        $hidden = $this->service->adminOnlyFolderId($business);
        if ($folderId !== null && $hidden !== null && $folderId === $hidden
            && ! $this->service->isBusinessAdmin($business, (int) $request->user()?->id)) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function filePayload(FileManagerFile $file): array
    {
        return [
            'id'         => $file->id,
            'name'       => $file->original_filename,
            // A signature (listed to admins only) is handed out inline — it has no URL anyone could reuse
            'url'        => $file->isAdminOnly() ? $this->inlineImage($file) : $file->publicUrl(),
            'mime'       => $file->mime_type,
            'size'       => $file->humanSize(),
            'is_image'   => $file->isImage(),
            'managed'    => $file->isManaged(),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function inlineImage(FileManagerFile $file): string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk($file->diskName());
        if (! $file->isImage() || ! $disk->exists($file->stored_path)) {
            return '';
        }

        return 'data:' . $file->mime_type . ';base64,' . base64_encode((string) $disk->get($file->stored_path));
    }
}
