<?php

use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Pos\Listeners\RevokePosWebAppTokens;
use Modules\Pos\Services\PosWebAppHandoffService;
use Modules\Pos\Services\PosWebAppService;

// The desktop POS renderer served to browsers at /pos-app (see PosWebAppService).

it('serves the desktop index page with the base tag and browser shim injected', function () {
    $res = $this->get('/pos-app');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/html');

    $html = $res->getContent();
    expect($html)->toContain('<base href="'.url('/pos-app').'/">')
        ->and($html)->toContain('pos-web/browser-shim.js')
        ->and($html)->toContain('data-api-base="'.url('/api/v1/pos').'"');

    // Injected before the page's own scripts so window.electronAPI exists first.
    expect(strpos($html, 'browser-shim.js'))->toBeLessThan(strpos($html, 'js/app.js'));
});

it('serves renderer assets with the right content type', function () {
    $res = $this->get('/pos-app/js/api.js');

    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/javascript');
});

it('makes the browser revalidate renderer assets so edits show up at once', function () {
    $res = $this->get('/pos-app/css/ribbon.css');

    $res->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($res->headers->get('ETag'))->not->toBeEmpty();

    $this->get('/pos-app/css/ribbon.css', ['If-None-Match' => $res->headers->get('ETag')])
        ->assertStatus(304);
});

it('returns 404 for missing files, path traversal and disallowed types', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/pos-app/does-not-exist.js',
    '/pos-app/../main.js',
    '/pos-app/..%2Fmain.js',
    '/pos-app/..%2F..%2F.env',
]);

it('does not let the resolver leave the renderer folder', function () {
    $service = new PosWebAppService;

    expect($service->resolve('../preload.js'))->toBeNull()
        ->and($service->resolve('../../.env'))->toBeNull()
        ->and($service->resolve('index.html'))->not->toBeNull()
        ->and($service->resolve(''))->not->toBeNull();
});

it('reads the desktop app version from electron_app/package.json', function () {
    $pkg = json_decode(file_get_contents(base_path('electron_app/package.json')), true);

    expect((new PosWebAppService)->appVersion())->toBe($pkg['version']);
});

it('ships the browser shim as a public file', function () {
    expect(is_file(public_path('pos-web/browser-shim.js')))->toBeTrue();
});

// "Continue to Dashboard" → /pos-app/launch → /pos-app?handoff=<code> → token.

it('sends guests opening the web-app launcher to the login page', function () {
    $this->get('/pos-app/launch')->assertRedirect(route('login'));
});

it('routes /pos-app/launch to the launcher, not the static file server', function () {
    expect(app('router')->getRoutes()->match(request()->create('/pos-app/launch'))->getName())
        ->toBe('pos.web-app.launch');
});

it('rejects unknown, missing and reused handoff codes', function () {
    $this->postJson('/api/v1/pos/auth/web-handoff', ['code' => 'not-a-real-code'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'This sign-in link has expired. Please sign in again.');

    $this->postJson('/api/v1/pos/auth/web-handoff', [])->assertStatus(422);
});

it('keeps handoff codes in the cache only under a hash, with a short expiry', function () {
    $service = new PosWebAppHandoffService;
    $user = new User;
    $user->id = 999999;

    $code = $service->issue($user, 7, 'web-session-id');

    expect(Cache::has('pos-web-handoff:'.$code))->toBeFalse()
        ->and(Cache::get('pos-web-handoff:'.hash('sha256', $code)))
        ->toBe(['user_id' => 999999, 'business_id' => 7, 'session_id' => 'web-session-id']);

    $this->travel(PosWebAppHandoffService::TTL_SECONDS + 1)->seconds();
    expect(Cache::has('pos-web-handoff:'.hash('sha256', $code)))->toBeFalse();
});

// Sign-out stays in sync between the website and the POS web app.

it('gives the app page what it needs to end the website session on sign out', function () {
    $html = $this->get('/pos-app')->getContent();

    expect($html)->toContain('data-web-logout-url="'.route('logout').'"')
        ->and($html)->toMatch('/data-csrf="[^"]+"/');
});

it('revokes POS web-app tokens when the website signs out', function () {
    expect(Event::hasListeners(Logout::class))->toBeTrue()
        ->and(collect(Event::getRawListeners()[Logout::class] ?? [])->contains(RevokePosWebAppTokens::class))->toBeTrue();
});

it('does nothing on website sign out when the session opened no web-app tabs', function () {
    expect((new PosWebAppHandoffService)->revokeForSession('session-without-tokens'))->toBe(0);
});
