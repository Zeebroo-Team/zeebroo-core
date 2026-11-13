<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Pos\Services\PosWebAppService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /pos-app/{path?} — the desktop POS app, running in a web browser.
 */
class PosWebAppController extends Controller
{
    public function __construct(private PosWebAppService $webApp) {}

    public function show(?string $path = null): Response|BinaryFileResponse
    {
        $file = $this->webApp->resolve($path);
        abort_if($file === null, 404);

        if ($file['html']) {
            $shim = public_path('pos-web/browser-shim.js');
            $shimUrl = asset('pos-web/browser-shim.js').'?v='.(is_file($shim) ? filemtime($shim) : '0');

            return response(
                $this->webApp->renderHtml($file['path'], url('/pos-app'), $shimUrl, url('/api/v1/pos')),
                200,
                ['Content-Type' => $file['mime'], 'Cache-Control' => 'no-cache, must-revalidate'],
            );
        }

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
