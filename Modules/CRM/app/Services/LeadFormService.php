<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\Project;

class LeadFormService
{
    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadCustomFieldService $customFields,
    ) {}

    public function listForProject(Project $project): Collection
    {
        return LeadForm::query()
            ->where('project_id', $project->id)
            ->with('defaultStage')
            ->orderByDesc('id')
            ->get();
    }

    public function create(Project $project, array $data): LeadForm
    {
        $templateKey = $data['template'] ?? 'blank';
        $template    = self::templates()[$templateKey] ?? self::templates()['blank'];
        $blocks      = $template['blocks'];

        if (array_key_exists($templateKey, self::autoMappedFieldSpecs())) {
            $blocks = array_merge($blocks, $this->buildAutoMappedFieldBlocks($project, $templateKey));
        }

        return LeadForm::create([
            'project_id'          => $project->id,
            'name'                => $data['name'],
            'type'                => $data['type'] ?? $template['kind'],
            'token'               => LeadForm::generateToken(),
            'blocks'              => $blocks,
            'style'               => LeadForm::defaultStyle(),
            'submit_button_text'  => $template['submit_button_text'],
            'success_message'     => filled($data['success_message'] ?? null) ? $data['success_message'] : $template['success_message'],
            'default_stage_id'    => $data['default_stage_id'] ?? null,
            'is_published'        => true,
        ]);
    }

    /**
     * For the auto-mapped templates (customer/supplier/employee), find-or-create the
     * project's custom fields for the columns that have no core Lead slot (name/email/phone/
     * company), then return `field` blocks referencing them via "custom:{id}" — the same
     * reference format the builder's manual "Add Custom Field" flow produces.
     *
     * @return array<int, array{type:string,field:string,label:string,required:bool}>
     */
    private function buildAutoMappedFieldBlocks(Project $project, string $templateKey): array
    {
        $existing = $this->customFields->listForProject($project)->keyBy(fn (LeadCustomField $f) => strtolower($f->label));

        $blocks = [];
        foreach (self::autoMappedFieldSpecs()[$templateKey] as $spec) {
            $key   = strtolower($spec['label']);
            $field = $existing->get($key);

            if (! $field) {
                $field = $this->customFields->create($project, [
                    'label'   => $spec['label'],
                    'type'    => $spec['type'],
                    'options' => $spec['options'] ?? '',
                ]);
                $existing->put($key, $field);
            }

            $blocks[] = [
                'type'     => 'field',
                'field'    => "custom:{$field->id}",
                'label'    => $spec['label'],
                'required' => false,
            ];
        }

        return $blocks;
    }

    /**
     * Custom-field specs for each auto-mapped template — the DB columns of the matching
     * business model that have no core Lead slot, limited to contact-facing fields
     * (salary, bank details, tax IDs, and internal foreign keys are intentionally excluded).
     *
     * @return array<string, array<int, array{label:string,type:string,options?:string}>>
     */
    private static function autoMappedFieldSpecs(): array
    {
        return [
            'customer_contact' => [
                ['label' => 'Address', 'type' => LeadCustomField::TYPE_TEXTAREA],
                ['label' => 'Customer Type', 'type' => LeadCustomField::TYPE_SELECT, 'options' => "Retail\nWholesale"],
                ['label' => 'Notes', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
            'supplier_registration' => [
                ['label' => 'Contact Person', 'type' => LeadCustomField::TYPE_TEXT],
                ['label' => 'Category', 'type' => LeadCustomField::TYPE_TEXT],
                ['label' => 'Address', 'type' => LeadCustomField::TYPE_TEXTAREA],
                ['label' => 'Notes', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
            'employee_registration' => [
                ['label' => 'NIC / Passport Number', 'type' => LeadCustomField::TYPE_TEXT],
                ['label' => 'Date of Birth', 'type' => LeadCustomField::TYPE_DATE],
                ['label' => 'Permanent Address', 'type' => LeadCustomField::TYPE_TEXTAREA],
                ['label' => 'Emergency Contact Name', 'type' => LeadCustomField::TYPE_TEXT],
                ['label' => 'Emergency Contact Phone', 'type' => LeadCustomField::TYPE_TEXT],
                ['label' => 'Employment Type', 'type' => LeadCustomField::TYPE_SELECT, 'options' => "Full Time\nPart Time\nContract"],
            ],
            'supplier_quotation' => [
                ['label' => 'Quotation Details', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
            'supplier_feedback' => [
                ['label' => 'Feedback', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
            'employee_leave_request' => [
                ['label' => 'Leave Type', 'type' => LeadCustomField::TYPE_SELECT, 'options' => "Annual\nSick\nCasual\nUnpaid\nOther"],
                ['label' => 'Start Date', 'type' => LeadCustomField::TYPE_DATE],
                ['label' => 'End Date', 'type' => LeadCustomField::TYPE_DATE],
                ['label' => 'Reason', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
            'employee_feedback' => [
                ['label' => 'Feedback', 'type' => LeadCustomField::TYPE_TEXTAREA],
            ],
        ];
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
     * @return array<int, array{key:string,label:string,description:string,icon:string,blocks:array,kind:string}>
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
                'kind'        => $t['kind'],
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
            'default_stage_id'   => array_key_exists('default_stage_id', $data) ? $data['default_stage_id'] : $form->default_stage_id,
        ]);

        return $form->fresh();
    }

    /**
     * Flip a form's default-form flag. Only one form per project may be the default at a
     * time, so setting a form default clears the flag on every other form in that project;
     * clicking the current default again clears it, leaving the project with no default.
     */
    public function toggleDefault(LeadForm $form): LeadForm
    {
        return DB::transaction(function () use ($form) {
            if ($form->is_default) {
                $form->update(['is_default' => false]);
            } else {
                LeadForm::query()
                    ->where('project_id', $form->project_id)
                    ->where('id', '!=', $form->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                $form->update(['is_default' => true]);
            }

            return $form->fresh();
        });
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

    public function defaultForProject(Project $project): ?LeadForm
    {
        return LeadForm::query()
            ->where('project_id', $project->id)
            ->where('is_default', true)
            ->first();
    }

    /**
     * The form used to build the internal "New lead" modal and to validate lead
     * submissions: the project's explicit default form, falling back to its most
     * recently created form when no form has been marked default yet (e.g. a
     * project created before multiple forms per project were supported).
     */
    public function defaultOrFirstForProject(Project $project): ?LeadForm
    {
        return $this->defaultForProject($project) ?? $this->listForProject($project)->first();
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
     * Create a Lead from a form submission — either the public web form or the desktop
     * "New Lead" flow, which reuses the same field-block mapping. The lead always lands on
     * the form's own default stage, keeping both entry points consistent. Tagging the lead
     * with form_id lets Edit Lead keep showing *this* form's fields later, even if a
     * different form is made the project's default afterwards.
     *
     * @param  array<string, string>  $input  keyed by block path (see LeadForm::fieldBlocksWithPaths())
     */
    public function submit(LeadForm $form, array $input, string $source = 'public-form'): Lead
    {
        $mapped = $form->mapPathedInputsToLeadData($input);
        $name   = $mapped['core']['name'] ?: ($mapped['first_text'] ?: 'Website inquiry');

        return $this->leadService->create($form->project, [
            'form_id'       => $form->id,
            'name'          => $name,
            'company'       => $mapped['core']['company'],
            'email'         => $mapped['core']['email'],
            'phone'         => $mapped['core']['phone'],
            'source'        => $source,
            'custom_fields' => $mapped['custom_fields'],
            'stage_id'      => $form->default_stage_id,
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
                'kind'        => 'generic',
                'blocks'      => [],
                'submit_button_text' => 'Submit',
                'success_message'    => 'Thanks for your submission.',
            ],
            'contact' => [
                'label'       => 'Contact us',
                'description' => 'Name, email, and phone — a general-purpose inquiry form.',
                'icon'        => 'fa-comment-dots',
                'kind'        => 'generic',
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
                'kind'        => 'generic',
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
                'kind'        => 'generic',
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
                'kind'        => 'generic',
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
            'customer_contact' => [
                'label'       => 'Customer contact form',
                'description' => 'Name, email, phone, and address — auto-mapped to customer fields.',
                'icon'        => 'fa-user-check',
                'kind'        => 'customer',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Customer contact details', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Tell us how to reach you.'],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Full name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Submit',
                'success_message'    => "Thanks! We've saved your details.",
            ],
            'supplier_registration' => [
                'label'       => 'Supplier registration form',
                'description' => 'Contact and company details — auto-mapped to supplier fields.',
                'icon'        => 'fa-truck-field',
                'kind'        => 'supplier',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Supplier registration', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Register your business as a supplier.'],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Company name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Register',
                'success_message'    => "Thanks! Your supplier registration has been received.",
            ],
            'employee_registration' => [
                'label'       => 'Employee registration form',
                'description' => 'Personal and contact details — auto-mapped to employee fields.',
                'icon'        => 'fa-id-badge',
                'kind'        => 'employee',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Employee registration', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Please complete your registration details.'],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Full name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Personal email', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Submit',
                'success_message'    => "Thanks! Your registration has been received.",
            ],
            'customer_quote' => [
                'label'       => 'Request a quote',
                'description' => 'Contact details plus company — a sales inquiry from a customer.',
                'icon'        => 'fa-file-invoice-dollar',
                'kind'        => 'customer',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Request a quote', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "Tell us a bit about your needs and we'll send a tailored quote."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Your name', 'required' => true],
                    ['type' => 'field', 'field' => 'company', 'label' => 'Company', 'required' => false],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Request quote',
                'success_message'    => "Thanks! We'll send your quote within one business day.",
            ],
            'customer_newsletter' => [
                'label'       => 'Newsletter signup',
                'description' => 'A minimal single-field email capture form for customers.',
                'icon'        => 'fa-envelope-open-text',
                'kind'        => 'customer',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Join our newsletter', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Get occasional updates — no spam, unsubscribe anytime.'],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                ],
                'submit_button_text' => 'Subscribe',
                'success_message'    => "You're subscribed! Thanks for joining.",
            ],
            'supplier_quotation' => [
                'label'       => 'Request quotation',
                'description' => 'Ask a supplier to submit pricing — company details plus quotation notes.',
                'icon'        => 'fa-file-invoice',
                'kind'        => 'supplier',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Request for quotation', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Please submit your pricing for the items or services below.'],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Company name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => true],
                    ['type' => 'field', 'field' => 'phone', 'label' => 'Phone number', 'required' => false],
                ],
                'submit_button_text' => 'Submit quotation',
                'success_message'    => "Thanks! Your quotation has been received.",
            ],
            'supplier_feedback' => [
                'label'       => 'Supplier feedback',
                'description' => 'Collect feedback or comments from an existing supplier.',
                'icon'        => 'fa-comment-dots',
                'kind'        => 'supplier',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Supplier feedback', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "We'd love to hear your feedback."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Company name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => false],
                ],
                'submit_button_text' => 'Submit feedback',
                'success_message'    => 'Thanks for your feedback!',
            ],
            'employee_leave_request' => [
                'label'       => 'Leave request form',
                'description' => 'Collect a leave / time-off request — dates, type, and reason.',
                'icon'        => 'fa-calendar-minus',
                'kind'        => 'employee',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Leave request', 'size' => 'lg'],
                    ['type' => 'text', 'text' => 'Submit your leave request for approval.'],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Employee name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => false],
                ],
                'submit_button_text' => 'Submit request',
                'success_message'    => 'Your leave request has been submitted.',
            ],
            'employee_feedback' => [
                'label'       => 'Employee feedback',
                'description' => 'Collect feedback or comments from an employee.',
                'icon'        => 'fa-comment-dots',
                'kind'        => 'employee',
                'blocks'      => [
                    ['type' => 'heading', 'text' => 'Employee feedback', 'size' => 'lg'],
                    ['type' => 'text', 'text' => "We'd love to hear your feedback."],
                    ['type' => 'field', 'field' => 'name', 'label' => 'Employee name', 'required' => true],
                    ['type' => 'field', 'field' => 'email', 'label' => 'Email address', 'required' => false],
                ],
                'submit_button_text' => 'Submit feedback',
                'success_message'    => 'Thanks for your feedback!',
            ],
        ];
    }
}
