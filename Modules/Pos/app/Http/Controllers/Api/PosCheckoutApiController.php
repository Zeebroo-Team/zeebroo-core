<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleService;

class PosCheckoutApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleService $sales,
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,card,credit'],
            'channel' => ['nullable', 'string', 'in:retail,online'],
            'credit_account_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(in_array($request->input('payment_method'), ['cash', 'card'], true)),
            ],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $channel = $validated['channel'] ?? Sale::CHANNEL_ONLINE;

        try {
            $sale = $this->sales->checkout(
                $business,
                $request->user(),
                $validated['items'],
                $validated['payment_method'],
                isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
                isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
                $validated['notes'] ?? null,
                $channel,
                isset($validated['discount_percent']) ? (float) $validated['discount_percent'] : null,
                isset($validated['amount_tendered']) ? (float) $validated['amount_tendered'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Checkout validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Sale '.$sale->sale_number.' completed.',
            'data' => $this->api->formatSale($sale),
        ], 201);
    }
}
