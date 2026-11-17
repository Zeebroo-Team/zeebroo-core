<?php

namespace Modules\Mail\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mail\Models\AdminEmailCampaign;
use Modules\Mail\Models\AdminEmailCampaignRecipient;
use Modules\Mail\Models\AdminEmailTemplate;
use Modules\Mail\Services\AdminEmailMarketingService;
use Throwable;

class AdminEmailMarketingController extends Controller
{
    public function __construct(private readonly AdminEmailMarketingService $marketing) {}

    public function index(Request $request): View
    {
        return view('mail::admin.marketing.index', [
            'tab' => $request->query('tab') === 'campaigns' ? 'campaigns' : 'templates',
            'templates' => $this->marketing->templates(),
            'campaigns' => $this->marketing->campaigns(),
        ]);
    }

    // ── Templates ──────────────────────────────────────────────

    public function createTemplate(): View
    {
        return view('mail::admin.marketing.template-form', [
            'template' => null,
            'placeholders' => AdminEmailMarketingService::PLACEHOLDERS,
        ]);
    }

    public function editTemplate(AdminEmailTemplate $template): View
    {
        return view('mail::admin.marketing.template-form', [
            'template' => $template,
            'placeholders' => AdminEmailMarketingService::PLACEHOLDERS,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $template = $this->marketing->saveTemplate($this->validateTemplate($request), null, $request->user());

        return $this->afterTemplateSave($request, $template, __('Template ":name" created.', ['name' => $template->name]));
    }

    public function updateTemplate(Request $request, AdminEmailTemplate $template): RedirectResponse
    {
        $this->marketing->saveTemplate($this->validateTemplate($request), $template, $request->user());

        return $this->afterTemplateSave($request, $template, __('Template ":name" saved.', ['name' => $template->name]));
    }

    public function destroyTemplate(AdminEmailTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.email-marketing.index')
            ->with('status', __('Template ":name" deleted.', ['name' => $template->name]));
    }

    // ── Compose & send ─────────────────────────────────────────

    public function compose(Request $request): View
    {
        $templates = $this->marketing->templates();

        return view('mail::admin.marketing.compose', [
            'templates' => $templates,
            'selectedTemplate' => $templates->firstWhere('id', (int) $request->query('template')),
            'recipients' => $this->marketing->recipientCandidates(),
            'placeholders' => AdminEmailMarketingService::PLACEHOLDERS,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:admin_email_templates,id'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500000'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ], [
            'user_ids.required' => __('Select at least one recipient.'),
        ]);

        $campaign = $this->marketing->launchCampaign(
            $data['subject'],
            $data['body'],
            $data['template_id'] ?? null,
            $data['user_ids'],
            $request->user(),
        );

        if ($campaign->recipients_count === 0) {
            return redirect()->route('admin.email-marketing.campaigns.show', $campaign)
                ->withErrors(['user_ids' => __('None of the selected users can receive marketing email (unsubscribed or hidden).')]);
        }

        return redirect()->route('admin.email-marketing.campaigns.show', $campaign)
            ->with('status', trans_choice('{1} Email queued for :count recipient.|[2,*] Email queued for :count recipients.', $campaign->recipients_count, ['count' => $campaign->recipients_count]));
    }

    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500000'],
        ]);

        try {
            $this->marketing->sendTest($data['subject'], $data['body'], $request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => __('Could not send the test email: :error', ['error' => $e->getMessage()])], 422);
        }

        return response()->json(['success' => true, 'message' => __('Test email sent to :email.', ['email' => $request->user()->email])]);
    }

    // ── Campaigns ──────────────────────────────────────────────

    public function showCampaign(Request $request, AdminEmailCampaign $campaign): View
    {
        $status = $request->query('status');
        $statuses = [AdminEmailCampaignRecipient::STATUS_PENDING, AdminEmailCampaignRecipient::STATUS_SENDING, AdminEmailCampaignRecipient::STATUS_SENT, AdminEmailCampaignRecipient::STATUS_FAILED];

        $campaign->load(['template:id,name', 'creator:id,name,email']);

        return view('mail::admin.marketing.campaign', [
            'campaign' => $campaign,
            'status' => in_array($status, $statuses, true) ? $status : null,
            'recipients' => $campaign->recipients()
                ->when(in_array($status, $statuses, true), fn ($q) => $q->where('status', $status))
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function retryCampaign(AdminEmailCampaign $campaign): RedirectResponse
    {
        $count = $this->marketing->retryFailed($campaign);

        return back()->with('status', $count
            ? trans_choice('{1} Re-queued :count failed email.|[2,*] Re-queued :count failed emails.', $count, ['count' => $count])
            : __('There are no failed emails to retry.'));
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * @return array{name: string, subject: string, body: string}
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500000'],
        ]);
    }

    private function afterTemplateSave(Request $request, AdminEmailTemplate $template, string $message): RedirectResponse
    {
        if ($request->input('after') === 'send') {
            return redirect()->route('admin.email-marketing.compose', ['template' => $template->id])->with('status', $message);
        }

        return redirect()->route('admin.email-marketing.templates.edit', $template)->with('status', $message);
    }
}
