<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\StockAudit;
use Modules\Pos\Services\StockAuditService;

class StockAuditController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly StockAuditService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $audits    = $this->service->listForBusiness($business);
        $hasAudits = $this->service->businessHasAudits($business);
        $currency  = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::stock-audits.index', compact('business', 'audits', 'hasAudits', 'currency'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $productCount = $this->service->productsForPreview($business);

        return view('pos::stock-audits.create', compact('business', 'productCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $request->validate([
            'audit_date' => ['required', 'date'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        $audit = $this->service->create($business, $data, $request->user());

        return redirect()->route('pos.stock-audits.show', $audit)
            ->with('status', "Audit {$audit->audit_number} created — enter physical counts below.");
    }

    public function show(Request $request, StockAudit $stockAudit): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $audit    = $this->service->auditForBusiness($business, $stockAudit);
        $audit->load('lines', 'finalizedBy', 'createdBy');

        $search   = trim((string) $request->query('q', ''));
        $lines    = $audit->lines;
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $lines = $lines->filter(fn ($l)
                => mb_strpos(mb_strtolower($l->product_name), $lower) !== false
                || ($l->sku && mb_strpos(mb_strtolower($l->sku), $lower) !== false)
            );
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        return view('pos::stock-audits.show', compact('business', 'audit', 'lines', 'search', 'currency'));
    }

    public function saveLines(Request $request, StockAudit $stockAudit): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $audit = $this->service->auditForBusiness($business, $stockAudit);

        $request->validate([
            'lines'                    => ['required', 'array'],
            'lines.*.counted_qty'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->saveLines($audit, $request->input('lines', []));

        return redirect()->route('pos.stock-audits.show', $audit)
            ->with('status', 'Counts saved successfully.');
    }

    public function finalize(Request $request, StockAudit $stockAudit): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $audit = $this->service->auditForBusiness($business, $stockAudit);
        $this->service->finalize($audit, $request->user());

        return redirect()->route('pos.stock-audits.show', $audit)
            ->with('status', "Audit {$audit->audit_number} finalized — stock quantities updated.");
    }

    public function destroy(Request $request, StockAudit $stockAudit): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $audit = $this->service->auditForBusiness($business, $stockAudit);
        $this->service->delete($audit);

        return redirect()->route('pos.stock-audits.index')
            ->with('status', 'Audit deleted.');
    }
}
