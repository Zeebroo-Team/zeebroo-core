<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Services\InvoiceAppearanceService;

// POS Lite's "Invoice Setup" screen: template, accent color, paper size,
// margins and header arrangement for the invoices a POS sale generates
// (Invoice mode). Backed by the same per-business settings the full desktop
// app's Sales → Invoice Setup page uses (Modules\Sales\Services\InvoiceAppearanceService),
// but letterhead is intentionally out of scope here — POS Lite never reads or
// writes the `letterhead_enabled` flag.
class PosInvoiceSetupApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly InvoiceAppearanceService $appearance,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json([
            'data' => $this->appearance->forBusiness($business),
            'templates' => InvoiceAppearanceService::TEMPLATES,
            'color_presets' => InvoiceAppearanceService::COLOR_PRESETS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'template'      => ['required', 'string', 'in:classic,bold,minimal,compact,executive'],
            'accent_color'  => ['nullable', 'string', 'max:9'],
            'paper_size'    => ['required', 'string', 'in:a4,a5,letter,legal'],
            'orientation'   => ['required', 'string', 'in:portrait,landscape'],
            'margin_top'    => ['required', 'integer', 'min:0', 'max:80'],
            'margin_bottom' => ['required', 'integer', 'min:0', 'max:80'],
            'margin_left'   => ['required', 'integer', 'min:0', 'max:80'],
            'margin_right'  => ['required', 'integer', 'min:0', 'max:80'],
            'header_layout' => ['required', 'string', 'in:num-left,num-right,num-center'],
        ]);

        $this->appearance->saveForBusiness($business, $validated);

        return response()->json([
            'message' => 'Invoice setup saved.',
            'data' => $this->appearance->forBusiness($business),
        ]);
    }
}
