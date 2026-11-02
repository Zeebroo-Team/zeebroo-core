<?php

namespace Modules\CRM\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmAuditLog;

/**
 * Writes and reads crm_audit_logs — the raw before/after of every audited CRM record (see the
 * Auditable trait). Only admins read it (CrmAuditLogController / the desktop audit viewer).
 */
class CrmAuditService
{
    public function record(Model $model, string $event, array $old, array $new): void
    {
        if (! $model->getAttribute('business_id')) {
            return;
        }

        [$userId, $ip] = $this->context();

        CrmAuditLog::create([
            'business_id'  => $model->getAttribute('business_id'),
            'user_id'      => $userId,
            'subject_type' => $model->getMorphClass(),
            'subject_id'   => $model->getKey(),
            'event'        => $event,
            'old'          => $old ?: null,
            'new'          => $new ?: null,
            'ip'           => $ip,
        ]);
    }

    /**
     * @param  array{subject?:string|null, subject_id?:int|null, event?:string|null, user_id?:int|null, from?:string|null, to?:string|null}  $filters
     */
    public function list(Business $business, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $subject = CrmAuditLog::SUBJECTS[$filters['subject'] ?? ''][0] ?? null;

        return CrmAuditLog::query()
            ->where('business_id', $business->id)
            ->with('user:id,name')
            ->when($subject !== null, fn ($q) => $q->where('subject_type', $subject))
            ->when(filled($filters['subject_id'] ?? null), fn ($q) => $q->where('subject_id', (int) $filters['subject_id']))
            ->when(filled($filters['event'] ?? null), fn ($q) => $q->where('event', $filters['event']))
            ->when(filled($filters['user_id'] ?? null), fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Users who appear in the business's audit log — feeds the "changed by" filter. */
    public function actors(Business $business): Collection
    {
        return \App\Models\User::query()
            ->whereIn('id', CrmAuditLog::query()->where('business_id', $business->id)->whereNotNull('user_id')->distinct()->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, mixed> */
    public function present(CrmAuditLog $log): array
    {
        $key     = CrmAuditLog::subjectKey($log->subject_type);
        $old     = $log->old ?? [];
        $new     = $log->new ?? [];
        $changes = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
            $changes[] = ['field' => $field, 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null];
        }

        return [
            'id'            => $log->id,
            'event'         => $log->event,
            'event_label'   => CrmAuditLog::events()[$log->event] ?? ucfirst($log->event),
            'subject'       => $key ?? class_basename($log->subject_type),
            'subject_label' => $key !== null ? CrmAuditLog::SUBJECTS[$key][1] : class_basename($log->subject_type),
            'subject_id'    => $log->subject_id,
            'user'          => $log->user_id ? ['id' => $log->user_id, 'name' => $log->user?->name ?? 'Deleted user'] : null,
            'ip'            => $log->ip,
            'changes'       => $changes,
            'created_at'    => $log->created_at?->toIso8601String(),
        ];
    }

    /** @return array{0: int|null, 1: string|null} the acting user (not a POS cashier) and IP */
    private function context(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return [null, null];
        }

        $request = request();
        // The guard in use (web session, or Sanctum on the desktop API); a cashier token is no user
        $user    = auth()->user() ?? $request->user();

        return [$user instanceof \App\Models\User ? (int) $user->id : null, $request->ip()];
    }
}
