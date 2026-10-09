<?php

namespace Modules\Mail\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\AppConnection\Models\AppRelease;
use Modules\Business\Models\Business;
use Modules\Mail\Jobs\SendAutomatedEmailJob;
use Modules\Mail\Mail\AdminMarketingMail;
use Modules\Mail\Models\AdminAutomatedEmail;
use Modules\Mail\Models\AdminAutomatedEmailLog;
use Modules\Mail\Support\AutomatedEmailDefaults;
use Modules\Mail\Support\HtmlSanitizer;
use Throwable;

/**
 * Automatic platform emails: email verification OTP, welcome, password reset OTP, inactivity reminder,
 * periodic activity report and new release announcement. Each has an admin-editable
 * template + settings stored in admin_automated_emails (defaults live in code).
 */
class AutomatedEmailService
{
    /** Merge tags available in every automated email. */
    private const COMMON_TAGS = [
        'name' => 'Full name',
        'first_name' => 'First name',
        'email' => 'Email address',
        'app_name' => 'App name',
    ];

    /** Tags whose values are trusted HTML built by this service (not escaped when filled). */
    private const HTML_TAGS = ['report_summary', 'release_notes'];

    public const FREQUENCIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    public const RELEASE_CHANNELS = ['stable', 'beta', 'alpha', 'rc'];

    public function __construct(private readonly AdminEmailMarketingService $marketing) {}

    /**
     * Static description of every automation, in display order.
     *
     * - required: can't be switched off (the feature would break without it)
     * - marketing: honours the user's marketing opt-out and carries an unsubscribe link
     */
    public function definitions(): array
    {
        return [
            AdminAutomatedEmail::EMAIL_VERIFICATION => [
                'name' => 'Email verification',
                'description' => 'Emails a 6-digit code to a new user to confirm their address. Until they enter it, the web app, POS desktop and POS Lite show a "Verify your email" banner.',
                'icon' => 'fa-envelope-circle-check',
                'required' => false,
                'marketing' => false,
                'default_enabled' => true,
                'settings' => ['otp_minutes' => 15],
                'tags' => ['otp_code' => 'Verification code', 'expiry_minutes' => 'Minutes until the code expires'],
            ],
            AdminAutomatedEmail::WELCOME => [
                'name' => 'Registration successful',
                'description' => 'Welcome email sent when a new user creates an account (web, Google or desktop app) — after they verify their email when verification is on.',
                'icon' => 'fa-user-check',
                'required' => false,
                'marketing' => false,
                'default_enabled' => true,
                'settings' => [],
                'tags' => ['dashboard_url' => 'Dashboard link'],
            ],
            AdminAutomatedEmail::PASSWORD_RESET => [
                'name' => 'Forgot password (OTP)',
                'description' => 'One-time code emailed when a user asks to reset their password.',
                'icon' => 'fa-key',
                'required' => true,
                'marketing' => false,
                'default_enabled' => true,
                'settings' => ['otp_minutes' => 10],
                'tags' => ['otp_code' => 'One-time code', 'expiry_minutes' => 'Minutes until the code expires'],
            ],
            AdminAutomatedEmail::INACTIVITY => [
                'name' => 'Inactivity reminder',
                'description' => 'Nudges users who haven\'t used the system for a set number of days. Sent once per inactive stretch.',
                'icon' => 'fa-bell',
                'required' => false,
                'marketing' => true,
                'default_enabled' => false,
                'settings' => ['days' => 2, 'send_hour' => 9],
                'tags' => ['days_inactive' => 'Days since last use', 'login_url' => 'Sign-in link'],
            ],
            AdminAutomatedEmail::REPORT => [
                'name' => 'Activity report',
                'description' => 'Daily, weekly or monthly summary of sales and new customers for every business the user owns.',
                'icon' => 'fa-chart-column',
                'required' => false,
                'marketing' => true,
                'default_enabled' => false,
                'settings' => ['frequency' => 'weekly', 'day_of_week' => 1, 'send_hour' => 8, 'skip_empty' => true],
                'tags' => [
                    'period_label' => 'Report period',
                    'sales_count' => 'Number of sales',
                    'new_customers' => 'New customers',
                    'report_summary' => 'Per-business table',
                    'dashboard_url' => 'Dashboard link',
                ],
            ],
            AdminAutomatedEmail::NEW_RELEASE => [
                'name' => 'New release',
                'description' => 'Announces a new desktop app version to every user when a release is published from Release Management.',
                'icon' => 'fa-rocket',
                'required' => false,
                'marketing' => true,
                'default_enabled' => true,
                'settings' => ['channels' => ['stable']],
                'tags' => [
                    'release_app' => 'App name (e.g. Zeebroo POS)',
                    'release_version' => 'Version',
                    'release_date' => 'Release date',
                    'release_notes' => 'Release notes (bullet list)',
                    'download_url' => 'Download link',
                ],
            ],
        ];
    }

    public function definition(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    /** @return array<string, string> tag => label */
    public function tagsFor(string $key): array
    {
        return self::COMMON_TAGS + ($this->definition($key)['tags'] ?? []);
    }

    // ── Storage ────────────────────────────────────────────────

    /** Every automation, creating any missing row from its defaults. */
    public function all(): Collection
    {
        $rows = AdminAutomatedEmail::query()->get()->keyBy('key');

        return collect(array_keys($this->definitions()))
            ->map(fn (string $key) => $rows->get($key) ?? $this->get($key))
            ->keyBy('key');
    }

    public function get(string $key): AdminAutomatedEmail
    {
        $definition = $this->definition($key) ?? throw new \InvalidArgumentException("Unknown automated email [{$key}].");

        $email = AdminAutomatedEmail::query()->firstOrCreate(['key' => $key], [
            'is_enabled' => $definition['default_enabled'],
            ...AutomatedEmailDefaults::for($key),
            'settings' => $definition['settings'],
        ]);

        // Fill settings added in later releases without overwriting the admin's values.
        $email->settings = array_merge($definition['settings'], $email->settings ?? []);

        return $email;
    }

    /**
     * @param  array{is_enabled?: bool, subject: string, body: string, settings?: array}  $data
     */
    public function update(string $key, array $data, User $admin): AdminAutomatedEmail
    {
        $email = $this->get($key);
        $definition = $this->definition($key);

        $enabled = $definition['required'] ? true : (bool) ($data['is_enabled'] ?? false);
        $settings = array_merge($email->settings ?? [], array_intersect_key($data['settings'] ?? [], $definition['settings']));

        // Scheduled emails: (re)start the clock on enable or schedule change so saving never
        // triggers an immediate catch-up send for a slot that already passed.
        if (in_array($key, [AdminAutomatedEmail::INACTIVITY, AdminAutomatedEmail::REPORT], true)
            && $enabled && (! $email->is_enabled || $settings != $email->settings)) {
            $email->last_run_at = now();
        }

        $email->fill([
            'is_enabled' => $enabled,
            'subject' => trim($data['subject']),
            'body' => HtmlSanitizer::cleanEmail($data['body']),
            'settings' => $settings,
            'updated_by' => $admin->id,
        ])->save();

        return $email;
    }

    public function resetTemplate(string $key, User $admin): AdminAutomatedEmail
    {
        $email = $this->get($key);
        $email->fill([...AutomatedEmailDefaults::for($key), 'updated_by' => $admin->id])->save();

        return $email;
    }

    public function toggle(string $key, User $admin): AdminAutomatedEmail
    {
        $email = $this->get($key);

        if ($this->definition($key)['required']) {
            return $email;
        }

        return $this->update($key, [
            'is_enabled' => ! $email->is_enabled,
            'subject' => $email->subject,
            'body' => $email->body,
            'settings' => $email->settings,
        ], $admin);
    }

    // ── History ────────────────────────────────────────────────

    public function recentLogs(string $key, int $limit = 25): Collection
    {
        return AdminAutomatedEmailLog::query()
            ->where('key', $key)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, array{sent: int, failed: int, last: ?string}> sent/failed in the last 30 days per key */
    public function stats(): array
    {
        $rows = AdminAutomatedEmailLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->select('key', 'status')
            ->selectRaw('COUNT(*) as total, MAX(created_at) as last_at')
            ->groupBy('key', 'status')
            ->get();

        $stats = [];
        foreach (array_keys($this->definitions()) as $key) {
            $forKey = $rows->where('key', $key);
            $stats[$key] = [
                'sent' => (int) $forKey->firstWhere('status', AdminAutomatedEmailLog::STATUS_SENT)?->total,
                'failed' => (int) $forKey->firstWhere('status', AdminAutomatedEmailLog::STATUS_FAILED)?->total,
                'last' => $forKey->max('last_at'),
            ];
        }

        return $stats;
    }

    // ── Triggers ───────────────────────────────────────────────

    /** Called right after any self-service sign-up. Queued so registration never waits on SMTP. */
    public function queueWelcome(User $user): void
    {
        if ($this->get(AdminAutomatedEmail::WELCOME)->is_enabled) {
            SendAutomatedEmailJob::dispatch(AdminAutomatedEmail::WELCOME, $user->id);
        }
    }

    /**
     * Sent synchronously: the user is waiting on the page for this code.
     *
     * @throws Throwable when the mail transport fails
     */
    public function sendPasswordResetCode(User $user, string $otp): void
    {
        $this->deliver(AdminAutomatedEmail::PASSWORD_RESET, $user, [
            'otp_code' => $otp,
            'expiry_minutes' => (string) $this->otpMinutes(),
        ], throw: true);
    }

    public function otpMinutes(): int
    {
        return (int) $this->get(AdminAutomatedEmail::PASSWORD_RESET)->setting('otp_minutes', 10);
    }

    /** New sign-ups must confirm their email while this automation is on. */
    public function emailVerificationEnabled(): bool
    {
        return $this->get(AdminAutomatedEmail::EMAIL_VERIFICATION)->is_enabled;
    }

    /**
     * Sent synchronously: the user is waiting on the verify screen for this code.
     *
     * @throws Throwable when the mail transport fails
     */
    public function sendVerificationCode(User $user, string $otp): void
    {
        $this->deliver(AdminAutomatedEmail::EMAIL_VERIFICATION, $user, [
            'otp_code' => $otp,
            'expiry_minutes' => (string) $this->verificationMinutes(),
        ], throw: true);
    }

    public function verificationMinutes(): int
    {
        return (int) $this->get(AdminAutomatedEmail::EMAIL_VERIFICATION)->setting('otp_minutes', 15);
    }

    /** Announce a release to every eligible user (at most once per release). Returns the number queued. */
    public function announceRelease(AppRelease $release): int
    {
        $email = $this->get(AdminAutomatedEmail::NEW_RELEASE);

        if (! $email->is_enabled
            || ! in_array($release->channel, (array) $email->setting('channels', []), true)) {
            return 0;
        }

        // Claim the release atomically so a double submit can't announce it twice.
        $claimed = AppRelease::whereKey($release->id)->whereNull('users_notified_at')->update(['users_notified_at' => now()]);
        if ($claimed === 0) {
            return 0;
        }

        $count = 0;
        $this->eligibleUsers(true)->select('id')->chunkById(500, function ($users) use ($release, &$count) {
            foreach ($users as $user) {
                SendAutomatedEmailJob::dispatch(AdminAutomatedEmail::NEW_RELEASE, $user->id, ['release_id' => $release->id]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Run the scheduled automations that are due. Called every few minutes by the
     * mail:automated-emails command. Returns how many emails were queued per key.
     *
     * @return array<string, int>
     */
    public function runScheduled(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            AdminAutomatedEmail::INACTIVITY => $this->runInactivity($now),
            AdminAutomatedEmail::REPORT => $this->runReport($now),
        ];
    }

    private function runInactivity(CarbonImmutable $now): int
    {
        $email = $this->get(AdminAutomatedEmail::INACTIVITY);
        $slot = $this->dailySlot($now, (int) $email->setting('send_hour', 9));

        if (! $this->claimSlot($email, $slot)) {
            return 0;
        }

        $days = max(1, (int) $email->setting('days', 2));
        $seen = 'COALESCE(users.last_seen_at, users.created_at)';

        $query = $this->eligibleUsers(true)
            ->whereRaw("{$seen} <= ?", [$now->subDays($days)])
            // Only one reminder per inactive stretch: skip users already reminded since they were last seen.
            ->whereNotExists(fn ($q) => $q->from('admin_automated_email_logs as l')
                ->whereColumn('l.user_id', 'users.id')
                ->where('l.key', AdminAutomatedEmail::INACTIVITY)
                ->where('l.status', AdminAutomatedEmailLog::STATUS_SENT)
                ->whereRaw("l.created_at >= {$seen}"));

        return $this->dispatchFor($query, AdminAutomatedEmail::INACTIVITY);
    }

    private function runReport(CarbonImmutable $now): int
    {
        $email = $this->get(AdminAutomatedEmail::REPORT);
        $hour = (int) $email->setting('send_hour', 8);

        [$slot, $from, $to] = match ($email->setting('frequency', 'weekly')) {
            'daily' => (function () use ($now, $hour) {
                $slot = $this->dailySlot($now, $hour);

                return [$slot, $slot->startOfDay()->subDay(), $slot->startOfDay()];
            })(),
            'monthly' => (function () use ($now, $hour) {
                $slot = $now->startOfMonth()->setTime($hour, 0);
                $slot = $slot->greaterThan($now) ? $slot->subMonthNoOverflow() : $slot;

                return [$slot, $slot->startOfDay()->subMonthNoOverflow(), $slot->startOfDay()];
            })(),
            default => (function () use ($now, $hour, $email) {
                $day = min(7, max(1, (int) $email->setting('day_of_week', 1)));
                $slot = $now->startOfDay()->subDays(($now->dayOfWeekIso - $day + 7) % 7)->setTime($hour, 0);
                $slot = $slot->greaterThan($now) ? $slot->subWeek() : $slot;

                return [$slot, $slot->startOfDay()->subWeek(), $slot->startOfDay()];
            })(),
        };

        if (! $this->claimSlot($email, $slot)) {
            return 0;
        }

        $query = $this->eligibleUsers(true)->whereHas('businesses');

        return $this->dispatchFor($query, AdminAutomatedEmail::REPORT, [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
        ]);
    }

    /** Today's send time, or yesterday's if today's hasn't come yet. */
    private function dailySlot(CarbonImmutable $now, int $hour): CarbonImmutable
    {
        $slot = $now->setTime(min(23, max(0, $hour)), 0);

        return $slot->greaterThan($now) ? $slot->subDay() : $slot;
    }

    /** Atomically mark the slot as handled; false if disabled or another run already took it. */
    private function claimSlot(AdminAutomatedEmail $email, CarbonImmutable $slot): bool
    {
        if (! $email->is_enabled) {
            return false;
        }

        if ($email->last_run_at === null) {
            // First run ever: start counting from now instead of back-filling a past slot.
            $email->forceFill(['last_run_at' => now()])->save();

            return false;
        }

        return AdminAutomatedEmail::whereKey($email->id)
            ->where('last_run_at', '<', $slot)
            ->update(['last_run_at' => now()]) > 0;
    }

    private function dispatchFor(Builder $query, string $key, array $context = []): int
    {
        $count = 0;
        $query->select('users.id')->chunkById(500, function ($users) use ($key, $context, &$count) {
            foreach ($users as $user) {
                SendAutomatedEmailJob::dispatch($key, $user->id, $context);
                $count++;
            }
        }, 'users.id', 'id');

        return $count;
    }

    /** Active, non-admin users visible to admins; optionally only those who accept marketing email. */
    private function eligibleUsers(bool $marketing): Builder
    {
        return $this->marketing->visibleUsers()
            ->where('users.is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->when($marketing, fn ($q) => $q->whereNull('users.marketing_opt_out_at'));
    }

    // ── Delivery ───────────────────────────────────────────────

    /**
     * Render and send one automated email to one user, recording the result.
     * Returns false when skipped (disabled, opted out, nothing to report) or failed.
     */
    public function deliver(string $key, User $user, array $context = [], bool $throw = false): bool
    {
        $email = $this->get($key);
        $definition = $this->definition($key);

        if (! $email->is_enabled || ! $user->email || ($definition['marketing'] && $user->marketing_opt_out_at !== null)) {
            return false;
        }

        $vars = $this->contextVars($key, $user, $context);
        if ($vars === null) {
            return false;
        }

        $mail = $this->render($email->subject, $email->body, $user, $vars, $definition['marketing']);

        try {
            Mail::to($user->email, $user->name)->send($mail);
            $this->log($key, $user, $mail->emailSubject, true);
        } catch (Throwable $e) {
            $this->log($key, $user, $mail->emailSubject, false, $e->getMessage());

            if ($throw) {
                throw $e;
            }

            report($e);

            return false;
        }

        return true;
    }

    /** Send the (unsaved) subject/body to the admin with sample values. */
    public function sendTest(string $key, string $subject, string $body, User $admin): void
    {
        $mail = $this->render('[Test] '.$subject, HtmlSanitizer::cleanEmail($body), $admin, $this->sampleVars($key), $this->definition($key)['marketing']);

        Mail::to($admin->email)->send($mail);
    }

    /** Example values for the preview and test send. */
    public function sampleVars(string $key): array
    {
        $release = AppRelease::query()->orderByDesc('release_date')->orderByDesc('id')->first();

        return match ($key) {
            AdminAutomatedEmail::EMAIL_VERIFICATION => ['otp_code' => '730584', 'expiry_minutes' => (string) $this->verificationMinutes()],
            AdminAutomatedEmail::WELCOME => ['dashboard_url' => route('dashboard')],
            AdminAutomatedEmail::PASSWORD_RESET => ['otp_code' => '482915', 'expiry_minutes' => (string) $this->otpMinutes()],
            AdminAutomatedEmail::INACTIVITY => [
                'days_inactive' => (string) $this->get(AdminAutomatedEmail::INACTIVITY)->setting('days', 2),
                'login_url' => route('login'),
            ],
            AdminAutomatedEmail::REPORT => [
                'period_label' => now()->subWeek()->startOfWeek()->format('d M').' – '.now()->subWeek()->endOfWeek()->format('d M Y'),
                'sales_count' => '128',
                'new_customers' => '14',
                'report_summary' => $this->summaryTable([
                    ['name' => 'Main Street Store', 'sales' => 96, 'revenue' => '245,800.00 LKR', 'customers' => 9],
                    ['name' => 'Online Shop', 'sales' => 32, 'revenue' => '61,250.00 LKR', 'customers' => 5],
                ]),
                'dashboard_url' => route('dashboard'),
            ],
            AdminAutomatedEmail::NEW_RELEASE => $release
                ? $this->releaseVars($release)
                : [
                    'release_app' => 'Zeebroo POS',
                    'release_version' => 'v5.1.0',
                    'release_date' => now()->format('d M Y'),
                    'release_notes' => $this->notesList(['Faster checkout and receipt printing', 'New sales dashboard', 'Bug fixes and stability improvements']),
                    'download_url' => route('home'),
                ],
            default => [],
        };
    }

    /**
     * Per-email values for one real recipient. Null means "don't send".
     */
    private function contextVars(string $key, User $user, array $context): ?array
    {
        return match ($key) {
            AdminAutomatedEmail::WELCOME => ['dashboard_url' => route('dashboard')],
            AdminAutomatedEmail::EMAIL_VERIFICATION, AdminAutomatedEmail::PASSWORD_RESET => $context,
            AdminAutomatedEmail::INACTIVITY => [
                'days_inactive' => (string) max(1, (int) ($user->last_seen_at ?? $user->created_at)?->diffInDays(now())),
                'login_url' => route('login'),
            ],
            AdminAutomatedEmail::REPORT => $this->reportVars($user, CarbonImmutable::parse($context['from']), CarbonImmutable::parse($context['to'])),
            AdminAutomatedEmail::NEW_RELEASE => ($release = AppRelease::find($context['release_id'] ?? 0)) ? $this->releaseVars($release) : null,
            default => null,
        };
    }

    private function reportVars(User $user, CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $businesses = Business::query()->where('user_id', $user->id)->orderBy('name')->get();
        if ($businesses->isEmpty()) {
            return null;
        }

        $ids = $businesses->pluck('id');

        $sales = DB::table('pos_sales')
            ->whereIn('business_id', $ids)
            ->where('status', 'completed')
            ->where('sold_at', '>=', $from)
            ->where('sold_at', '<', $to)
            ->groupBy('business_id')
            ->selectRaw('business_id, COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue')
            ->get()->keyBy('business_id');

        $customers = DB::table('pos_customers')
            ->whereIn('business_id', $ids)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->groupBy('business_id')
            ->selectRaw('business_id, COUNT(*) as cnt')
            ->pluck('cnt', 'business_id');

        $salesCount = (int) $sales->sum('cnt');
        $newCustomers = (int) $customers->sum();

        if ($salesCount === 0 && $newCustomers === 0 && $this->get(AdminAutomatedEmail::REPORT)->setting('skip_empty', true)) {
            return null;
        }

        $rows = $businesses->map(fn (Business $b) => [
            'name' => $b->name,
            'sales' => (int) ($sales[$b->id]->cnt ?? 0),
            'revenue' => trim(number_format((float) ($sales[$b->id]->revenue ?? 0), 2).' '.(string) $b->getSetting('business.currency', '')),
            'customers' => (int) ($customers[$b->id] ?? 0),
        ])->all();

        $lastDay = $to->subDay();

        return [
            'period_label' => $from->isSameDay($lastDay)
                ? $from->format('D, d M Y')
                : $from->format('d M').' – '.$lastDay->format('d M Y'),
            'sales_count' => number_format($salesCount),
            'new_customers' => number_format($newCustomers),
            'report_summary' => $this->summaryTable($rows),
            'dashboard_url' => route('dashboard'),
        ];
    }

    private function releaseVars(AppRelease $release): array
    {
        return [
            'release_app' => AppRelease::APPS[$release->app] ?? 'Zeebroo POS',
            'release_version' => 'v'.ltrim($release->version, 'v'),
            'release_date' => $release->release_date?->format('d M Y') ?? now()->format('d M Y'),
            'release_notes' => $this->notesList((array) $release->notes),
            'download_url' => $release->windows_url ?: ($release->macos_url ?: ($release->linux_url ?: route('home'))),
        ];
    }

    /** @param  array<int, array{name: string, sales: int, revenue: string, customers: int}>  $rows */
    private function summaryTable(array $rows): string
    {
        $th = 'padding:10px 12px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;';
        $td = 'padding:10px 12px;font-size:13px;color:#0f172a;border-bottom:1px solid #f1f5f9;';

        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;border-collapse:collapse;">'
            .'<tr><th align="left" style="'.$th.'">Business</th><th align="right" style="'.$th.'">Sales</th><th align="right" style="'.$th.'">Revenue</th><th align="right" style="'.$th.'">New customers</th></tr>';

        foreach ($rows as $r) {
            $html .= '<tr><td style="'.$td.'font-weight:600;">'.e($r['name']).'</td>'
                .'<td align="right" style="'.$td.'">'.e(number_format($r['sales'])).'</td>'
                .'<td align="right" style="'.$td.'white-space:nowrap;">'.e($r['revenue']).'</td>'
                .'<td align="right" style="'.$td.'">'.e(number_format($r['customers'])).'</td></tr>';
        }

        return $html.'</table>';
    }

    /** @param  array<int, string>  $notes */
    private function notesList(array $notes): string
    {
        $notes = array_values(array_filter(array_map('trim', $notes)));
        if ($notes === []) {
            return '';
        }

        return '<ul style="margin:0 0 16px;padding-left:20px;color:#475569;">'
            .implode('', array_map(fn ($n) => '<li style="margin-bottom:6px;">'.e($n).'</li>', $notes))
            .'</ul>';
    }

    private function render(string $subject, string $body, User $user, array $vars, bool $marketing): AdminMarketingMail
    {
        $vars += [
            'name' => (string) $user->name,
            'first_name' => strtok((string) $user->name, ' ') ?: '',
            'email' => (string) $user->email,
            'app_name' => (string) config('app.name'),
        ];

        return new AdminMarketingMail(
            $this->fill($subject, $vars, false),
            $this->fill($body, $vars, true),
            $marketing ? $this->marketing->unsubscribeUrl($user) : null,
        );
    }

    /**
     * Replace {{ tag }} merge tags (HTMLPurifier percent-encodes braces inside hrefs, so
     * the encoded form is matched too). Unknown tags are left as-is.
     */
    private function fill(string $text, array $vars, bool $html): string
    {
        return preg_replace_callback(
            '/(?:\{\{|%7B%7B)\s*([a-z_]+)\s*(?:\}\}|%7D%7D)/i',
            function ($m) use ($vars, $html) {
                $tag = strtolower($m[1]);
                if (! array_key_exists($tag, $vars)) {
                    return $m[0];
                }
                $value = (string) $vars[$tag];

                if (! $html) {
                    return strip_tags($value);
                }

                return in_array($tag, self::HTML_TAGS, true) ? $value : e($value);
            },
            $text,
        );
    }

    private function log(string $key, User $user, string $subject, bool $success, ?string $error = null): void
    {
        AdminAutomatedEmailLog::create([
            'key' => $key,
            'user_id' => $user->id,
            'email' => $user->email,
            'subject' => mb_substr($subject, 0, 255),
            'status' => $success ? AdminAutomatedEmailLog::STATUS_SENT : AdminAutomatedEmailLog::STATUS_FAILED,
            'error' => $success ? null : mb_substr((string) $error, 0, 2000),
        ]);
    }
}
