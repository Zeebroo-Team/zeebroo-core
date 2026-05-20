<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Property;
use Modules\Account\Models\Rental;
use Modules\Account\Services\BillService;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\Department;
use Modules\Modification\Models\Modification;
use Modules\Transaction\Services\BillManualPaymentSettlementService;

class BillController extends Controller
{
    public function __construct(
        private readonly BillService $billService,
        private readonly BillManualPaymentSettlementService $billBillingSettlementService,
    ) {}

    public function index(Request $request)
    {
        $business = Business::currentForNavbar($request->user());
        $bills = $business
            ? $this->billService->listForBusiness($business)
            : collect();

        $accounts = $business
            ? Account::query()
                ->with(['bankType', 'bank', 'warehouse'])
                ->where('user_id', $request->user()->id)
                ->where('business_id', $business->id)
                ->orderBy('account_name')
                ->get()
            : collect();

        return view('account::bills.index', array_merge([
            'business' => $business,
            'bills' => $bills,
            'accounts' => $accounts,
            'rentalsForBillLink' => $business ? $this->rentalsForBillLink($request, $business) : collect(),
            'recurringTypes' => Bill::recurringTypes(),
            'billCategories' => Bill::billCategories(),
            'paymentModes' => Bill::paymentModes(),
            'billPaymentOverdue' => $business !== null
                ? $this->billService->billOverdueMapForBusiness($business)
                : [],
            'departmentsForBill' => $business !== null ? $this->departmentsForBillForm($business) : collect(),
            'branchesForBill' => $business !== null ? $this->branchesForBillForm($business) : collect(),
            'propertiesForBill' => $business !== null ? $this->propertiesForBillForm($request, $business) : collect(),
            'employeesForBill' => $business !== null ? $this->employeesForBillForm($business) : collect(),
            'modificationsForBill' => $business !== null ? $this->modificationsForBillForm($business) : collect(),
        ], $this->warehousesFormContext($request)));
    }

    public function show(Request $request, Bill $bill): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $billModel = $this->billService->billForUser($user, $bill);

        abort_if($billModel === null, 403);
        abort_unless($business !== null && (int) $billModel->business_id === (int) $business->id, 404);

        $nextPaymentInsight = $this->billService->nextPaymentInsight($billModel);
        $billPaymentOverdue = $this->billService->billHasOverduePayments($billModel);
        $billScheduleRows = $this->billService->billBillingScheduleWithPaymentStatus($billModel);
        $billLedgerRows = $billModel->ledgerTransactions
            ->sortBy(fn ($row) => $row->occurrence_date?->timestamp ?? 0)
            ->values();

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('account::bills.show', [
            'business' => $business,
            'bill' => $billModel,
            'accounts' => $accounts,
            'recurringTypes' => Bill::recurringTypes(),
            'billCategories' => Bill::billCategories(),
            'paymentModes' => Bill::paymentModes(),
            'nextPaymentInsight' => $nextPaymentInsight,
            'detailCurrency' => (string) (get_settings('business.currency', '', $business) ?: ''),
            'billPaymentOverdue' => $billPaymentOverdue,
            'billScheduleRows' => $billScheduleRows,
            'billLedgerRows' => $billLedgerRows,
        ]);
    }

    public function settleBilling(Request $request, Bill $bill): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $billModel = $this->billService->billForUser($user, $bill);

        abort_if($billModel === null, 403);
        abort_unless($business !== null && (int) $billModel->business_id === (int) $business->id, 404);

        $accountExistsRule = Rule::exists('accounts', 'id')->where(fn ($q) => $q
            ->where('user_id', $user->id)
            ->where('business_id', $business->id));

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'payment_option' => ['required', Rule::in(['full', 'partial', 'split'])],
            'deduct_account_id' => [
                Rule::requiredIf(fn () => in_array((string) $request->input('payment_option'), ['full', 'partial'], true)),
                'nullable',
                'integer',
                $accountExistsRule,
            ],
            'partial_amount' => [
                Rule::requiredIf(fn () => (string) $request->input('payment_option') === 'partial'),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'split_rows' => [
                Rule::requiredIf(fn () => (string) $request->input('payment_option') === 'split'),
                'nullable',
                'array',
            ],
            'split_rows.*.deduct_account_id' => ['nullable', 'integer', $accountExistsRule],
            'split_rows.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'period_charge_total' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $billModel->loadMissing('ledgerTransactions');

        try {
            $day = Carbon::parse((string) $validated['occurrence_date'])->startOfDay();
            $occurrenceDateYmd = $day->toDateString();

            if (! $billModel->allow_split_payment && (string) ($validated['payment_option'] ?? '') === 'split') {
                throw ValidationException::withMessages([
                    'payment_option' => 'Split payments are not enabled for this bill.',
                ]);
            }

            $declarationRounded = isset($validated['period_charge_total']) && $validated['period_charge_total'] !== ''
                ? round((float) $validated['period_charge_total'], 2)
                : null;

            if ($this->billService->billNeedsPeriodChargeDeclaration($billModel, $day)
                && ($declarationRounded === null || $declarationRounded < 0.01)) {
                throw ValidationException::withMessages([
                    'period_charge_total' => 'Enter this period\'s invoice or metered charge total before recording payment.',
                ]);
            }

            if ($billModel->amount_varies_by_usage) {
                $lockedCap = $this->billService->billPeriodChargeDeclaredTotal($billModel, $day);
                $cap = $lockedCap ?? $declarationRounded;
                if ($cap === null || $cap <= 0.009) {
                    throw ValidationException::withMessages([
                        'period_charge_total' => 'Enter this period\'s invoice or metered charge total before recording payment.',
                    ]);
                }

                $paid = $this->billService->billAmountPaidTowardScheduledDate($billModel, $day);
                $outstandingCalc = max(0.0, round((float) $cap - $paid, 2));
            } else {
                $fromSchedule = $this->billService->billScheduledPeriodOutstandingAmount($billModel, $day);
                if ($fromSchedule === null) {
                    throw ValidationException::withMessages([
                        'occurrence_date' => 'This billing period has no payable amount.',
                    ]);
                }

                $outstandingCalc = round($fromSchedule, 2);
            }

            $outstanding = $outstandingCalc;

            if ($outstanding <= 0.009) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This billing date is already fully paid.',
                ]);
            }

            $option = (string) $validated['payment_option'];

            /** @var list<array{deduct_account_id: int, amount: float}> $lines */
            $lines = match ($option) {
                'full' => [
                    [
                        'deduct_account_id' => (int) $validated['deduct_account_id'],
                        'amount' => $outstanding,
                    ],
                ],
                'partial' => [
                    [
                        'deduct_account_id' => (int) $validated['deduct_account_id'],
                        'amount' => round((float) $validated['partial_amount'], 2),
                    ],
                ],
                'split' => collect($validated['split_rows'] ?? [])
                    ->map(fn (array $row): array => [
                        'deduct_account_id' => (int) ($row['deduct_account_id'] ?? 0),
                        'amount' => round((float) ($row['amount'] ?? 0), 2),
                    ])
                    ->filter(fn (array $line): bool => $line['deduct_account_id'] > 0 && $line['amount'] > 0)
                    ->values()
                    ->all(),
                default => [],
            };

            if ($option === 'partial') {
                $p = $lines[0]['amount'] ?? 0;
                if ($p > round($outstanding + 0.005, 2)) {
                    throw ValidationException::withMessages([
                        'partial_amount' => 'Amount cannot exceed the outstanding '.number_format($outstanding, 2).' for this billing date.',
                    ]);
                }
            }

            if ($option === 'split') {
                if (count($lines) < 2) {
                    throw ValidationException::withMessages([
                        'split_rows' => 'Split payment needs at least two lines with accounts and amounts.',
                    ]);
                }

                $sum = round(array_sum(array_column($lines, 'amount')), 2);

                if ($sum > round($outstanding + 0.005, 2)) {
                    throw ValidationException::withMessages([
                        'split_rows' => 'Split totals cannot exceed the outstanding '.number_format($outstanding, 2).' for this billing date.',
                    ]);
                }

                if (collect($lines)->pluck('deduct_account_id')->unique()->count() < 2) {
                    throw ValidationException::withMessages([
                        'split_rows' => 'Split payment uses multiple accounts — pick a different debit account on each line.',
                    ]);
                }
            }

            $created = $this->billBillingSettlementService->settlePaymentLines(
                bill: $billModel,
                business: $business,
                user: $user,
                occurrenceDateYmd: $occurrenceDateYmd,
                lines: $lines,
                paymentUiOption: $option,
                periodChargeDeclarationFromRequest: $billModel->amount_varies_by_usage ? $declarationRounded : null,
            );

            $count = $created->count();
            $status = $count > 1
                ? sprintf('Bill payments recorded (%d portions). Accounts updated.', $count)
                : 'Bill payment recorded and account balance updated.';

            return redirect()->route('account.bills.show', $billModel)->with('status', $status);
        } catch (ValidationException $e) {
            return redirect()->route('account.bills.show', $billModel)->withErrors($e->errors())->withInput();
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $validated = $this->validateBillPayload($request, $business);
        $payload = $this->billRowPayload($business, $validated);

        $this->billService->create($request->user(), $business, $payload);

        return redirect()->route('account.bills.index')->with('status', 'Bill saved.');
    }

    public function edit(Request $request, Bill $bill): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $billModel = $this->billService->billForUser($user, $bill);

        abort_if($billModel === null, 403);
        abort_unless($business !== null && (int) $billModel->business_id === (int) $business->id, 404);

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('account::bills.edit', array_merge([
            'bill' => $billModel,
            'business' => $business,
            'accounts' => $accounts,
            'rentalsForBillLink' => $this->rentalsForBillLink($request, $business),
            'recurringTypes' => Bill::recurringTypes(),
            'billCategories' => Bill::billCategories(),
            'paymentModes' => Bill::paymentModes(),
            'editingBill' => $billModel,
            'billFormAction' => route('account.bills.update', $billModel),
            'billFormMethod' => 'PATCH',
            'billSubmitLabel' => 'Save changes',
            'departmentsForBill' => $this->departmentsForBillForm($business),
            'branchesForBill' => $this->branchesForBillForm($business),
            'propertiesForBill' => $this->propertiesForBillForm($request, $business),
            'employeesForBill' => $this->employeesForBillForm($business),
            'modificationsForBill' => $this->modificationsForBillForm($business),
        ], $this->warehousesFormContext($request)));
    }

    public function update(Request $request, Bill $bill): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $billModel = $this->billService->billForUser($user, $bill);

        abort_if($billModel === null, 403);
        abort_unless($business !== null && (int) $billModel->business_id === (int) $business->id, 404);

        $validated = $this->validateBillPayload($request, $business);
        $payload = $this->billRowPayload($business, $validated);

        abort_unless($this->billService->updateForUser($user, $billModel, $payload), 403);

        return redirect()->route('account.bills.show', $billModel)->with('status', 'Bill updated.');
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($this->billService->deleteForUser($request->user(), $bill), 403);

        return redirect()->route('account.bills.index')->with('status', 'Bill removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBillPayload(Request $request, Business $business): array
    {
        $request->merge([
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'property_id' => $request->filled('property_id') ? $request->integer('property_id') : null,
            'employee_id' => $request->filled('employee_id') ? $request->integer('employee_id') : null,
            'modification_id' => $request->filled('modification_id') ? $request->integer('modification_id') : null,
            'deduct_account_id' => $request->filled('deduct_account_id') ? $request->integer('deduct_account_id') : null,
            'remind_before_days' => $request->filled('remind_before_days') ? $request->integer('remind_before_days') : null,
            'due_date' => $request->filled('due_date') ? $request->input('due_date') : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date') : null,
            'bill_category_other' => $request->filled('bill_category_other') ? trim((string) $request->input('bill_category_other')) : null,
            'rental_property_related' => $request->boolean('rental_property_related'),
            'rental_id' => $request->boolean('rental_property_related') && $request->filled('rental_id')
                ? $request->integer('rental_id')
                : null,
            'assignment_type' => $request->filled('assignment_type') ? (string) $request->input('assignment_type') : null,
        ]);

        $departmentIdRules = ['nullable', 'integer'];
        if (Schema::hasTable('hr_departments')) {
            $departmentIdRules[] = Rule::exists('hr_departments', 'id')->where(fn ($q) => $q->where('business_id', $business->id));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payment_mode' => ['required', Rule::in([
                Bill::PAYMENT_MODE_RECURRING,
                Bill::PAYMENT_MODE_ONE_TIME,
            ])],
            'bill_category' => ['required', Rule::in(array_keys(Bill::billCategories()))],
            'bill_category_other' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'agreement_valid_until_year' => [
                Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING),
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'department_id' => $departmentIdRules,
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('user_id', $request->user()->id)),
            ],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'modification_id' => [
                'nullable',
                'integer',
                Rule::exists('modifications', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'rental_property_related' => ['boolean'],
            'rental_id' => [
                Rule::requiredIf(fn () => $request->boolean('rental_property_related')),
                'nullable',
                'integer',
                Rule::exists('rentals', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('user_id', $request->user()->id)),
            ],
            'deduct_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $request->user()->id)
                    ->where('business_id', $business->id)),
            ],
            'amount_varies_by_usage' => ['sometimes', 'boolean'],
            'allow_split_payment' => ['sometimes', 'boolean'],
            'recurring_cost' => [
                Rule::requiredIf(fn () => ! $request->boolean('amount_varies_by_usage')),
                'nullable',
                'numeric',
                'min:0',
            ],
            'recurring_type' => [
                Rule::requiredIf(fn () => $request->input('payment_mode') === Bill::PAYMENT_MODE_RECURRING),
                'nullable',
                Rule::in([
                    Bill::RECURRING_PER_DAY,
                    Bill::RECURRING_PER_MONTH,
                    Bill::RECURRING_PER_YEAR,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'remind_before_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'due_date' => ['nullable', 'date'],
            'first_installment_due_date' => ['nullable', 'date'],
            'assignment_type' => ['nullable', Rule::in(['none', 'branch', 'department', 'property', 'employee', 'modification', 'rental'])],
        ]);

        $assignmentType = (string) ($validated['assignment_type'] ?? 'none');
        if ($assignmentType === '') {
            $assignmentType = 'none';
        }

        if ($assignmentType !== 'branch') {
            $validated['branch_id'] = null;
        }
        if ($assignmentType !== 'department') {
            $validated['department_id'] = null;
        }
        if ($assignmentType !== 'property') {
            $validated['property_id'] = null;
        }
        if ($assignmentType !== 'employee') {
            $validated['employee_id'] = null;
        }
        if ($assignmentType !== 'modification') {
            $validated['modification_id'] = null;
        }
        if ($assignmentType === 'rental') {
            $validated['rental_property_related'] = true;
        } else {
            $validated['rental_property_related'] = false;
            $validated['rental_id'] = null;
        }

        if (! ($validated['rental_property_related'] ?? false)) {
            $validated['rental_id'] = null;
        }

        $validated['amount_varies_by_usage'] = (bool) ($validated['amount_varies_by_usage'] ?? false);
        $validated['allow_split_payment'] = (bool) ($validated['allow_split_payment'] ?? true);

        if (($validated['bill_category'] ?? '') === Bill::CATEGORY_OTHER) {
            if (trim((string) ($validated['bill_category_other'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'bill_category_other' => 'Describe this bill when you choose Other.',
                ]);
            }
        } else {
            $validated['bill_category_other'] = null;
        }

        if (($validated['payment_mode'] ?? '') === Bill::PAYMENT_MODE_ONE_TIME) {
            if (empty($validated['due_date']) && empty($validated['first_installment_due_date'])) {
                throw ValidationException::withMessages([
                    'due_date' => 'Set a due date for this one-time bill (or use first installment date).',
                ]);
            }

            $anchorStr = $validated['due_date'] ?? $validated['first_installment_due_date'];
            $validated['agreement_valid_until_year'] = (int) Carbon::parse((string) $anchorStr)->format('Y');
            $validated['recurring_type'] = $validated['recurring_type'] ?? Bill::RECURRING_PER_MONTH;
        }

        $validated['recurring_cost'] = round((float) ($validated['recurring_cost'] ?? 0), 2);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function billRowPayload(Business $business, array $validated): array
    {
        return $this->finalizeWarehouseBranchOnBill($business, $validated);
    }

    /**
     * @return array{accountBusinessMultiWarehouse: array<int, bool>, accountBranchesByBusiness: array<int, list<array{id: int, name: string}>>}
     */
    private function warehousesFormContext(Request $request): array
    {
        $businesses = Business::query()
            ->where('user_id', Auth::id())
            ->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $multiWarehouse = [];
        $byBusiness = [];

        foreach ($businesses as $biz) {
            $multiWarehouse[$biz->id] = $biz->multiWarehouseBranchEnabled();
            $byBusiness[$biz->id] = $biz->branches->map(fn ($br) => [
                'id' => $br->id,
                'name' => $br->name,
            ])->values()->all();
        }

        return [
            'accountBusinessMultiWarehouse' => $multiWarehouse,
            'accountBranchesByBusiness' => $byBusiness,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function finalizeWarehouseBranchOnBill(Business $business, array $data): array
    {
        if (! $business->multiWarehouseBranchEnabled()) {
            $data['branch_id'] = null;
        } elseif (empty($data['branch_id'])) {
            $data['branch_id'] = null;
        } else {
            $data['branch_id'] = (int) $data['branch_id'];
        }

        return Arr::only($data, [
            'name',
            'payment_mode',
            'bill_category',
            'bill_category_other',
            'description',
            'agreement_valid_until_year',
            'branch_id',
            'department_id',
            'property_id',
            'employee_id',
            'modification_id',
            'rental_property_related',
            'rental_id',
            'deduct_account_id',
            'recurring_cost',
            'recurring_type',
            'amount_varies_by_usage',
            'allow_split_payment',
            'remind_before_days',
            'due_date',
            'first_installment_due_date',
            'notes',
        ]);
    }

    /**
     * @return EloquentCollection<int, Rental>
     */
    private function rentalsForBillLink(Request $request, Business $business): EloquentCollection
    {
        return Rental::query()
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->with('warehouse')
            ->orderBy('property_type')
            ->get();
    }

    /** @return EloquentCollection<int, Department> */
    private function departmentsForBillForm(Business $business): EloquentCollection
    {
        if (! Schema::hasTable('hr_departments')) {
            return new EloquentCollection([]);
        }

        return $business->departments()->orderBy('name')->orderBy('id')->get();
    }

    /** @return EloquentCollection<int, Property> */
    private function propertiesForBillForm(Request $request, Business $business): EloquentCollection
    {
        return Property::query()
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('property_name')
            ->get();
    }

    /** @return EloquentCollection<int, Employee> */
    private function employeesForBillForm(Business $business): EloquentCollection
    {
        if (! Schema::hasTable('hr_employees')) {
            return new EloquentCollection([]);
        }

        return Employee::query()
            ->where('business_id', $business->id)
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, \Modules\Business\Models\Branch> */
    private function branchesForBillForm(Business $business): EloquentCollection
    {
        return $business->branches()->where('is_active', true)->orderBy('name')->orderBy('id')->get();
    }

    /** @return EloquentCollection<int, Modification> */
    private function modificationsForBillForm(Business $business): EloquentCollection
    {
        return Modification::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
