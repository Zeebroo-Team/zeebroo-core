<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleService;

/**
 * POST sales/offline — uploads a sale the mobile app recorded while offline.
 *
 * Differences from POST sales (which is unchanged):
 *  - client_uuid makes the upload idempotent: a retry returns the sale that
 *    was already saved instead of creating a second one;
 *  - sold_at is the time on the phone (max 7 days old);
 *  - each line's unit_price is the price the customer paid and is honoured;
 *  - gift cards and coupons are not accepted (they need a live balance check).
 * Stock is still checked and consumed as usual.
 */
class PosOfflineSaleApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public const MAX_AGE_DAYS = 7;

    public function __construct(
        private readonly SaleService $sales,
        private readonly PosOnlineApiService $api,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'client_uuid'                   => ['required', 'uuid'],
            'offline_ref'                   => ['nullable', 'string', 'max:40'],
            'sold_at'                       => ['required', 'date'],
            'items'                         => ['required', 'array', 'min:1'],
            'items.*.product_id'            => ['required', 'integer'],
            'items.*.qty'                   => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'            => ['nullable', 'numeric', 'min:0'],
            'items.*.item_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method'                => ['required', 'string'],
            'customer_id'                   => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where('business_id', $business->id)],
            'amount_paid'                   => ['nullable', 'numeric', 'min:0'],
            'amount_tendered'               => ['nullable', 'numeric', 'min:0'],
            'discount_percent'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_flat'                 => ['nullable', 'numeric', 'min:0'],
            'notes'                         => ['nullable', 'string', 'max:2000'],
            'credit_due_date'               => ['nullable', 'date_format:Y-m-d'],
            'coupon_code'                   => ['prohibited'],
            'gift_card_code'                => ['prohibited'],
        ]);

        // Already uploaded (e.g. the connection dropped before the phone got
        // the answer) — hand back the saved sale, never create a duplicate.
        if ($existing = $this->findExisting($business, $data['client_uuid'])) {
            return $this->saleResponse($existing, duplicate: true);
        }

        // The phone sends UTC (…Z); store it in the app's own timezone.
        $soldAt = CarbonImmutable::parse($data['sold_at'])->setTimezone(config('app.timezone'));
        if ($soldAt->lt(now()->subDays(self::MAX_AGE_DAYS))) {
            return response()->json([
                'message' => 'This offline sale is older than '.self::MAX_AGE_DAYS.' days and can no longer be uploaded.',
                'code'    => 'offline_sale_too_old',
                'errors'  => ['sold_at' => ['Offline sales must be uploaded within '.self::MAX_AGE_DAYS.' days.']],
            ], 422);
        }
        // A phone clock running ahead must not create future-dated sales.
        if ($soldAt->gt(now())) {
            $soldAt = CarbonImmutable::now();
        }

        $items = array_map(fn ($i) => [
            'product_id'            => (int) $i['product_id'],
            'quantity'              => (float) $i['qty'],
            'item_discount_percent' => isset($i['item_discount_percent']) ? (float) $i['item_discount_percent'] : null,
            'custom_unit_price'     => isset($i['unit_price']) ? (float) $i['unit_price'] : null,
        ], $data['items']);

        $paymentMethod = match (strtolower((string) $data['payment_method'])) {
            'card', 'transfer' => Sale::PAYMENT_CARD,
            'credit'           => Sale::PAYMENT_CREDIT,
            default            => Sale::PAYMENT_CASH,
        };

        try {
            $sale = $this->sales->checkout(
                business:           $business,
                user:               $request->user(),
                items:              $items,
                paymentMethod:      $paymentMethod,
                creditAccountId:    null,
                amountPaid:         isset($data['amount_paid']) ? (float) $data['amount_paid'] : null,
                notes:              $data['notes'] ?? null,
                channel:            Sale::CHANNEL_RETAIL,
                discountPercent:    isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
                amountTendered:     isset($data['amount_tendered']) ? (float) $data['amount_tendered'] : null,
                customerId:         isset($data['customer_id']) ? (int) $data['customer_id'] : null,
                creditDueDate:      $data['credit_due_date'] ?? null,
                discountFlat:       isset($data['discount_flat']) ? (float) $data['discount_flat'] : null,
                soldAt:             $soldAt,
                honourClientPrices: true,
                clientUuid:         $data['client_uuid'],
                offlineRef:         $data['offline_ref'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            // Two uploads of the same sale raced; the unique index stopped the
            // second one — return the one that won.
            if ($existing = $this->findExisting($business, $data['client_uuid'])) {
                return $this->saleResponse($existing, duplicate: true);
            }
            throw $e;
        }

        // Some MySQL/MariaDB setups define pos_sales.sold_at as
        // "ON UPDATE CURRENT_TIMESTAMP", so checkout's own total update resets
        // it to now. Setting it explicitly in the UPDATE keeps the phone's time.
        if (! $sale->sold_at->equalTo($soldAt)) {
            Sale::query()->whereKey($sale->id)->update(['sold_at' => $soldAt]);
            $sale->refresh();
        }

        return $this->saleResponse($sale, duplicate: false);
    }

    protected function findExisting(Business $business, string $clientUuid): ?Sale
    {
        return $business->sales()->where('client_uuid', $clientUuid)->first();
    }

    private function saleResponse(Sale $sale, bool $duplicate): JsonResponse
    {
        $sale->load(['items.product', 'creditAccount', 'user']);

        return response()->json([
            'message'   => $duplicate
                ? 'Offline sale was already uploaded as '.$sale->sale_number.'.'
                : 'Offline sale uploaded as '.$sale->sale_number.'.',
            'duplicate' => $duplicate,
            'data'      => array_merge($this->api->formatSale($sale), [
                'client_uuid' => $sale->client_uuid,
                'offline_ref' => $sale->offline_ref,
            ]),
        ], $duplicate ? 200 : 201);
    }
}
