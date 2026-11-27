<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\AffiliateCode;
use Modules\Auth\Models\AffiliateReferral;
use Spatie\Permission\Models\Role;

/**
 * Affiliate sales marketers: an admin creates their login (role "affiliate"),
 * they generate referral codes, and every account that signs up with one of
 * those codes (web form, Google sign-up or the desktop app) is recorded as
 * their referral.
 */
class AffiliateService
{
    public const ROLE = 'affiliate';

    /** Upper limit of codes per affiliate, so the list stays manageable. */
    public const MAX_CODES = 20;

    /** Where a ?ref= code from a shared link is kept until the visitor signs up. */
    public const SESSION_KEY = 'affiliate_ref';

    public const COOKIE_NAME = 'zb_ref';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    /** Random codes avoid look-alike characters (0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Custom codes: letters, digits and dashes, 4 to 20 characters. */
    public const CUSTOM_CODE_PATTERN = '/^[A-Za-z0-9-]{4,20}$/';

    public function __construct(private readonly AffiliateCommissionService $commissions) {}

    public function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /**
     * An active code whose owner is still an active affiliate, or null.
     */
    public function findActiveCode(?string $code): ?AffiliateCode
    {
        $code = $this->normalize($code);
        if ($code === '') {
            return null;
        }

        return AffiliateCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->whereHas('roles', fn ($r) => $r->where('name', self::ROLE)))
            ->first();
    }

    public function isCodeTaken(string $code): bool
    {
        return AffiliateCode::query()->where('code', $this->normalize($code))->exists();
    }

    /**
     * Validation for the "new code" form: an optional custom code (random when
     * left blank) and an optional label such as "Facebook" or "Colombo expo".
     *
     * @return array<string, array<int, mixed>>
     */
    public function codeRules(): array
    {
        return [
            'code' => ['nullable', 'string', 'regex:'.self::CUSTOM_CODE_PATTERN, function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && $this->isCodeTaken((string) $value)) {
                    $fail(__('This code is already taken. Try another one.'));
                }
            }],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Validation for the optional referral code on sign-up forms.
     *
     * @return array<int, mixed>
     */
    public function signupCodeRule(): array
    {
        return ['nullable', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && $this->findActiveCode((string) $value) === null) {
                $fail(__('This referral code is not valid. Check it, or leave the field empty.'));
            }
        }];
    }

    public function canAddCode(User $affiliate): bool
    {
        return AffiliateCode::query()->where('user_id', $affiliate->id)->count() < self::MAX_CODES;
    }

    /**
     * Creates a code for the affiliate: the custom one when given (already
     * validated as free), otherwise a random "ZB" code.
     */
    public function generateCode(User $affiliate, ?string $customCode = null, ?string $label = null): AffiliateCode
    {
        $code = $this->normalize($customCode);
        if ($code === '') {
            $code = $this->randomUniqueCode();
        }

        $label = trim((string) $label);

        return AffiliateCode::query()->create([
            'user_id' => $affiliate->id,
            'code' => $code,
            'label' => $label !== '' ? $label : null,
            'is_active' => true,
        ]);
    }

    public function toggleCode(AffiliateCode $code): AffiliateCode
    {
        $code->is_active = ! $code->is_active;
        $code->save();

        return $code;
    }

    /**
     * Links a just-created account to the affiliate who owns $code. Does nothing
     * for an empty, unknown or disabled code, for the affiliate's own account,
     * or when the account was already referred.
     */
    public function recordReferral(User $newUser, ?string $code, string $platform): ?AffiliateReferral
    {
        $affiliateCode = $this->findActiveCode($code);
        if ($affiliateCode === null || (int) $affiliateCode->user_id === (int) $newUser->id) {
            return null;
        }

        return AffiliateReferral::query()->firstOrCreate(
            ['referred_user_id' => $newUser->id],
            [
                'affiliate_user_id' => $affiliateCode->user_id,
                'affiliate_code_id' => $affiliateCode->id,
                'code' => $affiliateCode->code,
                'platform' => $platform,
            ],
        );
    }

    /**
     * Sign-up page opened from a shared link (/register?ref=CODE): keep a valid
     * code in the session and a 30-day cookie, so it still applies when the
     * visitor signs up with Google or comes back later.
     */
    public function rememberFromRequest(Request $request): void
    {
        $code = $this->findActiveCode((string) $request->query('ref', ''));
        if ($code === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $code->code);
        Cookie::queue(self::COOKIE_NAME, $code->code, self::COOKIE_MINUTES);
    }

    /** The remembered code from a shared link, when it is still valid. */
    public function pendingCode(Request $request): ?string
    {
        $candidates = [
            (string) $request->session()->get(self::SESSION_KEY, ''),
            (string) $request->cookie(self::COOKIE_NAME, ''),
        ];

        foreach ($candidates as $candidate) {
            if ($code = $this->findActiveCode($candidate)) {
                return $code->code;
            }
        }

        return null;
    }

    public function forgetPending(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }

    /**
     * Headline numbers for one affiliate: referred accounts, this month, how
     * many have set up a business and how many have paid for a plan.
     *
     * @return array{total: int, this_month: int, with_business: int, paid: int}
     */
    public function stats(User $affiliate): array
    {
        $referredIds = AffiliateReferral::query()
            ->where('affiliate_user_id', $affiliate->id)
            ->pluck('referred_user_id');

        return [
            'total' => $referredIds->count(),
            'this_month' => AffiliateReferral::query()
                ->where('affiliate_user_id', $affiliate->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'with_business' => $referredIds->isEmpty() ? 0 : User::query()
                ->whereIn('id', $referredIds)
                ->has('businesses')
                ->count(),
            // Same payments the commission page counts (successful plan payments).
            'paid' => $referredIds->isEmpty() ? 0 : $this->commissions->payingUserIds($affiliate)->count(),
        ];
    }

    /** @return Collection<int, AffiliateCode> */
    public function codes(User $affiliate): Collection
    {
        return AffiliateCode::query()
            ->where('user_id', $affiliate->id)
            ->withCount('referrals')
            ->orderByDesc('is_active')
            ->latest()
            ->get();
    }

    /**
     * Accounts referred by this affiliate, newest first, with their business,
     * package and whether they have paid. Search matches name or email.
     */
    public function referrals(User $affiliate, ?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) $search);

        $page = AffiliateReferral::query()
            ->where('affiliate_user_id', $affiliate->id)
            ->when($search !== '', fn ($q) => $q->whereHas('referredUser', fn ($u) => $u->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))))
            ->with(['referredUser.businesses.package'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $paidIds = $page->getCollection()->isEmpty() ? collect() : $this->commissions->payingUserIds($affiliate);

        $page->getCollection()->each(function (AffiliateReferral $referral) use ($paidIds): void {
            $referral->setAttribute('has_paid', $paidIds->contains((int) $referral->referred_user_id));
        });

        return $page;
    }

    /**
     * Admin list: every affiliate login with code and referral counts.
     *
     * @return Collection<int, User>
     */
    public function affiliates(?string $search = null): Collection
    {
        $search = trim((string) $search);

        // whereHas, not Spatie's role() scope: that one throws when the role row is missing.
        return User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', self::ROLE))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withCount([
                'affiliateCodes',
                'affiliateReferrals',
                'affiliateReferrals as referrals_this_month_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()),
            ])
            ->orderBy('name')
            ->get();
    }

    public function isAffiliate(User $user): bool
    {
        return $user->hasRole(self::ROLE);
    }

    /**
     * Admin creates an affiliate login. The first code is created straight away
     * (the admin's custom one, or a random one) so the marketer can start sharing.
     *
     * @param  array{name: string, email: string, password: string, code?: ?string}  $data
     */
    public function createAffiliate(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            Role::firstOrCreate(['name' => self::ROLE, 'guard_name' => 'web']);
            $user->syncRoles([self::ROLE]);

            $this->generateCode($user, $data['code'] ?? null);

            return $user;
        });
    }

    /** The affiliate who referred this account, if any. */
    public function referralOf(User $user): ?AffiliateReferral
    {
        return AffiliateReferral::query()
            ->where('referred_user_id', $user->id)
            ->with('affiliate')
            ->first();
    }

    private function randomUniqueCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        do {
            $code = 'ZB';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while ($this->isCodeTaken($code));

        return $code;
    }
}
