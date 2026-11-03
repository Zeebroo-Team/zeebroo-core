<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Modules\CRM\Mail\QuotationApprovalRequestMail;
use Modules\CRM\Models\Lead;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Services\QuotationPrintService;

/**
 * The approval request emailed to the heads ticked on submit: the quotation's lines and totals,
 * its PDF, and a signed link per head to approve or reject it without signing in
 * (QuotationApprovalLinkController). A link is tied to that submission — once the quotation is
 * decided or submitted again, the old links only show what happened.
 */
class QuotationApprovalMailer
{
    public const LINK_DAYS = 30;

    public function __construct(
        private readonly BusinessMailerService $mailer,
        private readonly QuotationPrintService $print,
    ) {}

    /**
     * @param  list<array{id: int, name: string, email: string, role: string}>  $approvers
     * @return array{sent: list<string>, failed: list<string>}
     */
    public function send(Lead $lead, Quotation $quotation, array $approvers): array
    {
        $result = ['sent' => [], 'failed' => []];
        if ($approvers === []) {
            return $result;
        }

        $submission = QuotationApproval::query()
            ->where('quotation_id', $quotation->id)
            ->where('action', QuotationApproval::ACTION_SUBMITTED)
            ->latest('id')
            ->first();
        if ($submission === null) {
            return $result;
        }

        // The PDF for the head to read; the email still goes without it if rendering fails
        try {
            $pdfPath = $this->print->generatePdf($quotation);
        } catch (\Throwable $e) {
            Log::warning('Quotation PDF could not be generated for the approval email: ' . $e->getMessage(), ['quotation_id' => $quotation->id]);
            $pdfPath = null;
        }

        foreach ($approvers as $approver) {
            $mail = new QuotationApprovalRequestMail(
                quotation: $quotation,
                lead: $lead,
                approver: $approver,
                approvers: $approvers,
                submission: $submission,
                reviewUrl: $this->link($quotation, $approver['id'], (int) $submission->id),
                pdfPath: $pdfPath,
            );
            $sent = $this->mailer->send($quotation->business, $mail, $approver['email']);
            $result[$sent['success'] ? 'sent' : 'failed'][] = $approver['email'];
        }

        return $result;
    }

    /** The signed page where this head approves or rejects this submission. */
    public function link(Quotation $quotation, int $approverId, int $submissionId): string
    {
        return URL::temporarySignedRoute('crm.quotation-approval.show', now()->addDays(self::LINK_DAYS), [
            'quotation'  => $quotation->id,
            'approver'   => $approverId,
            'submission' => $submissionId,
        ]);
    }
}
