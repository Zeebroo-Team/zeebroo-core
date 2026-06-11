<?php

declare(strict_types=1);

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\DesignStudio\Services\CompanyProfileGeneratorService;

final class GenerateCompanyProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $hub      = new DesignStudioController();
        $business = $hub->resolveBusiness($request);

        if ($business instanceof RedirectResponse) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->only([
            'name', 'tagline', 'desc', 'features',
            'category', 'color', 'logoUrl',
            'address', 'phone', 'email', 'website',
            'instagram', 'facebook', 'twitter', 'linkedin', 'whatsapp',
        ]);

        /* Enrich from the authenticated business when wizard fields are blank */
        $data['name']     = filled($data['name'] ?? '')     ? $data['name']     : $business->name;
        $data['category'] = filled($data['category'] ?? '') ? $data['category'] : ($business->category ?? '');

        $service = new CompanyProfileGeneratorService();
        $result  = $service->generate($data);

        return response()->json($result);
    }
}
