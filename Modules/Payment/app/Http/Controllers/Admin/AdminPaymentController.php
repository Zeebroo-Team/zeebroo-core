<?php

namespace Modules\Payment\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Payment\Services\AdminPaymentHistoryService;
use Modules\Payment\Services\AdminUserSeatOverviewService;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly AdminPaymentHistoryService $history,
        private readonly AdminUserSeatOverviewService $seats,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'type', 'gateway', 'platform', 'from', 'to', 'sort']);

        return view('payment::admin.index', [
            'payments' => $this->history->paginate(25, $filters),
            'summary' => $this->history->summary($filters),
            'statuses' => AdminPaymentHistoryService::STATUSES,
            'gateways' => $this->history->gateways(),
        ]);
    }

    public function show(int $payment): View
    {
        $record = $this->history->find($payment);

        return view('payment::admin.show', [
            'payment' => $record,
            'related' => $this->history->relatedForBusiness($record),
            // The business's user seats today (plan limit, extra users, monthly total)
            'seats' => $record->business ? $this->seats->forBusiness($record->business) : null,
        ]);
    }
}
