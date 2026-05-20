<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Transaction\Services\TransactionService;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());
        $transactions = $this->transactionService->listForBusiness($business);

        return view('transaction::index', [
            'business' => $business,
            'transactions' => $transactions,
        ]);
    }
}
