<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Account\Services\LoanOverviewTooltipService;
use Illuminate\Http\RedirectResponse;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessCategory;

class DashboardController extends Controller
{
    public function dashboard(Request $request): Response|RedirectResponse
    {
        $redirect = $this->redirectIfSingleLocationNeedsBranch($request->user());
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $loanOverviewTooltip = app(LoanOverviewTooltipService::class)->forUser($request->user());
        $needsWarehouseBranchIntro = $this->needsWarehouseBranchIntro($request->user());

        return response()
            ->view('dashboard', [
                'loanOverviewTooltip' => $loanOverviewTooltip,
                'needsWarehouseBranchIntro' => $needsWarehouseBranchIntro,
                'businessCategoryOptions' => BusinessCategory::optionsForSelect(),
            ])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    private function redirectIfSingleLocationNeedsBranch(?User $user): ?RedirectResponse
    {
        if (!$user) {
            return null;
        }

        $business = $user->businesses()->latest()->first();
        if (!$business instanceof Business) {
            return null;
        }

        if ($business->warehouse_branch_intro_acknowledged_at === null) {
            return null;
        }

        if ($business->multiWarehouseBranchEnabled()) {
            return null;
        }

        if ($business->branches()->exists()) {
            return null;
        }

        return redirect()->route('business.single-branch.setup');
    }

    private function needsWarehouseBranchIntro(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $business = $user->businesses()->latest()->first();
        if (!$business instanceof Business) {
            return false;
        }

        /** @var int|string $bizId */
        $bizId = $business->getKey();
        if (session()->has('warehouse_intro_ack.'.$bizId)) {
            return false;
        }

        return Business::query()
            ->whereKey($bizId)
            ->whereNull('warehouse_branch_intro_acknowledged_at')
            ->exists();
    }

    public function adminPanel(): View
    {
        return view('admin');
    }
}
