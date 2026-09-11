<?php

namespace Modules\CRM\Services;

use Modules\AutomationEditor\Mail\AutomationMail;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageMailTemplate;
use Modules\CRM\Models\Project;
use Modules\Mail\Services\BusinessMailerService;

class LeadStageMailTemplateService
{
    public function __construct(
        private readonly BusinessMailerService $businessMailer,
    ) {}

    public function forStage(LeadStage $stage): ?LeadStageMailTemplate
    {
        return LeadStageMailTemplate::where('stage_id', $stage->id)->first();
    }

    public function save(Project $project, LeadStage $stage, array $data): LeadStageMailTemplate
    {
        return LeadStageMailTemplate::updateOrCreate(
            ['project_id' => $project->id, 'stage_id' => $stage->id],
            [
                'is_active'       => (bool) ($data['is_active'] ?? true),
                'recipient_type'  => $data['recipient_type'],
                'recipient_email' => $data['recipient_type'] === LeadStageMailTemplate::RECIPIENT_CUSTOM ? $data['recipient_email'] : null,
                'subject'         => $data['subject'],
                'body'            => $data['body'],
            ],
        );
    }

    public function delete(LeadStageMailTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Manually send the stage's mail template to every lead currently sitting
     * in that stage. Triggered only when a user explicitly clicks "Send".
     *
     * @return array{sent: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function sendNow(LeadStage $stage, LeadStageMailTemplate $template): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $leads = Lead::where('stage_id', $stage->id)->get();

        foreach ($leads as $lead) {
            $result = $this->deliverTo($template, $lead);

            if ($result['skipped']) {
                $skipped++;
            } elseif ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = "{$lead->name}: {$result['error']}";
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Send the stage's mail template to a single lead — used when a lead
     * enters the stage automatically while Pipeline Automation is OFF for
     * its relation, as opposed to sendNow()'s manual whole-stage blast.
     *
     * @return array{success: bool, skipped: bool, error: ?string}
     */
    public function sendToLead(Lead $lead, LeadStageMailTemplate $template): array
    {
        return $this->deliverTo($template, $lead);
    }

    /**
     * @return array{success: bool, skipped: bool, error: ?string}
     */
    private function deliverTo(LeadStageMailTemplate $template, Lead $lead): array
    {
        $to = $this->resolveRecipient($template, $lead);
        if (!filled($to)) {
            return ['success' => false, 'skipped' => true, 'error' => null];
        }

        $subject = $this->renderTemplate($template->subject, $lead);
        // Lead-supplied fields (name, company, ...) can come from a public
        // form submission, so they're escaped before landing in the email
        // body rather than trusted as raw HTML.
        $bodyHtml = nl2br(e($this->renderTemplate($template->body, $lead)));

        $result = $this->businessMailer->send($lead->business, new AutomationMail($subject, $bodyHtml), $to);

        return [
            'success' => $result['success'],
            'skipped' => false,
            'error'   => $result['success'] ? null : $result['error'],
        ];
    }

    private function resolveRecipient(LeadStageMailTemplate $template, Lead $lead): ?string
    {
        return match ($template->recipient_type) {
            LeadStageMailTemplate::RECIPIENT_ASSIGNED_USER => $lead->assignedTo?->email,
            LeadStageMailTemplate::RECIPIENT_CUSTOM        => $template->recipient_email,
            default                                        => $lead->email,
        };
    }

    /**
     * @return array<string, string>
     */
    private function mergeFieldValues(Lead $lead): array
    {
        return [
            '{{lead.name}}'            => (string) $lead->name,
            '{{lead.company}}'         => (string) ($lead->company ?? ''),
            '{{lead.email}}'           => (string) ($lead->email ?? ''),
            '{{lead.phone}}'           => (string) ($lead->phone ?? ''),
            '{{lead.estimated_value}}' => $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : '',
            '{{lead.stage_name}}'      => (string) ($lead->stage?->name ?? ''),
            '{{assigned_to.name}}'     => (string) ($lead->assignedTo?->name ?? ''),
            '{{project.name}}'         => (string) ($lead->project?->name ?? ''),
        ];
    }

    private function renderTemplate(string $template, Lead $lead): string
    {
        return strtr($template, $this->mergeFieldValues($lead));
    }
}
