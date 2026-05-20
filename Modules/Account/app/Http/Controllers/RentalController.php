<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Rental;
use Modules\Account\Services\AddressBookService;
use Modules\Account\Services\BillService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\Transaction\Services\RentalManualRentSettlementService;

class RentalController extends Controller
{
    public function __construct(
        private readonly RentalService $rentalService,
        private readonly AddressBookService $addressBookService,
        private readonly RentalManualRentSettlementService $rentalRentSettlementService,
        private readonly BillService $billService,
    ) {}

    public function index(Request $request)
    {
        $business = Business::currentForNavbar($request->user());
        $rentals = $business
            ? $this->rentalService->listForBusiness($business)
            : collect();

        $accounts = $business
            ? Account::query()
                ->with(['bankType', 'bank', 'warehouse'])
                ->where('user_id', $request->user()->id)
                ->where('business_id', $business->id)
                ->orderBy('account_name')
                ->get()
            : collect();

        return view('account::rentals.index', array_merge([
            'business' => $business,
            'rentals' => $rentals,
            'accounts' => $accounts,
            'recurringTypes' => Rental::recurringTypes(),
            'rentalPaymentOverdue' => $business !== null
                ? $this->rentalService->rentalOverdueMapForBusiness($business)
                : [],
        ], $this->warehousesFormContext($request)));
    }

    public function show(Request $request, Rental $rental): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $rentalModel = $this->rentalService->rentalForUser($user, $rental);

        abort_if($rentalModel === null, 403);
        abort_unless($business !== null && (int) $rentalModel->business_id === (int) $business->id, 404);

        $nextPaymentInsight = $this->rentalService->nextPaymentInsight($rentalModel);
        $rentalPaymentOverdue = $this->rentalService->rentalHasOverduePayments($rentalModel);
        $rentalScheduleRows = $this->rentalService->rentalBillingScheduleWithPaymentStatus($rentalModel);
        $rentalLedgerRows = $rentalModel->ledgerTransactions
            ->sortBy(fn ($row) => $row->occurrence_date?->timestamp ?? 0)
            ->values();

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        $rentalBillPaymentOverdue = [];
        foreach ($rentalModel->bills as $linkedBill) {
            $rentalBillPaymentOverdue[(int) $linkedBill->id] = $this->billService->billHasOverduePayments($linkedBill);
        }

        return view('account::rentals.show', [
            'business' => $business,
            'rental' => $rentalModel,
            'accounts' => $accounts,
            'recurringTypes' => Rental::recurringTypes(),
            'billRecurringLabels' => Bill::recurringTypes(),
            'billPaymentModes' => Bill::paymentModes(),
            'nextPaymentInsight' => $nextPaymentInsight,
            'detailCurrency' => (string) (get_settings('business.currency', '', $business) ?: ''),
            'rentalPaymentOverdue' => $rentalPaymentOverdue,
            'rentalBillPaymentOverdue' => $rentalBillPaymentOverdue,
            'rentalScheduleRows' => $rentalScheduleRows,
            'rentalLedgerRows' => $rentalLedgerRows,
        ]);
    }

    public function settleBilling(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $rentalModel = $this->rentalService->rentalForUser($user, $rental);

        abort_if($rentalModel === null, 403);
        abort_unless($business !== null && (int) $rentalModel->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'deduct_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)),
            ],
        ]);

        try {
            $this->rentalRentSettlementService->settle(
                rental: $rentalModel,
                business: $business,
                user: $user,
                occurrenceDateYmd: Carbon::parse((string) $validated['occurrence_date'])->toDateString(),
                deductAccountId: (int) $validated['deduct_account_id'],
            );
        } catch (ValidationException $e) {
            return redirect()->route('account.rentals.show', $rentalModel)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.rentals.show', $rentalModel)->with('status', 'Rent payment recorded and account balance updated.');
    }

    public function edit(Request $request, Rental $rental): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $rentalModel = $this->rentalService->rentalForUser($user, $rental);

        abort_if($rentalModel === null, 403);
        abort_unless($business !== null && (int) $rentalModel->business_id === (int) $business->id, 404);

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('account::rentals.edit', array_merge([
            'rental' => $rentalModel,
            'business' => $business,
            'accounts' => $accounts,
            'recurringTypes' => Rental::recurringTypes(),
            'editingRental' => $rentalModel,
            'rentalFormAction' => route('account.rentals.update', $rentalModel),
            'rentalFormMethod' => 'PATCH',
            'rentalSubmitLabel' => 'Save changes',
        ], $this->warehousesFormContext($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $validated = $this->validateRentalPayload($request, $business);
        $addressBookId = $this->syncLandlordFromValidated($request, $validated);
        $payload = $this->rentalRowPayload($business, $validated, $addressBookId);

        $this->rentalService->create($request->user(), $business, $payload);

        return redirect()->route('account.rentals.index')->with('status', 'Rental saved.');
    }

    public function update(Request $request, Rental $rental): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $rentalModel = $this->rentalService->rentalForUser($user, $rental);

        abort_if($rentalModel === null, 403);
        abort_unless($business !== null && (int) $rentalModel->business_id === (int) $business->id, 404);

        $validated = $this->validateRentalPayload($request, $business);
        $addressBookId = $this->syncLandlordFromValidated($request, $validated);
        $payload = $this->rentalRowPayload($business, $validated, $addressBookId);

        abort_unless($this->rentalService->updateForUser($user, $rentalModel, $payload), 403);

        return redirect()->route('account.rentals.show', $rentalModel)->with('status', 'Rental updated.');
    }

    public function destroy(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($this->rentalService->deleteForUser($request->user(), $rental), 403);

        return redirect()->route('account.rentals.index')->with('status', 'Rental removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRentalPayload(Request $request, Business $business): array
    {
        $request->merge([
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'deduct_account_id' => $request->filled('deduct_account_id') ? $request->integer('deduct_account_id') : null,
            'key_money' => $request->filled('key_money') ? $request->input('key_money') : null,
            'remind_before_days' => $request->filled('remind_before_days') ? $request->integer('remind_before_days') : null,
            'due_date' => $request->filled('due_date') ? $request->input('due_date') : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date') : null,
        ]);

        $validated = $request->validate([
            'property_type' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'key_money' => ['nullable', 'numeric', 'min:0'],
            'agreement_valid_until_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'deduct_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $request->user()->id)
                    ->where('business_id', $business->id)),
            ],
            'recurring_cost' => ['required', 'numeric', 'min:0'],
            'recurring_type' => ['required', Rule::in([
                Rental::RECURRING_PER_DAY,
                Rental::RECURRING_PER_MONTH,
                Rental::RECURRING_PER_YEAR,
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'remind_before_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'due_date' => ['nullable', 'date'],
            'first_installment_due_date' => ['nullable', 'date'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:40'],
            'owner_address' => ['nullable', 'string', 'max:2000'],
            'owner_bank_details' => ['nullable', 'string', 'max:5000'],
            'owner_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $request->filled('owner_email') && ! $request->filled('owner_phone')) {
            throw ValidationException::withMessages([
                'owner_email' => 'Provide landlord email or phone so we can save them once in your address book.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncLandlordFromValidated(Request $request, array $validated): int
    {
        return (int) $this->addressBookService->syncLandlord($request->user(), [
            'name' => $validated['owner_name'],
            'email' => $validated['owner_email'] ?? null,
            'phone' => $validated['owner_phone'] ?? null,
            'street_address' => $validated['owner_address'] ?? null,
            'bank_account_details' => $validated['owner_bank_details'] ?? null,
            'owner_notes' => $validated['owner_notes'] ?? null,
        ])->getKey();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function rentalRowPayload(Business $business, array $validated, int $addressBookId): array
    {
        $payload = Arr::except($validated, [
            'owner_name',
            'owner_email',
            'owner_phone',
            'owner_address',
            'owner_bank_details',
            'owner_notes',
        ]);
        $payload['address_book_id'] = $addressBookId;

        return $this->finalizeWarehouseBranchOnRental($business, $payload);
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
    private function finalizeWarehouseBranchOnRental(Business $business, array $data): array
    {
        if (! $business->multiWarehouseBranchEnabled()) {
            $data['branch_id'] = null;
        } elseif (empty($data['branch_id'])) {
            $data['branch_id'] = null;
        } else {
            $data['branch_id'] = (int) $data['branch_id'];
        }

        return $data;
    }
}
