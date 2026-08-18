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
use Modules\Account\Models\Account;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasRole('admin')) {
            return redirect()->route('admin.panel');
        }

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
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth   = $now->copy()->subMonth()->endOfMonth();

        // Summary counts
        $totalUsers        = User::count();
        $usersThisMonth    = User::where('created_at', '>=', $startOfMonth)->count();
        $usersLastMonth    = User::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $totalBusinesses   = Business::count();
        $bizThisMonth      = Business::where('created_at', '>=', $startOfMonth)->count();
        $bizLastMonth      = Business::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $totalAccounts     = Account::count();
        $accountsThisMonth = Account::where('created_at', '>=', $startOfMonth)->count();

        // Monthly join chart — last 12 months
        $months = collect(range(11, 0))->map(fn ($i) => $now->copy()->subMonths($i)->format('Y-m'));

        $driver = DB::getDriverName();
        $ymExpr = $driver === 'sqlite'
            ? DB::raw("strftime('%Y-%m', created_at) as ym")
            : DB::raw("DATE_FORMAT(created_at,'%Y-%m') as ym");

        $usersByMonth = User::query()
            ->select($ymExpr, DB::raw('count(*) as total'))
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $bizByMonth = Business::query()
            ->select($ymExpr, DB::raw('count(*) as total'))
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $chartLabels    = $months->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y'))->values();
        $chartUsers     = $months->map(fn ($m) => (int) ($usersByMonth[$m] ?? 0))->values();
        $chartBiz       = $months->map(fn ($m) => (int) ($bizByMonth[$m] ?? 0))->values();

        // Recent 5 users
        $recentUsers = User::with('roles')->orderByDesc('created_at')->limit(5)->get();

        return view('admin', compact(
            'totalUsers', 'usersThisMonth', 'usersLastMonth',
            'totalBusinesses', 'bizThisMonth', 'bizLastMonth',
            'totalAccounts', 'accountsThisMonth',
            'chartLabels', 'chartUsers', 'chartBiz',
            'recentUsers',
        ));
    }
}
