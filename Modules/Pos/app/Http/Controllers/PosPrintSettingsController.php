<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Services\PosSettingsService;

// Web Settings → Printer and Settings → Receipt Setting. Same per-business POS
// settings POS Lite edits through `online/settings` (PosSettingsApiController),
// so a change made here shows up on every POS client and vice versa. Printer
// device choices (which printer, silent print) stay per-device in POS Lite; on
// the web the only per-device choice — auto print after sale — lives in the
// browser (see pos::settings.printer).
class PosPrintSettingsController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PosSettingsService $posSettings,
    ) {}

    public function printer(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('pos::settings.printer', [
            'business'    => $business,
            'posSettings' => $this->posSettings->forBusiness($business),
        ]);
    }

    public function updatePrinter(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'receipt_mode'        => ['required', 'string', 'in:bill,invoice'],
            'receipt_paper_width' => ['required', 'string', 'in:58,80'],
        ]);

        $this->posSettings->saveForBusiness($business, $validated);

        return redirect()->route('settings.printer')->with('status', 'Printer settings saved.');
    }

    public function receipt(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('pos::settings.receipt-layout', [
            'business'    => $business,
            'posSettings' => $this->posSettings->forBusiness($business),
        ]);
    }

    public function updateReceipt(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'receipt_logo'          => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
            'remove_receipt_logo'   => ['nullable', 'boolean'],
            'receipt_logo_gap'      => ['required', 'numeric', 'min:0', 'max:20'],
            'show_business_name'    => ['nullable', 'boolean'],
            'show_business_address' => ['nullable', 'boolean'],
            'receipt_address_line'  => ['nullable', 'string', 'max:300'],
            'receipt_header'        => ['nullable', 'string', 'max:200'],
            'receipt_footer'        => ['nullable', 'string', 'max:200'],
            'show_account_info'     => ['nullable', 'boolean'],
            'receipt_margin_top'    => ['required', 'numeric', 'min:0', 'max:20'],
            'receipt_margin_right'  => ['required', 'numeric', 'min:0', 'max:20'],
            'receipt_margin_bottom' => ['required', 'numeric', 'min:0', 'max:20'],
            'receipt_margin_left'   => ['required', 'numeric', 'min:0', 'max:20'],
            'receipt_language'      => ['required', 'string', 'in:en,si,ta'],
        ]);

        if ($request->hasFile('receipt_logo')) {
            $this->posSettings->uploadReceiptLogo($business, $request->file('receipt_logo'));
        } elseif ($request->boolean('remove_receipt_logo')) {
            $this->posSettings->removeReceiptLogo($business);
        }

        // Unchecked toggles are not posted — save them as off.
        foreach (['show_business_name', 'show_business_address', 'show_account_info'] as $toggle) {
            $validated[$toggle] = $request->boolean($toggle);
        }
        unset($validated['receipt_logo'], $validated['remove_receipt_logo']);

        $this->posSettings->saveForBusiness($business, $validated);

        return redirect()->route('settings.receipt')->with('status', 'Receipt layout saved.');
    }
}
