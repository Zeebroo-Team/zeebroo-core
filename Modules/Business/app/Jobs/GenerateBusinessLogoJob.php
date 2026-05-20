<?php

namespace Modules\Business\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\BusinessLogoGeneration;
use Modules\Business\Services\BusinessLogoGeminiImageService;
use Throwable;

class GenerateBusinessLogoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Max seconds for Gemini image HTTP call inside the worker. */
    public int $timeout;

    public function __construct(public readonly int $businessLogoGenerationId)
    {
        $this->timeout = max(60, (int) config('business.logo_ai.timeout', 120));
    }

    public function handle(BusinessLogoGeminiImageService $logoGeminiImageService): void
    {
        $generation = BusinessLogoGeneration::query()
            ->with('business')
            ->find($this->businessLogoGenerationId);

        if (! $generation instanceof BusinessLogoGeneration) {
            return;
        }

        $claimed = BusinessLogoGeneration::query()
            ->whereKey($generation->getKey())
            ->where('status', BusinessLogoGeneration::STATUS_PENDING)
            ->update(['status' => BusinessLogoGeneration::STATUS_PROCESSING]);

        if ($claimed === 0) {
            return;
        }

        $fresh = BusinessLogoGeneration::query()
            ->with('business')
            ->find($generation->getKey());

        if (! $fresh instanceof BusinessLogoGeneration) {
            return;
        }

        try {
            $relativePath = $logoGeminiImageService->generateAndSave($fresh);

            BusinessLogoGeneration::query()
                ->whereKey($generation->getKey())
                ->update([
                    'logo_path' => $relativePath,
                    'status' => BusinessLogoGeneration::STATUS_COMPLETED,
                    'error_message' => null,
                ]);
        } catch (Throwable $e) {
            BusinessLogoGeneration::query()
                ->whereKey($generation->getKey())
                ->update([
                    'status' => BusinessLogoGeneration::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
        }
    }
}
