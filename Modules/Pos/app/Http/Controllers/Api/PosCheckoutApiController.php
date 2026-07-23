<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleService;

class PosCheckoutApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleService $sales,
        private readonly PosOnlineApiService $api,
        private readonly PosSettingsService $posSettings,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.item_type'              => ['nullable', 'string', 'in:product,service'],
            'items.*.product_id'             => ['nullable', 'integer', 'min:1'],
            'items.*.service_item_id'        => ['nullable', 'integer', 'min:1'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'items.*.product_selling_unit_id'=> ['nullable', 'integer', 'min:1'],
            'items.*.warranty_type'          => ['nullable', 'string', 'in:lifetime,days'],
            'items.*.warranty_days'          => ['nullable', 'integer', 'min:1', 'max:36500'],
            'payment_method'                 => ['required', 'string', 'in:cash,card,credit'],
            'channel'                        => ['nullable', 'string', 'in:retail,online'],
            'credit_account_id'              => ['nullable', 'integer', 'min:1'],
            'pos_customer_id'                => ['nullable', 'integer', 'min:1'],
            'amount_paid'                    => ['nullable', 'numeric', 'min:0'],
            'amount_tendered'                => ['nullable', 'numeric', 'min:0'],
            'discount_percent'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                          => ['nullable', 'string', 'max:2000'],
            'scheduled_at'                   => ['nullable', 'date'],
            'branch_id'                      => ['nullable', 'integer', 'min:1'],
            'pos_counter_id'                 => ['nullable', 'integer', 'min:1'],
        ]);

        $channel = $validated['channel'] ?? Sale::CHANNEL_ONLINE;

        $settings       = $this->posSettings->forBusiness($business);
        $deferSettlement = ($settings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day'
            && in_array($validated['payment_method'], [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true);

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
                isset($validated['pos_customer_id']) ? (int) $validated['pos_customer_id'] : null,
                $deferSettlement,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                $validated['scheduled_at'] ?? null,
                isset($validated['pos_counter_id']) ? (int) $validated['pos_counter_id'] : null,
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
