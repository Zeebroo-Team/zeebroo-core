<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Account\Models\Bill;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosOfflineRecordService;

/**
 * Uploads of customers and expenses the mobile app recorded while offline:
 *  - POST customers/offline
 *  - POST expenses/offline
 * Both are idempotent on client_uuid: a retry returns the saved record
 * (HTTP 200, duplicate: true) instead of creating a second one.
 */
class PosOfflineRecordApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public const MAX_AGE_DAYS = PosOfflineSaleApiController::MAX_AGE_DAYS;

    public function __construct(private readonly PosOfflineRecordService $records)
    {
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'pos_customers');

        // The business's "phone/email/address required" settings are not
        // enforced here: the customer was already served offline, and a
        // rejected customer would also block the sale made to them.
        $data = $request->validate([
            'client_uuid'          => ['required', 'uuid'],
            'name'                 => ['required', 'string', 'max:255'],
            'phone'                => ['nullable', 'string', 'max:50'],
            'email'                => ['nullable', 'email', 'max:255'],
            'address'              => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'customer_type'        => ['nullable', 'string', 'in:retail,wholesale'],
            'customer_category_id' => ['nullable', 'integer'],
        ]);

        ['customer' => $customer, 'duplicate' => $duplicate, 'matched' => $matched] = $this->records->saveCustomer($business, $data);

        return response()->json([
            'message'   => $matched
                ? 'A customer with this phone number already exists — using '.$customer->name.'.'
                : ($duplicate ? 'Offline customer was already uploaded.' : 'Offline customer uploaded.'),
            'duplicate' => $duplicate,
            'matched'   => $matched,
            'data'      => [
                'id'            => $customer->id,
                'client_uuid'   => $customer->client_uuid,
                'name'          => $customer->name,
                'phone'         => $customer->phone,
                'email'         => $customer->email,
                'address'       => $customer->address,
                'customer_type' => $customer->customer_type ?? 'retail',
            ],
        ], $duplicate || $matched ? 200 : 201);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_bills');

        $data = $request->validate([
            'client_uuid'         => ['required', 'uuid'],
            'name'                => ['required', 'string', 'max:255'],
            'amount'              => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'bill_category'       => ['required', Rule::in(array_keys(Bill::billCategories()))],
            'bill_category_other' => ['nullable', 'required_if:bill_category,'.Bill::CATEGORY_OTHER, 'string', 'max:255'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'spent_at'            => ['required', 'date'],
        ]);

        // The phone sends UTC (…Z); store it in the app's own timezone.
        $spentAt = CarbonImmutable::parse($data['spent_at'])->setTimezone(config('app.timezone'));
        if ($spentAt->lt(now()->subDays(self::MAX_AGE_DAYS))) {
            return response()->json([
                'message' => 'This offline expense is older than '.self::MAX_AGE_DAYS.' days and can no longer be uploaded.',
                'code'    => 'offline_record_too_old',
                'errors'  => ['spent_at' => ['Offline expenses must be uploaded within '.self::MAX_AGE_DAYS.' days.']],
            ], 422);
        }
        if ($spentAt->gt(now())) {
            $spentAt = CarbonImmutable::now();
        }

        ['bill' => $bill, 'duplicate' => $duplicate] = $this->records->saveExpense($request->user(), $business, $data, $spentAt);

        return response()->json([
            'message'   => $duplicate ? 'Offline expense was already uploaded.' : 'Offline expense uploaded.',
            'duplicate' => $duplicate,
            'data'      => [
                'id'             => $bill->id,
                'client_uuid'    => $bill->client_uuid,
                'name'           => $bill->name,
                'amount'         => (float) $bill->recurring_cost,
                'bill_category'  => $bill->bill_category,
                'due_date'       => $bill->due_date?->format('Y-m-d'),
            ],
        ], $duplicate ? 200 : 201);
    }
}
