<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bank;
use Modules\Account\Models\Loan;
use Modules\Account\Services\LoanExternalInstallmentMarkService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\LoanService;
use Modules\Business\Models\Business;
use Modules\Transaction\Services\LoanManualInstallmentSettlementService;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly LoanManualInstallmentSettlementService $installmentSettlementService,
        private readonly LoanExternalInstallmentMarkService $externalInstallmentMarkService,
    ) {}

    public function index(Request $request)
    {
        $business = Business::currentForNavbar($request->user());
        $loans = $this->loanService->listForBusiness($business);
        $banks = Bank::orderBy('name')->get();
        $accounts = $business
            ? Account::query()
                ->with(['bankType', 'bank', 'warehouse'])
                ->where('user_id', $request->user()->id)
                ->where('business_id', $business->id)
                ->orderBy('account_name')
                ->get()
            : collect();

        $loanSummaries = [];
        $loanInstallmentOverdue = [];
        $loanCurrency = '';
        $loanPortfolioTotals = ['principal' => 0.0, 'approx_monthly' => 0.0];
        if ($business !== null && $loans->isNotEmpty()) {
            $loanCurrency = (string) (get_settings('business.currency', '', $business) ?: '');
            $calc = app(LoanOverviewTooltipService::class);
            foreach ($loans as $loanItem) {
                $loanSummaries[$loanItem->id] = $calc->summarizeLoan($loanItem);
                $loanInstallmentOverdue[$loanItem->id] = $calc->loanHasOverdueInstallments($loanItem);
                $loanPortfolioTotals['principal'] += (float) $loanItem->borrowed_amount;
                $loanPortfolioTotals['approx_monthly'] += $loanSummaries[$loanItem->id]['approx_monthly'];
            }
        }

        return view('account::loans.index', [
            'business' => $business,
            'loans' => $loans,
            'banks' => $banks,
            'accounts' => $accounts,
            'interestRateTypes' => Loan::interestRateTypes(),
            'recurringTypes' => Loan::recurringTypes(),
            'loanSummaries' => $loanSummaries,
            'loanInstallmentOverdue' => $loanInstallmentOverdue,
            'loanCurrency' => $loanCurrency,
            'loanPortfolioTotals' => $loanPortfolioTotals,
        ]);
    }

    public function show(Request $request, Loan $loan): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $loanModel = $this->loanService->loanForUser($user, $loan);
        abort_if($loanModel === null, 403);
        abort_unless($business !== null && (int) $loanModel->business_id === (int) $business->id, 404);

        $calc = app(LoanOverviewTooltipService::class);
        $loanSummary = $calc->summarizeLoan($loanModel);
        $installmentOverdue = $calc->loanHasOverdueInstallments($loanModel);
        $loanCurrency = (string) (get_settings('business.currency', '', $business) ?: '');
        $scheduleRows = $calc->installmentScheduleWithPaymentStatus($loanModel);
        $ledgerRows = $loanModel->ledgerTransactions->sortBy(fn ($row) => $row->occurrence_date?->timestamp ?? 0)->values();

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('account::loans.show', [
            'business' => $business,
            'loan' => $loanModel,
            'loanSummary' => $loanSummary,
            'loanCurrency' => $loanCurrency,
            'installmentOverdue' => $installmentOverdue,
            'interestRateTypes' => Loan::interestRateTypes(),
            'recurringTypes' => Loan::recurringTypes(),
            'scheduleRows' => $scheduleRows,
            'ledgerRows' => $ledgerRows,
            'accounts' => $accounts,
        ]);
    }

    public function settleInstallment(Request $request, Loan $loan): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $loanModel = $this->loanService->loanForUser($user, $loan);

        abort_if($loanModel === null, 403);
        abort_if($business === null || (int) $loanModel->business_id !== (int) $business->id, 404);

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
            $this->installmentSettlementService->settle(
                loan: $loanModel,
                business: $business,
                user: $user,
                occurrenceDateYmd: Carbon::parse((string) $validated['occurrence_date'])->toDateString(),
                deductAccountId: (int) $validated['deduct_account_id'],
            );
        } catch (ValidationException $e) {
            return redirect()->route('account.loans.show', $loanModel)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.loans.show', $loanModel)->with('status', 'Installment payment recorded and account balance updated.');
    }

    public function markInstallmentPaidExternally(Request $request, Loan $loan): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $loanModel = $this->loanService->loanForUser($user, $loan);

        abort_if($loanModel === null, 403);
        abort_if($business === null || (int) $loanModel->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
        ]);

        try {
            $this->externalInstallmentMarkService->mark(
                loan: $loanModel,
                business: $business,
                user: $user,
                occurrenceDateYmd: Carbon::parse((string) $validated['occurrence_date'])->toDateString(),
            );
        } catch (ValidationException $e) {
            return redirect()->route('account.loans.show', $loanModel)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.loans.show', $loanModel)->with('status', 'Installment marked as already paid (no ledger entry).');
    }

    public function unmarkInstallmentPaidExternally(Request $request, Loan $loan): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $loanModel = $this->loanService->loanForUser($user, $loan);

        abort_if($loanModel === null, 403);
        abort_if($business === null || (int) $loanModel->business_id !== (int) $business->id, 404);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
        ]);

        try {
            $this->externalInstallmentMarkService->unmark(
                loan: $loanModel,
                business: $business,
                user: $user,
                occurrenceDateYmd: Carbon::parse((string) $validated['occurrence_date'])->toDateString(),
            );
        } catch (ValidationException $e) {
            return redirect()->route('account.loans.show', $loanModel)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.loans.show', $loanModel)->with('status', '“Already paid” mark removed for that installment.');
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }

        $request->merge([
            'deduct_account_id' => $request->filled('deduct_account_id') ? $request->integer('deduct_account_id') : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date') : null,
            'loan_ending_date' => $request->filled('loan_ending_date') ? $request->input('loan_ending_date') : null,
            'remind_before_days' => $request->filled('remind_before_days') ? $request->integer('remind_before_days') : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'bank_id' => ['required', 'exists:banks,id'],
            'borrowed_amount' => ['required', 'numeric', 'min:0'],
            'interest_rate_type' => ['required', Rule::in([Loan::INTEREST_RATE_PERCENTAGE, Loan::INTEREST_RATE_FLAT])],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'recurring_type' => ['required', Rule::in([Loan::RECURRING_PER_DAY, Loan::RECURRING_PER_MONTH, Loan::RECURRING_PER_YEAR])],
            'first_installment_due_date' => ['nullable', 'date'],
            'loan_ending_date' => ['nullable', 'date'],
            'deduct_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $request->user()->id)
                    ->where('business_id', $business->id)),
            ],
            'remind_before_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        if (! empty($data['loan_ending_date']) && ! empty($data['first_installment_due_date'])) {
            if ($data['loan_ending_date'] < $data['first_installment_due_date']) {
                throw ValidationException::withMessages([
                    'loan_ending_date' => 'Loan ending date must be on or after the first installment due date.',
                ]);
            }
        }

        $this->loanService->create($request->user(), $business, $data);

        return redirect()->route('account.loans.index')->with('status', 'Loan added successfully.');
    }

    public function destroy(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($this->loanService->deleteForUser($request->user(), $loan), 403);

        return redirect()->route('account.loans.index')->with('status', 'Loan removed.');
    }
}
