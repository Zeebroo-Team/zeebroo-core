<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Coupon;
use Modules\Pos\Services\CouponService;

class PosCouponApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CouponService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $coupons = $this->service->list(
            $business,
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
        );

        return response()->json([
            'data' => $coupons->map(fn (Coupon $c) => $this->service->format($c))->values(),
        ]);
    }

    public function generateCode(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => ['code' => $this->service->generateCode($business)]]);
    }

    /** Lookup by code — used by the POS "Check Coupon" button and checkout. */
    public function lookup(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $code     = (string) $request->query('code', '');

        if (trim($code) === '') {
            return response()->json(['message' => 'Enter a coupon code.'], 422);
        }

        $coupon = $this->service->findByCode($business, $code);
        if ($coupon === null) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        $coupon->load(['redemptions.sale', 'redemptions.user']);

        return response()->json(['data' => $this->service->format($coupon, true)]);
    }

    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $coupon)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $coupon->load(['redemptions.sale', 'redemptions.user']);

        return response()->json(['data' => $this->service->format($coupon, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->validateData($request);

        try {
            $coupon = $this->service->create($business, $this->actingUser($request), $data);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $coupon->load(['redemptions.sale', 'redemptions.user']);

        return response()->json([
            'message' => 'Coupon created.',
            'data'    => $this->service->format($coupon, true),
        ], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $coupon)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $this->validateData($request, $coupon);

        try {
            $coupon = $this->service->update($business, $coupon, $data);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $coupon->load(['redemptions.sale', 'redemptions.user']);

        return response()->json([
            'message' => 'Coupon updated.',
            'data'    => $this->service->format($coupon, true),
        ]);
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $coupon)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $this->service->delete($coupon);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return response()->json(['message' => 'Coupon deleted.']);
    }

    private function validateData(Request $request, ?Coupon $coupon = null): array
    {
        $sometimes = $coupon !== null ? 'sometimes|' : '';
        $type      = $request->input('discount_type', $coupon?->discount_type);

        $data = $request->validate([
            'name'           => "{$sometimes}required|string|max:191",
            'code'           => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]{3,40}$/'],
            'discount_type'  => [$coupon !== null ? 'sometimes' : 'required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FLAT])],
            'discount_value' => array_filter([
                $coupon !== null ? 'sometimes' : null,
                'required',
                'numeric',
                'min:0.01',
                $type === Coupon::TYPE_PERCENT ? 'max:100' : 'max:99999999',
            ]),
            'quantity'       => "{$sometimes}required|integer|min:1|max:".CouponService::MAX_QUANTITY,
            'valid_from'     => 'nullable|date',
            'expires_at'     => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string|max:2000',
        ], [
            'code.regex'         => 'Code must be 3–40 characters: letters, numbers and dashes only.',
            'discount_value.max' => $type === Coupon::TYPE_PERCENT ? 'A percentage discount can be at most 100%.' : 'Discount value is too large.',
        ]);

        return $data;
    }

    /** PosCashier tokens aren't Users — audit columns reference users, so leave them null. */
    private function actingUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function validationError(ValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            'errors'  => $e->errors(),
        ], 422);
    }
}
