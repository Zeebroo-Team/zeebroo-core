<?php

namespace Modules\FileManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\FileManager\Http\Controllers\Concerns\ResolvesFileManagerBusiness;
use Modules\FileManager\Models\FileManagerFolder;
use Modules\FileManager\Services\FileManagerService;

class FileManagerFolderController extends Controller
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
            'parent_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        if ($parentId === 0) {
            $parentId = null;
        }

        $folder = $this->fileManagerService->createFolder($business, $parentId, $validated['name']);

        return redirect()
            ->route('filemanager.index', ['folder' => $folder->id])
            ->with('status', 'Folder created.');
    }

    public function destroy(Request $request, FileManagerFolder $folder): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->fileManagerService->folderForBusiness($business, $folder->id) instanceof FileManagerFolder, 404);

        $parentId = $folder->parent_id;
        $this->fileManagerService->deleteFolder($folder);

        return redirect()
            ->route('filemanager.index', array_filter(['folder' => $parentId]))
            ->with('status', 'Folder removed.');
    }
}
