<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateCode;
use Modules\Auth\Services\AffiliateCommissionService;
use Modules\Auth\Services\AffiliateProfileService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Auth\Services\AffiliateService;

/** Admin side of the affiliate programme: create marketer logins and see their referrals. */
class AdminAffiliateController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request, AffiliateCommissionService $commissions): View
    {
        $search = (string) $request->query('search', '');
        $affiliates = $this->affiliates->affiliates($search);

        return view('auth::admin.affiliates.index', [
            'affiliates' => $affiliates,
            'commissionTotals' => $affiliates->mapWithKeys(fn (User $a) => [$a->id => $commissions->totalFor($a)]),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'code' => $this->affiliates->codeRules()['code'],
        ]);

        $affiliate = $this->affiliates->createAffiliate($data);

        return redirect()->route('admin.affiliates.show', $affiliate)
            ->with('success', __('Affiliate login created for :name. They can sign in with :email.', [
                'name' => $affiliate->name,
                'email' => $affiliate->email,
            ]));
    }

    public function show(Request $request, User $user, AffiliateCommissionService $commissions, AffiliateProfileService $profiles): View
    {
        abort_unless($this->affiliates->isAffiliate($user), 404);

        $search = (string) $request->query('search', '');
        $profile = $profiles->profileFor($user);
        $documents = $profiles->documentsFor($user);

        return view('auth::admin.affiliates.show', [
            'affiliate' => $user,
            'profile' => $profile,
            'documents' => $documents,
            'completion' => $profiles->completion($user, $profile, $documents),
            'commissionTotal' => $commissions->totalFor($user),
            'stats' => $this->affiliates->stats($user),
            'codes' => $this->affiliates->codes($user),
            'referrals' => $this->affiliates->referrals($user, $search),
            'search' => $search,
            'maxCodes' => AffiliateService::MAX_CODES,
        ]);
    }

    /** The affiliate's commission page, as the admin sees it (to pay them out). */
    public function payments(Request $request, User $user, AffiliateCommissionService $commissions): View
    {
        abort_unless($this->affiliates->isAffiliate($user), 404);

        return view('auth::admin.affiliates.payments', ['affiliate' => $user] + $commissions->pageData(
            $user,
            $request->query('month'),
            $request->query('search'),
        ));
    }

    /** Opens an affiliate's ID card or passport copy (private file, admins only). */
    public function document(User $user, string $type, AffiliateProfileService $profiles): StreamedResponse
    {
        abort_unless($this->affiliates->isAffiliate($user), 404);

        return $profiles->documentResponse($user, $type);
    }

    public function storeCode(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->affiliates->isAffiliate($user), 404);

        $data = $request->validate($this->affiliates->codeRules());

        if (! $this->affiliates->canAddCode($user)) {
            return redirect()->route('admin.affiliates.show', $user)->withErrors([
                'code' => __('This affiliate already has :max codes.', ['max' => AffiliateService::MAX_CODES]),
            ]);
        }

        $code = $this->affiliates->generateCode($user, $data['code'] ?? null, $data['label'] ?? null);

        return redirect()->route('admin.affiliates.show', $user)
            ->with('success', __('Code :code created.', ['code' => $code->code]));
    }

    public function toggleCode(AffiliateCode $affiliateCode): RedirectResponse
    {
        $code = $this->affiliates->toggleCode($affiliateCode);

        return redirect()->route('admin.affiliates.show', $code->user_id)->with('success', $code->is_active
            ? __('Code :code is active again.', ['code' => $code->code])
            : __('Code :code is disabled. New sign-ups can no longer use it.', ['code' => $code->code]));
    }
}
