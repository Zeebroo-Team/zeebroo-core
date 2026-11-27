<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateCode;
use Modules\Auth\Services\AffiliateCommissionService;
use Modules\Auth\Services\AffiliateProfileService;
use Modules\Auth\Services\AffiliateService;

/** The affiliate marketer's own panel: their codes and the accounts they referred. */
class AffiliateController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request, AffiliateCommissionService $commissions, AffiliateProfileService $profiles): View
    {
        $user = $request->user();
        $search = (string) $request->query('search', '');

        return view('auth::affiliate.index', [
            'settingsCompletion' => $profiles->completion($user),
            'commissionTotal' => $commissions->totalFor($user),
            'stats' => $this->affiliates->stats($user),
            'codes' => $this->affiliates->codes($user),
            'referrals' => $this->affiliates->referrals($user, $search),
            'search' => $search,
            'maxCodes' => AffiliateService::MAX_CODES,
        ]);
    }

    /** Payments made by referred accounts and the commission each one earns. */
    public function payments(Request $request, AffiliateCommissionService $commissions): View
    {
        return view('auth::affiliate.payments', $commissions->pageData(
            $request->user(),
            $request->query('month'),
            $request->query('search'),
        ));
    }

    public function storeCode(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate($this->affiliates->codeRules());

        if (! $this->affiliates->canAddCode($user)) {
            return redirect()->route('affiliate.dashboard')->withErrors([
                'code' => __('You can have up to :max codes. Disable or reuse an existing code instead.', ['max' => AffiliateService::MAX_CODES]),
            ]);
        }

        $code = $this->affiliates->generateCode($user, $data['code'] ?? null, $data['label'] ?? null);

        return redirect()->route('affiliate.dashboard')
            ->with('success', __('Code :code is ready to share.', ['code' => $code->code]));
    }

    public function toggleCode(Request $request, AffiliateCode $affiliateCode): RedirectResponse
    {
        abort_unless((int) $affiliateCode->user_id === (int) $request->user()->id, 404);

        $code = $this->affiliates->toggleCode($affiliateCode);

        return redirect()->route('affiliate.dashboard')->with('success', $code->is_active
            ? __('Code :code is active again.', ['code' => $code->code])
            : __('Code :code is disabled. New sign-ups can no longer use it.', ['code' => $code->code]));
    }
}
