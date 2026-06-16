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
            ->with(['property', 'employee', 'modification', 'department', 'rental', 'warehouse'])
            ->orderBy('name')
            ->get([
                'id', 'name', 'recurring_cost', 'due_date', 'first_installment_due_date',
                'payment_mode', 'bill_category', 'description', 'recurring_type',
                'agreement_valid_until_year', 'remind_before_days', 'notes',
                'custom_category_name', 'business_id',
                'property_id', 'employee_id', 'modification_id', 'department_id',
                'rental_id', 'branch_id',
            ]);

        return response()->json([
            'data' => $bills->map(function (Bill $b) use ($today) {
                $dueDate = $b->due_date ?? $b->first_installment_due_date;
                return [
                    'id'             => $b->id,
                    'name'           => $b->name,
                    'amount'         => (float) $b->recurring_cost,
                    'overdue'        => $dueDate?->lt($today) ?? false,
                    'due_date'       => $dueDate?->format('Y-m-d') ?? '',
                    'due_date_fmt'   => $dueDate?->format('M j, Y') ?? '',
                    'actual_due_date_fmt'   => $b->due_date?->format('M j, Y') ?? '',
                    'first_install_date_fmt' => $b->first_installment_due_date?->format('M j, Y') ?? '',
                    'payment_mode'   => $b->payment_mode ?? Bill::PAYMENT_MODE_RECURRING,
                    'category'       => $b->bill_category ?? Bill::CATEGORY_OTHER,
                    'category_label' => $b->categoryDisplayLabel(),
                    'description'    => $b->description ?? '',
                    'recurring_type' => $b->recurring_type ?? '',
                    'agreement_until' => $b->agreement_valid_until_year !== null ? (string) $b->agreement_valid_until_year : '',
                    'remind_days'    => (int) ($b->remind_before_days ?? 0),
                    'notes'          => $b->notes ?? '',
                    'property_name'      => $b->property?->property_name ?? '',
                    'employee_name'      => $b->employee?->full_name ?? '',
                    'modification_name'  => $b->modification?->name ?? '',
                    'department_name'    => $b->department?->name ?? '',
                    'rental_type'        => $b->rental?->property_type ?? '',
                    'branch_name'        => $b->warehouse?->name ?? '',
                ];
            })->values(),
        ]);
    }
}
