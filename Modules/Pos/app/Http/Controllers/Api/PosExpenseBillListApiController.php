<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseBillListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('bills')) {
            return response()->json(['data' => []]);
        }

        $today = \Illuminate\Support\Carbon::today();
        $bills = Bill::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name', 'recurring_cost', 'due_date', 'first_installment_due_date']);

        return response()->json([
            'data' => $bills->map(fn (Bill $b) => [
                'id'      => $b->id,
                'name'    => $b->name,
                'amount'  => (float) $b->recurring_cost,
                'overdue' => ($b->due_date ?? $b->first_installment_due_date)?->lt($today) ?? false,
            ])->values(),
        ]);
    }
}
