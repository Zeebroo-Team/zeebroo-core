<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\GiftCard;
use Modules\Pos\Models\GiftCardGroup;
use Modules\Pos\Services\GiftCardService;

class PosGiftCardApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly GiftCardService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $cards = $this->service->list(
            $business,
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
        );

        return response()->json([
            'data' => $cards->map(fn (GiftCard $c) => $this->service->format($c))->values(),
        ]);
    }

    public function generateCode(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => ['code' => $this->service->generateCode($business)]]);
    }

    /** Balance check by code — used by the POS "Check Gift Card" button and checkout. */
    public function lookup(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $code     = (string) $request->query('code', '');

        if (trim($code) === '') {
            return response()->json(['message' => 'Enter a gift card code.'], 422);
        }

        $card = $this->service->findByCode($business, $code);
        if ($card === null) {
            return response()->json(['message' => 'Gift card not found.'], 404);
        }

        $card->load(['customer', 'transactions.sale', 'transactions.user']);

        return response()->json(['data' => $this->service->format($card, true)]);
    }

    public function show(Request $request, GiftCard $giftCard): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $giftCard)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $giftCard->load(['customer', 'transactions.sale', 'transactions.user']);

        return response()->json(['data' => $this->service->format($giftCard, true)]);
    }

    /** Creates a group with `quantity` cards (default 1) and returns the group. */
    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->validateData($request, $business);
        $data    += $request->validate(['quantity' => 'nullable|integer|min:1|max:'.GiftCardService::MAX_BATCH]);

        try {
            $group = $this->service->create($business, $this->actingUser($request), $data);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $group->load('cards.customer');
        $count = $group->cards->count();

        return response()->json([
            'message' => $count === 1 ? 'Gift card created.' : "{$count} gift cards created.",
            'data'    => $this->service->formatGroup($group),
        ], 201);
    }

    public function groups(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $groups = $this->service->listGroups(
            $business,
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
        );

        return response()->json([
            'data' => $groups->map(fn (GiftCardGroup $g) => $this->service->formatGroup($g))->values(),
        ]);
    }

    public function showGroup(Request $request, GiftCardGroup $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->groupForBusiness($business, $group)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $group->load('cards.customer');

        return response()->json(['data' => $this->service->formatGroup($group)]);
    }

    public function updateGroup(Request $request, GiftCardGroup $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->groupForBusiness($business, $group)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:191',
            'valid_from' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:valid_from',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string|max:2000',
        ]);

        $group = $this->service->updateGroup($group, $data);
        $group->load('cards.customer');

        return response()->json([
            'message' => 'Gift card group updated.',
            'data'    => $this->service->formatGroup($group),
        ]);
    }

    public function addCards(Request $request, GiftCardGroup $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->groupForBusiness($business, $group)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate(['quantity' => 'required|integer|min:1|max:'.GiftCardService::MAX_BATCH]);

        $this->service->addCards($business, $group, $this->actingUser($request), (int) $data['quantity']);
        $group->load('cards.customer');

        return response()->json([
            'message' => "{$data['quantity']} gift card".($data['quantity'] > 1 ? 's' : '').' added.',
            'data'    => $this->service->formatGroup($group),
        ], 201);
    }

    public function destroyGroup(Request $request, GiftCardGroup $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->groupForBusiness($business, $group)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $this->service->deleteGroup($group);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return response()->json(['message' => 'Gift card group deleted.']);
    }

    public function update(Request $request, GiftCard $giftCard): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $giftCard)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $this->validateData($request, $business, $giftCard);

        try {
            $card = $this->service->update($business, $giftCard, $this->actingUser($request), $data);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $card->load(['customer', 'transactions.sale', 'transactions.user']);

        return response()->json([
            'message' => 'Gift card updated.',
            'data'    => $this->service->format($card, true),
        ]);
    }

    public function destroy(Request $request, GiftCard $giftCard): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->forBusiness($business, $giftCard)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $this->service->delete($giftCard);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        return response()->json(['message' => 'Gift card deleted.']);
    }

    private function validateData(Request $request, Business $business, ?GiftCard $card = null): array
    {
        $sometimes = $card !== null ? 'sometimes|' : '';

        $data = $request->validate([
            'name'            => "{$sometimes}required|string|max:191",
            'code'            => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]{4,40}$/'],
            'initial_value'   => "{$sometimes}required|numeric|min:0.01|max:99999999",
            'valid_from'      => 'nullable|date',
            'expires_at'      => 'nullable|date|after_or_equal:valid_from',
            'is_active'       => 'boolean',
            'notes'           => 'nullable|string|max:2000',
            'pos_customer_id' => 'nullable|integer',
        ], [
            'code.regex' => 'Code must be 4–40 characters: letters, numbers and dashes only.',
        ]);

        if (! empty($data['pos_customer_id'])
            && ! Customer::query()->where('business_id', $business->id)->whereKey($data['pos_customer_id'])->exists()) {
            $data['pos_customer_id'] = null;
        }

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
