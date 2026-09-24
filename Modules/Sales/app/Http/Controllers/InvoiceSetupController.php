<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Sales\Http\Controllers\Concerns\ResolvesSalesBusiness;
use Modules\Sales\Services\InvoiceAppearanceService;
use Modules\Sales\Services\InvoiceDocumentBuilder;

class InvoiceSetupController extends Controller
{
    use ResolvesSalesBusiness;

    public function __construct(
        private readonly InvoiceAppearanceService $appearance,
        private readonly InvoiceDocumentBuilder $documentBuilder,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('sales::invoice-setup.edit', [
            'business'          => $business,
            'settings'          => $this->appearance->forBusiness($business),
            'templates'         => InvoiceAppearanceService::TEMPLATES,
            'colorPresets'      => InvoiceAppearanceService::COLOR_PRESETS,
            'letterheadEnabled' => $this->appearance->letterheadEnabledForInvoices($business),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'template'      => ['required', 'string', 'in:classic,bold,minimal,compact,executive'],
            'accent_color'  => ['nullable', 'string', 'max:9'],
            'paper_size'    => ['required', 'string', 'in:a4,a5,letter,legal'],
            'orientation'   => ['required', 'string', 'in:portrait,landscape'],
            'margin_top'    => ['required', 'integer', 'min:0', 'max:80'],
            'margin_bottom' => ['required', 'integer', 'min:0', 'max:80'],
            'margin_left'   => ['required', 'integer', 'min:0', 'max:80'],
            'margin_right'  => ['required', 'integer', 'min:0', 'max:80'],
            'header_layout' => ['required', 'string', 'in:num-left,num-right,num-center'],
            'letterhead_enabled' => ['nullable', 'boolean'],
        ]);

        $this->appearance->saveForBusiness($business, $validated);
        $this->appearance->setLetterheadEnabledForInvoices($business, $request->boolean('letterhead_enabled'));

        return redirect()->route('sales.invoice-setup.edit')->with('status', 'Invoice setup saved.');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $template = (string) $request->query('template', 'classic');
        $settings = [
            'template'      => array_key_exists($template, InvoiceAppearanceService::TEMPLATES) ? $template : 'classic',
            'accent_color'  => (string) $request->query('accent_color', ''),
            'paper_size'    => (string) $request->query('paper_size', 'a4'),
            'orientation'   => (string) $request->query('orientation', 'portrait'),
            'margin_top'    => (int) $request->query('margin_top', 20),
            'margin_bottom' => (int) $request->query('margin_bottom', 20),
            'margin_left'   => (int) $request->query('margin_left', 15),
            'margin_right'  => (int) $request->query('margin_right', 25),
            'header_layout' => (string) $request->query('header_layout', 'num-left'),
        ];

        $letterheadEnabled = $request->query('letterhead_enabled', '1') === '1';
        $letterhead = $this->appearance->resolveLetterheadForInvoices($business, $letterheadEnabled);
        $accent     = $this->appearance->resolveAccent($settings, $letterhead['accent']);
        $geometry   = $this->appearance->geometry($settings);
        $hdrCss     = $this->appearance->headerLayoutCss($settings['header_layout']).$this->appearance->pageAtCss($geometry);
        $margins    = [
            'top'    => $settings['margin_top'],
            'bottom' => $settings['margin_bottom'],
            'left'   => $settings['margin_left'],
            'right'  => $settings['margin_right'],
        ];

        return view('sales::invoices.print', [
            'template'             => $settings['template'],
            'doc'                  => $this->documentBuilder->dummy(),
            'accent'               => $accent,
            'geomW'                => $geometry['w_px'],
            'geomMinH'             => $geometry['min_height_css'],
            'hdrCss'               => $hdrCss,
            'mg'                   => $margins,
            'letterheadCanvasJson' => $letterhead['canvasJson'],
            'business'             => $business,
            'mainBranch'           => $business->branches()->first(),
            'currency'             => (string) (get_settings('business.currency', '', $business) ?: ''),
            'backUrl'              => '#',
            'hideActions'          => true,
        ]);
    }
}
