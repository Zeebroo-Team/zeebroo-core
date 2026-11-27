<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateWithdrawal;
use Modules\Auth\Services\AffiliateWithdrawalService;

/** Admin side: review affiliate withdrawal requests, approve, pay or reject them. */
class AdminAffiliateWithdrawalController extends Controller
{
    public function __construct(private readonly AffiliateWithdrawalService $withdrawals) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', AffiliateWithdrawal::STATUS_PENDING);
        if ($status !== 'all' && ! array_key_exists($status, AffiliateWithdrawal::STATUS_LABELS)) {
            $status = AffiliateWithdrawal::STATUS_PENDING;
        }

        $affiliateId = $request->integer('affiliate') ?: null;
        $search = (string) $request->query('search', '');

        return view('auth::admin.affiliates.withdrawals', [
            'withdrawals' => $this->withdrawals->adminList($status, $affiliateId, $search),
            'counts' => $this->withdrawals->statusCounts($affiliateId),
            'status' => $status,
            'search' => $search,
            'affiliate' => $affiliateId ? User::query()->find($affiliateId) : null,
        ]);
    }

    public function approve(Request $request, AffiliateWithdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $this->withdrawals->approve($withdrawal, $request->user(), $data['admin_note'] ?? null);

        return $this->back($request, __('Approved :amount for :name. Mark it as paid once the bank transfer is done.', [
            'amount' => $withdrawal->formattedAmount(),
            'name' => $withdrawal->user?->name,
        ]));
    }

    public function reject(Request $request, AffiliateWithdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'max:500']], [
            'admin_note.required' => __('Give a reason, so the affiliate knows why the request was rejected.'),
        ]);

        $this->withdrawals->reject($withdrawal, $request->user(), $data['admin_note']);

        return $this->back($request, __('Rejected :amount for :name. The amount is back in their available balance.', [
            'amount' => $withdrawal->formattedAmount(),
            'name' => $withdrawal->user?->name,
        ]));
    }

    public function markPaid(Request $request, AffiliateWithdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$withdrawal->created_at->toDateString()],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ], [
            'payment_reference.required' => __('Enter the bank transfer reference.'),
            'paid_on.after_or_equal' => __('The payment date cannot be before the request date.'),
        ]);

        $paidAt = Carbon::parse($data['paid_on'])->setTimeFrom(now());
        $this->withdrawals->markPaid($withdrawal, $request->user(), $data['payment_reference'], $paidAt, $data['admin_note'] ?? null);

        return $this->back($request, __('Marked :amount for :name as paid.', [
            'amount' => $withdrawal->formattedAmount(),
            'name' => $withdrawal->user?->name,
        ]));
    }

    /** Back to the list the admin was looking at (status tab, filters, page). */
    private function back(Request $request, string $message): RedirectResponse
    {
        $query = array_filter([
            'status' => $request->input('return_status'),
            'affiliate' => $request->input('return_affiliate'),
            'search' => $request->input('return_search'),
        ], fn ($v) => filled($v));

        return redirect()->route('admin.affiliate-withdrawals.index', $query)->with('success', $message);
    }
}
