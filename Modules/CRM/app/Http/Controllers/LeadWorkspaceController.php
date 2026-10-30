<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadAttachmentService;
use Modules\CRM\Services\LeadMessageService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The lead page's workspace actions: assignees, internal messages and files. */
class LeadWorkspaceController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadAssignmentService $assignments,
        private readonly LeadMessageService $messages,
        private readonly LeadAttachmentService $attachments,
    ) {}

    // ── Assignees ────────────────────────────────────────────────────────

    /** Replace the assignees: { user_ids[], primary_user_id?, note? } */
    public function syncAssignees(Request $request, Lead $lead): RedirectResponse
    {
        $this->requireLead($request, $lead, 'canAssign');

        $data = $request->validate([
            'user_ids'        => ['nullable', 'array', 'max:50'],
            'user_ids.*'      => ['integer'],
            'primary_user_id' => ['nullable', 'integer'],
            'note'            => ['nullable', 'string', 'max:2000'],
        ]);

        // No boxes ticked = take everyone off the lead
        $this->assignments->sync(
            $lead,
            $data['user_ids'] ?? [],
            isset($data['primary_user_id']) ? (int) $data['primary_user_id'] : null,
            $request->user()?->id,
            $data['note'] ?? null,
        );

        return $this->back($lead, 'Assignees saved.');
    }

    public function removeAssignee(Request $request, Lead $lead, int $user): RedirectResponse
    {
        $this->requireLead($request, $lead, 'canAssign');
        $this->assignments->remove($lead, $user, $request->user()?->id);

        return $this->back($lead, 'Assignee removed.');
    }

    public function primaryAssignee(Request $request, Lead $lead, int $user): RedirectResponse
    {
        $this->requireLead($request, $lead, 'canAssign');
        $this->assignments->setPrimary($lead, $user, $request->user()?->id);

        return $this->back($lead, 'Primary assignee changed.');
    }

    // ── Messages ─────────────────────────────────────────────────────────

    public function storeMessage(Request $request, Lead $lead): RedirectResponse
    {
        $this->requireLead($request, $lead, 'canContribute');

        $data = $request->validate([
            'body'       => ['required_without:files', 'nullable', 'string', 'max:5000'],
            'parent_id'  => ['nullable', 'integer'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer'],
            'files'      => ['nullable', 'array', 'max:10'],
            'files.*'    => LeadAttachmentService::fileRules(),
        ], [
            'body.required_without' => 'Write a message or attach a file.',
            'files.*.mimes'         => 'Allowed file types: ' . implode(', ', LeadAttachmentService::ALLOWED_MIMES) . '.',
            'files.*.max'           => 'Each file can be at most ' . round(LeadAttachmentService::maxKb() / 1024) . ' MB.',
        ]);

        // Files shared in the message are lead files too (they also show under Files)
        $userId = (int) $request->user()->id;
        $ids    = collect($request->file('files', []))->map(fn ($file) => $this->attachments->upload($lead, $file, $userId)->id)->all();

        $this->messages->post($lead, $userId, $data['body'] ?? null,
            isset($data['parent_id']) ? (int) $data['parent_id'] : null, $data['mentions'] ?? [], $ids);

        return $this->back($lead, 'Message posted.', 'messages');
    }

    public function updateMessage(Request $request, Lead $lead, int $message): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        $message  = $this->messages->messageForLead($lead, $message);
        abort_unless($this->leadPolicy()->canEditMessage($this->crmAccess($request, $business), $lead, $message), 403);

        $data = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);

        $this->messages->update($message, $data['body'], $data['mentions'] ?? []);

        return $this->back($lead, 'Message updated.', 'messages');
    }

    public function destroyMessage(Request $request, Lead $lead, int $message): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        $message  = $this->messages->messageForLead($lead, $message);
        abort_unless($this->leadPolicy()->canDeleteContribution($this->crmAccess($request, $business), $lead, $message->user_id ? (int) $message->user_id : null), 403);

        $this->messages->delete($message);

        return $this->back($lead, 'Message deleted.', 'messages');
    }

    // ── Files ────────────────────────────────────────────────────────────

    public function storeAttachments(Request $request, Lead $lead): RedirectResponse
    {
        $this->requireLead($request, $lead, 'canContribute');

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => LeadAttachmentService::fileRules(),
        ], [
            'files.*.mimes' => 'Allowed file types: ' . implode(', ', LeadAttachmentService::ALLOWED_MIMES) . '.',
            'files.*.max'   => 'Each file can be at most ' . round(LeadAttachmentService::maxKb() / 1024) . ' MB.',
        ]);

        foreach ($request->file('files', []) as $file) {
            $this->attachments->upload($lead, $file, $request->user()?->id);
        }

        return $this->back($lead, count($request->file('files', [])) === 1 ? 'File uploaded.' : 'Files uploaded.', 'files');
    }

    /** ?inline=1 shows an image in the browser (previews); everything else downloads. */
    public function downloadAttachment(Request $request, Lead $lead, int $attachment): StreamedResponse
    {
        $this->requireLead($request, $lead);

        return $this->attachments->download($this->attachments->attachmentForLead($lead, $attachment), $request->boolean('inline'));
    }

    public function destroyAttachment(Request $request, Lead $lead, int $attachment): RedirectResponse
    {
        $business   = $this->requireLead($request, $lead);
        $attachment = $this->attachments->attachmentForLead($lead, $attachment);
        abort_unless($this->leadPolicy()->canDeleteContribution($this->crmAccess($request, $business), $lead, $attachment->uploaded_by ? (int) $attachment->uploaded_by : null), 403);

        $this->attachments->delete($attachment);

        return $this->back($lead, 'File deleted.', 'files');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** A lead of the current business the user may see (404 otherwise) and, given an ability, act on (403). */
    private function requireLead(Request $request, Lead $lead, ?string $ability = null): Business
    {
        $business = $this->requireBusiness($request);
        abort_if($business instanceof RedirectResponse, 403);
        abort_unless((int) $lead->business_id === (int) $business->id, 404);

        $access = $this->crmAccess($request, $business);
        abort_unless($this->leadPolicy()->canView($access, $lead), 404);
        if ($ability !== null) {
            abort_unless($this->leadPolicy()->{$ability}($access, $lead), 403);
        }

        return $business;
    }

    private function back(Lead $lead, string $status, ?string $tab = null): RedirectResponse
    {
        return redirect()->to(route('crm.leads.show', $lead) . ($tab ? '#' . $tab : ''))->with('status', $status);
    }
}
