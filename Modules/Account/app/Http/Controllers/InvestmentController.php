<?php

declare(strict_types=1);

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Account\Models\Investment;
use Modules\Account\Services\InvestmentService;
use Modules\Business\Models\Business;

class InvestmentController extends Controller
{
    public function __construct(private readonly InvestmentService $investments) {}

    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        $rows = $business ? $this->investments->listForBusiness($business) : collect();

        $accounts = $business
            ? Account::query()
                ->with(['bankType', 'bank', 'warehouse'])
                ->where('user_id', $request->user()->id)
                ->where('business_id', $business->id)
                ->orderBy('account_name')
                ->get()
            : collect();

        $currency = $business ? (string) (get_settings('business.currency', '', $business) ?: '') : '';
        $summaries = [];
        $totals = ['total_invested' => 0.0, 'active_count' => 0, 'overdue_count' => 0];
        foreach ($rows as $inv) {
            $s = $this->investments->summary($inv);
            $summaries[$inv->id] = $s;
            $totals['total_invested'] += $s['total_invested'];
            if ($inv->status === Investment::STATUS_ACTIVE) {
                $totals['active_count']++;
            }
            if ($s['overdue_count'] > 0) {
                $totals['overdue_count']++;
            }
        }

        return view('account::investments.index', [
            'business' => $business,
            'investments' => $rows,
            'summaries' => $summaries,
            'totals' => $totals,
            'accounts' => $accounts,
            'investmentTypes' => Investment::investmentTypes(),
            'currency' => $currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $request->merge([
            'deduct_account_id' => $request->filled('deduct_account_id') ? $request->integer('deduct_account_id') : null,
            'remind_before_days' => $request->filled('remind_before_days') ? $request->integer('remind_before_days') : null,
        ]);

        $isRecurring = fn () => $request->input('payment_mode') === Investment::PAYMENT_MODE_RECURRING;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'investment_type' => ['required', Rule::in(array_keys(Investment::investmentTypes()))],
            'investment_type_other' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'payment_mode' => ['required', Rule::in([Investment::PAYMENT_MODE_RECURRING, Investment::PAYMENT_MODE_ONE_TIME])],
            'recurring_type' => [Rule::requiredIf($isRecurring), 'nullable', Rule::in([Investment::RECURRING_PER_DAY, Investment::RECURRING_PER_MONTH, Investment::RECURRING_PER_YEAR])],
            'schedule_valid_until_year' => [Rule::requiredIf($isRecurring), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'contribution_amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'expected_return_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'deduct_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'remind_before_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated['investment_type'] === Investment::TYPE_OTHER) {
            if (trim((string) ($validated['investment_type_other'] ?? '')) === '') {
                throw ValidationException::withMessages(['investment_type_other' => 'Describe this investment type when you choose Other.']);
            }
        } else {
            $validated['investment_type_other'] = null;
        }

        if ($validated['payment_mode'] === Investment::PAYMENT_MODE_ONE_TIME) {
            $validated['recurring_type'] = null;
            $validated['schedule_valid_until_year'] = (int) Carbon::parse((string) $validated['start_date'])->format('Y');
        }

        $validated['status'] = Investment::STATUS_ACTIVE;

        $this->investments->create($request->user(), $business, $validated);

        return redirect()->route('account.investments.index')->with('status', 'Investment added.');
    }

    public function show(Request $request, Investment $investment): View
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        abort_unless($business !== null && (int) $investment->business_id === (int) $business->id, 404);

        $investment->load(['deductAccount.bank', 'ledgerTransactions.deductAccount']);
        $summary = $this->investments->summary($investment);

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('account::investments.show', [
            'business' => $business,
            'investment' => $investment,
            'summary' => $summary,
            'accounts' => $accounts,
            'investmentTypes' => Investment::investmentTypes(),
            'currency' => (string) (get_settings('business.currency', '', $business) ?: ''),
        ]);
    }

    public function contribute(Request $request, Investment $investment): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        abort_unless($business !== null && (int) $investment->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'pay_from' => ['nullable', Rule::in([InvestmentService::PAY_FROM_ACCOUNT, InvestmentService::PAY_FROM_CASH_DRAWER])],
            'deduct_account_id' => [
                Rule::requiredIf(fn () => $request->input('pay_from', InvestmentService::PAY_FROM_ACCOUNT) !== InvestmentService::PAY_FROM_CASH_DRAWER),
                'nullable', 'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)),
            ],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $payFrom = $validated['pay_from'] ?? InvestmentService::PAY_FROM_ACCOUNT;

        try {
            $this->investments->contribute(
                $investment,
                $business,
                $user,
                (string) $validated['occurrence_date'],
                $payFrom === InvestmentService::PAY_FROM_CASH_DRAWER ? null : (int) $validated['deduct_account_id'],
                isset($validated['amount']) ? (float) $validated['amount'] : null,
                $payFrom,
            );
        } catch (ValidationException $e) {
            return redirect()->route('account.investments.show', $investment)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('account.investments.show', $investment)->with('status', 'Contribution recorded and account balance updated.');
    }

    public function destroy(Request $request, Investment $investment): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_unless($business !== null && (int) $investment->business_id === (int) $business->id, 404);

        $this->investments->deleteForBusiness($investment);

        return redirect()->route('account.investments.index')->with('status', 'Investment removed.');
    }
}
