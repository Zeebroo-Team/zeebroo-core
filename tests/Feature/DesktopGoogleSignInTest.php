<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Auth\Services\DesktopGoogleHandoffService;

// Google sign-in for the desktop app: browser → /auth/google/desktop → Google →
// callback → socibiz://oauth?code&state → POST /api/v1/pos/auth/google/exchange.

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
    Schema::create('personal_access_tokens', function (Blueprint $t) {
        $t->id();
        $t->morphs('tokenable');
        $t->string('name');
        $t->string('token', 64)->unique();
        $t->text('abilities')->nullable();
        $t->timestamp('last_used_at')->nullable();
        $t->timestamp('expires_at')->nullable();
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

    config([
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
        'services.google.auth_redirect' => 'http://localhost/auth/google/callback',
    ]);

    $this->state = str_repeat('ab', 32);
});

afterEach(function () {
    Schema::dropIfExists('user_activity_logs');
    Schema::dropIfExists('personal_access_tokens');
    Schema::dropIfExists('settings');
    Schema::dropIfExists('users');
});

function fakeGoogleUser(string $id, string $email, string $name = 'Jane Google'): void
{
    $googleUser = (new SocialiteUser)->setRaw(['given_name' => 'Jane'])->map([
        'id' => $id, 'email' => $email, 'name' => $name,
    ]);

    $provider = Mockery::mock();
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('rejects a desktop start without a valid state', function () {
    $this->get('/auth/google/desktop')->assertStatus(400);
    $this->get('/auth/google/desktop?state=short')->assertStatus(400);
});

it('starts Google sign-in for the desktop app', function () {
    $res = $this->get('/auth/google/desktop?state='.$this->state);

    $res->assertRedirect();
    expect($res->headers->get('Location'))->toContain('accounts.google.com');
    $res->assertSessionHas('oauth_desktop_state', $this->state);
});

it('keeps the desktop start and the callback open to browsers already signed in to the website', function (string $uri) {
    $route = app('router')->getRoutes()->match(request()->create($uri));

    expect($route->gatherMiddleware())->not->toContain('guest');
})->with(['/auth/google/desktop', '/auth/google/callback']);

it('hands the desktop app a one-time code instead of signing the browser in', function () {
    $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'x']);
    fakeGoogleUser('g-123', 'jane@example.com');

    $res = $this->withSession(['oauth_desktop_state' => $this->state])->get('/auth/google/callback');

    $res->assertOk()->assertSee('socibiz://oauth?code=', false);
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBe('g-123');

    preg_match('/socibiz:\/\/oauth\?code=([A-Za-z0-9]+)&amp;state=([A-Za-z0-9]+)/', $res->getContent(), $m);
    expect($m[2] ?? null)->toBe($this->state);

    $ok = $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $m[1], 'state' => $this->state, 'device_name' => 'pos-desktop-1']);
    $ok->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonPath('is_new_user', false);
    expect($ok->json('access_token'))->not->toBeEmpty();
});

it('sends Google errors back to the desktop app', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new RuntimeException('denied'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->withSession(['oauth_desktop_state' => $this->state])->get('/auth/google/callback')
        ->assertOk()
        ->assertSee('socibiz://oauth?error=', false);
});

it('only redeems a code once and only with its own state', function () {
    $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'x']);
    $code = app(DesktopGoogleHandoffService::class)->issue($user, $this->state, true);

    $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $code, 'state' => str_repeat('cd', 32)])
        ->assertStatus(401);

    // A wrong state burns the code too.
    $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $code, 'state' => $this->state])
        ->assertStatus(401);

    $code = app(DesktopGoogleHandoffService::class)->issue($user, $this->state, true);
    $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $code, 'state' => $this->state])
        ->assertOk()->assertJsonPath('is_new_user', true);
    $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $code, 'state' => $this->state])
        ->assertStatus(401);
});

it('refuses disabled accounts', function () {
    $user = User::query()->create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'x']);
    $user->forceFill(['is_active' => false])->save();
    $code = app(DesktopGoogleHandoffService::class)->issue($user, $this->state, false);

    $this->postJson('/api/v1/pos/auth/google/exchange', ['code' => $code, 'state' => $this->state])
        ->assertStatus(401);
});
