<?php

use App\Services\TagManagerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\Pos\Services\PosWebAppService;

// Google Tag Manager on every web page, except pages whose URL carries a secret.

it('loads nothing while no container ID is set (as in tests)', function () {
    $this->get('/privacy-policy')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false);
});

it('adds the Tag Manager script and noscript frame when a container ID is set', function () {
    config(['services.google_tag_manager.id' => 'GTM-W6SQLDRK']);

    foreach (['/privacy-policy', '/terms-of-service'] as $page) {
        $html = $this->get($page)->assertOk()->getContent();

        expect($html)->toContain("'gtm.start'")
            ->toContain("'dataLayer',\"GTM-W6SQLDRK\"")
            ->toContain('function gtag(){dataLayer.push(arguments);}')
            ->toContain('https://www.googletagmanager.com/ns.html?id=GTM-W6SQLDRK');

        // Script in <head>, noscript frame after <body>.
        expect(strpos($html, 'gtm.start'))->toBeLessThan(strpos($html, '</head>'))
            ->and(strpos($html, 'ns.html?id='))->toBeGreaterThan(strpos($html, '<body'));
    }
});

it('skips pages whose URL carries a one-time or signed token', function (string $routeName, string $uri) {
    config(['services.google_tag_manager.id' => 'GTM-W6SQLDRK']);

    $request = Request::create($uri);
    $request->setRouteResolver(fn () => (new Route('GET', ltrim($uri, '/'), []))->name($routeName));

    expect((new TagManagerService($request))->containerId())->toBeNull();
})->with([
    'team invitation' => ['business.invitations.show', '/invitations/abc123'],
    'public lead form' => ['crm.public-forms.show', '/f/abc123'],
    'shared invoice' => ['sales.invoices.public', '/invoice/share/abc123'],
    'quotation approval' => ['crm.quotation-approval.show', '/crm/quotation-approval/1/2/3'],
    'unsubscribe' => ['marketing.unsubscribe', '/marketing/unsubscribe/5'],
    'Google sign-in callback' => ['auth.google.callback', '/auth/google/callback'],
    'contact import' => ['crm.contacts.import.preview', '/crm/contacts/import/abc123'],
]);

it('ignores a container ID that is not a GTM ID', function (string $id) {
    config(['services.google_tag_manager.id' => $id]);

    expect(app(TagManagerService::class)->containerId())->toBeNull();
})->with(['', 'G-ABC123', "GTM-1');alert(1);//", 'gtm-abc']);

it('adds Tag Manager to the POS web app after the shim, which removes the handoff code first', function () {
    config(['services.google_tag_manager.id' => 'GTM-W6SQLDRK']);

    $file = tempnam(sys_get_temp_dir(), 'posweb').'.html';
    file_put_contents($file, "<!DOCTYPE html>\n<html>\n<head>\n<title>POS</title>\n</head>\n<body class=\"x\">\n<div id=\"app\"></div>\n</body>\n</html>");

    try {
        $html = app(PosWebAppService::class)->renderHtml($file, 'https://example.test/pos-app', 'https://example.test/pos-web/browser-shim.js', 'https://example.test/api/v1/pos');
    } finally {
        @unlink($file);
    }

    expect(strpos($html, 'browser-shim.js'))->toBeLessThan(strpos($html, 'gtm.start'))
        ->and(strpos($html, 'gtm.start'))->toBeLessThan(strpos($html, '</head>'))
        ->and(strpos($html, 'ns.html?id=GTM-W6SQLDRK'))->toBeGreaterThan(strpos($html, '<body class="x">'))
        ->and(substr_count($html, 'googletagmanager.com/gtm.js'))->toBe(1);
});

it('leaves the POS web app untouched when Tag Manager is off', function () {
    $file = tempnam(sys_get_temp_dir(), 'posweb').'.html';
    file_put_contents($file, "<html><head></head><body><p>x</p></body></html>");

    try {
        $html = app(PosWebAppService::class)->renderHtml($file, 'https://example.test/pos-app', 'https://example.test/shim.js', 'https://example.test/api');
    } finally {
        @unlink($file);
    }

    expect($html)->not->toContain('googletagmanager')
        ->and($html)->toContain('<body><p>x</p></body>');
});
