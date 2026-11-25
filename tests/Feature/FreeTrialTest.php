<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Package\Models\Package;
use Modules\Package\Services\PlanChangeService;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\ExtraUserPurchaseService;
use Modules\Payment\Services\PaymentProvisioningService;
use Modules\Payment\Services\StripePaymentSyncService;
use Modules\Payment\Services\StripeSubscriptionService;
use Modules\Pos\Services\PosNotificationService;

// Free trial package: starting it, single use, expiry, and moving to a real plan
// (hidden-feature warnings, recommendations, seat fitting, paid vs free targets).
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->timestamp('trial_used_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('packages', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('slug')->nullable();
        $t->text('description')->nullable();
        $t->decimal('price', 10, 2)->default(0);
        $t->decimal('discounted_price', 10, 2)->nullable();
        $t->string('currency')->default('LKR');
        $t->unsignedInteger('max_users')->nullable();
        $t->decimal('extra_user_price', 10, 2)->nullable();
        $t->boolean('is_free')->default(false);
        $t->boolean('is_trial')->default(false);
        $t->unsignedSmallInteger('trial_days')->nullable();
        $t->json('features')->nullable();
        $t->boolean('is_active')->default(true);
        $t->boolean('is_mobile_only')->default(false);
        $t->boolean('supports_pos_lite')->default(false);
        $t->integer('sort_order')->default(0);
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('package_id')->nullable();
        $t->boolean('has_unlimited_access')->default(false);
        $t->boolean('has_manual_features')->default(false);
        $t->unsignedInteger('max_users_override')->nullable();
        $t->unsignedInteger('payment_grace_days')->nullable();
        $t->timestamp('trial_ends_at')->nullable();
        $t->timestamp('trial_reminder_sent_at')->nullable();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('business_feature_overrides', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('feature_key');
        $t->boolean('enabled');
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
        $t->timestamp('deactivated_at')->nullable();
        $t->timestamps();
    });
    Schema::create('business_invitations', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('email');
        $t->string('role', 60);
        $t->string('token_hash', 64)->unique();
        $t->string('status', 20)->default('pending');
        $t->timestamp('expires_at');
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
    // Usage table for the "you used CRM" warning.
    Schema::create('crm_leads', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->timestamps();
    });

    $all = array_keys(config('features.list'));

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x'],
        ['id' => 2, 'name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'x'],
        ['id' => 3, 'name' => 'Ben', 'email' => 'ben@example.com', 'password' => 'x'],
    ]);
    foreach ([
        ['id' => 1, 'name' => 'Free Trial', 'price' => 0, 'is_trial' => true, 'trial_days' => 7, 'max_users' => 10, 'features' => json_encode($all), 'sort_order' => 5],
        ['id' => 2, 'name' => 'Silver', 'price' => 2000, 'is_trial' => false, 'trial_days' => null, 'max_users' => 2,
            'features' => json_encode(['point_of_sale', 'product_management', 'account_management']), 'sort_order' => 1],
        ['id' => 3, 'name' => 'Gold', 'price' => 5000, 'is_trial' => false, 'trial_days' => null, 'max_users' => null,
            'features' => json_encode(['point_of_sale', 'product_management', 'account_management', 'crm']), 'sort_order' => 2],
        ['id' => 4, 'name' => 'Starter', 'price' => 0, 'is_trial' => false, 'trial_days' => null, 'max_users' => 1,
            'features' => json_encode(['account_management']), 'sort_order' => 3],
    ] as $row) {
        DB::table('packages')->insert($row);
    }
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'package_id' => 1, 'name' => 'ABC Solution']);

    $this->owner = User::find(1);
    $this->business = Business::find(7);
    $this->trialPkg = Package::find(1);

    $this->stripe = Mockery::mock(StripeSubscriptionService::class);
    app()->instance(StripeSubscriptionService::class, $this->stripe);
});

function trialStart($test): Payment
{
    return app(PaymentProvisioningService::class)->createInitialPayment($test->business, $test->trialPkg, $test->owner);
}

test('starting the trial needs no payment and sets its end date', function () {
    $payment = trialStart($this);

    $this->business->refresh();
    expect($payment->payment_type)->toBe(Payment::TYPE_TRIAL)
        ->and($payment->payment_status)->toBe(Payment::STATUS_SUCCEEDED)
        ->and($this->business->isOnTrial())->toBeTrue()
        ->and($this->business->trialHasExpired())->toBeFalse()
        ->and($this->business->trial_ends_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($this->owner->fresh()->trial_used_at)->not->toBeNull()
        ->and($this->business->overdueSubscriptionPayment())->toBeNull();
});

test('the trial is offered first, and never again once used', function () {
    expect(Package::query()->offeredTo($this->owner)->pluck('name')->first())->toBe('Free Trial');

    trialStart($this);

    expect(Package::query()->offeredTo($this->owner->fresh())->pluck('name')->all())->not->toContain('Free Trial')
        ->and(fn () => trialStart($this))->toThrow(ValidationException::class);
});

test('the trial is hidden from the mobile and POS Lite apps', function () {
    expect(Package::query()->visibleForPlatform('mobile')->pluck('id')->all())->not->toContain(1)
        ->and(Package::query()->visibleForPlatform('desktop')->pluck('id')->all())->toContain(1);
});

test('the trial expires after its days run out', function () {
    trialStart($this);

    $this->travel(8)->days();

    expect($this->business->fresh()->trialHasExpired())->toBeTrue();
});

test('preview warns only about features with data and recommends plans that keep them', function () {
    trialStart($this);
    DB::table('crm_leads')->insert([['business_id' => 7], ['business_id' => 7], ['business_id' => 99]]);

    $preview = app(PlanChangeService::class)->preview($this->business->fresh(), Package::find(2));

    expect($preview['lost_features'])->toHaveCount(1)
        ->and($preview['lost_features'][0]['key'])->toBe('crm')
        ->and($preview['lost_features'][0]['total'])->toBe(2) // other businesses' leads don't count
        ->and($preview['lost_unused'])->toContain('E-Commerce')
        ->and($preview['recommended']->pluck('name')->all())->toBe(['Gold']);
});

test('recommends the cheapest plan that keeps the used features, even without the chosen plan\'s unused ones', function () {
    trialStart($this);
    DB::table('crm_leads')->insert([['business_id' => 7]]);
    // Has CRM but lacks Silver's POS / products — which this business never used.
    DB::table('packages')->insert(['id' => 5, 'name' => 'CRM Basic', 'price' => 3000, 'is_trial' => false,
        'features' => json_encode(['crm', 'account_management']), 'sort_order' => 4]);

    $preview = app(PlanChangeService::class)->preview($this->business->fresh(), Package::find(2));

    expect($preview['recommended']->pluck('name')->all())->toBe(['CRM Basic', 'Gold']);
});

test('the trial package itself is never a plan-change option', function () {
    expect(app(PlanChangeService::class)->options()->pluck('id')->all())->not->toContain(1);
});

test('moving to a free plan switches at once, hides lost features and fits seats', function () {
    trialStart($this);
    DB::table('business_members')->insert([
        ['business_id' => 7, 'user_id' => 2, 'role' => 'staff'],
        ['business_id' => 7, 'user_id' => 3, 'role' => 'staff'],
    ]);
    // Starter allows 1 user (just the owner) → both members must go.
    $result = app(PlanChangeService::class)->start(
        $this->business->fresh(), Package::find(4), $this->owner->fresh(), [], 'web', 'https://ok', 'https://cancel',
    );

    $business = $this->business->fresh();
    expect($result['applied'])->toBeTrue()
        ->and($business->package_id)->toBe(4)
        ->and($business->isOnTrial())->toBeFalse()
        ->and($business->hasFeature('crm'))->toBeFalse()
        ->and(BusinessMember::query()->whereNotNull('deactivated_at')->count())->toBe(2)
        ->and(Business::canAccess(User::find(2), $business))->toBeFalse();
});

test('keeping more members than the new plan allows is refused', function () {
    trialStart($this);
    DB::table('business_members')->insert([
        ['id' => 11, 'business_id' => 7, 'user_id' => 2, 'role' => 'staff'],
        ['id' => 12, 'business_id' => 7, 'user_id' => 3, 'role' => 'staff'],
    ]);

    // Silver: 2 users = owner + 1 member.
    expect(fn () => app(PlanChangeService::class)->start(
        $this->business->fresh(), Package::find(2), $this->owner->fresh(), [11, 12], 'web', 'https://ok', 'https://cancel',
    ))->toThrow(ValidationException::class);
});

test('a paid plan switches only after Stripe confirms, keeping the chosen member', function () {
    trialStart($this);
    DB::table('business_members')->insert([
        ['id' => 11, 'business_id' => 7, 'user_id' => 2, 'role' => 'staff'],
        ['id' => 12, 'business_id' => 7, 'user_id' => 3, 'role' => 'staff'],
    ]);
    $this->stripe->shouldReceive('createSubscriptionCheckoutSession')->once()
        ->andReturn(\Stripe\Checkout\Session::constructFrom(['id' => 'cs_1', 'url' => 'https://stripe.test/cs_1']));

    $plans = app(PlanChangeService::class);
    $result = $plans->start($this->business->fresh(), Package::find(2), $this->owner->fresh(), [12], 'web', 'https://ok', 'https://cancel?p=__PAYMENT__');

    // Nothing changes until payment succeeds, and an abandoned checkout never locks the business.
    $payment = $result['payment']->fresh();
    expect($result['checkout_url'])->toBe('https://stripe.test/cs_1')
        ->and($payment->isPlanChange())->toBeTrue()
        ->and($payment->due_at)->toBeNull()
        ->and($this->business->fresh()->package_id)->toBe(1);

    $sync = new StripePaymentSyncService(Mockery::mock(PosNotificationService::class), Mockery::mock(ExtraUserPurchaseService::class));
    $sync->markCanceledIfPending($payment, 'left');
    expect($payment->fresh()->due_at)->toBeNull()
        ->and($this->business->fresh()->overdueSubscriptionPayment())->toBeNull();

    // Stripe confirms → switch (twice is harmless).
    $payment->update(['payment_status' => Payment::STATUS_SUCCEEDED]);
    $plans->applyPaid($payment->fresh());
    $plans->applyPaid($payment->fresh());

    $business = $this->business->fresh();
    expect($business->package_id)->toBe(2)
        ->and($business->isOnTrial())->toBeFalse()
        ->and(BusinessMember::find(12)->deactivated_at)->toBeNull()
        ->and(BusinessMember::find(11)->deactivated_at)->not->toBeNull();
});

test('only the owner of a trial business may change its plan', function () {
    trialStart($this);

    expect(app(PlanChangeService::class)->canChange($this->business->fresh(), User::find(2)))->toBeFalse()
        ->and(app(PlanChangeService::class)->canChange($this->business->fresh(), $this->owner))->toBeTrue();
});
