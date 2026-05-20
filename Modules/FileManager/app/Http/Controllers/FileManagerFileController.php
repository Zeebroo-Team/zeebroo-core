<?php

namespace Modules\FileManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\FileManager\Http\Controllers\Concerns\ResolvesFileManagerBusiness;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerFileController extends Controller
{
    use ResolvesFileManagerBusiness;

    public function __construct(private readonly FileManagerService $fileManagerService)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'file',
                'max:'.FileManagerService::MAX_FILE_KB,
                'mimes:'.implode(',', FileManagerService::ALLOWED_MIMES),
            ],
        ]);

        $folderId = isset($validated['folder_id']) ? (int) $validated['folder_id'] : null;
        if ($folderId === 0) {
            $folderId = null;
        }

        foreach ($request->file('files', []) as $uploaded) {
            $this->fileManagerService->storeFile(
                $business,
                $uploaded,
                $folderId,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
            );
        }

        $redirect = redirect()->route('filemanager.index', array_filter(['folder' => $folderId]));

        return $redirect->with('status', 'File(s) uploaded.');
    }

    public function download(Request $request, FileManagerFile $file): StreamedResponse|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->fileManagerService->fileForBusiness($business, $file) instanceof FileManagerFile, 404);
        abort_unless(Storage::disk('public')->exists($file->stored_path), 404);

        return Storage::disk('public')->download($file->stored_path, $file->original_filename);
    }

    public function destroy(Request $request, FileManagerFile $file): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->fileManagerService->fileForBusiness($business, $file) instanceof FileManagerFile, 404);

        $folderId = $file->folder_id;
        $this->fileManagerService->deleteFile($file);

        return redirect()
            ->route('filemanager.index', array_filter(['folder' => $folderId]))
            ->with('status', 'File removed.');
    }
}
