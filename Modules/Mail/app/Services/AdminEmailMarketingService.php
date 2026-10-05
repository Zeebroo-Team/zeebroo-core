<?php

namespace Modules\Mail\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Mail\Jobs\SendAdminMarketingMailJob;
use Modules\Mail\Mail\AdminMarketingMail;
use Modules\Mail\Models\AdminEmailCampaign;
use Modules\Mail\Models\AdminEmailCampaignRecipient;
use Modules\Mail\Models\AdminEmailTemplate;
use Modules\Mail\Support\HtmlSanitizer;

class AdminEmailMarketingService
{
    /** Merge tags admins can drop into a subject or body. */
    public const PLACEHOLDERS = [
        'name' => 'Full name',
        'first_name' => 'First name',
        'email' => 'Email address',
        'app_name' => 'App name',
    ];

    // ── Templates ──────────────────────────────────────────────

    public function templates(): Collection
    {
        return AdminEmailTemplate::query()
            ->withCount('campaigns')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @param  array{name: string, subject: string, body: string}  $data
     */
    public function saveTemplate(array $data, ?AdminEmailTemplate $template, User $admin): AdminEmailTemplate
    {
        $template ??= new AdminEmailTemplate(['created_by' => $admin->id]);

        $template->fill([
            'name' => trim($data['name']),
            'subject' => trim($data['subject']),
            'body' => HtmlSanitizer::cleanEmail($data['body']),
        ])->save();

        return $template;
    }

    // ── Recipients ─────────────────────────────────────────────

    /**
     * Every user an admin may email, in a light shape for the client-side picker.
     * Opted-out users are included (flagged) so the admin can see why they're unselectable.
     */
    public function recipientCandidates(): Collection
    {
        return $this->visibleUsers()
            ->withCount('businesses')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'is_active', 'marketing_opt_out_at', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'active' => (bool) $u->is_active,
                'opted_out' => $u->marketing_opt_out_at !== null,
                'has_business' => $u->businesses_count > 0,
                'joined' => $u->created_at?->format('d M Y'),
            ]);
    }

    /** Users hidden from admin listings (e.g. test domains) are never emailed either. */
    private function visibleUsers(): Builder
    {
        $hiddenDomains = config('app.hidden_user_email_domains', []);

        return User::query()
            ->whereNotNull('email')
            ->when($hiddenDomains, fn ($q) => $q->where(function ($w) use ($hiddenDomains) {
                foreach ($hiddenDomains as $domain) {
                    $w->whereRaw('LOWER(email) NOT LIKE ?', ['%@'.$domain]);
                }
            }));
    }

    // ── Campaigns ──────────────────────────────────────────────

    public function campaigns(int $perPage = 15): LengthAwarePaginator
    {
        return AdminEmailCampaign::query()
            ->with(['template:id,name', 'creator:id,name'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Snapshot the content, record one row per eligible recipient and queue a job each.
     *
     * @param  array<int>  $userIds
     */
    public function launchCampaign(string $subject, string $body, ?int $templateId, array $userIds, User $admin): AdminEmailCampaign
    {
        $users = $this->visibleUsers()
            ->whereIn('id', array_unique(array_map('intval', $userIds)))
            ->whereNull('marketing_opt_out_at')
            ->get(['id', 'name', 'email'])
            ->unique(fn (User $u) => strtolower($u->email));

        $campaign = DB::transaction(function () use ($subject, $body, $templateId, $users, $admin) {
            $campaign = AdminEmailCampaign::create([
                'template_id' => $templateId,
                'subject' => trim($subject),
                'body' => HtmlSanitizer::cleanEmail($body),
                'status' => $users->isEmpty() ? AdminEmailCampaign::STATUS_COMPLETED : AdminEmailCampaign::STATUS_SENDING,
                'recipients_count' => $users->count(),
                'created_by' => $admin->id,
                'completed_at' => $users->isEmpty() ? now() : null,
            ]);

            $now = now();
            foreach ($users->chunk(500) as $chunk) {
                AdminEmailCampaignRecipient::insert($chunk->map(fn (User $u) => [
                    'campaign_id' => $campaign->id,
                    'user_id' => $u->id,
                    'email' => $u->email,
                    'name' => $u->name,
                    'status' => AdminEmailCampaignRecipient::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            return $campaign;
        });

        // Dispatch after commit so workers never pick up a recipient row that doesn't exist yet.
        $campaign->recipients()->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                SendAdminMarketingMailJob::dispatch($row->id);
            }
        });

        return $campaign;
    }

    /** Re-queue the failed recipients of a campaign. Returns how many were queued. */
    public function retryFailed(AdminEmailCampaign $campaign): int
    {
        $ids = $campaign->recipients()
            ->where('status', AdminEmailCampaignRecipient::STATUS_FAILED)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($campaign, $ids) {
            AdminEmailCampaignRecipient::whereIn('id', $ids)->update([
                'status' => AdminEmailCampaignRecipient::STATUS_PENDING,
                'error' => null,
            ]);

            $campaign->update([
                'failed_count' => max(0, $campaign->failed_count - $ids->count()),
                'status' => AdminEmailCampaign::STATUS_SENDING,
                'completed_at' => null,
            ]);
        });

        $ids->each(fn (int $id) => SendAdminMarketingMailJob::dispatch($id));

        return $ids->count();
    }

    /** Called by the job once a recipient has been attempted. */
    public function recordResult(AdminEmailCampaignRecipient $recipient, bool $success, ?string $error = null): void
    {
        $recipient->update([
            'status' => $success ? AdminEmailCampaignRecipient::STATUS_SENT : AdminEmailCampaignRecipient::STATUS_FAILED,
            'error' => $success ? null : mb_substr((string) $error, 0, 2000),
            'sent_at' => $success ? now() : null,
        ]);

        AdminEmailCampaign::whereKey($recipient->campaign_id)->increment($success ? 'sent_count' : 'failed_count');

        AdminEmailCampaign::whereKey($recipient->campaign_id)
            ->where('status', AdminEmailCampaign::STATUS_SENDING)
            ->whereRaw('sent_count + failed_count >= recipients_count')
            ->update(['status' => AdminEmailCampaign::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    // ── Rendering & delivery ───────────────────────────────────

    public function buildMail(string $subject, string $body, ?User $user, ?string $email = null, ?string $name = null): AdminMarketingMail
    {
        $vars = [
            'name' => $user?->name ?? $name ?? '',
            'first_name' => strtok((string) ($user?->name ?? $name ?? ''), ' ') ?: '',
            'email' => $user?->email ?? $email ?? '',
            'app_name' => (string) config('app.name'),
        ];

        return new AdminMarketingMail(
            $this->fill($subject, $vars, false),
            $this->fill($body, $vars, true),
            $user ? $this->unsubscribeUrl($user) : null,
        );
    }

    /** Sends a one-off preview to the admin, synchronously, so they see the result immediately. */
    public function sendTest(string $subject, string $body, User $admin): void
    {
        $mail = $this->buildMail('[Test] '.$subject, HtmlSanitizer::cleanEmail($body), $admin);

        Mail::to($admin->email)->send($mail);
    }

    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['user' => $user->id]);
    }

    /**
     * Replace {{ tag }} merge tags. HTMLPurifier percent-encodes braces inside hrefs,
     * so the encoded form is matched too.
     *
     * @param  array<string, string>  $vars
     */
    private function fill(string $text, array $vars, bool $html): string
    {
        $tags = implode('|', array_keys(self::PLACEHOLDERS));

        return preg_replace_callback(
            '/(?:\{\{|%7B%7B)\s*('.$tags.')\s*(?:\}\}|%7D%7D)/i',
            fn ($m) => $html ? e($vars[strtolower($m[1])] ?? '') : ($vars[strtolower($m[1])] ?? ''),
            $text,
        );
    }
}
