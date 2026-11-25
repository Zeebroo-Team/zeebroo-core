<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Exceptions\UserLimitReachedException;
use Modules\Business\Models\Business;
use Modules\Business\Services\BusinessInvitationService;
use Modules\Business\Services\UserSeatService;
use Modules\Mail\Services\AutomatedEmailService;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\ExtraUserPurchaseService;
use Modules\Payment\Services\StripeSubscriptionService;
use Modules\Pos\Services\PosNotificationService;

// Package user limit: seat counting, blocking invites when full, and buying extra users.
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->timestamp('email_verified_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('packages', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->decimal('price', 10, 2)->default(0);
        $t->decimal('discounted_price', 10, 2)->nullable();
        $t->string('currency')->default('LKR');
        $t->unsignedInteger('max_users')->nullable();
        $t->decimal('extra_user_price', 10, 2)->nullable();
        $t->boolean('is_free')->default(false);
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('package_id')->nullable();
        $t->unsignedInteger('max_users_override')->nullable();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('business_roles', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->string('slug');
        $t->json('permissions')->nullable();
        $t->timestamps();
    });
    Schema::create('business_members', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id');
        $t->string('role');
        $t->json('permissions')->nullable();
        $t->string('status')->default('active');
        $t->unsignedBigInteger('invited_by')->nullable();
        $t->timestamps();
    });
    Schema::create('business_invitations', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('email');
        $t->string('role', 60);
        $t->json('permissions')->nullable();
        $t->string('token_hash', 64)->unique();
        $t->string('status', 20)->default('pending');
        $t->unsignedBigInteger('invited_by')->nullable();
        $t->unsignedBigInteger('accepted_by')->nullable();
        $t->timestamp('expires_at');
        $t->timestamp('last_sent_at')->nullable();
        $t->timestamp('accepted_at')->nullable();
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
    Schema::create('payments', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id')->nullable();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('package_id')->nullable();
        $t->string('payment_type')->default('subscription');
        $t->string('payment_status')->default('pending');
        $t->string('billing_cycle')->nullable();
        $t->string('platform')->nullable();
        $t->string('gateway')->nullable();
        $t->decimal('amount', 10, 2)->default(0);
        $t->string('currency')->nullable();
        $t->string('stripe_customer_id')->nullable();
        $t->string('stripe_checkout_session_id')->nullable();
        $t->string('stripe_subscription_id')->nullable();
        $t->string('stripe_payment_intent_id')->nullable();
        $t->string('stripe_invoice_id')->nullable();
        $t->string('stripe_subscription_status')->nullable();
        $t->timestamp('current_period_end')->nullable();
        $t->boolean('cancel_at_period_end')->default(false);
        $t->timestamp('paid_at')->nullable();
        $t->text('failure_reason')->nullable();
        $t->timestamp('due_at')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
    });

    DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    DB::table('users')->insert(['id' => 2, 'name' => 'Member', 'email' => 'member@example.com', 'password' => bcrypt('x')]);
    DB::table('packages')->insert([
        'id' => 3, 'name' => 'Bronze Plus', 'price' => 3500, 'currency' => 'LKR',
        'max_users' => 3, 'extra_user_price' => 500,
    ]);
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'package_id' => 3, 'name' => 'ABC Solution']);
    DB::table('business_roles')->insert(['business_id' => 7, 'name' => 'Staff', 'slug' => 'staff', 'permissions' => json_encode([])]);
    DB::table('business_members')->insert(['business_id' => 7, 'user_id' => 2, 'role' => 'staff']);

    Mail::fake();
    $this->seats = new UserSeatService();
    $this->invitations = new BusinessInvitationService(Mockery::mock(AutomatedEmailService::class), $this->seats);
    $this->business = Business::find(7);
    $this->owner = User::find(1);
});

/** Active paid Stripe subscription at the plan price (the signup row). */
function seatActiveSubscription(): Payment
{
    return Payment::create([
        'business_id' => 7, 'user_id' => 1, 'package_id' => 3,
        'payment_type' => Payment::TYPE_SUBSCRIPTION, 'payment_status' => Payment::STATUS_SUCCEEDED,
        'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'amount' => 3500, 'currency' => 'lkr',
        'stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_1',
        'stripe_subscription_status' => 'active', 'current_period_end' => now()->addDays(20), 'paid_at' => now(),
    ]);
}

test('owner, members and open invitations each take a seat', function () {
    expect($this->seats->used($this->business))->toBe(2) // owner + member
        ->and($this->seats->limit($this->business))->toBe(3);

    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);

    expect($this->seats->used($this->business))->toBe(3)
        ->and($this->seats->remaining($this->business))->toBe(0);
});

test('a new invitation is refused once every seat is taken', function () {
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);

    expect(fn () => $this->invitations->invite($this->business, 'b@example.com', 'staff', null, $this->owner))
        ->toThrow(UserLimitReachedException::class);
});

test('re-sending an open invitation does not need a free seat', function () {
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);

    [$again] = $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);

    expect($again->email)->toBe('a@example.com');
});

test('expired invitations free their seat', function () {
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);
    DB::table('business_invitations')->update(['expires_at' => now()->subDay()]);

    expect($this->seats->used($this->business))->toBe(2);
});

test('packages without max_users are unlimited', function () {
    DB::table('packages')->update(['max_users' => null]);
    $business = Business::find(7);

    expect($this->seats->limit($business))->toBeNull()
        ->and(fn () => $this->seats->assertCanAdd($business))->not->toThrow(UserLimitReachedException::class);
});

test('bought extra seats raise the limit', function () {
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);
    $this->seats->addExtraSeats($this->business, 1, 500);

    [$invitation] = $this->invitations->invite($this->business, 'b@example.com', 'staff', null, $this->owner);

    expect($invitation->exists)->toBeTrue()
        ->and($this->seats->limit($this->business))->toBe(4);
});

test('the summary shows plan price, seats and the monthly total incl. extra users', function () {
    seatActiveSubscription();
    $this->seats->addExtraSeats($this->business, 1, 500);

    $service = new ExtraUserPurchaseService(
        Mockery::mock(StripeSubscriptionService::class),
        $this->seats,
        Mockery::mock(PosNotificationService::class),
    );
    $summary = $service->summary($this->business, $this->owner);

    expect($summary['base_amount'])->toBe(3500.0)
        ->and($summary['extra_users'])->toBe(1)
        ->and($summary['extra_user_price'])->toBe(500.0)
        ->and($summary['monthly_total'])->toBe(4000.0)
        ->and($summary['user_limit'])->toBe(4)
        ->and($summary['currency'])->toBe('LKR')
        ->and($summary['can_purchase'])->toBeTrue();
});

test('only the owner of an active paid subscription can buy extra users', function () {
    $service = new ExtraUserPurchaseService(
        Mockery::mock(StripeSubscriptionService::class),
        $this->seats,
        Mockery::mock(PosNotificationService::class),
    );

    expect($service->summary($this->business, $this->owner)['can_purchase'])->toBeFalse(); // no subscription yet

    seatActiveSubscription();
    expect($service->summary($this->business, $this->owner)['can_purchase'])->toBeTrue()
        ->and($service->summary($this->business, User::find(2))['can_purchase'])->toBeFalse();

    DB::table('packages')->update(['extra_user_price' => null]);
    expect($service->summary(Business::find(7), $this->owner)['purchase_blocked_reason'])->toContain("doesn't offer extra users");
});

test('a paid extra-users checkout adds the seats and raises the Stripe subscription once', function () {
    seatActiveSubscription();

    $stripe = Mockery::mock(StripeSubscriptionService::class);
    $stripe->shouldReceive('upsertExtraUsersItem')->once()->with('sub_1', 2, 500.0, 'lkr', Mockery::type('string'));
    $notifications = Mockery::mock(PosNotificationService::class);
    $notifications->shouldReceive('notifyPaymentSucceeded')->once();

    $service = new ExtraUserPurchaseService($stripe, $this->seats, $notifications);

    $payment = Payment::create([
        'business_id' => 7, 'user_id' => 1, 'package_id' => 3,
        'payment_type' => Payment::TYPE_EXTRA_USERS, 'payment_status' => Payment::STATUS_PENDING,
        'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'amount' => 1000, 'currency' => 'lkr',
        'metadata' => ['quantity' => 2, 'unit_price' => 500, 'base_amount' => 3500, 'extra_users_before' => 0, 'new_monthly_total' => 4500],
    ]);

    expect($service->settle($payment, 'cs_1', 'cus_1', 'pi_1'))->toBeTrue()
        ->and($service->settle($payment->fresh(), 'cs_1', 'cus_1', 'pi_1'))->toBeFalse(); // webhook + return page

    $payment->refresh();
    expect($payment->isSucceeded())->toBeTrue()
        ->and($payment->metadata['stripe_synced'])->toBeTrue()
        ->and($this->seats->extraSeats(Business::find(7)))->toBe(2)
        ->and($this->seats->limit(Business::find(7)))->toBe(5);
});

test('the receipt lists extra users and the new monthly total', function () {
    $business = new Business(['name' => 'ABC Solution']);
    $purchase = new Payment([
        'payment_type' => Payment::TYPE_EXTRA_USERS, 'payment_status' => 'succeeded', 'amount' => 500,
        'currency' => 'lkr', 'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'paid_at' => now(),
        'metadata' => ['quantity' => 1, 'unit_price' => 500, 'base_amount' => 3500, 'extra_users_before' => 0, 'new_monthly_total' => 4000],
    ]);
    $renewal = new Payment([
        'payment_type' => Payment::TYPE_SUBSCRIPTION, 'payment_status' => 'succeeded', 'amount' => 4000,
        'currency' => 'lkr', 'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'paid_at' => now(),
        'metadata' => ['extra_users' => 1, 'extra_user_price' => 500],
    ]);

    $html = view('payment::receipt', ['business' => $business, 'payment' => $purchase])->render();
    expect($html)->toContain('Extra users')
        ->and($html)->toContain('Rs.4,000.00 / month from the next renewal');

    $html = view('payment::receipt', ['business' => $business, 'payment' => $renewal])->render();
    expect($html)->toContain('Rs.3,500.00')
        ->and($html)->toContain('Extra users')
        ->and($html)->toContain('Rs.4,000.00');
});

test('the admin overview totals extra users, their monthly revenue and full businesses', function () {
    seatActiveSubscription();
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner); // 3 / 3 → full
    $this->seats->addExtraSeats($this->business, 2, 500);                                    // 3 / 5
    Payment::create([
        'business_id' => 7, 'user_id' => 1, 'package_id' => 3,
        'payment_type' => Payment::TYPE_EXTRA_USERS, 'payment_status' => Payment::STATUS_SUCCEEDED,
        'amount' => 1000, 'currency' => 'lkr', 'paid_at' => now(), 'metadata' => ['quantity' => 2, 'unit_price' => 500],
    ]);
    DB::table('businesses')->insert(['id' => 8, 'user_id' => 2, 'package_id' => 3, 'name' => 'Full Shop']); // owner only…
    DB::table('business_members')->insert([
        ['business_id' => 8, 'user_id' => 1, 'role' => 'staff'],
    ]);
    DB::table('business_invitations')->insert([
        'business_id' => 8, 'email' => 'x@example.com', 'role' => 'staff', 'token_hash' => str_repeat('a', 64),
        'status' => 'pending', 'expires_at' => now()->addDay(),
    ]); // …+ member + invite = 3 / 3

    $service = new \Modules\Payment\Services\AdminUserSeatOverviewService(new ExtraUserPurchaseService(
        Mockery::mock(StripeSubscriptionService::class),
        $this->seats,
        Mockery::mock(PosNotificationService::class),
    ));
    $o = $service->overview();

    expect($o['limited_businesses'])->toBe(2)
        ->and($o['full_businesses'])->toBe(1)
        ->and($o['extra_seats_total'])->toBe(2)
        ->and($o['businesses_with_extras'])->toBe(1)
        ->and($o['extra_mrr']->all())->toBe(['LKR' => 1000.0])
        ->and($o['extra_revenue']->all())->toBe(['LKR' => 1000.0])
        ->and($o['extra_purchases_this_month'])->toBe(1)
        ->and($o['rows']->first()['business'])->toBe('Full Shop'); // fullest first
});

test('the admin seats page lists every business and filters by seat state', function () {
    DB::table('packages')->insert(['id' => 4, 'name' => 'Unlimited', 'price' => 0, 'currency' => 'LKR']);
    DB::table('businesses')->insert(['id' => 9, 'user_id' => 2, 'package_id' => 4, 'name' => 'Open Shop']);
    $this->seats->addExtraSeats($this->business, 1, 500);

    $service = app(\Modules\Payment\Services\AdminUserSeatOverviewService::class);

    expect($service->paginate([])->total())->toBe(2)
        ->and($service->paginate(['seats' => 'unlimited'])->items()[0]['business'])->toBe('Open Shop')
        ->and($service->paginate(['seats' => 'extras'])->items()[0]['business'])->toBe('ABC Solution')
        ->and($service->paginate(['search' => 'owner@example'])->total())->toBe(1)
        ->and($service->paginate(['package' => 4])->total())->toBe(1);
});

test('the admin seat detail lists the owner, members, open invitations and purchases', function () {
    $this->invitations->invite($this->business, 'a@example.com', 'staff', null, $this->owner);
    DB::table('business_invitations')->insert([
        'business_id' => 7, 'email' => 'old@example.com', 'role' => 'staff', 'token_hash' => str_repeat('b', 64),
        'status' => 'pending', 'expires_at' => now()->subDay(),
    ]);
    Payment::create([
        'business_id' => 7, 'user_id' => 1, 'package_id' => 3,
        'payment_type' => Payment::TYPE_EXTRA_USERS, 'payment_status' => Payment::STATUS_SUCCEEDED,
        'amount' => 1000, 'currency' => 'lkr', 'paid_at' => now(), 'metadata' => ['quantity' => 2, 'unit_price' => 500],
    ]);

    $d = app(\Modules\Payment\Services\AdminUserSeatOverviewService::class)->detail($this->business);

    expect($d['owner']->email)->toBe('owner@example.com')
        ->and($d['members']->pluck('user.email')->all())->toBe(['member@example.com'])
        ->and($d['open_invitations']->pluck('email')->all())->toBe(['a@example.com'])
        ->and($d['past_invitations']->pluck('email')->all())->toBe(['old@example.com'])
        ->and($d['seats_bought_total'])->toBe(2)
        ->and($d['purchase_revenue'])->toBe(1000.0)
        ->and($d['summary']['users_used'])->toBe(3);
});

test('an admin user limit for one business replaces the package limit', function () {
    (new \Modules\Package\Services\BusinessPackageService())->setMaxUsersOverride($this->business, 10);
    $business = Business::find(7);
    $this->seats->addExtraSeats($business, 2, 500);

    expect($this->seats->includedSeats($business))->toBe(10)
        ->and($this->seats->limit($business))->toBe(12); // bought extras still on top

    (new \Modules\Package\Services\BusinessPackageService())->setMaxUsersOverride($business, null);
    expect($this->seats->limit(Business::find(7)))->toBe(5); // back to package (3) + 2 extra
});

test('lowering the admin limit below usage blocks new invitations', function () {
    (new \Modules\Package\Services\BusinessPackageService())->setMaxUsersOverride($this->business, 1);

    expect(fn () => $this->invitations->invite(Business::find(7), 'a@example.com', 'staff', null, $this->owner))
        ->toThrow(UserLimitReachedException::class);
});

test('line items break extra users out on purchases and renewals', function () {
    $purchase = new Payment([
        'payment_type' => Payment::TYPE_EXTRA_USERS, 'amount' => 500,
        'metadata' => ['quantity' => 1, 'unit_price' => 500],
    ]);
    $renewal = new Payment([
        'payment_type' => Payment::TYPE_SUBSCRIPTION, 'amount' => 4000,
        'metadata' => ['extra_users' => 1, 'extra_user_price' => 500],
    ]);

    expect($purchase->lineItems())->toHaveCount(1)
        ->and($purchase->lineItems()[0]['quantity'])->toBe(1)
        ->and($renewal->lineItems())->toHaveCount(2)
        ->and($renewal->lineItems()[0]['amount'])->toBe(3500.0)
        ->and($renewal->lineItems()[1]['amount'])->toBe(500.0);
});
