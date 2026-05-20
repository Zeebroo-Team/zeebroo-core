<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Business\Jobs\GenerateBusinessLogoJob;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessLogoGeneration;
use Modules\Business\Support\LogoGenerationCatalog;

class BusinessLogoGenerationController extends Controller
{
    public function dispatch(Request $request): JsonResponse
    {
        $business = $this->currentBusinessOrAbort($request);
        if (! trim((string) env('GEMINI_API_KEY', config('aibot.gemini.api_key', '')))) {
            return response()->json([
                'message' => 'Logo generation requires GEMINI_API_KEY to be configured.',
            ], 503);
        }

        $categoryKeys = array_column(LogoGenerationCatalog::categories(), 'value');
        $styleKeys = array_column(LogoGenerationCatalog::styles(), 'value');
        $bgKeys = array_column(LogoGenerationCatalog::backgrounds(), 'value');

        $validated = $request->validate([
            'company_category' => ['required', 'string', Rule::in($categoryKeys)],
            'logo_style' => ['required', 'string', Rule::in($styleKeys)],
            'background_theme' => ['required', 'string', Rule::in($bgKeys)],
            'custom_prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $generation = BusinessLogoGeneration::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $business->getKey(),
            'status' => BusinessLogoGeneration::STATUS_PENDING,
            'company_category' => $validated['company_category'],
            'logo_style' => $validated['logo_style'],
            'background_theme' => $validated['background_theme'],
            'custom_prompt' => $validated['custom_prompt'] ?? null,
            'logo_path' => null,
            'error_message' => null,
        ]);

        GenerateBusinessLogoJob::dispatch($generation->getKey())->afterResponse();

        return response()->json([
            'uuid' => $generation->uuid,
            'status' => $generation->status,
        ]);
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $business = $this->currentBusinessOrAbort($request);

        $generation = BusinessLogoGeneration::query()
            ->where('business_id', $business->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'status' => $generation->status,
            'preview_url' => filled($generation->logo_path)
                ? asset('storage/'.$generation->logo_path)
                : null,
            'error' => $generation->error_message,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $business = $this->currentBusinessOrAbort($request);

        $validated = $request->validate([
            'generation_uuid' => ['required', 'string', 'uuid'],
        ]);

        $generation = BusinessLogoGeneration::query()
            ->where('business_id', $business->getKey())
            ->where('uuid', $validated['generation_uuid'])
            ->firstOrFail();

        if ($generation->status !== BusinessLogoGeneration::STATUS_COMPLETED || ! filled($generation->logo_path)) {
            return redirect()->route('business.profile')->withErrors([
                'generation_uuid' => 'Wait until generation finishes, then try saving again.',
            ]);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($generation->logo_path)) {
            return redirect()->route('business.profile')->withErrors([
                'generation_uuid' => 'Generated file is missing—run Generate again.',
            ]);
        }

        $oldPath = $business->logo_path;
        $business->update([
            'logo_path' => $generation->logo_path,
        ]);

        if ($oldPath && $oldPath !== $generation->logo_path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        $business->setSetting('business.brand.logo_company_category', $generation->company_category);
        $business->setSetting('business.brand.logo_style', $generation->logo_style);
        $business->setSetting('business.brand.logo_background_theme', $generation->background_theme);
        $business->setSetting('business.brand.logo_custom_prompt', (string) ($generation->custom_prompt ?? ''));

        return redirect()
            ->route('business.profile')
            ->with('status', 'AI logo saved and brand defaults updated.');
    }

    private function currentBusinessOrAbort(Request $request): Business
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            abort(404, 'No business profile.');
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        return $business;
    }
}
