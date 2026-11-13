<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosWebAppHandoffService;
use Modules\Pos\Services\PosWebAppService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /pos-app/{path?} — the desktop POS app, running in a web browser.
 */
class PosWebAppController extends Controller
{
    public function __construct(
        private PosWebAppService $webApp,
        private PosWebAppHandoffService $handoff,
    ) {}

    /**
     * GET /pos-app/launch — open the POS web app already signed in as the
     * current web-session user, on the business selected in the web navbar.
     */
    public function launch(Request $request): RedirectResponse
    {
        $user = $request->user();
        $business = Business::currentForNavbar($user);
        $code = $this->handoff->issue($user, $business?->id, $request->session()->getId());

        return redirect()->to(url('/pos-app').'?handoff='.$code);
    }

    public function show(?string $path = null): Response|BinaryFileResponse
    {
        $file = $this->webApp->resolve($path);
        abort_if($file === null, 404);

        if ($file['html']) {
            $shim = public_path('pos-web/browser-shim.js');
            $shimUrl = asset('pos-web/browser-shim.js').'?v='.(is_file($shim) ? filemtime($shim) : '0');

            return response(
                $this->webApp->renderHtml($file['path'], url('/pos-app'), $shimUrl, url('/api/v1/pos'), route('logout'), csrf_token()),
                200,
                ['Content-Type' => $file['mime'], 'Cache-Control' => 'no-cache, must-revalidate'],
            );
        }

        // no-cache = the browser keeps its copy but checks with us on every load,
        // so a changed CSS/JS file shows up straight away; an unchanged one is a
        // cheap 304. (A max-age here served stale app.js/ribbon.css after edits.)
        $response = response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
        $response->setAutoEtag();
        $response->isNotModified(request());

        return $response;
    }
}
