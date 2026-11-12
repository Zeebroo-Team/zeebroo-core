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

        $data  = $this->service->browse($business, $folderId);
        $files = $data['files'];

        if ($imgOnly) {
            $files = $files->filter(fn ($f) => $f->isImage());
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
        $folder   = $this->service->createFolder($business, $parentId, $validated['name']);

        return response()->json(['data' => ['id' => $folder->id, 'name' => $folder->name]], 201);
    }

    public function destroyFolder(Request $request, FileManagerFolder $folder): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        abort_unless($this->service->folderForBusiness($business, $folder->id) instanceof FileManagerFolder, 404);

        $this->service->deleteFolder($folder);

        return response()->json(['message' => 'Folder removed.']);
    }

    /** @return array<string, mixed> */
    private function filePayload(FileManagerFile $file): array
    {
        return [
            'id'         => $file->id,
            'name'       => $file->original_filename,
            'url'        => $file->publicUrl(),
            'mime'       => $file->mime_type,
            'size'       => $file->humanSize(),
            'is_image'   => $file->isImage(),
            'managed'    => $file->isManaged(),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }
}
