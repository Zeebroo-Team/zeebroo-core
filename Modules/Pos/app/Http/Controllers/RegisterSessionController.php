<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\PosCounter;
use Modules\Pos\Services\PosCashDrawerService;

class RegisterSessionController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PosCashDrawerService $drawer,
    ) {
    }

    public function drawerStatus(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        return response()->json(['data' => $this->drawer->todayStatus($business)]);
    }

    public function openDrawer(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $this->drawer->setOpening($business, (float) $validated['opening_float'], $request->user()?->id);

        return response()->json([
            'message' => 'Opening cash recorded.',
            'data'    => $this->drawer->todayStatus($business),
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $this->drawer->addWithdrawal($business, (float) $validated['amount'], $validated['note'] ?? null, $request->user()?->id);

        return response()->json([
            'message' => 'Withdrawal recorded.',
            'data'    => $this->drawer->todayStatus($business),
        ]);
    }

    public function counters(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $counters = PosCounter::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $counters]);
    }

    private function businessOrJson(Request $request): \Modules\Business\Models\Business|JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['message' => 'Select or create a business first.'], 422);
        }

        return $business;
    }
}
