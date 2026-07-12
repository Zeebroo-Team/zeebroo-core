<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Models\ScheduledMail;
use Modules\Mail\Services\ScheduledMailService;

class MailScheduledController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly ScheduledMailService $scheduledMails,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('mail::scheduled.index', [
            'business'   => $business,
            'scheduled'  => $this->scheduledMails->listForBusiness($business),
        ]);
    }

    public function cancel(Request $request, ScheduledMail $scheduled): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->scheduledMails->scheduledForBusiness($business, $scheduled) instanceof ScheduledMail, 404);

        $cancelled = $this->scheduledMails->cancel($scheduled);

        return redirect()->route('mail.scheduled.index')->with(
            $cancelled ? 'status' : 'error',
            $cancelled ? 'Scheduled message cancelled.' : 'That message has already been sent and can\'t be cancelled.'
        );
    }
}
