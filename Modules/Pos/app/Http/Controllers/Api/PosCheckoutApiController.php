<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleService;
use Modules\Product\Models\Product;

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

        // A rental sold offline may come back after its return date; it only
        // has to be on/after the day it was sold.
        $rentalFloor = 'today';
        if ($request->filled('sold_at')) {
            try {
                $rentalFloor = CarbonImmutable::parse((string) $request->input('sold_at'))->setTimezone(config('app.timezone'))->toDateString();
            } catch (\Throwable) {
                // sold_at itself fails validation below
            }
        }

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
            'items.*.rental_return_date'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$rentalFloor],
            'items.*.item_discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.custom_unit_price'      => ['nullable', 'numeric', 'min:0.01'],
            'items.*.custom_requirement_values'         => ['nullable', 'array', 'max:20'],
            'items.*.custom_requirement_values.*.key'    => ['nullable', 'string', 'max:100'],
            'items.*.custom_requirement_values.*.label'  => ['nullable', 'string', 'max:255'],
            'items.*.custom_requirement_values.*.type'   => ['nullable', 'string', 'in:text,textarea,select,number,date,checkbox,radio'],
            'items.*.custom_requirement_values.*.value'  => ['nullable', 'string', 'max:1000'],
            'payment_method'                 => ['required', 'string', 'in:cash,card,credit,cheque'],
            'cheque_number'                  => ['nullable', 'required_if:payment_method,cheque', 'string', 'max:60'],
            'cheque_date'                    => ['nullable', 'required_if:payment_method,cheque', 'date_format:Y-m-d'],
            'cheque_bank'                    => ['nullable', 'string', 'max:120'],
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
            'gift_card_code'                 => ['nullable', 'string', 'max:40'],
            'gift_card_amount'               => ['nullable', 'required_with:gift_card_code', 'numeric', 'min:0.01'],
            'coupon_code'                    => ['nullable', 'string', 'max:40'],
            // Desktop offline mode: every checkout carries a client_uuid so a retry
            // never creates a second sale; sold_at is set only when a sale recorded
            // offline is replayed later (the price paid is then kept as-is).
            'client_uuid'                    => ['nullable', 'uuid'],
            'offline_ref'                    => ['nullable', 'string', 'max:40'],
            'sold_at'                        => ['nullable', 'date', 'required_with:offline_ref'],
        ]);

        $clientUuid = $validated['client_uuid'] ?? null;
        if ($clientUuid !== null && ($existing = $this->findByClientUuid($business, $clientUuid))) {
            return $this->saleResponse($existing, duplicate: true);
        }

        $soldAt = null;
        if (! empty($validated['sold_at'])) {
            if ($clientUuid === null) {
                return response()->json([
                    'message' => 'An offline sale must carry a client_uuid.',
                    'errors'  => ['client_uuid' => ['Required when sold_at is sent.']],
                ], 422);
            }
            // Gift cards and coupons are allowed: SaleService checks them as of
            // sold_at and still enforces the live balance / remaining uses, so a
            // card spent elsewhere in the meantime fails here (shown on the desktop).
            // The desktop sends UTC (…Z); store it in the app's own timezone.
            $soldAt = CarbonImmutable::parse($validated['sold_at'])->setTimezone(config('app.timezone'));
            if ($soldAt->lt(now()->subDays(PosOfflineSaleApiController::MAX_AGE_DAYS))) {
                return response()->json([
                    'message' => 'This offline sale is older than '.PosOfflineSaleApiController::MAX_AGE_DAYS.' days and can no longer be uploaded.',
                    'code'    => 'offline_sale_too_old',
                    'errors'  => ['sold_at' => ['Offline sales must be uploaded within '.PosOfflineSaleApiController::MAX_AGE_DAYS.' days.']],
                ], 422);
            }
            // A PC clock running ahead must not create future-dated sales.
            if ($soldAt->gt(now())) {
                $soldAt = CarbonImmutable::now();
            }
        }

        $branchId = $validated['branch_id']
            ?? $request->query('branch')
            ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        if (in_array($validated['payment_method'], ['credit', 'cheque'], true) && empty($validated['pos_customer_id'])) {
            return response()->json([
                'message' => "A customer must be assigned for {$validated['payment_method']} payment.",
                'errors'  => ['pos_customer_id' => ["A customer is required for {$validated['payment_method']} payment."]],
            ], 422);
        }

        if (empty($validated['pos_customer_id'])) {
            $productIds = collect($validated['items'])->pluck('product_id')->filter()->unique();
            if ($productIds->isNotEmpty() && Product::query()->whereIn('id', $productIds)->where('is_subscription', true)->exists()) {
                return response()->json([
                    'message' => 'A customer must be assigned for subscription products.',
                    'errors'  => ['pos_customer_id' => ['A customer is required to sell a subscription product.']],
                ], 422);
            }
            if ($productIds->isNotEmpty() && Product::query()->whereIn('id', $productIds)->where('is_rental', true)->exists()) {
                return response()->json([
                    'message' => 'A customer must be assigned for rental products.',
                    'errors'  => ['pos_customer_id' => ['A customer is required to rent a product.']],
                ], 422);
            }
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
                $validated['gift_card_code'] ?? null,
                isset($validated['gift_card_amount']) ? (float) $validated['gift_card_amount'] : null,
                $validated['coupon_code'] ?? null,
                soldAt: $soldAt,
                honourClientPrices: $soldAt !== null,
                clientUuid: $clientUuid,
                offlineRef: $validated['offline_ref'] ?? null,
                cheque: $validated['payment_method'] === Sale::PAYMENT_CHEQUE ? [
                    'cheque_number' => $validated['cheque_number'] ?? null,
                    'cheque_date'   => $validated['cheque_date'] ?? null,
                    'bank_name'     => $validated['cheque_bank'] ?? null,
                ] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Checkout validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            // Two uploads of the same sale raced; the unique index stopped the
            // second one — return the one that won.
            if ($clientUuid !== null && ($existing = $this->findByClientUuid($business, $clientUuid))) {
                return $this->saleResponse($existing, duplicate: true);
            }
            throw $e;
        }

        // Some MySQL/MariaDB setups define pos_sales.sold_at as
        // "ON UPDATE CURRENT_TIMESTAMP"; keep the time the sale really happened.
        if ($soldAt !== null && ! $sale->sold_at->equalTo($soldAt)) {
            Sale::query()->whereKey($sale->id)->update(['sold_at' => $soldAt]);
            $sale->refresh();
        }

        return $this->saleResponse($sale, duplicate: false);
    }

    protected function findByClientUuid(Business $business, string $clientUuid): ?Sale
    {
        return $business->sales()->where('client_uuid', $clientUuid)->first();
    }

    private function saleResponse(Sale $sale, bool $duplicate): JsonResponse
    {
        return response()->json([
            'message'   => $duplicate
                ? 'Sale '.$sale->sale_number.' was already saved.'
                : 'Sale '.$sale->sale_number.' completed.',
            'duplicate' => $duplicate,
            'data'      => array_merge($this->api->formatSale($sale), [
                'client_uuid' => $sale->client_uuid,
                'offline_ref' => $sale->offline_ref,
            ]),
        ], $duplicate ? 200 : 201);
    }
}
