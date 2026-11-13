<?php

namespace Modules\Pos\Services;

/**
 * Serves the desktop POS renderer (electron_app/renderer) to web browsers.
 *
 * The files are read straight from electron_app/renderer, so the browser
 * version always matches the desktop build in the same commit. HTML pages get
 * a <base> tag and the browser shim (public/pos-web/browser-shim.js) injected
 * at the top of <head>; the shim provides window.electronAPI on top of fetch()
 * and the existing /api/v1/pos endpoints. Nothing under electron_app is changed.
 */
class PosWebAppService
{
    /** Extensions that may be served, with their Content-Type. */
    private const MIME_TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
    ];

    public function __construct(private ?string $rendererDir = null) {}

    public function rendererDir(): string
    {
        return $this->rendererDir ?? base_path('electron_app/renderer');
    }

    /**
     * Resolve a request path to a servable file inside the renderer folder.
     *
     * @return array{path: string, mime: string, html: bool}|null null when missing or not allowed
     */
    public function resolve(?string $relativePath): ?array
    {
        $relativePath = trim((string) $relativePath, '/');
        if ($relativePath === '') {
            $relativePath = 'index.html';
        }

        $root = realpath($this->rendererDir());
        if ($root === false) {
            return null;
        }

        $full = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($full === false || ! is_file($full) || ! str_starts_with($full, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (! isset(self::MIME_TYPES[$ext])) {
            return null;
        }

        return ['path' => $full, 'mime' => self::MIME_TYPES[$ext], 'html' => $ext === 'html'];
    }

    /**
     * Return an HTML page with the <base> tag and browser shim injected right
     * after <head>, before any of the page's own scripts run.
     */
    public function renderHtml(string $path, string $baseUrl, string $shimUrl, string $apiBaseUrl): string
    {
        $html = (string) file_get_contents($path);

        $inject = "\n".'<base href="'.e(rtrim($baseUrl, '/').'/').'">'
            ."\n".'<script src="'.e($shimUrl).'" data-api-base="'.e(rtrim($apiBaseUrl, '/')).'"'
            .' data-app-version="'.e($this->appVersion()).'"></script>'."\n";

        $count = 0;
        $out = preg_replace('/<head\b[^>]*>/i', '$0'.str_replace(['\\', '$'], ['\\\\', '\\$'], $inject), $html, 1, $count);

        return $count > 0 && is_string($out) ? $out : $inject.$html;
    }

    /** Version of the desktop app these files belong to (electron_app/package.json). */
    public function appVersion(): string
    {
        $pkg = dirname($this->rendererDir()).DIRECTORY_SEPARATOR.'package.json';
        if (! is_file($pkg)) {
            return '0.0.0';
        }
        $data = json_decode((string) file_get_contents($pkg), true);

        return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : '0.0.0';
    }
}
