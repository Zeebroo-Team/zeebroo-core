<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\Project;

class LeadFormService
{
    public function __construct(
        private readonly LeadService $leadService,
    ) {}

    public function listForProject(Project $project): Collection
    {
        return LeadForm::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get();
    }

    public function create(Project $project, array $data): LeadForm
    {
        $template = self::templates()[$data['template'] ?? 'blank'] ?? self::templates()['blank'];

        return LeadForm::create([
            'project_id'          => $project->id,
            'name'                => $data['name'],
            'token'               => LeadForm::generateToken(),
            'blocks'              => $template['blocks'],
            'style'               => LeadForm::defaultStyle(),
            'submit_button_text'  => $template['submit_button_text'],
            'success_message'     => $template['success_message'],
            'is_published'        => true,
        ]);
    }

    /**
     * Template keys valid for the "template" input on create — used for validation.
     *
     * @return array<int, string>
     */
    public static function templateKeys(): array
    {
        return array_keys(self::templates());
    }

    /**
     * Template metadata for rendering the template picker (blocks included so cards can show a mini live preview).
     *
     * @return array<int, array{key:string,label:string,description:string,icon:string,blocks:array}>
     */
    public function templateChoices(): array
    {
        return collect(self::templates())
            ->map(fn (array $t, string $key) => [
                'key'         => $key,
                'label'       => $t['label'],
                'description' => $t['description'],
                'icon'        => $t['icon'],
                'blocks'      => $t['blocks'],
            ])
            ->values()
            ->all();
    }

    public function update(LeadForm $form, array $data): LeadForm
    {
        $form->update([
            'name'               => $data['name'] ?? $form->name,
            'blocks'             => $data['blocks'] ?? $form->blocks,
            'style'              => isset($data['style']) ? array_merge($form->styleSettings(), $data['style']) : $form->style,
            'submit_button_text' => filled($data['submit_button_text'] ?? '') ? $data['submit_button_text'] : $form->submit_button_text,
            'success_message'    => filled($data['success_message'] ?? '') ? $data['success_message'] : $form->success_message,
        ]);

        return $form->fresh();
    }

    public function publish(LeadForm $form): LeadForm
    {
        $form->update(['is_published' => true]);

        return $form;
    }

    public function unpublish(LeadForm $form): LeadForm
    {
        $form->update(['is_published' => false]);

        return $form;
    }

    public function delete(LeadForm $form): void
    {
        $form->delete();
    }

    public function formForProject(Project $project, LeadForm $form): ?LeadForm
    {
        return $form->project_id === $project->id ? $form : null;
    }

    public function findPublishedByToken(string $token): ?LeadForm
    {
        return LeadForm::query()
            ->where('token', $token)
            ->where('is_published', true)
            ->with('project')
            ->first();
    }

    /**
     * Create a Lead from a public form submission.
     *
     * @param  array<string, string>  $input  keyed by block path (see LeadForm::fieldBlocksWithPaths())
     */
    public function submit(LeadForm $form, array $input): Lead
    {
        $mapped = $form->mapPathedInputsToLeadData($input);
        $name   = $mapped['core']['name'] ?: ($mapped['first_text'] ?: 'Website inquiry');

        return $this->leadService->create($form->project, [
            'name'          => $name,
            'company'       => $mapped['core']['company'],
            'email'         => $mapped['core']['email'],
            'phone'         => $mapped['core']['phone'],
            'source'        => 'public-form',
            'custom_fields' => $mapped['custom_fields'],
        ]);
    }

    /**
     * @return array<string, array{label:string,description:string,icon:string,blocks:array,submit_button_text:string,success_message:string}>
     */
    private static function templates(): array
    {
        return [
            'blank' => [
                'label'       => 'Blank form',
                'description' => 'Start from scratch and add your own blocks.',
                'icon'        => 'fa-file',
                'blocks'      => [],
                'submit_button_text' => 'Submit',
                'success_message'    => 'Thanks for your submission.',
            ],
            'contact' => [
                'label'       => 'Contact us',
                'description' => 'Name, email, and phone — a general-purpose inquiry form.',
                'icon'        => 'fa-comment-dots',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Get in touch', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "Fill out the form below and we'll get back to you shortly."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Your name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Submit',
                'success_message'    => "Thanks! We'll be in touch soon.",
            ],
            'quote' => [
                'label'       => 'Request a quote',
                'description' => 'Contact details plus company — built for sales inquiries.',
                'icon'        => 'fa-file-invoice-dollar',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Request a quote', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "Tell us a bit about your business and we'll send a tailored quote."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Your name', 'required' => true],
                    ['type' => 'field', 'field' => 'company', 'label' => 'Company', 'required' => false],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Request quote',
                'success_message'    => "Thanks! We'll send your quote within one business day.",
            ],
            'newsletter' => [
                'label'       => 'Newsletter signup',
                'description' => 'A minimal single-field email capture form.',
                'icon'        => 'fa-envelope-open-text',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Join our newsletter', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Get occasional updates — no spam, unsubscribe anytime.'],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                ],
                'submit_button_text' => 'Subscribe',
                'success_message'    => "You're subscribed! Thanks for joining.",
            ],
            'event' => [
                'label'       => 'Event registration',
                'description' => 'Collect attendee details for an upcoming event.',
                'icon'        => 'fa-calendar-check',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Register for our event', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "Reserve your spot — we'll email you the details."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Full name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                    ['type' => 'divider'],
                    ['type' => 'text', 'text' => "We'll send calendar details after you register."],
                ],
                'submit_button_text' => 'Register',
                'success_message'    => "You're registered! Check your email for details.",
            ],
        ];
    }
}
