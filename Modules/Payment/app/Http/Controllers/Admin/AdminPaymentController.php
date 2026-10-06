<?php

namespace Modules\Payment\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Payment\Services\AdminPaymentHistoryService;

class AdminPaymentController extends Controller
{
    public function __construct(private readonly AdminPaymentHistoryService $history) {}

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
        ]);
    }
}
