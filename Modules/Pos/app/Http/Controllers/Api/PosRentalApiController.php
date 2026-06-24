<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Rental;
use Modules\Account\Services\AddressBookService;
use Modules\Account\Services\RentalService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Transaction\Services\RentalManualRentSettlementService;

class PosRentalApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly RentalService $rentalService,
        private readonly RentalManualRentSettlementService $settlementService,
        private readonly AddressBookService $addressBookService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $rentals = Rental::with(['deductAccount.bank', 'deductAccount.bankType', 'ledgerTransactions'])
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        $activeCount  = 0;
        $overdueCount = 0;
        $totalMonthly = 0.0;

        $data = $rentals->map(function (Rental $r) use (&$activeCount, &$overdueCount, &$totalMonthly) {
            $isOverdue = $this->rentalService->rentalHasOverduePayments($r);
            $activeCount++;
            if ($isOverdue) {
                $overdueCount++;
            }
            $totalMonthly += $this->monthlyEquiv((float) $r->recurring_cost, (string) $r->recurring_type);

            return $this->format($r, $isOverdue);
        })->values();

        return response()->json([
            'data'             => $data,
            'active_count'     => $activeCount,
            'overdue_count'    => $overdueCount,
            'total_monthly_fmt'=> number_format($totalMonthly, 2, '.', ','),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $request->merge([
            'deduct_account_id'          => $request->filled('deduct_account_id')          ? $request->integer('deduct_account_id')          : null,
            'key_money'                  => $request->filled('key_money')                  ? $request->input('key_money')                    : null,
            'remind_before_days'         => $request->filled('remind_before_days')         ? $request->integer('remind_before_days')         : null,
            'agreement_valid_until_year' => $request->filled('agreement_valid_until_year') ? $request->integer('agreement_valid_until_year') : null,
            'due_date'                   => $request->filled('due_date')                   ? $request->input('due_date')                     : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date')   : null,
        ]);

        $validated = $request->validate([
            'property_type'              => ['required', 'string', 'max:255'],
            'purpose'                    => ['nullable', 'string', 'max:2000'],
            'key_money'                  => ['nullable', 'numeric', 'min:0'],
            'agreement_valid_until_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'deduct_account_id'          => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)->where('business_id', $business->id))],
            'recurring_cost'             => ['required', 'numeric', 'min:0'],
            'recurring_type'             => ['required', Rule::in([Rental::RECURRING_PER_DAY, Rental::RECURRING_PER_MONTH, Rental::RECURRING_PER_YEAR])],
            'notes'                      => ['nullable', 'string', 'max:5000'],
            'remind_before_days'         => ['nullable', 'integer', 'min:0', 'max:366'],
            'due_date'                   => ['nullable', 'date'],
            'first_installment_due_date' => ['nullable', 'date'],
            'owner_name'                 => ['required', 'string', 'max:255'],
            'owner_email'                => ['nullable', 'email', 'max:255'],
            'owner_phone'                => ['nullable', 'string', 'max:40'],
            'owner_address'              => ['nullable', 'string', 'max:2000'],
            'owner_bank_details'         => ['nullable', 'string', 'max:5000'],
            'owner_notes'                => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $request->filled('owner_email') && ! $request->filled('owner_phone')) {
            return response()->json([
                'message' => 'Provide landlord email or phone so we can save them once in your address book.',
                'errors'  => ['owner_email' => ['Provide landlord email or phone.']],
            ], 422);
        }

        try {
            $addressBookId = (int) $this->addressBookService->syncLandlord($user, [
                'name'                 => $validated['owner_name'],
                'email'                => $validated['owner_email'] ?? null,
                'phone'                => $validated['owner_phone'] ?? null,
                'street_address'       => $validated['owner_address'] ?? null,
                'bank_account_details' => $validated['owner_bank_details'] ?? null,
                'owner_notes'          => $validated['owner_notes'] ?? null,
            ])->getKey();
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        $payload = Arr::except($validated, ['owner_name', 'owner_email', 'owner_phone', 'owner_address', 'owner_bank_details', 'owner_notes']);
        $payload['address_book_id'] = $addressBookId;

        $rental = $this->rentalService->create($user, $business, $payload);
        $rental->load(['deductAccount.bank', 'deductAccount.bankType']);

        return response()->json(['message' => 'Rental created successfully.', 'data' => $this->format($rental, false)], 201);
    }

    public function show(Request $request, Rental $rental): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rental not found.'], 404);
        }

        $rental->load([
            'deductAccount.bank',
            'deductAccount.bankType',
            'ledgerTransactions',
            'bills',
            'landlord',
            'warehouse',
        ]);

        $isOverdue = $this->rentalService->rentalHasOverduePayments($rental);
        $schedule  = $this->rentalService->rentalBillingScheduleWithPaymentStatus($rental);

        $billRecurringTypes = \Modules\Account\Models\Bill::recurringTypes();
        $billPaymentModes   = \Modules\Account\Models\Bill::paymentModes();

        return response()->json([
            'data' => array_merge($this->format($rental, $isOverdue), [
                'schedule' => $schedule->map(fn ($row) => [
                    'period'           => $row['period'],
                    'due_ymd'          => $row['due_ymd'],
                    'amount_formatted' => $row['amount_formatted'],
                    'paid'             => $row['paid'],
                    'past_due_unpaid'  => $row['past_due_unpaid'],
                    'status_label'     => $row['status_label'],
                ])->values(),
                'bills' => $rental->bills->map(fn (\Modules\Account\Models\Bill $bill) => [
                    'id'                   => $bill->id,
                    'name'                 => $bill->name,
                    'description'          => $bill->description,
                    'category_label'       => $bill->categoryDisplayLabel(),
                    'is_one_time'          => $bill->isOneTime(),
                    'payment_mode_label'   => $billPaymentModes[$bill->payment_mode] ?? $bill->payment_mode,
                    'recurring_type_label' => $billRecurringTypes[$bill->recurring_type] ?? $bill->recurring_type,
                    'recurring_cost_fmt'   => number_format((float) $bill->recurring_cost, 2, '.', ','),
                    'amount_varies'        => (bool) $bill->amount_varies_by_usage,
                ])->values(),
                'landlord' => $rental->landlord ? [
                    'name'                 => $rental->landlord->name,
                    'email'                => $rental->landlord->email,
                    'phone'                => $rental->landlord->phone,
                    'street_address'       => $rental->landlord->street_address,
                    'bank_account_details' => $rental->landlord->bank_account_details,
                    'notes'                => $rental->landlord->notes,
                ] : null,
                'warehouse_name'         => $rental->warehouse?->name,
            ]),
        ]);
    }

    public function pay(Request $request, Rental $rental): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rental not found.'], 404);
        }

        $validated = $request->validate([
            'due_date'   => ['required', 'date'],
            'account_id' => ['required', 'integer'],
        ]);

        try {
            $this->settlementService->settle(
                $rental,
                $business,
                $request->user(),
                $validated['due_date'],
                (int) $validated['account_id'],
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['message' => 'Rent payment recorded successfully.']);
    }

    public function destroy(Request $request, Rental $rental): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rental not found.'], 404);
        }

        $deleted = $this->rentalService->deleteForUser($request->user(), $rental);

        if (! $deleted) {
            return response()->json(['message' => 'Unable to delete this rental.'], 403);
        }

        return response()->json(['message' => 'Rental deleted successfully.']);
    }

    private function format(Rental $rental, bool $isOverdue): array
    {
        $acc           = $rental->deductAccount;
        $recurringTypes = Rental::recurringTypes();
        $anchor        = $rental->due_date ?? $rental->first_installment_due_date;

        return [
            'id'                         => $rental->id,
            'property_type'              => $rental->property_type,
            'purpose'                    => $rental->purpose,
            'name'                       => $rental->property_type.($rental->purpose ? ' · '.$rental->purpose : ''),
            'recurring_cost'             => (float) $rental->recurring_cost,
            'recurring_cost_fmt'         => number_format((float) $rental->recurring_cost, 2, '.', ','),
            'recurring_type'             => $rental->recurring_type,
            'cadence_label'              => $recurringTypes[$rental->recurring_type] ?? $rental->recurring_type,
            'key_money'                  => $rental->key_money !== null ? (float) $rental->key_money : null,
            'key_money_fmt'              => $rental->key_money !== null ? number_format((float) $rental->key_money, 2, '.', ',') : null,
            'agreement_valid_until_year' => $rental->agreement_valid_until_year,
            'due_date'                   => $anchor?->format('Y-m-d'),
            'due_date_fmt'               => $anchor?->format('M j, Y'),
            'notes'                      => $rental->notes,
            'overdue'                    => $isOverdue,
            'account_name'               => $acc?->account_name ?? $acc?->name,
            'account_number'             => $acc?->bank_account_number,
            'account_bank_name'          => $acc?->bank?->name ?? $acc?->bank_name,
            'account_type_name'          => $acc?->bankType?->name,
            'account_balance'            => $acc !== null ? number_format((float) $acc->current_balance, 2, '.', ',') : null,
        ];
    }

    private function monthlyEquiv(float $amount, string $type): float
    {
        return match ($type) {
            Rental::RECURRING_PER_MONTH => $amount,
            Rental::RECURRING_PER_DAY   => $amount * 30.0,
            Rental::RECURRING_PER_YEAR  => $amount / 12.0,
            default                      => $amount,
        };
    }
}
