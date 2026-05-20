<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Purchase\Http\Controllers\Concerns\ResolvesPurchaseBusiness;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Services\ChequePaymentService;

class ChequeController extends Controller
{
    use ResolvesPurchaseBusiness;

    public function __construct(
        private readonly ChequePaymentService $chequePayments,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $filter = $request->query('filter');
        $allowedFilters = ['all', 'pending', 'overdue', 'cleared', 'due'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('purchase::cheques.index', [
            'business' => $business,
            'currency' => $currency,
            'filter' => $filter,
            'cheques' => $this->chequePayments->listForBusiness($business, $filter === 'all' ? null : $filter),
            'summary' => $this->chequePayments->summaryForBusiness($business),
        ]);
    }

    public function show(Request $request, ChequePayment $chequePayment): View|RedirectResponse
    {
        $business = $this->requireCheque($request, $chequePayment);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $chequePayment->load([
            'goodsReceiveNote.purchase.supplier',
            'deductAccount.bankType',
            'ledgerTransaction',
            'user',
        ]);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = $this->accountsForPurchasePayment($business, $request);

        return view('purchase::cheques.show', [
            'business' => $business,
            'cheque' => $chequePayment,
            'currency' => $currency,
            'displayStatus' => $chequePayment->displayStatus(),
            'canDeduct' => ! $chequePayment->isCleared(),
            'accounts' => $accounts,
            'hasPaymentAccounts' => $accounts->isNotEmpty(),
            'canPayByCheque' => $this->businessHasCurrentAccount($business, $request),
        ]);
    }

    public function deduct(Request $request, ChequePayment $chequePayment): RedirectResponse
    {
        $business = $this->requireCheque($request, $chequePayment);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'deduct_account_id' => [
                Rule::requiredIf(! $chequePayment->deduct_account_id),
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('user_id', $request->user()->id)),
            ],
        ]);

        try {
            $cheque = $this->chequePayments->deductFromAccount(
                $chequePayment,
                $business,
                $request->user(),
                isset($validated['deduct_account_id']) ? (int) $validated['deduct_account_id'] : null,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('purchase.cheques.show', $chequePayment)
                ->withErrors($e->errors())
                ->withInput();
        }

        $accountLabel = $cheque->deductAccount?->deductOptionLabel() ?? 'account';

        return redirect()
            ->route('purchase.cheques.show', $cheque)
            ->with('status', 'Deducted '.number_format((float) $cheque->amount, 2).' from '.$accountLabel.'.');
    }

    private function requireCheque(Request $request, ChequePayment $cheque): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->chequePayments->chequeForBusiness($business, $cheque) instanceof ChequePayment, 404);

        return $business;
    }
}
