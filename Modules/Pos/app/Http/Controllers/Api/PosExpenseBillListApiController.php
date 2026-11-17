<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseBillListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly BillService $billService) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('bills')) {
            return response()->json(['data' => []]);
        }

        $bills = Bill::where('business_id', $business->id)
            ->with(['property', 'employee', 'modification', 'department', 'rental', 'warehouse', 'ledgerTransactions', 'deductAccount.bank'])
            ->orderBy('name')
            ->get([
                'id', 'name', 'recurring_cost', 'due_date', 'first_installment_due_date',
                'payment_mode', 'bill_category', 'description', 'recurring_type',
                'agreement_valid_until_year', 'remind_before_days', 'notes',
                'bill_category_other', 'business_id', 'amount_varies_by_usage',
                'allow_split_payment', 'deduct_account_id', 'rental_property_related',
                'property_id', 'employee_id', 'modification_id', 'department_id',
                'rental_id', 'branch_id',
            ]);

        return response()->json([
            'data' => $bills->map(fn (Bill $b) => $this->present($b))->values(),
        ]);
    }

    /** Bills assigned to one employee, each with its money totals, plus the totals across them. */
    public function forEmployee(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $employee->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        if (! Schema::hasTable('bills')) {
            return response()->json($this->withTotals(collect()));
        }

        return response()->json($this->withTotals($this->billService->listForEmployee($business, (int) $employee->id)));
    }

    /** Bills assigned to one department, each with its money totals, plus the totals across them. */
    public function forDepartment(Request $request, Department $department): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $department->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        if (! Schema::hasTable('bills')) {
            return response()->json($this->withTotals(collect()));
        }

        return response()->json($this->withTotals($this->billService->listForDepartment($business, (int) $department->id)));
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array{data: Collection, summary: array}
     */
    private function withTotals(Collection $bills): array
    {
        $summary = ['count' => 0, 'billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'overdue' => 0.0, 'overdue_count' => 0];

        $data = $bills->map(function (Bill $b) use (&$summary) {
            $row    = $this->present($b);
            $totals = $this->billService->billTotalsToDate($b);
            $row['totals'] = $totals;

            $summary['count']++;
            foreach (['billed', 'paid', 'outstanding', 'overdue'] as $k) {
                $summary[$k] += $totals[$k];
            }
            if ($row['overdue']) {
                $summary['overdue_count']++;
            }

            return $row;
        })->values();

        foreach (['billed', 'paid', 'outstanding', 'overdue'] as $k) {
            $summary[$k] = round($summary[$k], 2);
        }

        return ['data' => $data, 'summary' => $summary];
    }

    /** One bill in the shape the desktop renders cards and the detail view from. */
    private function present(Bill $b): array
    {
        $dueDate     = $b->due_date ?? $b->first_installment_due_date;
        $isOverdue   = $this->billService->billHasOverduePayments($b);
        $isFullyPaid = $this->billService->billIsFullyPaid($b);
        $nextDue     = $isFullyPaid ? null : $this->billService->billNextUnpaidDueDate($b);
        $account     = $b->deductAccount;

        return [
            // Same keys as the show endpoint so the desktop can render cards & detail from one shape
            'bill_category'              => $b->bill_category ?? Bill::CATEGORY_OTHER,
            'bill_category_other'        => $b->bill_category_other,
            'recurring_cost'             => (float) $b->recurring_cost,
            'agreement_valid_until_year' => $b->agreement_valid_until_year,
            'remind_before_days'         => $b->remind_before_days,
            'first_installment_due_date' => $b->first_installment_due_date?->format('Y-m-d'),
            'allow_split_payment'        => (bool) $b->allow_split_payment,
            'assignment_type'            => $b->resolvedAssignmentType(),
            'assignment_name'            => $b->employee?->full_name
                ?? $b->property?->property_name
                ?? $b->modification?->name
                ?? $b->department?->name
                ?? $b->rental?->property_type
                ?? $b->warehouse?->name
                ?? null,
            'account'                    => $account ? [
                'id'           => $account->id,
                'account_name' => $account->account_name ?? $account->name,
                'bank_name'    => $account->bank?->name,
            ] : null,
            'next_due_date'              => $nextDue?->format('Y-m-d'),
            'total_paid'                 => round((float) $b->ledgerTransactions->sum('amount'), 2),
            'payments_count'             => $b->ledgerTransactions->count(),

            'id'             => $b->id,
            'name'           => $b->name,
            'amount'         => (float) $b->recurring_cost,
            'overdue'        => $isOverdue,
            'is_fully_paid'  => $isFullyPaid,
            'due_date'       => $dueDate?->format('Y-m-d') ?? '',
            'due_date_fmt'   => $dueDate?->format('M j, Y') ?? '',
            'actual_due_date_fmt'    => $b->due_date?->format('M j, Y') ?? '',
            'first_install_date_fmt' => $b->first_installment_due_date?->format('M j, Y') ?? '',
            'payment_mode'   => $b->payment_mode ?? Bill::PAYMENT_MODE_RECURRING,
            'category'       => $b->bill_category ?? Bill::CATEGORY_OTHER,
            'category_label' => $b->categoryDisplayLabel(),
            'description'    => $b->description ?? '',
            'recurring_type' => $b->recurring_type ?? '',
            'agreement_until' => $b->agreement_valid_until_year !== null ? (string) $b->agreement_valid_until_year : '',
            'remind_days'    => (int) ($b->remind_before_days ?? 0),
            'notes'          => $b->notes ?? '',
            'amount_varies_by_usage' => (bool) $b->amount_varies_by_usage,
            'property_name'      => $b->property?->property_name ?? '',
            'employee_name'      => $b->employee?->full_name ?? '',
            'modification_name'  => $b->modification?->name ?? '',
            'department_name'    => $b->department?->name ?? '',
            'rental_type'        => $b->rental?->property_type ?? '',
            'branch_name'        => $b->warehouse?->name ?? '',
        ];
    }
}
