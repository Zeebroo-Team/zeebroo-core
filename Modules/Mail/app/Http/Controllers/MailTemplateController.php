<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Models\MailTemplate;
use Modules\Mail\Services\MailTemplateService;

class MailTemplateController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly MailTemplateService $templates,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('mail::templates.index', [
            'business'  => $business,
            'templates' => $this->templates->listForBusiness($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->templates->create($business, $this->validated($request));

        return redirect()->route('mail.templates.index')->with('status', 'Template added.');
    }

    public function update(Request $request, MailTemplate $template): RedirectResponse
    {
        $business = $this->requireTemplate($request, $template);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->templates->update($template, $this->validated($request));

        return redirect()->route('mail.templates.index')->with('status', 'Template updated.');
    }

    public function destroy(Request $request, MailTemplate $template): RedirectResponse
    {
        $business = $this->requireTemplate($request, $template);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->templates->delete($template);

        return redirect()->route('mail.templates.index')->with('status', 'Template removed.');
    }

    private function requireTemplate(Request $request, MailTemplate $template): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->templates->templateForBusiness($business, $template) instanceof MailTemplate, 404);

        return $business;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);
    }
}
