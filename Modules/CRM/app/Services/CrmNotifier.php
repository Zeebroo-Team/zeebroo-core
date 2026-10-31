<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\CRM\Mail\CrmNotificationMail;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\UserPreference;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\Pos\Models\PosNotification;

/**
 * Tells people about the CRM work they must act on. DECISION: the in-app side reuses
 * pos_notifications (user_id + action_url) rather than Laravel's notifications table, because the
 * desktop already has the bell, list, read / unread and dismiss built on it — CRM rows simply
 * show up there and deep-link to the lead from their payload. Each recipient also gets a queued
 * email unless they turned CRM emails off (crm_user_preferences).
 *
 * One row per recipient and record: notifying about the same lead again re-surfaces that row
 * (unread, on top) instead of stacking duplicates. Recipients outside the lead's business are
 * dropped, and so is whoever caused the event.
 */
class CrmNotifier
{
    const TYPE_LEAD_NEW             = 'crm_lead_new';
    const TYPE_LEAD_FORWARDED       = 'crm_lead_forwarded';
    const TYPE_LEAD_ASSIGNED        = 'crm_lead_assigned';
    const TYPE_QUOTATION_SUBMITTED  = 'crm_quotation_submitted';
    const TYPE_QUOTATION_DECIDED    = 'crm_quotation_decided';
    const TYPE_FOLLOW_UP_DUE        = 'crm_follow_up_due';
    const TYPE_FOLLOW_UP_OVERDUE    = 'crm_follow_up_overdue';
    const TYPE_LEAD_CLOSED          = 'crm_lead_closed';

    public function __construct(private readonly CrmOrgService $org) {}

    /**
     * @param  int[]  $userIds
     * @param  array<string, mixed>  $payload  added to the lead / project ids the desktop opens
     * @return int[] who was notified
     */
    public function notify(
        Lead $lead,
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?int $actorId = null,
        array $payload = [],
        string $referenceType = 'crm_lead',
        ?int $referenceId = null,
    ): array {
        $business   = $lead->business()->first();
        $recipients = $this->membersOf($business, array_filter(
            array_unique(array_map('intval', $userIds)),
            fn (int $id) => $id > 0 && $id !== (int) $actorId,
        ));

        foreach ($recipients as $userId) {
            $notification = PosNotification::query()->updateOrCreate(
                [
                    'business_id'    => $business->id,
                    'user_id'        => $userId,
                    'type'           => $type,
                    'reference_type' => $referenceType,
                    'reference_id'   => $referenceId ?? $lead->id,
                ],
                [
                    'branch_id'    => null,
                    'title'        => mb_substr($title, 0, 255),
                    'message'      => $message,
                    'payload'      => ['lead_id' => (int) $lead->id, 'project_id' => (int) $lead->project_id] + $payload,
                    'action_url'   => '/crm/leads/' . $lead->id,
                    'read_at'      => null,
                    'dismissed_at' => null,
                ],
            );
            if (! $notification->wasRecentlyCreated) {
                $notification->forceFill(['created_at' => now()])->save();
            }

            $this->email($business, $userId, $title, $message);
        }

        return $recipients;
    }

    // ── Recipients ───────────────────────────────────────────────────────

    /** Marketing: the crm_marketing role plus the staff of the marketing intake department. @return int[] */
    public function marketingUserIds(Business $business): array
    {
        $ids = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('role', CrmAccess::ROLE_MARKETING)
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        $intake = $this->org->marketingIntakeDepartment($business);
        if ($intake !== null) {
            $ids = [...$ids, ...$this->org->memberUserIds($intake)];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Whoever heads the lead's division, else its department. */
    public function headUserIdForLead(Lead $lead): ?int
    {
        if ($lead->department_id === null) {
            return null;
        }
        $department = Department::query()->where('business_id', $lead->business_id)->find($lead->department_id);

        return $department === null ? null : $this->org->headUserId($department, $lead->division);
    }

    // ── Email preference ─────────────────────────────────────────────────

    public function emailEnabled(int $businessId, int $userId): bool
    {
        return UserPreference::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->value('email_notifications') ?? true;
    }

    public function setEmailEnabled(int $businessId, int $userId, bool $enabled): void
    {
        UserPreference::query()->updateOrCreate(
            ['business_id' => $businessId, 'user_id' => $userId],
            ['email_notifications' => $enabled],
        );
    }

    /**
     * Only users who belong to the business: its owner, its members and its HR staff with a login.
     *
     * @param  int[]  $userIds
     * @return int[]
     */
    private function membersOf(Business $business, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->pluck('user_id')
            ->merge(Employee::query()->where('business_id', $business->id)->whereIn('user_id', $userIds)->pluck('user_id'))
            ->push($business->user_id)
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($userIds, $members));
    }

    private function email(Business $business, int $userId, string $title, string $message): void
    {
        if (! $this->emailEnabled($business->id, $userId)) {
            return;
        }
        $email = User::query()->whereKey($userId)->value('email');
        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->queue(new CrmNotificationMail($title, $message, (string) $business->name));
        } catch (\Throwable $e) {
            Log::warning('CRM notification email could not be queued: ' . $e->getMessage(), ['user_id' => $userId]);
        }
    }
}
