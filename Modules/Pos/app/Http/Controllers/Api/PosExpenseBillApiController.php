<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Transaction\Services\BillManualPaymentSettlementService;

class PosExpenseBillApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly BillService $service,
        private readonly BillManualPaymentSettlementService $settlementService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $request->merge([
            'branch_id'                  => $request->filled('branch_id')       ? $request->integer('branch_id')       : null,
            'department_id'              => $request->filled('department_id')    ? $request->integer('department_id')    : null,
            'property_id'                => $request->filled('property_id')      ? $request->integer('property_id')      : null,
            'employee_id'                => $request->filled('employee_id')      ? $request->integer('employee_id')      : null,
            'modification_id'            => $request->filled('modification_id')  ? $request->integer('modification_id')  : null,
            'deduct_account_id'          => $request->filled('deduct_account_id')? $request->integer('deduct_account_id'): null,
            'remind_before_days'         => $request->filled('remind_before_days')? $request->integer('remind_before_days'): null,
            'due_date'                   => $request->filled('due_date')         ? $request->input('due_date')           : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date') : null,
            'bill_category_other'        => $request->filled('bill_category_other') ? trim((string) $request->input('bill_category_other')) : null,
            'rental_property_related'    => $request->boolean('rental_property_related'),
            'rental_id'                  => $request->boolean('rental_property_related') && $request->filled('rental_id') ? $request->integer('rental_id') : null,
            'assignment_type'            => $request->filled('assignment_type') ? (string) $request->input('assignment_type') : 'none',
        ]);

        $deptRules = ['nullable', 'integer'];
        if (Schema::hasTable('hr_departments')) {
            $deptRules[] = Rule::exists('hr_departments', 'id')->where(fn ($q) => $q->where('business_id', $business->id));
        }

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'payment_mode'               => ['required', Rule::in([Bill::PAYMENT_MODE_RECURRING, Bill::PAYMENT_MODE_ONE_TIME])],
            'bill_category'              => ['required', Rule::in(array_keys(Bill::billCategories()))],
            'bill_category_other'        => ['nullable', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'agreement_valid_until_year' => [
                Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING),
                'nullable', 'integer', 'min:2000', 'max:2100',
            ],
            'branch_id'       => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'department_id'   => $deptRules,
            'property_id'     => ['nullable', 'integer', Rule::exists('properties', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('user_id', $user->id))],
            'employee_id'     => ['nullable', 'integer', Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'modification_id' => ['nullable', 'integer', Rule::exists('modifications', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'rental_property_related' => ['boolean'],
            'rental_id'       => [
                Rule::requiredIf(fn () => $request->boolean('rental_property_related')),
                'nullable', 'integer',
                Rule::exists('rentals', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('user_id', $user->id)),
            ],
            'assignment_type'            => ['nullable', Rule::in(['none', 'branch', 'department', 'property', 'employee', 'modification', 'rental'])],
            'deduct_account_id'          => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)->where('business_id', $business->id))],
            'amount_varies_by_usage'     => ['sometimes', 'boolean'],
            'allow_split_payment'        => ['sometimes', 'boolean'],
            'recurring_cost'             => [Rule::requiredIf(fn () => ! $request->boolean('amount_varies_by_usage')), 'nullable', 'numeric', 'min:0'],
            'recurring_type'             => [Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING), 'nullable', Rule::in([Bill::RECURRING_PER_DAY, Bill::RECURRING_PER_MONTH, Bill::RECURRING_PER_YEAR])],
            'notes'                      => ['nullable', 'string', 'max:5000'],
            'remind_before_days'         => ['nullable', 'integer', 'min:0', 'max:366'],
            'due_date'                   => ['nullable', 'date'],
            'first_installment_due_date' => ['nullable', 'date'],
        ]);

        // Enforce assignment mutual exclusivity
        $assignmentType = (string) ($validated['assignment_type'] ?? 'none');
        if ($assignmentType !== 'branch')       $validated['branch_id']       = null;
        if ($assignmentType !== 'department')   $validated['department_id']   = null;
        if ($assignmentType !== 'property')     $validated['property_id']     = null;
        if ($assignmentType !== 'employee')     $validated['employee_id']     = null;
        if ($assignmentType !== 'modification') $validated['modification_id'] = null;
        if ($assignmentType === 'rental') {
            $validated['rental_property_related'] = true;
        } else {
            $validated['rental_property_related'] = false;
            $validated['rental_id']               = null;
        }

        $validated['amount_varies_by_usage'] = (bool) ($validated['amount_varies_by_usage'] ?? false);
        $validated['allow_split_payment']    = (bool) ($validated['allow_split_payment']    ?? true);

        // bill_category_other required when category = other
        if (($validated['bill_category'] ?? '') === Bill::CATEGORY_OTHER) {
            if (trim((string) ($validated['bill_category_other'] ?? '')) === '') {
                throw ValidationException::withMessages(['bill_category_other' => 'Describe this bill when you choose Other.']);
            }
        } else {
            $validated['bill_category_other'] = null;
        }

        // One-time: require a date anchor and auto-compute agreement_valid_until_year
        if (($validated['payment_mode'] ?? '') === Bill::PAYMENT_MODE_ONE_TIME) {
            if (empty($validated['due_date']) && empty($validated['first_installment_due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'Set a due date for this one-time bill (or use first installment date).']);
            }
            $anchor = $validated['due_date'] ?? $validated['first_installment_due_date'];
            $validated['agreement_valid_until_year'] = (int) Carbon::parse((string) $anchor)->format('Y');
            $validated['recurring_type'] = $validated['recurring_type'] ?? Bill::RECURRING_PER_MONTH;
        }

        $validated['recurring_cost'] = round((float) ($validated['recurring_cost'] ?? 0), 2);

        $bill = $this->service->create($user, $business, $validated);

        return response()->json(['message' => 'Bill created successfully.', 'data' => $this->format($bill)], 201);
    }

    public function show(Request $request, Bill $bill): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $bill->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $full = $this->service->billForUser($request->user(), $bill);
        if (! $full) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $schedule = $this->service->billBillingScheduleWithPaymentStatus($full);

        $ledgerRows = $full->ledgerTransactions->sortByDesc('id')->map(fn ($tx) => [
            'id'              => $tx->id,
            'amount'          => (float) $tx->amount,
            'occurrence_date' => $tx->occurrence_date?->format('Y-m-d'),
            'account_name'    => $tx->deductAccount?->account_name ?? $tx->deductAccount?->name ?? null,
            'bank_name'       => $tx->deductAccount?->bank?->name ?? null,
            'created_at'      => $tx->created_at?->format('Y-m-d H:i'),
            'meta'            => $tx->meta ?? [],
        ])->values();

        $scheduleData = $schedule->map(fn ($row) => [
            'period'                       => $row['period'],
            'due_ymd'                      => $row['due_ymd'],
            'amount_formatted'             => $row['amount_formatted'],
            'amount_varies'                => $full->amount_varies_by_usage,
            'paid'                         => $row['paid'],
            'partially_paid'               => $row['partially_paid'],
            'paid_total'                   => $row['paid_total'],
            'paid_total_formatted'         => $row['paid_total_formatted'],
            'outstanding_formatted'        => $row['outstanding_formatted'],
            'needs_period_charge_declaration' => $row['needs_period_charge_declaration'],
            'past_due_unpaid'              => $row['past_due_unpaid'],
            'status_label'                 => $row['status_label'],
            'period_charge_declared'       => $row['period_charge_declared'],
        ])->values();

        $isOverdue   = $this->service->billHasOverduePayments($full);
        $isFullyPaid = $this->service->billIsFullyPaid($full);

        return response()->json([
            'data' => array_merge($this->format($full), [
                'is_overdue'    => $isOverdue,
                'is_fully_paid' => $isFullyPaid,
                'account' => $full->deductAccount ? [
                    'id'           => $full->deductAccount->id,
                    'account_name' => $full->deductAccount->account_name ?? $full->deductAccount->name,
                    'bank_name'    => $full->deductAccount->bank?->name,
                ] : null,
                'assignment_name' => $full->employee?->full_name
                    ?? $full->property?->property_name
                    ?? $full->modification?->name
                    ?? $full->department?->name
                    ?? $full->rental?->property_type
                    ?? $full->warehouse?->name
                    ?? null,
                'schedule'       => $scheduleData,
                'ledger'         => $ledgerRows,
            ]),
        ]);
    }

    public function pay(Request $request, Bill $bill): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        if ((int) $bill->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $billModel = $this->service->billForUser($user, $bill);
        if (! $billModel) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $accountExistsRule = Rule::exists('accounts', 'id')->where(fn ($q) => $q
            ->where('user_id', $user->id)
            ->where('business_id', $business->id));

        $validated = $request->validate([
            'occurrence_date'   => ['required', 'date'],
            'payment_option'    => ['required', Rule::in(['full', 'partial', 'split'])],
            'deduct_account_id' => [
                Rule::requiredIf(fn () => in_array((string) $request->input('payment_option'), ['full', 'partial'], true)),
                'nullable', 'integer', $accountExistsRule,
            ],
            'partial_amount'    => [
                Rule::requiredIf(fn () => (string) $request->input('payment_option') === 'partial'),
                'nullable', 'numeric', 'min:0.01',
            ],
            'split_rows'        => [
                Rule::requiredIf(fn () => (string) $request->input('payment_option') === 'split'),
                'nullable', 'array',
            ],
            'split_rows.*.deduct_account_id' => ['nullable', 'integer', $accountExistsRule],
            'split_rows.*.amount'            => ['nullable', 'numeric', 'min:0.01'],
            'period_charge_total'            => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $billModel->loadMissing('ledgerTransactions');

        try {
            $day               = Carbon::parse((string) $validated['occurrence_date'])->startOfDay();
            $occurrenceDateYmd = $day->toDateString();
            $option            = (string) $validated['payment_option'];

            if (! $billModel->allow_split_payment && $option === 'split') {
                throw ValidationException::withMessages(['payment_option' => 'Split payments are not enabled for this bill.']);
            }

            $declarationRounded = isset($validated['period_charge_total']) && $validated['period_charge_total'] !== ''
                ? round((float) $validated['period_charge_total'], 2) : null;

            if ($this->service->billNeedsPeriodChargeDeclaration($billModel, $day)
                && ($declarationRounded === null || $declarationRounded < 0.01)) {
                throw ValidationException::withMessages(['period_charge_total' => 'Enter this period\'s invoice or metered charge total before recording payment.']);
            }

            if ($billModel->amount_varies_by_usage) {
                $lockedCap = $this->service->billPeriodChargeDeclaredTotal($billModel, $day);
                $cap = $lockedCap ?? $declarationRounded;
                if ($cap === null || $cap <= 0.009) {
                    throw ValidationException::withMessages(['period_charge_total' => 'Enter this period\'s invoice or metered charge total.']);
                }
                $paid       = $this->service->billAmountPaidTowardScheduledDate($billModel, $day);
                $outstanding = max(0.0, round((float) $cap - $paid, 2));
            } else {
                $fromSchedule = $this->service->billScheduledPeriodOutstandingAmount($billModel, $day);
                if ($fromSchedule === null) {
                    throw ValidationException::withMessages(['occurrence_date' => 'This billing period has no payable amount.']);
                }
                $outstanding = round($fromSchedule, 2);
            }

            if ($outstanding <= 0.009) {
                throw ValidationException::withMessages(['occurrence_date' => 'This billing date is already fully paid.']);
            }

            $lines = match ($option) {
                'full'    => [['deduct_account_id' => (int) $validated['deduct_account_id'], 'amount' => $outstanding]],
                'partial' => [['deduct_account_id' => (int) $validated['deduct_account_id'], 'amount' => round((float) $validated['partial_amount'], 2)]],
                'split'   => collect($validated['split_rows'] ?? [])
                    ->map(fn ($row) => ['deduct_account_id' => (int) ($row['deduct_account_id'] ?? 0), 'amount' => round((float) ($row['amount'] ?? 0), 2)])
                    ->filter(fn ($l) => $l['deduct_account_id'] > 0 && $l['amount'] > 0)
                    ->values()->all(),
                default   => [],
            };

            if ($option === 'partial' && ($lines[0]['amount'] ?? 0) > round($outstanding + 0.005, 2)) {
                throw ValidationException::withMessages(['partial_amount' => 'Amount cannot exceed the outstanding '.number_format($outstanding, 2).'.']);
            }

            if ($option === 'split') {
                if (count($lines) < 2) {
                    throw ValidationException::withMessages(['split_rows' => 'Split payment needs at least two lines.']);
                }
                $sum = round(array_sum(array_column($lines, 'amount')), 2);
                if ($sum > round($outstanding + 0.005, 2)) {
                    throw ValidationException::withMessages(['split_rows' => 'Split totals cannot exceed the outstanding '.number_format($outstanding, 2).'.']);
                }
                if (collect($lines)->pluck('deduct_account_id')->unique()->count() < 2) {
                    throw ValidationException::withMessages(['split_rows' => 'Pick a different debit account on each split line.']);
                }
            }

            $created = $this->settlementService->settlePaymentLines(
                bill: $billModel, business: $business, user: $user,
                occurrenceDateYmd: $occurrenceDateYmd, lines: $lines,
                paymentUiOption: $option,
                periodChargeDeclarationFromRequest: $billModel->amount_varies_by_usage ? $declarationRounded : null,
            );

            return response()->json([
                'message' => $created->count() > 1
                    ? sprintf('Bill payments recorded (%d portions). Accounts updated.', $created->count())
                    : 'Bill payment recorded and account balance updated.',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $bill->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Bill not found.'], 404);
        }

        $deleted = $this->service->deleteForUser($request->user(), $bill);

        if (! $deleted) {
            return response()->json(['message' => 'Unable to delete this bill.'], 403);
        }

        return response()->json(['message' => 'Bill deleted successfully.']);
    }

    private function format(Bill $bill): array
    {
        return [
            'id'                         => $bill->id,
            'name'                       => $bill->name,
            'bill_category'              => $bill->bill_category,
            'bill_category_other'        => $bill->bill_category_other,
            'payment_mode'               => $bill->payment_mode,
            'recurring_cost'             => (float) $bill->recurring_cost,
            'recurring_type'             => $bill->recurring_type,
            'agreement_valid_until_year' => $bill->agreement_valid_until_year,
            'first_installment_due_date' => $bill->first_installment_due_date?->format('Y-m-d'),
            'due_date'                   => $bill->due_date?->format('Y-m-d'),
            'remind_before_days'         => $bill->remind_before_days,
            'amount_varies_by_usage'     => (bool) $bill->amount_varies_by_usage,
            'allow_split_payment'        => (bool) $bill->allow_split_payment,
            'assignment_type'            => $bill->assignment_type ?? 'none',
            'branch_id'                  => $bill->branch_id,
            'department_id'              => $bill->department_id,
            'property_id'                => $bill->property_id,
            'employee_id'                => $bill->employee_id,
            'modification_id'            => $bill->modification_id,
            'rental_property_related'    => (bool) $bill->rental_property_related,
            'rental_id'                  => $bill->rental_id,
            'deduct_account_id'          => $bill->deduct_account_id,
            'description'                => $bill->description,
            'notes'                      => $bill->notes,
        ];
    }
}
