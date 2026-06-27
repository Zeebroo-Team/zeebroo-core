<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'files'       => $files->map(fn ($f) => [
                'id'       => $f->id,
                'name'     => $f->original_filename,
                'url'      => $f->publicUrl(),
                'mime'     => $f->mime_type,
                'size'     => $f->humanSize(),
                'is_image' => $f->isImage(),
            ])->values(),
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
            $stored[] = [
                'id'       => $file->id,
                'name'     => $file->original_filename,
                'url'      => $file->publicUrl(),
                'mime'     => $file->mime_type,
                'is_image' => $file->isImage(),
            ];
        }

        return response()->json(['data' => $stored], 201);
    }
}
