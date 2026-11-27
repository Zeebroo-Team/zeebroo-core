<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\AffiliateCode;
use Modules\Auth\Models\AffiliateReferral;
use Modules\Auth\Services\AffiliateCommissionService;
use Modules\Auth\Services\AffiliateService;
use Modules\Mail\Services\AutomatedEmailService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Affiliate marketers: admin-created logins with role "affiliate" that generate
// referral codes; accounts created with a code are credited to that affiliate.

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->string('google_id')->nullable();
        $t->boolean('is_active')->default(true);
        $t->boolean('email_verification_required')->default(false);
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamp('last_seen_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('scope_type');
        $t->unsignedBigInteger('scope_id');
        $t->string('key');
        $t->text('value')->nullable();
        $t->string('value_type')->default('string');
        $t->timestamps();
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
    });
    Schema::create('permissions', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
    });
    Schema::create('hr_employees', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->string('personal_email')->nullable();
        $t->timestamps();
    });
    Schema::create('packages', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('package_id')->nullable();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('business_members', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id');
        $t->string('status')->default('active');
        $t->timestamps();
    });
    Schema::create('payments', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('business_id')->nullable();
        $t->unsignedBigInteger('package_id')->nullable();
        $t->string('payment_type')->default('subscription');
        $t->string('payment_status')->default('pending');
        $t->string('billing_cycle')->nullable();
        $t->decimal('amount', 10, 2)->default(0);
        $t->string('currency')->default('lkr');
        $t->timestamp('paid_at')->nullable();
        $t->timestamps();
    });
    Schema::create('user_activity_logs', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('platform');
        $t->string('event');
        $t->string('device_name')->nullable();
        $t->string('ip_address')->nullable();
        $t->text('user_agent')->nullable();
        $t->timestamps();
    });

    // The real migration: both tables plus the "affiliate" role.
    (require base_path('database/migrations/2026_11_26_200000_create_affiliate_tables.php'))->up();
    (require base_path('database/migrations/2026_11_27_100000_create_affiliate_profiles_table.php'))->up();
    (require base_path('database/migrations/2026_11_27_200000_create_affiliate_withdrawals_table.php'))->up();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Sign-up emails are out of scope here.
    $this->mock(AutomatedEmailService::class, function ($mock) {
        $mock->shouldReceive('emailVerificationEnabled')->andReturnFalse();
        $mock->shouldReceive('queueWelcome');
    });

    $this->affiliates = app(AffiliateService::class);
    $this->marketer = User::query()->create(['name' => 'Nimal', 'email' => 'nimal@example.com', 'password' => 'secret123'])->refresh();
    $this->marketer->assignRole('affiliate');
});

afterEach(function () {
    foreach (['affiliate_withdrawals', 'affiliate_documents', 'affiliate_profiles', 'affiliate_referrals', 'affiliate_codes', 'user_activity_logs', 'payments', 'business_members', 'businesses',
        'packages', 'hr_employees', 'role_has_permissions', 'model_has_permissions', 'model_has_roles', 'permissions', 'roles', 'settings', 'users'] as $table) {
        Schema::dropIfExists($table);
    }
});

it('creates the affiliate role in the migration', function () {
    expect(Role::query()->where('name', 'affiliate')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('generates random codes without look-alike characters, and custom codes in upper case', function () {
    $random = $this->affiliates->generateCode($this->marketer);
    $custom = $this->affiliates->generateCode($this->marketer, ' nimal-2026 ', 'Facebook');

    expect($random->code)->toMatch('/^ZB[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/')
        ->and($custom->code)->toBe('NIMAL-2026')
        ->and($custom->label)->toBe('Facebook')
        ->and($custom->signupUrl())->toEndWith('/register?ref=NIMAL-2026');
});

it('credits a web sign-up that used a referral code', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');

    $this->post('/register', [
        'name' => 'Kamal',
        'email' => 'kamal@example.com',
        'email_confirmation' => 'kamal@example.com',
        'password' => 'Password123!',
        'referral_code' => 'nimal2026',
    ])->assertRedirect(route('dashboard'));

    $kamal = User::query()->where('email', 'kamal@example.com')->firstOrFail();
    $referral = AffiliateReferral::query()->sole();

    expect($referral->affiliate_user_id)->toBe($this->marketer->id)
        ->and($referral->referred_user_id)->toBe($kamal->id)
        ->and($referral->code)->toBe('NIMAL2026')
        ->and($referral->platform)->toBe('web')
        ->and($kamal->hasRole('user'))->toBeTrue();
});

it('remembers the code from a shared link and prefills the sign-up form', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');

    $this->get('/register?ref=nimal2026')
        ->assertOk()
        ->assertSessionHas(AffiliateService::SESSION_KEY, 'NIMAL2026')
        ->assertCookie(AffiliateService::COOKIE_NAME)
        ->assertSee('value="NIMAL2026"', false);
});

it('ignores an unknown code in a shared link', function () {
    $this->get('/register?ref=NOPE1234')
        ->assertOk()
        ->assertSessionMissing(AffiliateService::SESSION_KEY);
});

it('rejects a sign-up with an unknown or disabled code and creates no account', function () {
    $code = $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    $this->affiliates->toggleCode($code);

    foreach (['NIMAL2026', 'WRONG999'] as $typed) {
        $this->post('/register', [
            'name' => 'Kamal',
            'email' => 'kamal@example.com',
            'email_confirmation' => 'kamal@example.com',
            'password' => 'Password123!',
            'referral_code' => $typed,
        ])->assertSessionHasErrors('referral_code');
    }

    expect(User::query()->where('email', 'kamal@example.com')->exists())->toBeFalse()
        ->and(AffiliateReferral::query()->count())->toBe(0);
});

it('stops crediting codes of a disabled affiliate login', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    $this->marketer->forceFill(['is_active' => false])->save();

    expect($this->affiliates->findActiveCode('NIMAL2026'))->toBeNull();
});

it('only credits an account once', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    $other = User::query()->create(['name' => 'Sunil', 'email' => 'sunil@example.com', 'password' => 'secret123'])->refresh();
    $other->assignRole('affiliate');
    $this->affiliates->generateCode($other, 'SUNIL2026');

    $kamal = User::query()->create(['name' => 'Kamal', 'email' => 'kamal@example.com', 'password' => 'x']);
    $this->affiliates->recordReferral($kamal, 'NIMAL2026', AffiliateReferral::PLATFORM_WEB);
    $this->affiliates->recordReferral($kamal, 'SUNIL2026', AffiliateReferral::PLATFORM_GOOGLE);

    expect(AffiliateReferral::query()->sole()->affiliate_user_id)->toBe($this->marketer->id);
});

it('lets an affiliate generate and disable their own codes, but not anyone else\'s', function () {
    $this->actingAs($this->marketer)
        ->post('/affiliate/codes', ['code' => 'nimal-fb', 'label' => 'Facebook'])
        ->assertRedirect(route('affiliate.dashboard'));

    $code = AffiliateCode::query()->sole();
    expect($code->code)->toBe('NIMAL-FB')->and($code->user_id)->toBe($this->marketer->id);

    $this->actingAs($this->marketer)
        ->post('/affiliate/codes', ['code' => 'NIMAL-FB'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->marketer)->patch("/affiliate/codes/{$code->id}/toggle")->assertRedirect();
    expect($code->fresh()->is_active)->toBeFalse();

    $other = User::query()->create(['name' => 'Sunil', 'email' => 'sunil@example.com', 'password' => 'secret123'])->refresh();
    $other->assignRole('affiliate');
    $this->actingAs($other)->patch("/affiliate/codes/{$code->id}/toggle")->assertNotFound();
});

it('keeps the affiliate panel away from other roles', function () {
    $user = User::query()->create(['name' => 'Kamal', 'email' => 'kamal@example.com', 'password' => 'x'])->refresh();
    $user->assignRole('user');

    $this->actingAs($user)->get('/affiliate')->assertForbidden();
    $this->actingAs($user)->post('/affiliate/codes', [])->assertForbidden();
    $this->actingAs($this->marketer)->get('/admin/affiliates')->assertForbidden();
});

it('sends affiliates to their panel after login and from the dashboard', function () {
    $this->post('/login', ['email' => 'nimal@example.com', 'password' => 'secret123'])
        ->assertRedirect(route('affiliate.dashboard'));

    $this->actingAs($this->marketer)->get('/dashboard')->assertRedirect(route('affiliate.dashboard'));
});

it('lets an admin create an affiliate login with a first code', function () {
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->refresh();
    $admin->assignRole('admin');

    $res = $this->actingAs($admin)->post('/admin/affiliates', [
        'name' => 'Ruwan',
        'email' => 'ruwan@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'code' => 'ruwan10',
    ]);

    $ruwan = User::query()->where('email', 'ruwan@example.com')->firstOrFail();
    $res->assertRedirect(route('admin.affiliates.show', $ruwan));

    expect($ruwan->hasRole('affiliate'))->toBeTrue()
        ->and($ruwan->affiliateCodes()->pluck('code')->all())->toBe(['RUWAN10']);

    // The new login works and lands on the affiliate panel.
    auth()->logout();
    $this->post('/login', ['email' => 'ruwan@example.com', 'password' => 'secret123'])
        ->assertRedirect(route('affiliate.dashboard'));
});

it('counts sign-ups, businesses set up and paid accounts for the affiliate', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');

    $a = User::query()->create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
    $b = User::query()->create(['name' => 'B', 'email' => 'b@example.com', 'password' => 'x']);
    $c = User::query()->create(['name' => 'C', 'email' => 'c@example.com', 'password' => 'x']);
    foreach ([$a, $b, $c] as $u) {
        $this->affiliates->recordReferral($u, 'NIMAL2026', AffiliateReferral::PLATFORM_WEB);
    }
    // Last month's sign-up
    AffiliateReferral::query()->where('referred_user_id', $c->id)->update(['created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    $package = DB::table('packages')->insertGetId(['name' => 'Pro']);
    $bizA = DB::table('businesses')->insertGetId(['user_id' => $a->id, 'name' => 'A Stores', 'package_id' => $package]);
    DB::table('businesses')->insert(['user_id' => $b->id, 'name' => 'B Traders']);
    DB::table('payments')->insert([
        ['user_id' => $a->id, 'business_id' => $bizA, 'payment_status' => 'succeeded', 'amount' => 2500],
        ['user_id' => $a->id, 'business_id' => $bizA, 'payment_status' => 'succeeded', 'amount' => 2500],
        ['user_id' => $b->id, 'business_id' => null, 'payment_status' => 'failed', 'amount' => 2500],
    ]);

    expect($this->affiliates->stats($this->marketer))->toBe([
        'total' => 3,
        'this_month' => 2,
        'with_business' => 2,
        'paid' => 1,
    ]);

    $rows = $this->affiliates->referrals($this->marketer)->getCollection()->keyBy('referred_user_id');
    expect($rows[$a->id]->has_paid)->toBeTrue()
        ->and($rows[$b->id]->has_paid)->toBeFalse()
        ->and($rows[$a->id]->referredUser->businesses->first()->package->name)->toBe('Pro');

    expect($this->affiliates->referrals($this->marketer, 'b@example')->total())->toBe(1);
});

it('never deletes an affiliate login through the sign-up cancel button', function () {
    $this->actingAs($this->marketer)->post('/signup/cancel')->assertRedirect(route('login'));

    expect(User::query()->whereKey($this->marketer->id)->exists())->toBeTrue();
});

// ── Commission ────────────────────────────────────────────────────────────────

/** A referred account with a business; returns [user, businessId]. */
function referredWithBusiness(AffiliateService $affiliates, string $email, string $code): array
{
    $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'x']);
    $affiliates->recordReferral($user, $code, AffiliateReferral::PLATFORM_WEB);
    $businessId = DB::table('businesses')->insertGetId(['user_id' => $user->id, 'name' => ucfirst(strtok($email, '@')).' Stores']);

    return [$user, $businessId];
}

function planPayment(int $userId, ?int $businessId, string $paidAt, float $amount = 2500, array $overrides = []): void
{
    DB::table('payments')->insert(array_merge([
        'user_id' => $userId,
        'business_id' => $businessId,
        'payment_type' => 'subscription',
        'payment_status' => 'succeeded',
        'billing_cycle' => 'monthly',
        'amount' => $amount,
        'currency' => 'lkr',
        'paid_at' => $paidAt,
        'created_at' => $paidAt,
        'updated_at' => $paidAt,
    ], $overrides));
}

it('pays half of the first 3 plan payments, then LKR 250 for each later payment', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    [$kamal, $biz] = referredWithBusiness($this->affiliates, 'kamal@example.com', 'NIMAL2026');

    // Inserted out of order: numbering follows the payment date, not the row id.
    planPayment($kamal->id, $biz, '2026-08-01 10:00:00', 3000);
    planPayment($kamal->id, $biz, '2026-06-01 10:00:00', 2500);
    planPayment($kamal->id, $biz, '2026-07-01 10:00:00', 2500);
    planPayment($kamal->id, $biz, '2026-09-01 10:00:00', 2500);
    planPayment($kamal->id, $biz, '2026-10-01 10:00:00', 2500);

    $commissions = app(AffiliateCommissionService::class);
    $entries = $commissions->entries($this->marketer)->sortBy('number')->values();

    expect($entries->pluck('number')->all())->toBe([1, 2, 3, 4, 5])
        ->and($entries->map(fn ($e) => $e['paid_at']->format('Y-m'))->all())->toBe(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10'])
        ->and($entries->pluck('commission')->all())->toBe([1250.0, 1250.0, 1500.0, 250.0, 250.0])
        ->and($entries->pluck('rule')->all())->toBe(['first', 'first', 'first', 'later', 'later'])
        ->and($entries->pluck('commission_currency')->unique()->all())->toBe(['LKR'])
        ->and($commissions->totalFor($this->marketer))->toBe('LKR 4,500.00');
});

it('counts the first 3 payments separately for each referred account', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    [$kamal, $kBiz] = referredWithBusiness($this->affiliates, 'kamal@example.com', 'NIMAL2026');
    [$sunil, $sBiz] = referredWithBusiness($this->affiliates, 'sunil@example.com', 'NIMAL2026');

    foreach (['2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01'] as $day) {
        planPayment($kamal->id, $kBiz, $day.' 09:00:00');
    }
    planPayment($sunil->id, $sBiz, '2026-09-15 09:00:00');

    $entries = app(AffiliateCommissionService::class)->entries($this->marketer);
    $sunilEntry = $entries->firstWhere(fn ($e) => $e['referred']->id === $sunil->id);

    expect($sunilEntry['number'])->toBe(1)
        ->and($sunilEntry['commission'])->toBe(1250.0)
        ->and($entries->where('rule', 'later')->count())->toBe(1);
});

it('leaves out failed, refunded, free, trial and extra-user payments, and other people\'s payments', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    [$kamal, $biz] = referredWithBusiness($this->affiliates, 'kamal@example.com', 'NIMAL2026');
    $stranger = User::query()->create(['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => 'x']);
    $strangerBiz = DB::table('businesses')->insertGetId(['user_id' => $stranger->id, 'name' => 'Other Co']);

    planPayment($kamal->id, $biz, '2026-06-01 09:00:00', 2500, ['payment_status' => 'failed']);
    planPayment($kamal->id, $biz, '2026-06-02 09:00:00', 2500, ['payment_status' => 'refunded']);
    planPayment($kamal->id, $biz, '2026-06-03 09:00:00', 0, ['payment_type' => 'free']);
    planPayment($kamal->id, $biz, '2026-06-04 09:00:00', 0, ['payment_type' => 'trial']);
    planPayment($kamal->id, $biz, '2026-06-05 09:00:00', 800, ['payment_type' => 'extra_users']);
    planPayment($stranger->id, $strangerBiz, '2026-06-06 09:00:00');
    planPayment($kamal->id, $biz, '2026-06-10 09:00:00', 2500);

    $entries = app(AffiliateCommissionService::class)->entries($this->marketer);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()['number'])->toBe(1)
        ->and($entries->first()['commission'])->toBe(1250.0);
});

it('credits payments on a referred account\'s business even when someone else paid', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    [$kamal, $biz] = referredWithBusiness($this->affiliates, 'kamal@example.com', 'NIMAL2026');
    $teammate = User::query()->create(['name' => 'Teammate', 'email' => 'teammate@example.com', 'password' => 'x']);

    planPayment($teammate->id, $biz, '2026-06-01 09:00:00');

    $entry = app(AffiliateCommissionService::class)->entries($this->marketer)->sole();
    expect($entry['referred']->id)->toBe($kamal->id);
});

it('filters commission by month and by account, and totals the filtered rows', function () {
    $this->affiliates->generateCode($this->marketer, 'NIMAL2026');
    [$kamal, $kBiz] = referredWithBusiness($this->affiliates, 'kamal@example.com', 'NIMAL2026');
    [$sunil, $sBiz] = referredWithBusiness($this->affiliates, 'sunil@example.com', 'NIMAL2026');
    planPayment($kamal->id, $kBiz, '2026-10-05 09:00:00', 2000);
    planPayment($kamal->id, $kBiz, '2026-11-05 09:00:00', 2000);
    planPayment($sunil->id, $sBiz, '2026-11-07 09:00:00', 4000);

    $commissions = app(AffiliateCommissionService::class);
    $november = $commissions->pageData($this->marketer, '2026-11');
    $kamalOnly = $commissions->pageData($this->marketer, null, 'kamal@');
    $badMonth = $commissions->pageData($this->marketer, 'not-a-month');

    expect($november['entries']->total())->toBe(2)
        ->and($november['filteredTotal'])->toBe('LKR 3,000.00')
        ->and($november['months'])->toBe(['2026-11' => 'November 2026', '2026-10' => 'October 2026'])
        ->and($kamalOnly['entries']->total())->toBe(2)
        ->and($kamalOnly['filteredTotal'])->toBe('LKR 2,000.00')
        ->and($badMonth['month'])->toBeNull()
        ->and($badMonth['entries']->total())->toBe(3);
});

it('keeps the commission pages to affiliates and admins', function () {
    $user = User::query()->create(['name' => 'Kamal', 'email' => 'kamal@example.com', 'password' => 'x'])->refresh();
    $user->assignRole('user');
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->refresh();
    $admin->assignRole('admin');

    $this->actingAs($user)->get('/affiliate/payments')->assertForbidden();
    $this->actingAs($this->marketer)->get("/admin/affiliates/{$this->marketer->id}/payments")->assertForbidden();
    $this->actingAs($admin)->get("/admin/affiliates/{$user->id}/payments")->assertNotFound();
});

it('numbers ordinals for the payment column', function () {
    $commissions = app(AffiliateCommissionService::class);

    expect(array_map(fn ($n) => $commissions->ordinal($n), [1, 2, 3, 4, 11, 12, 13, 21, 22, 103]))
        ->toBe(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '103rd']);
});

// ── Settings: profile, contact, payout bank, ID documents ─────────────────────

it('saves contact details and rejects a bad phone number', function () {
    $this->actingAs($this->marketer)->put('/affiliate/settings/contact', [
        'phone' => 'call me', 'address_line1' => 'No. 12, Main Street', 'city' => 'Kandy', 'country' => 'Sri Lanka',
    ])->assertSessionHasErrors('phone');

    $this->actingAs($this->marketer)->put('/affiliate/settings/contact', [
        'phone' => '+94 77 123 4567', 'phone_alt' => '011 234 5678',
        'address_line1' => 'No. 12, Main Street', 'address_line2' => '', 'city' => 'Kandy',
        'district' => 'Central', 'postal_code' => '20000', 'country' => 'Sri Lanka',
    ])->assertRedirect(route('affiliate.settings', ['tab' => 'contact']));

    $profile = \Modules\Auth\Models\AffiliateProfile::query()->where('user_id', $this->marketer->id)->sole();
    expect($profile->phone)->toBe('+94 77 123 4567')
        ->and($profile->address_line2)->toBeNull()
        ->and($profile->addressLine())->toBe('No. 12, Main Street, Kandy, Central 20000, Sri Lanka')
        ->and($profile->hasContact())->toBeTrue();
});

it('stores the payout account number encrypted, without spaces', function () {
    $this->actingAs($this->marketer)->put('/affiliate/settings/payout', [
        'bank_name' => 'Sampath Bank', 'bank_branch' => 'Kandy', 'account_holder_name' => 'Nimal Perera',
        'account_number' => '1234 5678 9012',
    ])->assertRedirect(route('affiliate.settings', ['tab' => 'payout']));

    $profile = \Modules\Auth\Models\AffiliateProfile::query()->where('user_id', $this->marketer->id)->sole();
    $raw = DB::table('affiliate_profiles')->where('user_id', $this->marketer->id)->value('account_number');

    expect($profile->account_number)->toBe('123456789012')
        ->and($raw)->not->toContain('123456789012')
        ->and($profile->maskedAccountNumber())->toBe('********9012')
        ->and($profile->hasPayout())->toBeTrue();
});

it('needs the current password to change the login email or password', function () {
    // A name change alone needs no password.
    $this->actingAs($this->marketer)->put('/affiliate/settings/profile', ['name' => 'Nimal P', 'email' => 'nimal@example.com'])
        ->assertSessionHasNoErrors();
    expect($this->marketer->fresh()->name)->toBe('Nimal P');

    $this->actingAs($this->marketer)->put('/affiliate/settings/profile', ['name' => 'Nimal P', 'email' => 'new@example.com'])
        ->assertSessionHasErrors('current_password');
    $this->actingAs($this->marketer)->put('/affiliate/settings/profile', [
        'name' => 'Nimal P', 'email' => 'new@example.com', 'current_password' => 'wrong-one',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($this->marketer)->put('/affiliate/settings/profile', [
        'name' => 'Nimal P', 'email' => 'new@example.com', 'current_password' => 'secret123',
        'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasNoErrors();

    $fresh = $this->marketer->fresh();
    expect($fresh->email)->toBe('new@example.com')
        ->and(\Illuminate\Support\Facades\Hash::check('brand-new-pass', $fresh->password))->toBeTrue();
});

it('requires both sides of the ID card and stores them privately', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'nic', 'id_number' => '199012345678',
        'nic_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg'),
    ])->assertSessionHasErrors('nic_back');

    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'nic', 'id_number' => '12345',
    ])->assertSessionHasErrors('id_number');

    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'nic', 'id_number' => '901234567v',
        'nic_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg'),
        'nic_back' => \Illuminate\Http\UploadedFile::fake()->create('back.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('affiliate.settings', ['tab' => 'identity']));

    $docs = \Modules\Auth\Models\AffiliateDocument::query()->where('user_id', $this->marketer->id)->get()->keyBy('type');
    expect($docs->keys()->sort()->values()->all())->toBe(['nic_back', 'nic_front'])
        ->and($docs['nic_back']->mime)->toBe('application/pdf')
        ->and(\Modules\Auth\Models\AffiliateProfile::query()->where('user_id', $this->marketer->id)->value('id_type'))->toBe('nic');
    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($docs['nic_front']->path);
    expect($docs['nic_front']->path)->toStartWith('affiliate-documents/'.$this->marketer->id.'/');

    $completion = app(\Modules\Auth\Services\AffiliateProfileService::class)->completion($this->marketer->fresh());
    expect($completion['identity'])->toBeTrue();

    // The front was already uploaded, so only a new back is needed to replace it.
    $oldBack = $docs['nic_back']->path;
    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'nic', 'id_number' => '901234567V',
        'nic_back' => \Illuminate\Http\UploadedFile::fake()->image('back2.png'),
    ])->assertSessionHasNoErrors();
    \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldBack);
});

it('rejects ID copies that are not images or PDFs', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'passport', 'id_number' => 'N1234567',
        'passport' => \Illuminate\Http\UploadedFile::fake()->create('passport.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors('passport');

    expect(\Modules\Auth\Models\AffiliateDocument::query()->count())->toBe(0);
});

it('removes the ID card copies when switching to a passport', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'nic', 'id_number' => '199012345678',
        'nic_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg'),
        'nic_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg'),
    ])->assertSessionHasNoErrors();
    $nicPaths = \Modules\Auth\Models\AffiliateDocument::query()->pluck('path');

    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'passport', 'id_number' => 'n1234567',
        'passport' => \Illuminate\Http\UploadedFile::fake()->image('passport.jpg'),
    ])->assertSessionHasNoErrors();

    expect(\Modules\Auth\Models\AffiliateDocument::query()->pluck('type')->all())->toBe(['passport'])
        ->and(\Modules\Auth\Models\AffiliateProfile::query()->value('id_number'))->toBe('N1234567');
    foreach ($nicPaths as $path) {
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
    }
});

it('shows ID copies only to their owner and to admins', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'passport', 'id_number' => 'N1234567',
        'passport' => \Illuminate\Http\UploadedFile::fake()->image('passport.jpg'),
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->marketer)->get('/affiliate/settings/documents/passport')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    // Another affiliate only ever reaches their own (missing) copy.
    $other = User::query()->create(['name' => 'Sunil', 'email' => 'sunil@example.com', 'password' => 'secret123'])->refresh();
    $other->assignRole('affiliate');
    $this->actingAs($other)->get('/affiliate/settings/documents/passport')->assertNotFound();

    $user = User::query()->create(['name' => 'Kamal', 'email' => 'kamal@example.com', 'password' => 'x'])->refresh();
    $user->assignRole('user');
    $this->actingAs($user)->get('/affiliate/settings/documents/passport')->assertForbidden();
    $this->actingAs($user)->get("/admin/affiliates/{$this->marketer->id}/documents/passport")->assertForbidden();

    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->refresh();
    $admin->assignRole('admin');
    $this->actingAs($admin)->get("/admin/affiliates/{$this->marketer->id}/documents/passport")->assertOk();
    $this->actingAs($admin)->get("/admin/affiliates/{$user->id}/documents/passport")->assertNotFound();
    $this->actingAs($admin)->get("/admin/affiliates/{$this->marketer->id}/documents/not-a-type")->assertNotFound();
});

it('lets an affiliate remove an ID copy', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->actingAs($this->marketer)->post('/affiliate/settings/identity', [
        'id_type' => 'passport', 'id_number' => 'N1234567',
        'passport' => \Illuminate\Http\UploadedFile::fake()->image('passport.jpg'),
    ]);
    $path = \Modules\Auth\Models\AffiliateDocument::query()->value('path');

    $this->actingAs($this->marketer)->delete('/affiliate/settings/documents/passport')
        ->assertRedirect(route('affiliate.settings', ['tab' => 'identity']));

    expect(\Modules\Auth\Models\AffiliateDocument::query()->count())->toBe(0);
    \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
});

// ── Withdrawals ──────────────────────────────────────────────────────────────

/** Fills every Settings section, so the affiliate may ask for a payout. */
function completeSettings(User $affiliate): void
{
    \Modules\Auth\Models\AffiliateProfile::query()->create([
        'user_id' => $affiliate->id, 'phone' => '+94 77 123 4567', 'address_line1' => 'No. 12, Main Street', 'city' => 'Kandy', 'country' => 'Sri Lanka',
        'bank_name' => 'Sampath Bank', 'bank_branch' => 'Kandy', 'account_holder_name' => 'Nimal Perera', 'account_number' => '123456789012',
        'id_type' => 'passport', 'id_number' => 'N1234567',
    ]);
    \Modules\Auth\Models\AffiliateDocument::query()->create([
        'user_id' => $affiliate->id, 'type' => 'passport', 'path' => 'x/passport.jpg', 'original_name' => 'passport.jpg', 'mime' => 'image/jpeg', 'size_bytes' => 100,
    ]);
}

/** Commission of LKR 4,250 for the marketer: one referred account, 5 payments of LKR 2,500. */
function earnCommission(AffiliateService $affiliates, User $marketer): void
{
    $affiliates->generateCode($marketer, 'NIMAL2026');
    [$kamal, $biz] = referredWithBusiness($affiliates, 'kamal@example.com', 'NIMAL2026');
    foreach (['2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'] as $day) {
        planPayment($kamal->id, $biz, $day.' 09:00:00');
    }
}

function adminUser(): User
{
    $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x'])->refresh();
    $admin->assignRole('admin');

    return $admin;
}

it('needs complete settings before a withdrawal can be requested', function () {
    earnCommission($this->affiliates, $this->marketer);

    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 2000])
        ->assertSessionHasErrors(['amount' => 'Complete your Settings (contact details, payout bank and ID) before asking for a withdrawal.']);

    expect(\Modules\Auth\Models\AffiliateWithdrawal::query()->count())->toBe(0);
});

it('checks the amount against the minimum and the available balance', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);

    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 500])->assertSessionHasErrors('amount');
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 4250.01])->assertSessionHasErrors('amount');
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => '1000.555'])->assertSessionHasErrors('amount');

    expect(\Modules\Auth\Models\AffiliateWithdrawal::query()->count())->toBe(0);
});

it('creates a request with a copy of the payout bank account and holds the amount', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);

    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => '2000.50', 'note' => 'For this month'])
        ->assertRedirect(route('affiliate.withdrawals'))
        ->assertSessionHasNoErrors();

    $w = \Modules\Auth\Models\AffiliateWithdrawal::query()->sole();
    expect($w->status)->toBe('pending')
        ->and((float) $w->amount)->toBe(2000.5)
        ->and($w->note)->toBe('For this month')
        ->and($w->account_number)->toBe('123456789012')
        ->and(DB::table('affiliate_withdrawals')->value('account_number'))->not->toContain('123456789012');

    // Changing the bank later does not move this request.
    \Modules\Auth\Models\AffiliateProfile::query()->update(['bank_name' => 'Other Bank']);
    expect($w->fresh()->bank_name)->toBe('Sampath Bank');

    $service = app(\Modules\Auth\Services\AffiliateWithdrawalService::class);
    expect($service->balance($this->marketer))->toBe(['earned' => 4250.0, 'in_review' => 2000.5, 'paid_out' => 0.0, 'available' => 2249.5]);

    // One open request at a time.
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 1000])->assertSessionHasErrors('amount');
});

it('lets an affiliate cancel their own request while it is in review', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 1500]);
    $w = \Modules\Auth\Models\AffiliateWithdrawal::query()->sole();

    $other = User::query()->create(['name' => 'Sunil', 'email' => 'sunil@example.com', 'password' => 'secret123'])->refresh();
    $other->assignRole('affiliate');
    $this->actingAs($other)->patch("/affiliate/withdrawals/{$w->id}/cancel")->assertNotFound();

    $this->actingAs($this->marketer)->patch("/affiliate/withdrawals/{$w->id}/cancel")->assertRedirect(route('affiliate.withdrawals'));
    expect($w->fresh()->status)->toBe('cancelled')
        ->and(app(\Modules\Auth\Services\AffiliateWithdrawalService::class)->balance($this->marketer)['available'])->toBe(4250.0);
});

it('walks a request through approve and mark as paid', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 3000]);
    $w = \Modules\Auth\Models\AffiliateWithdrawal::query()->sole();
    $admin = adminUser();

    // Paying before approving is not allowed.
    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/paid", ['payment_reference' => 'FT1', 'paid_on' => now()->toDateString()])
        ->assertSessionHasErrors('withdrawal');

    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/approve", ['admin_note' => 'Paying on Friday'])
        ->assertRedirect(route('admin.affiliate-withdrawals.index'));
    expect($w->fresh()->status)->toBe('approved')->and($w->fresh()->reviewed_by)->toBe($admin->id);

    // An approved request can no longer be cancelled by the affiliate.
    $this->actingAs($this->marketer)->patch("/affiliate/withdrawals/{$w->id}/cancel")->assertSessionHasErrors('withdrawal');

    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/paid", ['paid_on' => now()->toDateString()])
        ->assertSessionHasErrors('payment_reference');
    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/paid", ['payment_reference' => 'FT1', 'paid_on' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('paid_on');

    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/paid", [
        'payment_reference' => 'FT2611273344', 'paid_on' => now()->toDateString(), 'return_status' => 'approved',
    ])->assertRedirect(route('admin.affiliate-withdrawals.index', ['status' => 'approved']));

    $paid = $w->fresh();
    expect($paid->status)->toBe('paid')
        ->and($paid->payment_reference)->toBe('FT2611273344')
        ->and($paid->paid_at->toDateString())->toBe(now()->toDateString())
        ->and($paid->admin_note)->toBe('Paying on Friday')
        ->and(app(\Modules\Auth\Services\AffiliateWithdrawalService::class)->balance($this->marketer))
        ->toBe(['earned' => 4250.0, 'in_review' => 0.0, 'paid_out' => 3000.0, 'available' => 1250.0]);

    // A paid request is final.
    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/reject", ['admin_note' => 'Oops'])->assertSessionHasErrors('withdrawal');
});

it('rejects with a reason and gives the amount back', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 4250]);
    $w = \Modules\Auth\Models\AffiliateWithdrawal::query()->sole();
    $admin = adminUser();

    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/reject", ['admin_note' => ''])->assertSessionHasErrors('admin_note');

    $this->actingAs($admin)->patch("/admin/affiliate-withdrawals/{$w->id}/reject", ['admin_note' => 'Bank name does not match your ID'])
        ->assertSessionHasNoErrors();

    expect($w->fresh()->status)->toBe('rejected')
        ->and($w->fresh()->admin_note)->toBe('Bank name does not match your ID')
        ->and(app(\Modules\Auth\Services\AffiliateWithdrawalService::class)->balance($this->marketer)['available'])->toBe(4250.0);

    // The affiliate can ask again.
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 4250])->assertSessionHasNoErrors();
});

it('keeps withdrawal pages to their roles', function () {
    $user = User::query()->create(['name' => 'Ruwan', 'email' => 'ruwan@example.com', 'password' => 'x'])->refresh();
    $user->assignRole('user');

    $this->actingAs($user)->get('/affiliate/withdrawals')->assertForbidden();
    $this->actingAs($user)->post('/affiliate/withdrawals', ['amount' => 1000])->assertForbidden();
    $this->actingAs($this->marketer)->get('/admin/affiliate-withdrawals')->assertForbidden();

    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);
    $this->actingAs($this->marketer)->post('/affiliate/withdrawals', ['amount' => 1000]);
    $id = \Modules\Auth\Models\AffiliateWithdrawal::query()->value('id');
    $this->actingAs($this->marketer)->patch("/admin/affiliate-withdrawals/{$id}/approve")->assertForbidden();
});

it('counts and filters requests for the admin list', function () {
    earnCommission($this->affiliates, $this->marketer);
    completeSettings($this->marketer);
    $service = app(\Modules\Auth\Services\AffiliateWithdrawalService::class);
    $admin = adminUser();

    $first = $service->request($this->marketer, 1000);
    $service->reject($first, $admin, 'Wrong account');
    $second = $service->request($this->marketer, 1200);
    $service->approve($second, $admin);

    expect($service->statusCounts())->toMatchArray(['pending' => 0, 'approved' => 1, 'rejected' => 1, 'paid' => 0, 'all' => 2])
        ->and($service->adminList('approved')->total())->toBe(1)
        ->and($service->adminList('all', $this->marketer->id)->total())->toBe(2)
        ->and($service->adminList('all', null, 'nobody@')->total())->toBe(0);
});
