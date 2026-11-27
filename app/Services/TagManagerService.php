<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Decides whether the current page loads Google Tag Manager, and with which
 * container. Off when no container ID is configured (phpunit.xml clears it for
 * tests) and on pages whose URL holds a secret (services.google_tag_manager.except_routes).
 */
class TagManagerService
{
    public function __construct(private readonly Request $request) {}

    /** The container ID to load on this request, or null to load nothing. */
    public function containerId(): ?string
    {
        $id = trim((string) config('services.google_tag_manager.id'));

        if ($id === '' || ! preg_match('/^GTM-[A-Z0-9]+$/', $id)) {
            return null;
        }

        $except = (array) config('services.google_tag_manager.except_routes', []);
        if ($except !== [] && $this->request->route() !== null && $this->request->routeIs(...$except)) {
            return null;
        }

        return $id;
    }

    /** The <head> snippet for HTML built outside Blade (e.g. the POS web app). */
    public function headHtml(): string
    {
        return view('partials.tag-manager-head')->render();
    }

    /** The <noscript> fallback that goes right after <body>. */
    public function bodyHtml(): string
    {
        return view('partials.tag-manager-body')->render();
    }
}
