<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Services\BusinessInvitationService;
use Modules\Mail\Mail\AdminMarketingMail;
use Modules\Mail\Services\AutomatedEmailService;

// Builds only the tables the invitation flow touches.
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->string('password')->nullable();
        $t->string('google_id')->nullable();
        $t->timestamp('email_verified_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
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
    Schema::create('settings', function (Blueprint $t) { // new users get default UI settings
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
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
    });

    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')],
        ['id' => 2, 'name' => 'Kamal Perera', 'email' => 'kamal@example.com', 'password' => bcrypt('x')],
    ]);
    DB::table('businesses')->insert(['id' => 7, 'user_id' => 1, 'name' => 'ABC Solution']);
    DB::table('business_roles')->insert(['business_id' => 7, 'name' => 'Staff', 'slug' => 'staff', 'permissions' => json_encode(['pos_checkout'])]);

    Mail::fake();
    $this->automated = Mockery::mock(AutomatedEmailService::class);
    $this->service = new BusinessInvitationService($this->automated);
    $this->business = Business::find(7);
    $this->owner = User::find(1);
});

/** Pull the plain token out of the link in the sent email. */
function invitationToken(): string
{
    $mail = Mail::sent(AdminMarketingMail::class)->last();
    preg_match('~/invitations/([A-Za-z0-9]+)~', $mail->bodyHtml, $m);

    return $m[1];
}

test('inviting an unregistered email emails a link and does not add a member yet', function () {
    [$invitation, $sent] = $this->service->invite($this->business, 'New.Person@Example.com', 'staff', null, $this->owner);

    expect($sent)->toBeTrue()
        ->and($invitation->email)->toBe('new.person@example.com')
        ->and($invitation->permissions)->toBe(['pos_checkout'])
        ->and(BusinessMember::count())->toBe(0);

    Mail::assertSent(AdminMarketingMail::class, fn ($m) => $m->hasTo('new.person@example.com')
        && str_contains($m->bodyHtml, 'ABC Solution')
        && str_contains($m->bodyHtml, 'Staff')
        && str_contains($m->bodyHtml, 'set up account'));
});

test('accepting as a new person creates a verified account and the membership', function () {
    $this->automated->shouldReceive('queueWelcome')->once();
    $this->service->invite($this->business, 'new@example.com', 'staff', ['pos_session'], $this->owner);

    $invitation = $this->service->findByToken(invitationToken());
    $user = $this->service->accept($invitation, ['name' => 'Nimal', 'password' => 'secret-pass-1']);

    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-pass-1', $user->password))->toBeTrue()
        ->and(BusinessMember::where('user_id', $user->id)->first()->permissions)->toBe(['pos_session'])
        ->and($invitation->fresh()->status)->toBe(BusinessInvitation::STATUS_ACCEPTED);
});

test('an existing user joins without a password and the link only works once', function () {
    $this->automated->shouldNotReceive('queueWelcome');
    $this->service->invite($this->business, 'kamal@example.com', 'staff', null, $this->owner);
    Mail::assertSent(AdminMarketingMail::class, fn ($m) => str_contains($m->bodyHtml, 'Accept invitation')
        && str_contains($m->bodyHtml, 'Kamal Perera'));
    expect($this->service->payload(BusinessInvitation::first())['name'])->toBe('Kamal Perera');

    $invitation = $this->service->findByToken(invitationToken());
    $user = $this->service->accept($invitation, null, ' Kamal P ');

    expect($user->id)->toBe(2)
        ->and($user->fresh()->name)->toBe('Kamal P')
        ->and(BusinessMember::where('user_id', 2)->where('business_id', 7)->exists())->toBeTrue();

    $this->service->accept($invitation);
})->throws(ValidationException::class);

test('existing members and the owner cannot be invited', function (string $email) {
    BusinessMember::create(['business_id' => 7, 'user_id' => 2, 'role' => 'staff', 'status' => 'active']);

    $this->service->invite($this->business, $email, 'staff', null, $this->owner);
})->with(['kamal@example.com', 'owner@example.com'])->throws(ValidationException::class);

test('re-inviting refreshes the pending invitation and kills the old link', function () {
    $this->service->invite($this->business, 'new@example.com', 'staff', null, $this->owner);
    $oldToken = invitationToken();

    $this->service->invite($this->business, 'new@example.com', 'staff', ['pos_session'], $this->owner);

    expect(BusinessInvitation::count())->toBe(1)
        ->and($this->service->findByToken($oldToken))->toBeNull()
        ->and($this->service->findByToken(invitationToken())->permissions)->toBe(['pos_session']);
});

test('expired and cancelled invitations cannot be accepted', function (string $state) {
    [$invitation] = $this->service->invite($this->business, 'new@example.com', 'staff', null, $this->owner);

    $state === 'expired'
        ? $invitation->update(['expires_at' => now()->subMinute()])
        : $this->service->cancel($invitation);

    $this->service->accept($invitation, ['name' => 'Nimal', 'password' => 'secret-pass-1']);
})->with(['expired', 'cancelled'])->throws(ValidationException::class);
