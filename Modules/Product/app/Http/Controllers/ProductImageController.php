<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Modules\FileManager\Services\FileManagerService;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Services\ProductImageService;

class ProductImageController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(
        private readonly ProductImageService $productImageService,
        private readonly FileManagerService $fileManagerService,
    ) {
    }

    public function picker(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $images = $this->fileManagerService->listImagesForBusiness($business, 80);

        return response()->json([
            'images' => $images->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_filename,
                'url' => $file->publicUrl(),
            ])->values(),
            'files_url' => route('filemanager.index', ['folder' => $this->fileManagerService->productsFolder($business)->id]),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $rules = [
            'max:5120',
            'mimes:jpg,jpeg,png,gif,webp',
        ];

        if ($request->hasFile('images')) {
            $request->validate([
                'images' => ['required', 'array', 'max:20'],
                'images.*' => array_merge(['file'], $rules),
            ]);
            $uploaded = [];
            foreach ($request->file('images') as $uploadedFile) {
                $file = $this->productImageService->uploadImage(
                    $business,
                    $uploadedFile,
                    (int) $request->user()->id,
                );
                $uploaded[] = [
                    'id' => $file->id,
                    'name' => $file->original_filename,
                    'url' => $file->publicUrl(),
                ];
            }

            return response()->json([
                'images' => $uploaded,
                'image' => $uploaded[0] ?? null,
            ]);
        }

        $validated = $request->validate([
            'image' => array_merge(['required', 'file'], $rules),
        ]);

        $file = $this->productImageService->uploadImage(
            $business,
            $validated['image'],
            (int) $request->user()->id,
        );

        $payload = [
            'id' => $file->id,
            'name' => $file->original_filename,
            'url' => $file->publicUrl(),
        ];

        return response()->json(array_merge($payload, [
            'image' => $payload,
            'images' => [$payload],
        ]));
    }

    public function generate(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        $validated = $request->validate([
            'product_name' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $file = $this->productImageService->generateWithGemini(
                $business,
                $validated['product_name'] ?? null,
                $validated['prompt'] ?? null,
                (int) $request->user()->id,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        $payload = [
            'id' => $file->id,
            'name' => $file->original_filename,
            'url' => $file->publicUrl(),
        ];

        return response()->json(array_merge($payload, [
            'image' => $payload,
            'images' => [$payload],
        ]));
    }
}
