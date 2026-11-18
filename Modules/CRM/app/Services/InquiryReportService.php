<?php

namespace Modules\CRM\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Mail\InquiryReportMail;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\InquiryReport;
use Modules\CRM\Models\Project;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Models\PosNotification;

/**
 * The daily inquiry status report: one per department per day, with every member's figures and
 * the department totals (InquiryStatusService). The schedule (crm:inquiry-reports) makes the
 * day's reports at the time set in CRM settings; then either
 *
 *   - they are emailed to management at once and the heads are told they can review them, or
 *   - (review first) they wait as drafts until a head checks them, adds a note and sends them.
 *
 * A head (or an admin) can also make / refresh a report on demand, add a review note before or
 * after it goes out, and send it again.
 */
class InquiryReportService
{
    public const NOTIFICATION_TYPE = 'crm_inquiry_report';

    public function __construct(
        private readonly InquiryStatusService $status,
        private readonly CrmSettingsService $settings,
        private readonly CrmOrgService $org,
    ) {}

    /** Departments that run a pipeline — the ones a report is made for. @return Collection<int, Department> */
    public function reportDepartments(Business $business): Collection
    {
        return Department::query()
            ->where('business_id', $business->id)
            ->whereIn('id', Project::query()->where('business_id', $business->id)->whereNotNull('department_id')->select('department_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Make (or refresh) the department's report for the day. A report already sent keeps the
     * figures that went out unless $refreshSent.
     */
    public function generate(Business $business, Department $department, string $date, bool $refreshSent = false): InquiryReport
    {
        $report = InquiryReport::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->whereDate('report_date', $date)
            ->first();

        if ($report !== null && $report->status === InquiryReport::STATUS_SENT && ! $refreshSent) {
            return $report;
        }

        $values = [
            'data'         => $this->status->forDepartment($business, $department, $date),
            'generated_at' => now(),
        ];

        if ($report === null) {
            return InquiryReport::create([
                'business_id'   => $business->id,
                'department_id' => $department->id,
                'report_date'   => $date,
                'status'        => InquiryReport::STATUS_DRAFT,
            ] + $values);
        }

        $report->update($values);

        return $report;
    }

    /** Who the report goes to: the CRM setting's list, else the business owner. @return string[] */
    public function recipients(Business $business): array
    {
        $list = $this->settings->inquiryReport($business)['recipients'];
        if ($list === []) {
            $owner = $business->user()->value('email');
            $list  = $owner ? [$owner] : [];
        }

        return $list;
    }

    /** Email the report to management. Marks it sent, or failed with the error. */
    public function send(Business $business, InquiryReport $report, ?int $byUserId): InquiryReport
    {
        $recipients = $this->recipients($business);
        if ($recipients === []) {
            throw ValidationException::withMessages(['recipients' => 'Add the management email addresses in Daily Inquiries → Settings first.']);
        }

        try {
            Mail::to($recipients)->send(new InquiryReportMail($report, (string) $business->name));
        } catch (\Throwable $e) {
            Log::warning('CRM inquiry report could not be sent', ['report' => $report->id, 'error' => $e->getMessage()]);
            $report->update(['status' => InquiryReport::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'recipients' => $recipients]);

            throw ValidationException::withMessages(['report' => 'The email could not be sent: ' . $e->getMessage()]);
        }

        $report->update([
            'status'     => InquiryReport::STATUS_SENT,
            'recipients' => $recipients,
            'sent_by'    => $byUserId,
            'sent_at'    => now(),
            'error'      => null,
        ]);

        return $report;
    }

    /** The head's review: a note (shown in the email) and who checked it when. */
    public function review(InquiryReport $report, int $userId, ?string $note): InquiryReport
    {
        $report->update([
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_note' => filled($note) ? mb_substr(trim($note), 0, 5000) : null,
        ]);

        return $report;
    }

    public function reportForBusiness(Business $business, int $id): InquiryReport
    {
        return InquiryReport::query()->where('business_id', $business->id)->findOrFail($id);
    }

    /**
     * Reports between two days, newest first.
     *
     * @param  int[]|null  $departmentIds  null = every department
     * @return Collection<int, InquiryReport>
     */
    public function list(Business $business, string $from, string $to, ?array $departmentIds): Collection
    {
        return InquiryReport::query()
            ->where('business_id', $business->id)
            ->whereDate('report_date', '>=', $from)
            ->whereDate('report_date', '<=', $to)
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('department_id', $departmentIds ?: [0]))
            ->with(['department:id,name', 'reviewedBy:id,name', 'sentBy:id,name'])
            ->orderByDesc('report_date')
            ->orderBy('department_id')
            ->limit(500)
            ->get();
    }

    public function present(InquiryReport $r, bool $withData = false): array
    {
        $totals = $r->data['totals'] ?? [];

        return [
            'id'           => $r->id,
            'report_date'  => $r->report_date?->toDateString(),
            'department'   => $r->department_id ? ['id' => (int) $r->department_id, 'name' => $r->department?->name ?? ($r->data['department']['name'] ?? '')] : null,
            'status'       => $r->status,
            'totals'       => $totals,
            'recipients'   => $r->recipients ?? [],
            'generated_at' => $r->generated_at?->toIso8601String(),
            'reviewed_by'  => $r->reviewed_by ? ['id' => (int) $r->reviewed_by, 'name' => $r->reviewedBy?->name ?? 'Deleted user'] : null,
            'reviewed_at'  => $r->reviewed_at?->toIso8601String(),
            'review_note'  => $r->review_note,
            'sent_by'      => $r->sent_by ? ['id' => (int) $r->sent_by, 'name' => $r->sentBy?->name ?? 'Deleted user'] : null,
            'sent_at'      => $r->sent_at?->toIso8601String(),
            'error'        => $r->error,
        ] + ($withData ? ['data' => $r->data] : []);
    }

    // ── Schedule ─────────────────────────────────────────────────────────

    /**
     * Every business whose report time has passed today and has not run yet: make each
     * department's report, then send it (or leave it for the head when review comes first).
     * Run every few minutes by crm:inquiry-reports.
     *
     * @return array{businesses:int, reports:int, sent:int, failed:int}
     */
    public function runScheduled(?CarbonInterface $now = null): array
    {
        $now    = Carbon::instance($now ?? now());
        $counts = ['businesses' => 0, 'reports' => 0, 'sent' => 0, 'failed' => 0];

        CrmSetting::query()->where('inquiry_report_enabled', true)->each(function (CrmSetting $setting) use ($now, &$counts) {
            $business = Business::find($setting->business_id);
            if ($business === null) {
                return;
            }

            $config = $this->settings->inquiryReport($business);
            $local  = $now->copy()->setTimezone($this->settings->timezone($business));
            $today  = $local->toDateString();
            if ($local->format('H:i') < $config['time'] || $setting->inquiry_report_last_run?->toDateString() === $today) {
                return;
            }

            $setting->forceFill(['inquiry_report_last_run' => $today])->save();
            $counts['businesses']++;

            foreach ($this->reportDepartments($business) as $department) {
                $report = $this->generate($business, $department, $today);
                $counts['reports']++;

                if (! $config['review'] && $report->status !== InquiryReport::STATUS_SENT) {
                    try {
                        $this->send($business, $report, null);
                        $counts['sent']++;
                    } catch (\Throwable) {
                        $counts['failed']++;
                    }
                }

                $this->notifyHeads($business, $department, $report->fresh(), $config['review']);
            }
        });

        return $counts;
    }

    /** Tell the department's heads the day's report is ready to review (or went out). */
    public function notifyHeads(Business $business, Department $department, InquiryReport $report, bool $awaitingReview): void
    {
        $date  = $report->report_date?->format('j M');
        $title = $awaitingReview
            ? "Daily inquiry report for {$department->name} ({$date}) is ready to review"
            : "Daily inquiry report for {$department->name} ({$date}) was sent to management";
        $message = $awaitingReview
            ? 'Check it, add a note if needed, and send it from CRM → Daily Inquiries → Reports.'
            : ($report->status === InquiryReport::STATUS_SENT
                ? 'You can review it and add a note in CRM → Daily Inquiries → Reports.'
                : 'It could not be emailed — open CRM → Daily Inquiries → Reports to send it again.');

        foreach ($this->org->headUserIds($department) as $userId) {
            PosNotification::query()->updateOrCreate(
                [
                    'business_id'    => $business->id,
                    'user_id'        => $userId,
                    'type'           => self::NOTIFICATION_TYPE,
                    'reference_type' => 'crm_inquiry_report',
                    'reference_id'   => $report->id,
                ],
                [
                    'branch_id'    => null,
                    'title'        => mb_substr($title, 0, 255),
                    'message'      => $message,
                    'payload'      => ['report_id' => (int) $report->id, 'department_id' => (int) $department->id],
                    'action_url'   => '/crm/inquiries',
                    'read_at'      => null,
                    'dismissed_at' => null,
                ],
            );
        }
    }
}
