<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateWithdrawal;
use Modules\Auth\Services\AffiliateProfileService;
use Modules\Auth\Services\AffiliateWithdrawalService;

/** Affiliate side: ask for a commission payout and follow its status. */
class AffiliateWithdrawalController extends Controller
{
    public function __construct(private readonly AffiliateWithdrawalService $withdrawals) {}

    public function index(Request $request, AffiliateProfileService $profiles): View
    {
        $user = $request->user();
        $balance = $this->withdrawals->balance($user);

        return view('auth::affiliate.withdrawals', [
            'balance' => $balance,
            'blocker' => $this->withdrawals->blocker($user, $balance),
            'openRequest' => $this->withdrawals->openRequest($user),
            'history' => $this->withdrawals->history($user),
            'profile' => $profiles->profileFor($user),
            'currency' => AffiliateWithdrawalService::CURRENCY,
            'minAmount' => AffiliateWithdrawalService::MIN_AMOUNT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.decimal' => __('Enter the amount with at most 2 decimal places.'),
        ]);

        $withdrawal = $this->withdrawals->request($request->user(), (float) $data['amount'], $data['note'] ?? null);

        return redirect()->route('affiliate.withdrawals')
            ->with('success', __('Withdrawal request for :amount sent. We will let you know once it is reviewed.', ['amount' => $withdrawal->formattedAmount()]));
    }

    public function cancel(Request $request, AffiliateWithdrawal $withdrawal): RedirectResponse
    {
        abort_unless((int) $withdrawal->user_id === (int) $request->user()->id, 404);

        $this->withdrawals->cancel($withdrawal);

        return redirect()->route('affiliate.withdrawals')
            ->with('success', __('Withdrawal request for :amount cancelled.', ['amount' => $withdrawal->formattedAmount()]));
    }
}
