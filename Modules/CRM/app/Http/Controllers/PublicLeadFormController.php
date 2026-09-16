<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Services\LeadFormService;

class PublicLeadFormController extends Controller
{
    public function __construct(
        private readonly LeadFormService $forms,
    ) {}

    public function show(string $token): View
    {
        $form = $this->forms->findPublishedByToken($token);
        abort_unless($form instanceof LeadForm, 404);

        $customFields = LeadCustomField::query()
            ->where('project_id', $form->project_id)
            ->get()
            ->keyBy('id');

        return view('crm::leads.forms.public', [
            'form'         => $form,
            'customFields' => $customFields,
            'submitted'    => (bool) session('crm_form_submitted'),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $form = $this->forms->findPublishedByToken($token);
        abort_unless($form instanceof LeadForm, 404);

        $data = $request->validate($this->validationRules($form));

        $this->forms->submit($form, $data);

        return redirect()->route('crm.public-forms.show', $token)->with('crm_form_submitted', true);
    }

    private function validationRules(LeadForm $form): array
    {
        $customFields = LeadCustomField::query()
            ->where('project_id', $form->project_id)
            ->get()
            ->keyBy('id');

        $rules = [];
        foreach ($form->fieldBlocksWithPaths() as $path => $block) {
            $required = (bool) ($block['required'] ?? false);
            $fieldKey = (string) ($block['field'] ?? '');
            $customField = str_starts_with($fieldKey, 'custom:') ? ($customFields[(int) substr($fieldKey, 7)] ?? null) : null;

            if ($customField && $customField->isMultiValue()) {
                $rules[$path]         = [$required ? 'required' : 'nullable', 'array'];
                $rules["{$path}.*"]   = ['string', 'max:200'];
                continue;
            }

            $rules[$path] = [$required ? 'required' : 'nullable', 'string', 'max:2000'];

            if ($fieldKey === 'email') {
                $rules[$path][] = 'email';
            }
        }

        return $rules;
    }
}
