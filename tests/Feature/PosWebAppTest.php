<?php

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
