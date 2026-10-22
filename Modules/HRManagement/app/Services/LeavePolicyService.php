<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeavePolicy;

/**
 * CRUD for business leave rules. Exactly one rule per business is the default once any exist:
 * the first rule becomes default automatically, and deleting the default promotes the oldest remaining one.
 */
final class LeavePolicyService
{
    /** @return Collection<int, LeavePolicy> */
    public function list(Business $business): Collection
    {
        return LeavePolicy::where('business_id', $business->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function defaultFor(Business $business): ?LeavePolicy
    {
        if (! Schema::hasTable('hr_leave_policies')) {
            return null;
        }

        return LeavePolicy::where('business_id', $business->id)->where('is_default', true)->first();
    }

    /**
     * The rule that applies to an employee: their position's rule if one is set, otherwise the business default.
     *
     * @return array{policy: ?LeavePolicy, source: 'position'|'default'|'none'}
     */
    public function resolveFor(Business $business, Employee $employee): array
    {
        $fromPosition = $employee->jobTitle?->leavePolicy;
        if ($fromPosition !== null) {
            return ['policy' => $fromPosition, 'source' => 'position'];
        }

        $default = $this->defaultFor($business);

        return ['policy' => $default, 'source' => $default ? 'default' : 'none'];
    }

    /** @param  array<string, mixed>  $data  already validated */
    public function create(Business $business, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($business, $data) {
            $isFirst = ! LeavePolicy::where('business_id', $business->id)->exists();
            $payload = $this->normalize($data);
            $payload['is_default'] = $isFirst || ! empty($data['is_default']);

            if ($payload['is_default']) {
                $this->clearDefault($business);
            }

            return LeavePolicy::create(['business_id' => $business->id] + $payload);
        });
    }

    /** @param  array<string, mixed>  $data  already validated */
    public function update(LeavePolicy $policy, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($policy, $data) {
            $payload = $this->normalize($data);
            // The default can only move by marking another rule default — unticking it here is ignored.
            $payload['is_default'] = $policy->is_default || ! empty($data['is_default']);

            if ($payload['is_default'] && ! $policy->is_default) {
                $this->clearDefault($policy->business);
            }

            $policy->update($payload);

            return $policy->refresh();
        });
    }

    public function delete(LeavePolicy $policy): void
    {
        DB::transaction(function () use ($policy) {
            $wasDefault = $policy->is_default;
            $businessId = $policy->business_id;
            $policy->delete();

            if ($wasDefault) {
                LeavePolicy::where('business_id', $businessId)->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $shortOn = (bool) ($data['short_leave_enabled'] ?? false);

        return [
            'name'                    => trim((string) $data['name']),
            'annual_leave_days'       => (int) $data['annual_leave_days'],
            'casual_leave_days'       => (int) $data['casual_leave_days'],
            'sick_leave_days'         => isset($data['sick_leave_days']) ? (int) $data['sick_leave_days'] : null,
            'short_leave_enabled'     => $shortOn,
            'short_leaves_per_month'  => $shortOn ? (int) $data['short_leaves_per_month'] : null,
            'short_leave_max_minutes' => $shortOn ? (int) $data['short_leave_max_minutes'] : null,
            'half_day_allowed'        => (bool) ($data['half_day_allowed'] ?? false),
            'carry_forward_max_days'  => ! empty($data['carry_forward_max_days']) ? (int) $data['carry_forward_max_days'] : null,
            'eligible_after_months'   => (int) ($data['eligible_after_months'] ?? 0),
            'notes'                   => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];
    }

    private function clearDefault(Business $business): void
    {
        LeavePolicy::where('business_id', $business->id)->where('is_default', true)->update(['is_default' => false]);
    }
}
