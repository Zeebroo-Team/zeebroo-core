<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Modules\CRM\Models\UserPreference;
use Modules\HRManagement\Models\Employee;

/**
 * A user's CRM profile in one business: the name, designation and contact number printed as
 * "Prepared by" on their quotations. What they saved themselves (crm_user_preferences) wins;
 * anything left empty comes from their HR employee record (full name, job title, phone), and
 * the name finally from their login.
 */
class CrmProfileService
{
    /**
     * The profile as it prints, plus what the user saved and what HR has on file.
     *
     * @return array{name: string, designation: ?string, phone: ?string, saved: array{name: ?string, designation: ?string, phone: ?string}, hr: array{name: ?string, designation: ?string, phone: ?string}}
     */
    public function get(int $businessId, int $userId): array
    {
        $pref = UserPreference::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        $saved = [
            'name'        => $this->clean($pref?->display_name),
            'designation' => $this->clean($pref?->designation),
            'phone'       => $this->clean($pref?->phone),
        ];

        $employee = Employee::query()->with('jobTitle:id,name')
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->first();
        $hr = [
            'name'        => $this->clean($employee?->full_name),
            'designation' => $this->clean($employee?->jobTitle?->name),
            'phone'       => $this->clean($employee?->phone_number),
        ];

        return [
            'name'        => $saved['name'] ?? $hr['name'] ?? (string) (User::query()->whereKey($userId)->value('name') ?? ''),
            'designation' => $saved['designation'] ?? $hr['designation'],
            'phone'       => $saved['phone'] ?? $hr['phone'],
            'saved'       => $saved,
            'hr'          => $hr,
        ];
    }

    /**
     * Save the user's own values; an empty one goes back to the HR / login default.
     *
     * @param  array{name?: ?string, designation?: ?string, phone?: ?string}  $data
     */
    public function save(int $businessId, int $userId, array $data): array
    {
        $map = ['name' => 'display_name', 'designation' => 'designation', 'phone' => 'phone'];
        $values = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $values[$column] = $this->clean($data[$key]);
            }
        }
        if ($values !== []) {
            UserPreference::query()->updateOrCreate(['business_id' => $businessId, 'user_id' => $userId], $values);
        }

        return $this->get($businessId, $userId);
    }

    /**
     * The quotation columns for a quotation this user prepares — the profile as it is now.
     *
     * @return array{prepared_by: int, prepared_by_name: ?string, prepared_by_designation: ?string, prepared_by_phone: ?string}
     */
    public function quotationFields(int $businessId, int $userId): array
    {
        $p = $this->get($businessId, $userId);

        return [
            'prepared_by'             => $userId,
            'prepared_by_name'        => $this->clean($p['name']),
            'prepared_by_designation' => $p['designation'],
            'prepared_by_phone'       => $p['phone'],
        ];
    }

    private function clean(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}
