<?php

declare(strict_types=1);

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\DesignStudio\Services\LetterHeadGeneratorService;

final class GenerateLetterHeadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $hub      = new DesignStudioController();
        $business = $hub->resolveBusiness($request);

        if ($business instanceof RedirectResponse) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->only([
            'name', 'tagline', 'address', 'phone', 'email', 'website', 'color', 'logoUrl',
        ]);

        $data['name']     = filled($data['name']    ?? '') ? $data['name']    : $business->name;
        $data['category'] = $business->category ?? '';
        $data['logoUrl']  = filled($data['logoUrl'] ?? '') ? $data['logoUrl'] : $business->logoUrl();

        $service = new LetterHeadGeneratorService();
        $result  = $service->generate($data);

        return response()->json($result);
    }
}
