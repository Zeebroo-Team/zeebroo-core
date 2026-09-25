<?php

namespace App\Http\Controllers;

use App\Services\HomeOverviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;

class HomeController extends Controller
{
    public function __construct(private readonly HomeOverviewService $homeOverviewService) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard');
        }

        $hasBankAccount = Account::query()
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->exists();

        if (! $hasBankAccount) {
            return redirect()->route('dashboard');
        }

        return view('home', $this->homeOverviewService->forBusiness($business))
            ->with('business', $business);
    }
}
