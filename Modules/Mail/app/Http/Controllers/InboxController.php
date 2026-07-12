<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Mail\ComposedMail;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Mail\Services\MailTemplateService;
use Modules\Mail\Services\ScheduledMailService;

class InboxController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly BusinessMailerService $mailer,
        private readonly MailTemplateService $templates,
        private readonly ScheduledMailService $scheduledMails,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $box       = $request->query('box', 'inbox') === 'sent' ? 'sent' : 'inbox';
        $search    = trim((string) $request->query('q', ''));
        $status    = in_array($request->query('status'), ['read', 'unread'], true) ? $request->query('status') : 'all';
        $contact   = trim((string) $request->query('contact', ''));
        $direction = $box === 'sent' ? MailMessage::DIRECTION_OUTBOUND : MailMessage::DIRECTION_INBOUND;
        $contactColumn = $box === 'sent' ? 'to_address' : 'from_address';

        $base = MailMessage::query()
            ->where('business_id', $business->id)
            ->where('direction', $direction)
            ->when(filled($search), function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('from_address', 'like', "%{$search}%")
                        ->orWhere('to_address', 'like', "%{$search}%")
                        ->orWhere('body_text', 'like', "%{$search}%");
                });
            });

        $messages = (clone $base)
            ->when($box === 'inbox' && $status !== 'all', fn ($query) => $query->where('is_read', $status === 'read'))
            ->when(filled($contact), fn ($query) => $query->where($contactColumn, $contact))
            ->orderByDesc('occurred_at')
            ->paginate(25)
            ->withQueryString();

        $statusCounts = [
            'all'    => (clone $base)->count(),
            'unread' => $box === 'inbox' ? (clone $base)->where('is_read', false)->count() : 0,
        ];
        $statusCounts['read'] = $statusCounts['all'] - $statusCounts['unread'];

        $contacts = (clone $base)
            ->select($contactColumn . ' as address', 'from_name')
            ->selectRaw('count(*) as cnt')
            ->whereNotNull($contactColumn)
            ->where($contactColumn, '!=', '')
            ->groupBy($contactColumn, 'from_name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return view('mail::inbox.index', [
            'business'     => $business,
            'messages'     => $messages,
            'box'          => $box,
            'search'       => $search,
            'status'       => $status,
            'contact'      => $contact,
            'contacts'     => $contacts,
            'statusCounts' => $statusCounts,
            'unreadCount'  => MailMessage::where('business_id', $business->id)
                ->where('direction', MailMessage::DIRECTION_INBOUND)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function show(Request $request, MailMessage $message): View|RedirectResponse
    {
        $business = $this->requireMessage($request, $message);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        if ($message->direction === MailMessage::DIRECTION_INBOUND && !$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return view('mail::inbox.show', [
            'business' => $business,
            'message'  => $message,
        ]);
    }

    public function compose(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $replyTo = null;
        if ($request->filled('reply_to')) {
            $replyTo = MailMessage::where('business_id', $business->id)->find($request->query('reply_to'));
        }

        return view('mail::inbox.compose', [
            'business'  => $business,
            'replyTo'   => $replyTo,
            'templates' => $this->templates->listForBusiness($business),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'to'         => ['required', 'email', 'max:190'],
            'subject'    => ['required', 'string', 'max:200'],
            'body'       => ['required', 'string', 'max:10000'],
            'send_at'    => ['nullable', 'date', 'after:now'],
        ]);

        if (filled($data['send_at'] ?? null)) {
            $this->scheduledMails->schedule($business, [
                'to'           => $data['to'],
                'subject'      => $data['subject'],
                'body'         => $data['body'],
                'scheduled_at' => $data['send_at'],
            ]);

            return redirect()->route('mail.scheduled.index')->with('status', 'Message scheduled to send to ' . $data['to'] . '.');
        }

        // Plain-text compose box — escape before embedding in the HTML email body.
        $bodyHtml = nl2br(e($data['body']));
        $result = $this->mailer->send($business, new ComposedMail($data['subject'], $bodyHtml), $data['to']);

        if ($result['success']) {
            MailMessage::create([
                'business_id'  => $business->id,
                'direction'    => MailMessage::DIRECTION_OUTBOUND,
                'from_address' => $business->user?->email,
                'to_address'   => $data['to'],
                'subject'      => $data['subject'],
                'body_text'    => $data['body'],
                'body_html'    => $bodyHtml,
                'is_read'      => true,
                'occurred_at'  => now(),
            ]);
        }

        return redirect()->route('mail.inbox.index', ['box' => 'sent'])->with(
            $result['success'] ? 'status' : 'error',
            $result['success'] ? 'Message sent to ' . $data['to'] . '.' : $result['error']
        );
    }

    private function requireMessage(Request $request, MailMessage $message): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $message->business_id === (int) $business->id, 404);

        return $business;
    }
}
