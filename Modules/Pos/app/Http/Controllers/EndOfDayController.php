<?php
namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\SalePaymentSettlementService;

class EndOfDayController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly SalePaymentSettlementService $payments,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $unsettled = $business->sales()
            ->where('is_settled', false)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->with(['user', 'creditAccount'])
            ->withCount('items')
            ->orderByDesc('sold_at')
            ->get();

        $totalUnsettled = $unsettled->sum(fn ($s) => (float) $s->total);

        // Past settled batches grouped by date
        $history = $business->sales()
            ->where('is_settled', true)
            ->whereNotNull('settled_at')
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->selectRaw('DATE(settled_at) as settle_date, COUNT(*) as sale_count, SUM(total) as total_amount')
            ->groupBy('settle_date')
            ->orderByDesc('settle_date')
            ->limit(14)
            ->get();

        return view('pos::end-of-day.index', [
            'business'       => $business,
            'currency'       => $currency,
            'unsettled'      => $unsettled,
            'totalUnsettled' => $totalUnsettled,
            'history'        => $history,
        ]);
    }

    public function settle(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $unsettled = $business->sales()
            ->where('is_settled', false)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereIn('payment_method', [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD])
            ->whereNotNull('credit_account_id')
            ->get();

        if ($unsettled->isEmpty()) {
            return redirect()->route('pos.end-of-day')->with('status', 'No unsettled sales to process.');
        }

        $settled = 0;
        $errors  = 0;

        foreach ($unsettled as $sale) {
            try {
                $this->payments->settle(
                    $sale,
                    $business,
                    $request->user(),
                    (int) $sale->credit_account_id,
                    (float) $sale->total,
                    $sale->payment_method,
                );
                $sale->update(['is_settled' => true, 'settled_at' => now()]);
                $settled++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        $message = $settled > 0
            ? "{$settled} sale" . ($settled > 1 ? 's' : '') . ' settled to bank.'
            : 'Settlement failed.';
        if ($errors > 0) {
            $message .= " {$errors} could not be settled.";
        }

        return redirect()->route('pos.end-of-day')->with('status', $message);
    }
}
