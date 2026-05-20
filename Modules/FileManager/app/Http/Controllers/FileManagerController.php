<?php

namespace Modules\FileManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\FileManager\Http\Controllers\Concerns\ResolvesFileManagerBusiness;
use Modules\FileManager\Services\FileManagerService;

class FileManagerController extends Controller
{
    use ResolvesFileManagerBusiness;

    public function __construct(private readonly FileManagerService $fileManagerService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $folderId = $request->filled('folder') ? (int) $request->query('folder') : null;
        $browse = $this->fileManagerService->browse($business, $folderId);

        return view('filemanager::index', [
            'business' => $business,
            'currentFolder' => $browse['folder'],
            'folders' => $browse['folders'],
            'files' => $browse['files'],
            'breadcrumbs' => $browse['breadcrumbs'],
            'hasAnyItems' => $this->fileManagerService->businessHasAnyItems($business),
            'isEmptyHere' => $browse['folders']->isEmpty() && $browse['files']->isEmpty(),
        ]);
    }
}
