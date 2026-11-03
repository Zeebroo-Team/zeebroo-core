<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Services\QuotationApprovalBrief;
use Modules\CRM\Services\QuotationApprovalService;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;

/**
 * The signed link in a quotation approval email (QuotationApprovalMailer): the head reads the
 * quotation and approves or rejects it without signing in. GET only shows the page (so mail
 * scanners that open links decide nothing); POST makes the decision as that head.
 */
class QuotationApprovalLinkController extends Controller
{
    public function __construct(
        private readonly QuotationApprovalService $approvals,
        private readonly QuotationApprovalBrief $brief,
    ) {}

    public function show(Request $request, int $quotation, int $approver, int $submission): View
    {
        $q    = Quotation::query()->with(['lead', 'business'])->findOrFail($quotation);
        $done = session('crm_quotation_decided');
        abort_if($q->lead === null, 404);

        return view('crm::quotation-approval', $this->brief->build(
            $q, $q->lead, $approver, QuotationApproval::query()->where('quotation_id', $q->id)->find($submission),
        ) + [
            'approver'  => User::query()->findOrFail($approver),
            'blocker'   => $this->approvals->linkBlocker($q, $approver, $submission),
            'actionUrl' => $request->fullUrl(),
            // The thank-you shows only on the link that made the decision
            'done'      => ($done['link'] ?? null) === "$quotation/$approver/$submission" ? $done['decision'] : null,
        ]);
    }

    public function decide(Request $request, int $quotation, int $approver, int $submission): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comment'  => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ], ['comment.required_if' => 'Say why the quotation is rejected.']);

        $this->approvals->decideByLink(Quotation::query()->findOrFail($quotation), $approver, $submission, $data['decision'], $data['comment'] ?? null);

        return redirect()->to($request->fullUrl())
            ->with('crm_quotation_decided', ['link' => "$quotation/$approver/$submission", 'decision' => $data['decision']]);
    }
}
