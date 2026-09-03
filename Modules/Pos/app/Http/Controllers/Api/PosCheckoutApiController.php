<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;
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
        $this->abortUnlessPerm($request, $business, 'pos_checkout');

        $validated = $request->validate([
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.item_type'              => ['nullable', 'string', 'in:product,service'],
            'items.*.product_id'             => ['nullable', 'integer', 'min:1'],
            'items.*.service_item_id'        => ['nullable', 'integer', 'min:1'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'items.*.product_selling_unit_id'=> ['nullable', 'integer', 'min:1'],
            'items.*.warranty_type'          => ['nullable', 'string', 'in:lifetime,date'],
            'items.*.warranty_date'          => ['nullable', 'date_format:Y-m-d'],
            'items.*.item_discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.custom_requirement_values'          => ['nullable', 'array', 'max:20'],
            'items.*.custom_requirement_values.*.key'    => ['nullable', 'string', 'max:100'],
            'items.*.custom_requirement_values.*.label'  => ['nullable', 'string', 'max:255'],
            'items.*.custom_requirement_values.*.type'   => ['nullable', 'string', 'in:text,textarea,select,number,date,checkbox,radio'],
            'items.*.custom_requirement_values.*.value'  => ['nullable', 'string', 'max:1000'],
            'payment_method'                 => ['required', 'string', 'in:cash,card,credit'],
            'channel'                        => ['nullable', 'string', 'in:retail,online'],
            'credit_account_id'              => ['nullable', 'integer', 'min:1'],
            'pos_customer_id'                => ['nullable', 'integer', 'min:1'],
            'credit_due_date'                => ['nullable', 'date'],
            'amount_paid'                    => ['nullable', 'numeric', 'min:0'],
            'amount_tendered'                => ['nullable', 'numeric', 'min:0'],
            'discount_percent'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_flat'                  => ['nullable', 'numeric', 'min:0'],
            'notes'                          => ['nullable', 'string', 'max:2000'],
            'scheduled_at'                   => ['nullable', 'date'],
            'branch_id'                      => ['nullable', 'integer', 'min:1'],
            'pos_counter_id'                 => ['nullable', 'integer', 'min:1'],
        ]);

        $branchId = $validated['branch_id']
            ?? $request->query('branch')
            ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        if ($validated['payment_method'] === 'credit' && empty($validated['pos_customer_id'])) {
            return response()->json([
                'message' => 'A customer must be assigned for credit payment.',
                'errors'  => ['pos_customer_id' => ['A customer is required for credit payment.']],
            ], 422);
        }

        $channel = $validated['channel'] ?? Sale::CHANNEL_ONLINE;

        $settings       = $this->posSettings->forBusiness($business);
        $deferSettlement = ($settings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day'
            && in_array($validated['payment_method'], [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true);

        // PosCashier tokens are not User instances; use the business owner as the sale's user
        $authUser = $request->user();
        $saleUser = $authUser instanceof PosCashier
            ? User::findOrFail($business->user_id)
            : $authUser;

        try {
            $sale = $this->sales->checkout(
                $business,
                $saleUser,
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
                $branchId,
                $validated['scheduled_at'] ?? null,
                isset($validated['pos_counter_id']) ? (int) $validated['pos_counter_id'] : null,
                $validated['credit_due_date'] ?? null,
                isset($validated['discount_flat']) ? (float) $validated['discount_flat'] : null,
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
