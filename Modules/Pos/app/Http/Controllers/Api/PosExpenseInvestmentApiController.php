<?php

declare(strict_types=1);

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Investment;
use Modules\Account\Services\InvestmentService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosExpenseInvestmentApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly InvestmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('investments')) {
            return response()->json(['data' => [], 'types' => Investment::investmentTypes(), 'total_count' => 0]);
        }

        $rows = $this->service->listForBusiness($business)
            ->map(fn (Investment $i) => $this->format($i, $this->service->summary($i)));

        return response()->json([
            'data'                => $rows->values(),
            'types'               => Investment::investmentTypes(),
            'total_count'         => $rows->count(),
            'active_count'        => $rows->where('status', Investment::STATUS_ACTIVE)->count(),
            'overdue_count'       => $rows->where('overdue_count', '>', 0)->count(),
            'total_invested'      => round((float) $rows->sum('total_invested'), 2),
            'total_invested_fmt'  => number_format((float) $rows->sum('total_invested'), 2, '.', ','),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_bills');
        $user = $request->user();

        foreach (['deduct_account_id', 'remind_before_days'] as $key) {
            $request->merge([$key => $request->filled($key) ? $request->integer($key) : null]);
        }

        $isRecurring = fn () => $request->input('payment_mode') === Investment::PAYMENT_MODE_RECURRING;

        $validated = $request->validate([
            'name'                      => ['required', 'string', 'max:255'],
            'investment_type'           => ['required', Rule::in(array_keys(Investment::investmentTypes()))],
            'investment_type_other'     => ['nullable', 'string', 'max:255'],
            'provider'                  => ['nullable', 'string', 'max:255'],
            'reference_number'          => ['nullable', 'string', 'max:255'],
            'description'               => ['nullable', 'string', 'max:2000'],
            'payment_mode'              => ['required', Rule::in([Investment::PAYMENT_MODE_RECURRING, Investment::PAYMENT_MODE_ONE_TIME])],
            'recurring_type'            => [Rule::requiredIf($isRecurring), 'nullable', Rule::in([Investment::RECURRING_PER_DAY, Investment::RECURRING_PER_MONTH, Investment::RECURRING_PER_YEAR])],
            'schedule_valid_until_year' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'contribution_amount'       => ['required', 'numeric', 'min:0.01'],
            'start_date'                => ['required', 'date'],
            'maturity_date'             => ['nullable', 'date', 'after_or_equal:start_date'],
            'expected_return_rate'      => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'target_amount'             => ['nullable', 'numeric', 'min:0'],
            'deduct_account_id'         => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'remind_before_days'        => ['nullable', 'integer', 'min:0', 'max:366'],
            'notes'                     => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated['investment_type'] === Investment::TYPE_OTHER) {
            if (trim((string) ($validated['investment_type_other'] ?? '')) === '') {
                throw ValidationException::withMessages(['investment_type_other' => 'Describe this investment type when you choose Other.']);
            }
        } else {
            $validated['investment_type_other'] = null;
        }

        if ($validated['payment_mode'] === Investment::PAYMENT_MODE_ONE_TIME) {
            $validated['recurring_type']            = null;
            $validated['schedule_valid_until_year'] = (int) Carbon::parse((string) $validated['start_date'])->format('Y');
        }

        $validated['status'] = Investment::STATUS_ACTIVE;

        $investment = $this->service->create($user, $business, $validated);

        return response()->json([
            'message' => 'Investment created.',
            'data'    => $this->format($investment->load(['deductAccount', 'ledgerTransactions']), $this->service->summary($investment)),
        ], 201);
    }

    public function show(Request $request, Investment $investment): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $investment->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Investment not found.'], 404);
        }

        $investment->load(['deductAccount.bank', 'ledgerTransactions.deductAccount']);
        $summary = $this->service->summary($investment);

        $ledger = $investment->ledgerTransactions->sortByDesc('id')->map(fn ($tx) => [
            'id'              => $tx->id,
            'amount'          => (float) $tx->amount,
            'occurrence_date' => $tx->occurrence_date?->format('Y-m-d'),
            'account_name'    => ($tx->meta['pay_from'] ?? null) === InvestmentService::PAY_FROM_CASH_DRAWER
                ? 'Cash drawer (till)'
                : $tx->deductAccount?->account_name,
            'bank_name'       => $tx->deductAccount?->bank_name,
            'created_at'      => $tx->created_at?->format('Y-m-d H:i'),
        ])->values();

        return response()->json([
            'data' => array_merge($this->format($investment, $summary), [
                'schedule' => $summary['schedule'],
                'ledger'   => $ledger,
            ]),
        ]);
    }

    public function contribute(Request $request, Investment $investment): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_bills');

        if ((int) $investment->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Investment not found.'], 404);
        }

        $validated = $request->validate([
            'occurrence_date'   => ['required', 'date'],
            'pay_from'          => ['nullable', Rule::in([InvestmentService::PAY_FROM_ACCOUNT, InvestmentService::PAY_FROM_CASH_DRAWER])],
            'deduct_account_id' => [
                Rule::requiredIf(fn () => $request->input('pay_from', InvestmentService::PAY_FROM_ACCOUNT) !== InvestmentService::PAY_FROM_CASH_DRAWER),
                'nullable', 'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $request->user()->id)
                    ->where('business_id', $business->id)),
            ],
            'amount'            => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $payFrom = $validated['pay_from'] ?? InvestmentService::PAY_FROM_ACCOUNT;

        try {
            $this->service->contribute(
                $investment,
                $business,
                $request->user(),
                (string) $validated['occurrence_date'],
                $payFrom === InvestmentService::PAY_FROM_CASH_DRAWER ? null : (int) $validated['deduct_account_id'],
                isset($validated['amount']) ? (float) $validated['amount'] : null,
                $payFrom,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Contribution recorded and account balance updated.']);
    }

    public function destroy(Request $request, Investment $investment): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_bills');

        if ((int) $investment->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Investment not found.'], 404);
        }

        $this->service->deleteForBusiness($investment);

        return response()->json(['message' => 'Investment deleted.']);
    }

    /** @param  array<string, mixed>  $summary */
    private function format(Investment $i, array $summary): array
    {
        $fmt = fn ($v) => $v === null ? null : number_format((float) $v, 2, '.', ',');

        return [
            'id'                        => $i->id,
            'name'                      => $i->name,
            'investment_type'           => $i->investment_type,
            'type_label'                => $i->typeDisplayLabel(),
            'provider'                  => $i->provider,
            'reference_number'          => $i->reference_number,
            'description'               => $i->description,
            'payment_mode'              => $i->payment_mode,
            'recurring_type'            => $i->recurring_type,
            'contribution_amount'       => (float) $i->contribution_amount,
            'contribution_amount_fmt'   => $fmt($i->contribution_amount),
            'schedule_valid_until_year' => $i->schedule_valid_until_year,
            'start_date'                => $i->start_date?->format('Y-m-d'),
            'maturity_date'             => $i->maturity_date?->format('Y-m-d'),
            'expected_return_rate'      => $i->expected_return_rate !== null ? (float) $i->expected_return_rate : null,
            'target_amount'             => $i->target_amount !== null ? (float) $i->target_amount : null,
            'target_amount_fmt'         => $fmt($i->target_amount),
            'deduct_account_id'         => $i->deduct_account_id,
            'account_name'              => $i->deductAccount?->account_name,
            'account_category'          => $i->deductAccount?->category,
            'remind_before_days'        => $i->remind_before_days,
            'status'                    => $i->status,
            'notes'                     => $i->notes,
            'total_invested'            => $summary['total_invested'],
            'total_invested_fmt'        => $fmt($summary['total_invested']),
            'planned_total_fmt'         => $fmt($summary['planned_total']),
            'goal_amount_fmt'           => $fmt($summary['goal_amount']),
            'progress_pct'              => $summary['progress_pct'],
            'periods_total'             => $summary['periods_total'],
            'periods_paid'              => $summary['periods_paid'],
            'overdue_count'             => $summary['overdue_count'],
            'is_overdue'                => $summary['overdue_count'] > 0,
            'next_due_ymd'              => $summary['next_due_ymd'],
            'expected_annual_return_fmt'=> $fmt($summary['expected_annual_return']),
        ];
    }
}
