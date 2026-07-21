<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mail\Mail\ComposedMail;
use Modules\Mail\Models\MailFilter;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Models\MailTemplate;
use Modules\Mail\Models\ScheduledMail;
use Modules\Mail\Services\BusinessMailConfig;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Mail\Services\MailboxService;
use Modules\Mail\Services\ScheduledMailService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosMailApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly BusinessMailerService $mailer,
        private readonly ScheduledMailService $scheduledMails,
        private readonly BusinessMailConfig $config,
        private readonly MailboxService $mailboxes,
    ) {}

    /**
     * Grouped thread list — one entry per counterpart email address, sorted by
     * the most recent message in the thread. Mirrors the web inbox index view.
     */
    public function threads(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $box    = $request->query('box', 'inbox') === 'sent' ? 'sent' : 'inbox';
        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['read', 'unread'], true)
            ? $request->query('status') : '';

        $direction     = $box === 'sent' ? MailMessage::DIRECTION_OUTBOUND : MailMessage::DIRECTION_INBOUND;
        $contactColumn = $box === 'sent' ? 'to_address' : 'from_address';

        $base = MailMessage::query()
            ->where('business_id', $business->id)
            ->where('direction', $direction)
            ->when(filled($q), fn ($query) => $query->where(function ($sq) use ($q) {
                $sq->where('subject',      'like', "%{$q}%")
                   ->orWhere('from_name',  'like', "%{$q}%")
                   ->orWhere('from_address','like', "%{$q}%")
                   ->orWhere('to_address', 'like', "%{$q}%")
                   ->orWhere('body_text',  'like', "%{$q}%");
            }))
            ->when($status === 'read',   fn ($q2) => $q2->where('is_read', true))
            ->when($status === 'unread', fn ($q2) => $q2->where('is_read', false));

        $messages = (clone $base)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        $grouped = $messages->groupBy(
            fn (MailMessage $m) => strtolower($box === 'sent' ? ($m->to_address ?: '') : ($m->from_address ?: ''))
        );

        $threads = $grouped->map(function ($group, $address) use ($box) {
            /** @var \Illuminate\Support\Collection $group */
            $latest      = $group->first();
            $unreadCount = $box === 'inbox' ? $group->where('is_read', false)->count() : 0;
            $contactName = $box === 'sent'
                ? ($address ?: '(unknown recipient)')
                : ($latest->from_name ?: ($address ?: '(unknown sender)'));

            $preview = mb_substr(strip_tags(
                $latest->body_text ?: (string) ($latest->body_html ?? '')
            ), 0, 90);

            return [
                'contact_email'  => $address,
                'contact_name'   => $contactName,
                'message_count'  => $group->count(),
                'unread_count'   => $unreadCount,
                'has_unread'     => $unreadCount > 0,
                'latest_subject' => $latest->subject,
                'latest_label'   => $latest->occurred_at?->format('d M, H:i'),
                'latest_ts'      => $latest->occurred_at?->toIso8601String(),
                'preview'        => $preview,
            ];
        })->values();

        $statusCounts = null;
        if ($box === 'inbox') {
            $all    = (clone $base)->count();
            $unread = (clone $base)->where('is_read', false)->count();
            $statusCounts = ['all' => $all, 'unread' => $unread, 'read' => $all - $unread];
        }

        return response()->json([
            'data' => $threads,
            'meta' => [
                'box'           => $box,
                'total_unread'  => MailMessage::where('business_id', $business->id)
                    ->where('direction', MailMessage::DIRECTION_INBOUND)
                    ->where('is_read', false)
                    ->count(),
                'status_counts' => $statusCounts,
            ],
        ]);
    }

    /**
     * Full conversation timeline for a given counterpart email — both inbound
     * and outbound messages, sorted oldest-first, plus linked customer info.
     * Opens the whole thread as read.
     */
    public function thread(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $contact = trim((string) $request->query('contact', ''));
        $box     = $request->query('box', 'inbox') === 'sent' ? 'sent' : 'inbox';

        if (blank($contact)) {
            return response()->json(['message' => 'Contact email is required.'], 422);
        }

        $contactLower = strtolower($contact);

        if ($box === 'inbox') {
            MailMessage::where('business_id', $business->id)
                ->where('direction', MailMessage::DIRECTION_INBOUND)
                ->where('is_read', false)
                ->whereRaw('LOWER(from_address) = ?', [$contactLower])
                ->update(['is_read' => true]);
        }

        $messages = MailMessage::where('business_id', $business->id)
            ->where(function ($q) use ($contactLower) {
                $q->whereRaw('LOWER(from_address) = ?', [$contactLower])
                  ->orWhereRaw('LOWER(to_address) = ?', [$contactLower]);
            })
            ->orderBy('occurred_at')
            ->limit(50)
            ->get();

        $customer = \Modules\Pos\Models\Customer::where('business_id', $business->id)
            ->whereRaw('LOWER(email) = ?', [$contactLower])
            ->first(['id', 'name', 'email', 'phone']);

        $latestInbound  = $messages->where('direction', MailMessage::DIRECTION_INBOUND)->last();
        $contactName    = $latestInbound?->from_name ?: $contact;
        $lastSubject    = $messages->last()?->subject ?: '(no subject)';
        $replySubject   = str_starts_with($lastSubject, 'Re:') ? $lastSubject : 'Re: ' . $lastSubject;

        return response()->json([
            'data' => [
                'contact_email'  => $contact,
                'contact_name'   => $contactName,
                'reply_subject'  => $replySubject,
                'customer'       => $customer ? [
                    'id'    => $customer->id,
                    'name'  => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ] : null,
                'messages' => $messages->map(fn (MailMessage $m) => [
                    'id'           => $m->id,
                    'direction'    => $m->direction,
                    'from_name'    => $m->from_name,
                    'from_address' => $m->from_address,
                    'to_address'   => $m->to_address,
                    'subject'      => $m->subject,
                    'body_text'    => $m->body_text,
                    'body_html'    => $m->body_html,
                    'is_read'      => (bool) $m->is_read,
                    'occurred_at'  => $m->occurred_at?->toIso8601String(),
                    'date_label'   => $m->occurred_at?->format('d M, H:i'),
                ]),
            ],
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $box    = $request->query('box', 'inbox') === 'sent' ? 'sent' : 'inbox';
        $q      = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['read', 'unread'], true) ? $request->query('status') : '';

        $direction = $box === 'sent'
            ? MailMessage::DIRECTION_OUTBOUND
            : MailMessage::DIRECTION_INBOUND;

        $messages = MailMessage::query()
            ->where('business_id', $business->id)
            ->where('direction', $direction)
            ->when(filled($q), function ($query) use ($q) {
                $query->where(function ($sq) use ($q) {
                    $sq->where('subject', 'like', "%{$q}%")
                       ->orWhere('from_name', 'like', "%{$q}%")
                       ->orWhere('from_address', 'like', "%{$q}%")
                       ->orWhere('to_address', 'like', "%{$q}%");
                });
            })
            ->when($status === 'read',   fn ($q2) => $q2->where('is_read', true))
            ->when($status === 'unread', fn ($q2) => $q2->where('is_read', false))
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $messages->map(fn (MailMessage $m) => $this->formatMessage($m)),
            'meta' => [
                'box'    => $box,
                'unread' => MailMessage::where('business_id', $business->id)
                    ->where('direction', MailMessage::DIRECTION_INBOUND)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, MailMessage $message): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $message->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        if ($message->direction === MailMessage::DIRECTION_INBOUND && ! $message->is_read) {
            $message->update(['is_read' => true]);
            $message->refresh();
        }

        return response()->json(['data' => $this->formatMessage($message, true)]);
    }

    public function markRead(Request $request, MailMessage $message): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $message->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $message->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function send(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'to'           => ['required', 'email'],
            'subject'      => ['required', 'string', 'max:255'],
            'body'         => ['required', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (filled($validated['scheduled_at'] ?? null)) {
            $this->scheduledMails->schedule($business, [
                'to'           => $validated['to'],
                'subject'      => $validated['subject'],
                'body'         => $validated['body'],
                'scheduled_at' => $validated['scheduled_at'],
            ]);

            return response()->json(['message' => 'Email scheduled.']);
        }

        $bodyHtml = nl2br(htmlspecialchars($validated['body']));
        $mailable = new ComposedMail($validated['subject'], $bodyHtml);
        $result   = $this->mailer->send($business, $mailable, $validated['to']);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Failed to send email. Check your mail settings.'], 422);
        }

        MailMessage::create([
            'business_id'  => $business->id,
            'direction'    => MailMessage::DIRECTION_OUTBOUND,
            'from_address' => $business->user?->email,
            'to_address'   => $validated['to'],
            'subject'      => $validated['subject'],
            'body_text'    => $validated['body'],
            'body_html'    => $bodyHtml,
            'is_read'      => true,
            'occurred_at'  => now(),
        ]);

        return response()->json(['message' => 'Email sent.']);
    }

    public function templates(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q = trim((string) $request->query('q', ''));

        $rows = MailTemplate::query()
            ->where('business_id', $business->id)
            ->when(filled($q), fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (MailTemplate $t) => [
                'id'      => $t->id,
                'name'    => $t->name,
                'subject' => $t->subject,
                'body'    => $t->body,
            ]),
        ]);
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $template = MailTemplate::create([
            'business_id' => $business->id,
            'name'        => $validated['name'],
            'subject'     => $validated['subject'] ?? '',
            'body'        => $validated['body'],
        ]);

        return response()->json([
            'data'    => ['id' => $template->id, 'name' => $template->name, 'subject' => $template->subject, 'body' => $template->body],
            'message' => 'Template created.',
        ], 201);
    }

    public function deleteTemplate(Request $request, MailTemplate $template): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $template->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function scheduled(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $rows = ScheduledMail::query()
            ->where('business_id', $business->id)
            ->where('status', ScheduledMail::STATUS_PENDING)
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ScheduledMail $s) => [
                'id'              => $s->id,
                'to_address'      => $s->to_address,
                'subject'         => $s->subject,
                'scheduled_at'    => $s->scheduled_at?->toIso8601String(),
                'scheduled_label' => $s->scheduled_at?->format('d M Y, H:i'),
            ]),
        ]);
    }

    public function cancelScheduled(Request $request, ScheduledMail $scheduledMail): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $scheduledMail->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Scheduled mail not found.'], 404);
        }

        $cancelled = $this->scheduledMails->cancel($scheduledMail);

        if (! $cancelled) {
            return response()->json(['message' => 'That email has already been sent and cannot be cancelled.'], 422);
        }

        return response()->json(['message' => 'Scheduled email cancelled.']);
    }

    public function filters(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $rows = MailFilter::query()
            ->where('business_id', $business->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (MailFilter $f) => [
                'id'           => $f->id,
                'field'        => $f->field,
                'field_label'  => $f->fieldLabel(),
                'value'        => $f->value,
                'action'       => $f->action,
                'action_label' => $f->actionLabel(),
                'is_active'    => $f->is_active,
                'sort_order'   => $f->sort_order,
            ]),
        ]);
    }

    public function testMail(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'to' => ['required', 'email', 'max:190'],
        ]);

        $result = $this->mailer->sendTest($business, $validated['to']);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Failed to send test email.'], 422);
        }

        return response()->json(['message' => 'Test email sent to ' . $validated['to'] . '.']);
    }

    public function verifyCredentials(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $settings = $this->config->get($business);
        $provider = $settings['provider'] ?? BusinessMailConfig::PROVIDER_PLATFORM;

        if ($provider === BusinessMailConfig::PROVIDER_PLATFORM) {
            return response()->json(['message' => 'Using platform email — no credentials to verify.']);
        }

        if ($provider === BusinessMailConfig::PROVIDER_RESEND) {
            $apiKey = $settings['resend_api_key'] ?? '';
            if (! filled($apiKey)) {
                return response()->json(['message' => 'No Resend API key saved. Save your settings first.'], 422);
            }

            $ch = curl_init('https://api.resend.com/domains');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            ]);
            curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return response()->json(['message' => 'Cannot reach Resend API: ' . $err], 422);
            }

            return $http === 200
                ? response()->json(['message' => 'Resend API key is valid.'])
                : response()->json(['message' => 'Resend API key rejected (HTTP ' . $http . '). Check your key.'], 422);
        }

        // SMTP verification — connect and attempt AUTH LOGIN
        $host       = $settings['smtp_host'] ?? '';
        $port       = (int) ($settings['smtp_port'] ?? 587);
        $username   = $settings['smtp_username'] ?? '';
        $password   = $settings['smtp_password'] ?? '';
        $encryption = $settings['smtp_encryption'] ?? 'tls';

        if (! filled($host)) {
            return response()->json(['message' => 'No SMTP host saved. Save your settings first.'], 422);
        }

        try {
            $address = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
            $socket  = @fsockopen($address, $port, $errno, $errstr, 10);

            if (! $socket) {
                return response()->json(['message' => "Cannot connect to {$host}:{$port} — {$errstr} ({$errno})"], 422);
            }

            $read = fn () => fgets($socket, 512);
            $send = function (string $cmd) use ($socket, $read): string {
                fwrite($socket, $cmd . "\r\n");
                return $read();
            };

            $read(); // banner
            $send('EHLO posdesktop');

            // STARTTLS upgrade when not already SSL
            if ($encryption === 'tls') {
                $tlsResp = $send('STARTTLS');
                if (str_starts_with(trim($tlsResp), '220')) {
                    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    $send('EHLO posdesktop'); // re-handshake after TLS
                }
            }

            // Attempt AUTH LOGIN only when credentials exist
            $authMessage = 'SMTP server is reachable.';
            if (filled($username) && filled($password)) {
                $resp = $send('AUTH LOGIN');
                if (str_starts_with(trim($resp), '334')) {
                    $send(base64_encode($username));
                    $passResp = $send(base64_encode($password));
                    $authMessage = str_starts_with(trim($passResp), '235')
                        ? 'SMTP credentials verified successfully.'
                        : 'SMTP connected but authentication failed: ' . trim($passResp);
                    if (! str_starts_with(trim($passResp), '235')) {
                        fwrite($socket, "QUIT\r\n");
                        fclose($socket);
                        return response()->json(['message' => $authMessage], 422);
                    }
                }
            }

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return response()->json(['message' => $authMessage]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Verification error: ' . $e->getMessage()], 422);
        }
    }

    public function syncMailbox(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $result   = $this->mailboxes->syncNow($business);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Sync failed.'], 422);
        }

        return response()->json(['message' => 'Sync queued — new messages will appear shortly.']);
    }

    public function settingsGet(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $s        = $this->config->get($business);

        return response()->json([
            'data' => [
                'provider'           => $s['provider'],
                'from_address'       => $s['from_address'],
                'from_name'          => $s['from_name'],
                'letterhead_enabled' => $s['letterhead_enabled'],
                'smtp_host'          => $s['smtp_host'],
                'smtp_port'          => $s['smtp_port'],
                'smtp_username'      => $s['smtp_username'],
                'smtp_encryption'    => $s['smtp_encryption'] ?: 'tls',
                'has_smtp_password'  => $this->config->hasSecret($business, 'mail.smtp_password'),
                'has_resend_api_key' => $this->config->hasSecret($business, 'mail.resend_api_key'),
            ],
        ]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'provider'           => ['sometimes', 'string', 'in:platform,smtp,resend'],
            'from_address'       => ['sometimes', 'nullable', 'email', 'max:190'],
            'from_name'          => ['sometimes', 'nullable', 'string', 'max:150'],
            'letterhead_enabled' => ['sometimes', 'boolean'],
            'smtp_host'          => ['sometimes', 'nullable', 'string', 'max:190'],
            'smtp_port'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'      => ['sometimes', 'nullable', 'string', 'max:190'],
            'smtp_password'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_encryption'    => ['sometimes', 'nullable', 'string', 'in:tls,ssl,none'],
            'resend_api_key'     => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $this->config->save($business, $validated);

        return response()->json(['message' => 'Mail settings saved.']);
    }

    private function formatMessage(MailMessage $m, bool $withBody = false): array
    {
        $data = [
            'id'           => $m->id,
            'direction'    => $m->direction,
            'from_address' => $m->from_address,
            'from_name'    => $m->from_name,
            'to_address'   => $m->to_address,
            'subject'      => $m->subject,
            'is_read'      => (bool) $m->is_read,
            'occurred_at'  => $m->occurred_at?->toIso8601String(),
            'date_label'   => $m->occurred_at?->format('d M Y, H:i'),
        ];

        if ($withBody) {
            $data['body_text'] = $m->body_text ?: strip_tags((string) ($m->body_html ?? ''));
        }

        return $data;
    }
}
