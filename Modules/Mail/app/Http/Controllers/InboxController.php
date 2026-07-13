<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Mail\ComposedMail;
use Modules\Mail\Models\MailConversationAssignment;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Mail\Services\MailAssistantService;
use Modules\Mail\Services\MailTemplateService;
use Modules\Mail\Services\ScheduledMailService;
use Modules\Pos\Models\Customer;
use Throwable;

class InboxController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly BusinessMailerService $mailer,
        private readonly MailTemplateService $templates,
        private readonly ScheduledMailService $scheduledMails,
        private readonly MailAssistantService $assistant,
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

        // Grouped within the current page only — messages are already ordered
        // desc by occurred_at, so groupBy() naturally orders groups by their
        // most recent message, matching the flat list's ordering.
        $groupedMessages = $messages->getCollection()->groupBy(
            fn (MailMessage $m) => Str::lower($box === 'sent' ? ($m->to_address ?: '') : ($m->from_address ?: ''))
        );

        return view('mail::inbox.index', [
            'business'        => $business,
            'messages'        => $messages,
            'groupedMessages' => $groupedMessages,
            'box'             => $box,
            'search'          => $search,
            'status'          => $status,
            'contact'         => $contact,
            'contacts'        => $contacts,
            'statusCounts'    => $statusCounts,
            'unreadCount'     => MailMessage::where('business_id', $business->id)
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

        $counterpartEmail = $message->direction === MailMessage::DIRECTION_INBOUND
            ? $message->from_address
            : $message->to_address;

        // Opening the conversation shows the whole thread, so mark every
        // unread inbound message from this contact read, not just this one.
        if (filled($counterpartEmail)) {
            MailMessage::where('business_id', $business->id)
                ->where('direction', MailMessage::DIRECTION_INBOUND)
                ->where('is_read', false)
                ->whereRaw('LOWER(from_address) = ?', [Str::lower($counterpartEmail)])
                ->update(['is_read' => true]);
        } elseif ($message->direction === MailMessage::DIRECTION_INBOUND && !$message->is_read) {
            $message->update(['is_read' => true]);
        }

        ['customer' => $customer, 'timeline' => $timeline] = $this->resolveConversation($business, $message, $counterpartEmail);

        $responseRate = filled($counterpartEmail) ? $this->responseRate($business, $counterpartEmail) : null;

        $assignment = filled($counterpartEmail)
            ? MailConversationAssignment::where('business_id', $business->id)
                ->where('counterpart_email', Str::lower($counterpartEmail))
                ->first()
            : null;

        return view('mail::inbox.show', [
            'business'         => $business,
            'message'          => $message,
            'customer'         => $customer,
            'timeline'         => $timeline,
            'counterpartEmail' => $counterpartEmail,
            'responseRate'     => $responseRate,
            'assignment'       => $assignment,
            'assignableUsers'  => filled($counterpartEmail) ? $this->assignableUsers($business) : collect(),
        ]);
    }

    public function assignConversation(Request $request, MailMessage $message): RedirectResponse
    {
        $business = $this->requireMessage($request, $message);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $counterpartEmail = $message->direction === MailMessage::DIRECTION_INBOUND
            ? $message->from_address
            : $message->to_address;

        abort_if(blank($counterpartEmail), 422);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        MailConversationAssignment::updateOrCreate(
            ['business_id' => $business->id, 'counterpart_email' => Str::lower($counterpartEmail)],
            ['assigned_to' => $data['assigned_to'] ?? null]
        );

        $assignedUser = filled($data['assigned_to'] ?? null)
            ? $this->assignableUsers($business)->firstWhere('id', (int) $data['assigned_to'])
            : null;

        return redirect()->route('mail.inbox.show', $message)->with(
            'status',
            $assignedUser ? 'Conversation assigned to ' . $assignedUser->name . '.' : 'Conversation unassigned.'
        );
    }

    public function aiSummary(Request $request, MailMessage $message): JsonResponse
    {
        $business = $this->requireMessage($request, $message);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $counterpartEmail = $message->direction === MailMessage::DIRECTION_INBOUND
            ? $message->from_address
            : $message->to_address;

        ['timeline' => $timeline] = $this->resolveConversation($business, $message, $counterpartEmail);

        try {
            return response()->json(['summary' => $this->assistant->summarize($business, $timeline)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function aiSuggestReply(Request $request, MailMessage $message): JsonResponse
    {
        $business = $this->requireMessage($request, $message);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $counterpartEmail = $message->direction === MailMessage::DIRECTION_INBOUND
            ? $message->from_address
            : $message->to_address;

        ['customer' => $customer, 'timeline' => $timeline] = $this->resolveConversation($business, $message, $counterpartEmail);

        try {
            return response()->json(['reply' => $this->assistant->suggestReply($business, $timeline, $customer)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    /**
     * @return array{customer: ?Customer, timeline: \Illuminate\Support\Collection<int, MailMessage>}
     */
    private function resolveConversation(Business $business, MailMessage $message, ?string $counterpartEmail): array
    {
        if (blank($counterpartEmail)) {
            return ['customer' => null, 'timeline' => collect([$message])];
        }

        $customer = Customer::where('business_id', $business->id)
            ->whereRaw('LOWER(email) = ?', [Str::lower($counterpartEmail)])
            ->first();

        $relatedMessages = MailMessage::where('business_id', $business->id)
            ->where('id', '!=', $message->id)
            ->where(function ($query) use ($counterpartEmail) {
                $query->whereRaw('LOWER(from_address) = ?', [Str::lower($counterpartEmail)])
                    ->orWhereRaw('LOWER(to_address) = ?', [Str::lower($counterpartEmail)]);
            })
            ->orderByDesc('occurred_at')
            ->limit(19)
            ->get();

        $timeline = $relatedMessages->push($message)->sortBy('occurred_at')->values();

        return ['customer' => $customer, 'timeline' => $timeline];
    }

    /**
     * Percentage of this contact's inbound messages that were followed by
     * at least one outbound reply at any later point in the conversation.
     *
     * @return array{percent: int, replied: int, total: int}|null
     */
    private function responseRate(Business $business, string $counterpartEmail): ?array
    {
        $history = MailMessage::where('business_id', $business->id)
            ->where(function ($query) use ($counterpartEmail) {
                $query->whereRaw('LOWER(from_address) = ?', [Str::lower($counterpartEmail)])
                    ->orWhereRaw('LOWER(to_address) = ?', [Str::lower($counterpartEmail)]);
            })
            ->orderBy('occurred_at')
            ->get(['direction', 'occurred_at']);

        $inboundTotal = $history->where('direction', MailMessage::DIRECTION_INBOUND)->count();

        if ($inboundTotal === 0) {
            return null;
        }

        $replied = 0;
        foreach ($history as $index => $entry) {
            if ($entry->direction !== MailMessage::DIRECTION_INBOUND) {
                continue;
            }

            $hasLaterReply = $history->slice($index + 1)
                ->contains(fn (MailMessage $later) => $later->direction === MailMessage::DIRECTION_OUTBOUND);

            if ($hasLaterReply) {
                $replied++;
            }
        }

        return [
            'percent' => (int) round($replied / $inboundTotal * 100),
            'replied' => $replied,
            'total'   => $inboundTotal,
        ];
    }

    public function convertToCustomer(Request $request, MailMessage $message): RedirectResponse
    {
        $business = $this->requireMessage($request, $message);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $email = $message->direction === MailMessage::DIRECTION_INBOUND
            ? $message->from_address
            : $message->to_address;

        abort_if(blank($email), 422);

        $existing = Customer::where('business_id', $business->id)
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();

        if ($existing) {
            return redirect()->route('mail.inbox.show', $message)
                ->with('status', $existing->name . ' is already a customer.');
        }

        $name = $message->from_name ?: Str::title(str_replace(['.', '_', '+'], ' ', Str::before($email, '@')));

        $customer = Customer::create([
            'business_id' => $business->id,
            'name'        => $name,
            'email'       => $email,
        ]);

        return redirect()->route('mail.inbox.show', $message)
            ->with('status', $customer->name . ' added as a customer.');
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
            'to'               => ['required', 'email', 'max:190'],
            'subject'          => ['required', 'string', 'max:200'],
            'body'             => ['required', 'string', 'max:10000'],
            'send_at'          => ['nullable', 'date', 'after:now'],
            'reply_to_message' => ['nullable', 'integer'],
        ]);

        $replyToMessage = filled($data['reply_to_message'] ?? null)
            ? MailMessage::where('business_id', $business->id)->find($data['reply_to_message'])
            : null;
        $redirectRoute = $replyToMessage
            ? route('mail.inbox.show', $replyToMessage)
            : route('mail.inbox.index', ['box' => 'sent']);

        if (filled($data['send_at'] ?? null)) {
            $this->scheduledMails->schedule($business, [
                'to'           => $data['to'],
                'subject'      => $data['subject'],
                'body'         => $data['body'],
                'scheduled_at' => $data['send_at'],
            ]);

            return redirect($replyToMessage ? $redirectRoute : route('mail.scheduled.index'))
                ->with('status', 'Message scheduled to send to ' . $data['to'] . '.');
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

        return redirect($redirectRoute)->with(
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
