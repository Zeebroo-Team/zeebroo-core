<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Services\CrmImageService;
use Modules\FileManager\Services\FileManagerService;

class CrmImageController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly CrmImageService $crmImageService,
        private readonly FileManagerService $fileManagerService,
    ) {}

    public function picker(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $images = $this->fileManagerService->listImagesForBusiness($business, 80);

        return response()->json([
            'images' => $images->map(fn ($file) => [
                'id'   => $file->id,
                'name' => $file->original_filename,
                'url'  => $file->publicUrl(),
            ])->values(),
            'files_url' => route('filemanager.index', ['folder' => $this->fileManagerService->crmFolder($business)->id]),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $validated = $request->validate([
            'image' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $this->crmImageService->uploadImage($business, $validated['image'], (int) $request->user()->id);

        $payload = [
            'id'   => $file->id,
            'name' => $file->original_filename,
            'url'  => $file->publicUrl(),
        ];

        return response()->json(array_merge($payload, ['image' => $payload]));
    }

    public function generate(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'prompt'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $file = $this->crmImageService->generateWithGemini(
                $business,
                $validated['subject'] ?? null,
                $validated['prompt'] ?? null,
                (int) $request->user()->id,
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $payload = [
            'id'   => $file->id,
            'name' => $file->original_filename,
            'url'  => $file->publicUrl(),
        ];

        return response()->json(array_merge($payload, ['image' => $payload]));
    }
}
