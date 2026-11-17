<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Services\QuotationTemplateService;

/**
 * Quotation Setup (desktop Sales → Quotation Setup). Kept on the server so the PDFs it makes —
 * CRM lead quotation download, sending to the customer, approval mails — use the same layout,
 * including the business's Custom HTML template.
 */
class PosQuotationSetupApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly QuotationTemplateService $templates,
    ) {}

    /** GET quotation-setup */
    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => $this->templates->get($business)]);
    }

    /** PUT quotation-setup */
    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_quotations');

        $data = $request->validate([
            'template'    => ['sometimes', 'string', Rule::in(QuotationTemplateService::TEMPLATES)],
            'color'       => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'paper'       => ['sometimes', 'string', Rule::in(array_keys(QuotationTemplateService::PAPERS))],
            'orientation' => ['sometimes', 'string', Rule::in(['portrait', 'landscape'])],
            'mg_top'      => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'mg_bot'      => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'mg_left'     => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'mg_right'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'custom_html' => ['sometimes', 'nullable', 'string', 'max:' . QuotationTemplateService::MAX_HTML],
        ]);

        return response()->json([
            'message' => 'Quotation setup saved.',
            'data'    => $this->templates->save($business, $data),
        ]);
    }
}
