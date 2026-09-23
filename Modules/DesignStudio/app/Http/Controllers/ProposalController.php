<?php

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\DesignStudio\Services\ProposalService;
use Modules\Sales\Models\Invoice;

class ProposalController extends Controller
{
    public function __construct(private readonly ProposalService $proposals)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('designstudio::hub.proposals', [
            'business'   => $business,
            'groups'     => $this->proposals->listGrouped($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $created = $this->proposals->create($business, $request->user()->id, $validated['title'], $validated['client'] ?? null);

        $fillResult = $this->proposals->fillWithAi(
            $business,
            $created['group'],
            $validated['title'],
            $validated['client'] ?? null,
            $validated['description']
        );

        $redirect = redirect()->route('designstudio.proposals.show', $created['group']);

        if (! $fillResult['ok']) {
            return $redirect->with('proposal_error', 'Proposal created, but AI content generation failed: ' . $fillResult['message']);
        }

        return $redirect->with('status', 'Proposal created and filled with AI content.');
    }

    public function show(Request $request, string $group): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $result = $this->proposals->getGroupPages($business, $group);
        abort_if($result === null, 404);

        $invoices = Invoice::where('business_id', $business->id)
            ->latest('issue_date')
            ->limit(50)
            ->get();

        $linkedInvoice = null;
        $firstInvoiceId = $result['pages']->firstWhere('invoice_id', '!=', null)?->invoice_id;
        if ($firstInvoiceId) {
            $linkedInvoice = $invoices->firstWhere('id', $firstInvoiceId)
                ?? Invoice::where('business_id', $business->id)->find($firstInvoiceId);
        }

        return view('designstudio::hub.proposal-show', [
            'business'      => $business,
            'group'         => $result['group'],
            'title'         => $result['title'],
            'client'        => $result['client'],
            'pages'         => $result['pages'],
            'invoices'      => $invoices,
            'linkedInvoice' => $linkedInvoice,
        ]);
    }

    public function aiFill(Request $request, string $group): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $result = $this->proposals->fillWithAi($business, $group, $validated['title'], $validated['client'] ?? null, $validated['description']);
        abort_if($result['message'] === 'Proposal not found.', 404);

        $redirect = redirect()->route('designstudio.proposals.show', $group);

        return $result['ok']
            ? $redirect->with('status', $result['message'])
            : $redirect->with('proposal_error', $result['message']);
    }

    public function addPage(Request $request, string $group): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $design = $this->proposals->addPage($business, $request->user()->id, $group);
        abort_if($design === null, 404);

        return redirect()->route('designstudio.proposals.show', $group)->with('status', 'Page added.');
    }

    public function linkInvoice(Request $request, string $group): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $invoiceId = $validated['invoice_id'] ?? null;

        if ($invoiceId !== null) {
            abort_unless($this->proposals->invoiceBelongsToBusiness($business, $invoiceId), 422, 'Invoice not found.');
        }

        $this->proposals->linkInvoice($business, $group, $invoiceId);

        return redirect()->route('designstudio.proposals.show', $group)
            ->with('status', $invoiceId ? 'Proposal linked to invoice.' : 'Proposal unlinked from invoice.');
    }

    public function destroy(Request $request, string $group): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->proposals->destroy($business, $group);

        return redirect()->route('designstudio.proposals.index')->with('status', 'Proposal deleted.');
    }

    private function resolveBusiness(Request $request): \Modules\Business\Models\Business|RedirectResponse
    {
        return (new DesignStudioController())->resolveBusiness($request);
    }
}
