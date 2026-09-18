<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves business-uploaded media (e.g. the POS/mobile business logo) through
 * the Laravel router instead of the raw `storage/` symlink, so responses
 * always carry CORS headers. The symlinked path is served directly by the
 * webserver (or `php artisan serve`) and never reaches Laravel, so it can't
 * carry these headers — which breaks Flutter Web's cross-origin image
 * fetch even though the file loads fine everywhere else (native apps,
 * `<img>` tags, curl).
 */
class PosMediaApiController extends Controller
{
    public function businessLogo(int $business, string $filename): StreamedResponse
    {
        $disk = Storage::disk('public');
        $path = 'business-logos/'.$business.'/'.$filename;

        abort_unless($disk->exists($path), 404);

        return response()->stream(function () use ($disk, $path) {
            fpassthru($disk->readStream($path));
        }, 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Length' => $disk->size($path),
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Generic version of businessLogo() for files that were saved outside the dedicated
     * logo-upload flow (e.g. via the file manager) and so live elsewhere on the `public`
     * disk. `$path` is constrained to stay inside the given business's own folder so one
     * business can't fetch another's files through this route.
     */
    public function businessFile(int $business, string $path): StreamedResponse
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        abort_if(str_contains($normalized, '..'), 404);
        abort_unless(preg_match('#^[A-Za-z0-9_-]+/'.$business.'/#', $normalized), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($normalized), 404);

        return response()->stream(function () use ($disk, $normalized) {
            fpassthru($disk->readStream($normalized));
        }, 200, [
            'Content-Type' => $disk->mimeType($normalized) ?: 'application/octet-stream',
            'Content-Length' => $disk->size($normalized),
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
