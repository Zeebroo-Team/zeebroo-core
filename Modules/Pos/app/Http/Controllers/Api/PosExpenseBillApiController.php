<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseBillApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly BillService $service) {}

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'bill_category'   => ['required', 'string', 'in:water,electricity,telephone,internet,gas,waste,other'],
            'payment_mode'    => ['required', 'string', 'in:one_time,recurring'],
            'recurring_cost'  => ['required', 'numeric', 'min:0'],
            'recurring_type'  => ['required_if:payment_mode,recurring', 'nullable', 'string', 'in:per_day,per_month,per_year'],
            'due_date'        => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'description'     => ['nullable', 'string', 'max:2000'],
        ]);

        $bill = $this->service->create($request->user(), $business, $validated);

        return response()->json([
            'message' => 'Bill created successfully.',
            'data'    => $this->format($bill),
        ], 201);
    }

    private function format(Bill $bill): array
    {
        return [
            'id'             => $bill->id,
            'name'           => $bill->name,
            'bill_category'  => $bill->bill_category,
            'payment_mode'   => $bill->payment_mode,
            'recurring_cost' => (float) $bill->recurring_cost,
            'recurring_type' => $bill->recurring_type,
            'due_date'       => $bill->due_date?->format('Y-m-d'),
            'notes'          => $bill->notes,
        ];
    }
}
