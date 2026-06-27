<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Loan;
use Modules\Account\Services\LoanExternalInstallmentMarkService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\LoanService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Transaction\Services\LoanManualInstallmentSettlementService;

class PosLoanApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly LoanService $service,
        private readonly LoanOverviewTooltipService $loanOverviewService,
        private readonly LoanManualInstallmentSettlementService $settlementService,
        private readonly LoanExternalInstallmentMarkService $externalMarkService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $loans = Loan::with(['bank', 'deductAccount.bank', 'deductAccount.bankType'])
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        $todayStr       = now()->toDateString();
        $totalPrincipal = 0.0;
        $totalMonthly   = 0.0;
        $activeCount    = 0;

        $data = $loans->map(function (Loan $loan) use ($todayStr, &$totalPrincipal, &$totalMonthly, &$activeCount) {
            $summary  = $this->loanOverviewService->summarizeLoan($loan);
            $isActive = ! $loan->loan_ending_date || $loan->loan_ending_date->toDateString() >= $todayStr;
            if ($isActive) {
                $activeCount++;
                $totalPrincipal += (float) $loan->borrowed_amount;
                $totalMonthly   += $summary['approx_monthly'];
            }

            return $this->format($loan, $summary);
        })->values();

        return response()->json([
            'data'                  => $data,
            'active_count'          => $activeCount,
            'total_principal'       => round($totalPrincipal, 2),
            'total_principal_fmt'   => number_format($totalPrincipal, 2, '.', ','),
            'total_monthly_outflow' => round($totalMonthly, 2),
            'total_monthly_fmt'     => number_format($totalMonthly, 2, '.', ','),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        $request->merge([
            'bank_id'                    => $request->filled('bank_id')                    ? $request->integer('bank_id')                   : null,
            'deduct_account_id'          => $request->filled('deduct_account_id')          ? $request->integer('deduct_account_id')         : null,
            'remind_before_days'         => $request->filled('remind_before_days')         ? $request->integer('remind_before_days')        : null,
            'first_installment_due_date' => $request->filled('first_installment_due_date') ? $request->input('first_installment_due_date')  : null,
            'loan_ending_date'           => $request->filled('loan_ending_date')           ? $request->input('loan_ending_date')            : null,
        ]);

        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:5000'],
            'bank_id'                    => ['required', 'integer', Rule::exists('banks', 'id')],
            'borrowed_amount'            => ['required', 'numeric', 'min:0'],
            'interest_rate_type'         => ['required', Rule::in([Loan::INTEREST_RATE_PERCENTAGE, Loan::INTEREST_RATE_FLAT])],
            'interest_rate'              => ['required', 'numeric', 'min:0'],
            'recurring_type'             => ['required', Rule::in([Loan::RECURRING_PER_DAY, Loan::RECURRING_PER_MONTH, Loan::RECURRING_PER_YEAR])],
            'first_installment_due_date' => ['nullable', 'date'],
            'loan_ending_date'           => ['nullable', 'date'],
            'deduct_account_id'          => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)->where('business_id', $business->id))],
            'remind_before_days'         => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $loan = $this->service->create($user, $business, $validated);
        $loan->load(['bank', 'deductAccount.bank', 'deductAccount.bankType']);

        return response()->json(['message' => 'Loan created successfully.', 'data' => $this->format($loan)], 201);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $loan->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Loan not found.'], 404);
        }

        $loan->load(['bank', 'deductAccount.bank', 'deductAccount.bankType', 'ledgerTransactions', 'externalInstallmentMarks']);

        $summary  = $this->loanOverviewService->summarizeLoan($loan);
        $schedule = $this->loanOverviewService->installmentScheduleWithPaymentStatus($loan);

        return response()->json([
            'data' => array_merge($this->format($loan, $summary), [
                'schedule' => $schedule->map(fn ($row) => [
                    'period'          => $row['period'],
                    'due_ymd'         => $row['due_ymd'],
                    'amount_formatted'=> $row['amount_formatted'],
                    'paid'            => $row['paid'],
                    'paid_via_ledger' => $row['paid_via_ledger'],
                    'past_due_unpaid' => $row['past_due_unpaid'],
                    'status_label'    => $row['status_label'],
                ])->values(),
            ]),
        ]);
    }

    public function pay(Request $request, Loan $loan): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $loan->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Loan not found.'], 404);
        }

        $validated = $request->validate([
            'due_date'         => ['required', 'date'],
            'recording_option' => ['required', 'in:ledger,external'],
            'account_id'       => ['required_if:recording_option,ledger', 'nullable', 'integer'],
        ]);

        $user = $request->user();

        try {
            if ($validated['recording_option'] === 'ledger') {
                $this->settlementService->settle(
                    $loan, $business, $user,
                    $validated['due_date'],
                    (int) $validated['account_id'],
                );
            } else {
                $this->externalMarkService->mark(
                    $loan, $business, $user,
                    $validated['due_date'],
                );
            }
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['message' => 'Installment recorded successfully.']);
    }

    public function destroy(Request $request, Loan $loan): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $loan->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Loan not found.'], 404);
        }

        $deleted = $this->service->deleteForUser($request->user(), $loan);

        if (! $deleted) {
            return response()->json(['message' => 'Unable to delete this loan.'], 403);
        }

        return response()->json(['message' => 'Loan deleted successfully.']);
    }

    private function format(Loan $loan, ?array $summary = null): array
    {
        $summary  ??= $this->loanOverviewService->summarizeLoan($loan);
        $acc        = $loan->deductAccount;
        $rateTypes  = Loan::interestRateTypes();

        return [
            'id'                        => $loan->id,
            'name'                      => $loan->name,
            'description'               => $loan->description,
            'bank_id'                   => $loan->bank_id,
            'bank_name'                 => $loan->bank?->name,
            'borrowed_amount'           => (float) $loan->borrowed_amount,
            'borrowed_amount_fmt'       => number_format((float) $loan->borrowed_amount, 2, '.', ','),
            'interest_rate_type'        => $loan->interest_rate_type,
            'interest_rate_type_label'  => $rateTypes[$loan->interest_rate_type] ?? $loan->interest_rate_type,
            'interest_rate'             => (float) $loan->interest_rate,
            'recurring_type'            => $loan->recurring_type,
            'first_installment_due_date'=> $loan->first_installment_due_date?->format('Y-m-d'),
            'first_installment_due_fmt' => $loan->first_installment_due_date?->format('M j, Y'),
            'loan_ending_date'          => $loan->loan_ending_date?->format('Y-m-d'),
            'loan_ending_date_fmt'      => $loan->loan_ending_date?->format('M j, Y'),
            'remind_before_days'        => $loan->remind_before_days,
            'deduct_account_id'         => $loan->deduct_account_id,
            'account_name'              => $acc?->account_name ?? $acc?->name,
            'account_number'            => $acc?->bank_account_number,
            'account_bank_name'         => $acc?->bank?->name ?? $acc?->bank_name,
            'account_type_name'         => $acc?->bankType?->name,
            'account_balance'           => $acc !== null ? number_format((float) $acc->current_balance, 2, '.', ',') : null,
            // computed via LoanOverviewTooltipService
            'payment_formatted'         => $summary['payment_formatted'],
            'approx_monthly_formatted'  => $summary['approx_monthly_formatted'],
            'period_count'              => $summary['period_count'],
            'period_source'             => $summary['period_source'],
            'cadence_label'             => $summary['cadence_label'],
        ];
    }
}
