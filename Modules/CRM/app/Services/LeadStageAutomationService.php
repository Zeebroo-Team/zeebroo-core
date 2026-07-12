<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Mail\LeadStageAutomationMail;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageAutomation;
use Modules\CRM\Models\Project;
use Modules\Mail\Services\BusinessMailerService;

class LeadStageAutomationService
{
    public function __construct(
        private readonly BusinessMailerService $businessMailer,
    ) {}

    public function listForStage(LeadStage $stage): Collection
    {
        return $stage->automations()->get();
    }

    public function create(Project $project, LeadStage $stage, array $data): LeadStageAutomation
    {
        return LeadStageAutomation::create([
            'project_id'      => $project->id,
            'stage_id'        => $stage->id,
            'is_active'       => (bool) ($data['is_active'] ?? true),
            'recipient_type'  => $data['recipient_type'],
            'recipient_email' => $data['recipient_type'] === LeadStageAutomation::RECIPIENT_CUSTOM ? $data['recipient_email'] : null,
            'subject'         => $data['subject'],
            'body'            => $data['body'],
        ]);
    }

    public function update(LeadStageAutomation $automation, array $data): LeadStageAutomation
    {
        $automation->update([
            'is_active'       => (bool) ($data['is_active'] ?? false),
            'recipient_type'  => $data['recipient_type'],
            'recipient_email' => $data['recipient_type'] === LeadStageAutomation::RECIPIENT_CUSTOM ? $data['recipient_email'] : null,
            'subject'         => $data['subject'],
            'body'            => $data['body'],
        ]);

        return $automation->fresh();
    }

    public function delete(LeadStageAutomation $automation): void
    {
        $automation->delete();
    }

    public function automationForStage(LeadStage $stage, LeadStageAutomation $automation): ?LeadStageAutomation
    {
        return $automation->stage_id === $stage->id ? $automation : null;
    }

    /**
     * Fire every active automation configured for the stage a lead just entered.
     */
    public function runForStageChange(Lead $lead, LeadStage $toStage): void
    {
        foreach ($this->listForStage($toStage) as $automation) {
            if (!$automation->is_active) {
                continue;
            }

            $this->send($automation, $lead);
        }
    }

    private function send(LeadStageAutomation $automation, Lead $lead): void
    {
        $to = $this->resolveRecipient($automation, $lead);
        if (!filled($to)) {
            return;
        }

        $subject = $this->renderTemplate($automation->subject, $lead);
        // The rendered text can embed lead-supplied data (name, company, etc. — a
        // public form visitor controls these), so it's HTML-escaped before being
        // dropped into the email body, never trusted as raw markup.
        $bodyHtml = nl2br(e($this->renderTemplate($automation->body, $lead)));

        $this->businessMailer->send($lead->project?->business, new LeadStageAutomationMail($subject, $bodyHtml), $to);
    }

    private function resolveRecipient(LeadStageAutomation $automation, Lead $lead): ?string
    {
        return match ($automation->recipient_type) {
            LeadStageAutomation::RECIPIENT_ASSIGNED_USER => $lead->assignedTo?->email,
            LeadStageAutomation::RECIPIENT_CUSTOM        => $automation->recipient_email,
            default                                      => $lead->email,
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
