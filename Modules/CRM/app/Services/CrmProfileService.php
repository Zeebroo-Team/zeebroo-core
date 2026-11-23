<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\CRM\Models\UserPreference;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Services\EmployeeService;

/**
 * A user's CRM profile in one business: the name, designation and contact number printed as
 * "Prepared by" on their quotations. What they saved themselves (crm_user_preferences) wins;
 * anything left empty comes from their HR employee record (full name, job title, phone), and
 * the name finally from their login.
 */
class CrmProfileService
{
    /** Signature images: png / jpg up to 1 MB, kept privately. */
    public const SIGNATURE_DISK = 'local';

    /** File-manager owner tag: listed and opened there by business admins only. */
    public const SIGNATURE_MANAGED_BY = 'crm-signature';

    public function __construct(private readonly FileManagerService $files) {}

    /** @return list<string> validation rules for an uploaded signature */
    public static function signatureRules(): array
    {
        return ['file', 'mimes:png,jpg,jpeg', 'max:1024'];
    }

    /**
     * The profile as it prints, plus what the user saved and what HR has on file.
     *
     * @return array{name: string, designation: ?string, phone: ?string, signature: ?string, saved: array{name: ?string, designation: ?string, phone: ?string}, hr: array{name: ?string, designation: ?string, phone: ?string}}
     */
    public function get(int $businessId, int $userId): array
    {
        $pref = UserPreference::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        $saved = [
            'name'        => $this->clean($pref?->display_name),
            'designation' => $this->clean($pref?->designation),
            'phone'       => $this->clean($pref?->phone),
        ];

        $employee = Employee::query()->with(['jobTitle:id,name', 'department:id,name'])
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->first();
        $hr = [
            'name'        => $this->clean($employee?->full_name),
            'designation' => $this->clean($employee?->jobTitle?->name),
            'phone'       => $this->clean($employee?->phone_number),
        ];

        $loginName = (string) (User::query()->whereKey($userId)->value('name') ?? '');

        return [
            'name'        => $saved['name'] ?? $hr['name'] ?? $loginName,
            'login_name'  => $loginName,
            'designation' => $saved['designation'] ?? $hr['designation'],
            'phone'       => $saved['phone'] ?? $hr['phone'],
            // Data URI of the signature image printed in "Approved by" (quotations they approve), or null
            'signature'   => $this->signatureDataUri($businessId, $userId),
            'saved'       => $saved,
            'hr'          => $hr,
            // The HR record this profile is linked to (null when the login has no employee record)
            'employee'    => $employee ? $this->employeeLink($businessId, $employee) : null,
        ];
    }

    /**
     * Where the person sits in HR: employee no., department, position and the departments they
     * head / co-head — the same data HR → Employees and Settings → Organisation show.
     *
     * @return array{id: int, employee_id: ?string, department: ?string, job_title: ?string, employment_type: ?string, email: ?string, leads: list<array{role: string, department: string}>}
     */
    private function employeeLink(int $businessId, Employee $employee): array
    {
        $leads = Department::query()->where('business_id', $businessId)
            ->where(fn ($q) => $q->where('head_employee_id', $employee->id)->orWhere('co_head_employee_id', $employee->id))
            ->orderBy('name')->get(['name', 'head_employee_id', 'co_head_employee_id'])
            ->map(fn (Department $d) => [
                'role'       => (int) $d->head_employee_id === (int) $employee->id ? 'head' : 'co_head',
                'department' => (string) $d->name,
            ])->values()->all();

        return [
            'id'              => (int) $employee->id,
            'employee_id'     => $employee->employee_id,
            'department'      => $employee->department?->name,
            'job_title'       => $employee->jobTitle?->name,
            'employment_type' => $employee->employment_type,
            'email'           => $employee->personal_email,
            'leads'           => $leads,
        ];
    }

    /**
     * The business's HR positions (HR → Positions) — the designation dropdown on the profile
     * and on a quotation's "Prepared by". A custom designation can still be typed.
     *
     * @return list<string>
     */
    public function positions(int $businessId): array
    {
        return JobTitle::query()->where('business_id', $businessId)
            ->where('name', '!=', 'Unspecified')
            ->orderBy('name')->pluck('name')->map(fn ($n) => (string) $n)->values()->all();
    }

    /** Whether the user is the business owner or one of its members (whose profile an admin may manage). */
    public function belongsToBusiness(Business $business, int $userId): bool
    {
        return (int) $business->user_id === $userId
            || BusinessMember::query()->where('business_id', $business->id)->where('user_id', $userId)->exists();
    }

    /**
     * Every person of the business (owner + members) with their profile as it prints — the
     * admin's "All profiles" list. Bulk-loaded: one query each for users, preferences and HR records.
     *
     * @return list<array{user_id: int, name: string, email: ?string, designation: ?string, phone: ?string, department: ?string, employee_id: ?string, has_signature: bool, customized: bool}>
     */
    public function listForBusiness(Business $business): array
    {
        $userIds = BusinessMember::query()->where('business_id', $business->id)->pluck('user_id')
            ->push($business->user_id)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');
        $prefs = UserPreference::query()->where('business_id', $business->id)->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $employees = Employee::query()->with(['jobTitle:id,name', 'department:id,name'])
            ->where('business_id', $business->id)->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        // employee id => "Head · Sales", "Co-head · Marketing"
        $leads = [];
        foreach (Department::query()->where('business_id', $business->id)->orderBy('name')
            ->get(['name', 'head_employee_id', 'co_head_employee_id']) as $d) {
            if ($d->head_employee_id) {
                $leads[(int) $d->head_employee_id][] = 'Head · ' . $d->name;
            }
            if ($d->co_head_employee_id) {
                $leads[(int) $d->co_head_employee_id][] = 'Co-head · ' . $d->name;
            }
        }

        $rows = [];
        foreach ($userIds as $id) {
            $user = $users->get($id);
            if ($user === null) {
                continue;
            }
            $pref = $prefs->get($id);
            $emp  = $employees->get($id);
            $rows[] = [
                'user_id'       => $id,
                'name'          => $this->clean($pref?->display_name) ?? $this->clean($emp?->full_name) ?? (string) $user->name,
                'email'         => $user->email,
                'designation'   => $this->clean($pref?->designation) ?? $this->clean($emp?->jobTitle?->name),
                'phone'         => $this->clean($pref?->phone) ?? $this->clean($emp?->phone_number),
                'department'    => $emp?->department?->name,
                'employee_id'   => $emp?->employee_id,
                'has_employee'  => $emp !== null,
                'leads'         => $emp ? ($leads[(int) $emp->id] ?? []) : [],
                'is_owner'      => $id === (int) $business->user_id,
                'has_signature' => (bool) $pref?->signature_path,
                'customized'    => $pref !== null && ($pref->display_name || $pref->designation || $pref->phone),
            ];
        }
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Save the profile. For a person with an HR employee record the values are written to that
     * record (name → full name, designation → position, phone → phone number) and the name to
     * their login too, so CRM, HR → Employees and Settings → Users all show the same data. A
     * designation that is not one of the HR positions stays a quotation-only override. Without an
     * HR record the values are kept as CRM overrides; an empty one goes back to the HR / login default.
     *
     * @param  array{name?: ?string, designation?: ?string, phone?: ?string}  $data
     */
    public function save(int $businessId, int $userId, array $data): array
    {
        $employee = Employee::query()->where('business_id', $businessId)->where('user_id', $userId)->first();

        return DB::transaction(function () use ($businessId, $userId, $data, $employee) {
            if ($employee !== null) {
                $data = $this->pushToHr($businessId, $userId, $employee, $data);
            } elseif (array_key_exists('name', $data) && ($name = $this->clean($data['name'])) !== null) {
                // No HR record: the name is their login name (Settings → Users shows it)
                User::query()->whereKey($userId)->update(['name' => mb_substr($name, 0, 255)]);
            }

            return $this->saveOverrides($businessId, $userId, $data);
        });
    }

    /**
     * Write name / position / phone onto the HR employee (and the name onto the login). Returns
     * what is left to keep as a CRM override: the fields HR took are cleared so they follow HR.
     *
     * @param  array{name?: ?string, designation?: ?string, phone?: ?string}  $data
     * @return array{name?: ?string, designation?: ?string, phone?: ?string}
     */
    private function pushToHr(int $businessId, int $userId, Employee $employee, array $data): array
    {
        $hr = [];

        if (array_key_exists('name', $data) && ($name = $this->clean($data['name'])) !== null) {
            $hr['full_name'] = mb_substr($name, 0, 255);
            User::query()->whereKey($userId)->update(['name' => $hr['full_name']]);
            $data['name'] = null;
        }

        if (array_key_exists('phone', $data) && ($phone = $this->clean($data['phone'])) !== null) {
            $hr['phone_number'] = mb_substr($phone, 0, 40);
            $data['phone'] = null;
        }

        if (array_key_exists('designation', $data) && ($designation = $this->clean($data['designation'])) !== null) {
            $jobTitleId = JobTitle::query()->where('business_id', $businessId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($designation)])
                ->value('id');
            if ($jobTitleId !== null) {
                $hr['job_title_id'] = (int) $jobTitleId;
                $data['designation'] = null;
            }
            // Not an HR position: kept as a custom designation for quotations only
        }

        if ($hr !== []) {
            $employee->forceFill($hr)->save();
            app(EmployeeService::class)->markCompleteWhenFilled($employee);
        }

        return $data;
    }

    /**
     * Save the user's own CRM overrides; an empty one goes back to the HR / login default.
     *
     * @param  array{name?: ?string, designation?: ?string, phone?: ?string}  $data
     */
    private function saveOverrides(int $businessId, int $userId, array $data): array
    {
        $map = ['name' => 'display_name', 'designation' => 'designation', 'phone' => 'phone'];
        // A value equal to what HR (or, for the name, the login) already has is not an override:
        // it is stored empty so the profile keeps following the HR record when HR changes it.
        $current  = $this->get($businessId, $userId);
        $defaults = $current['hr'];
        $defaults['name'] ??= $current['login_name'];
        $values = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $v = $this->clean($data[$key]);
                $values[$column] = ($v !== null && $v === $this->clean($defaults[$key] ?? null)) ? null : $v;
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

    /**
     * Save the user's signature image (replaces the one on file). Kept on the private "local"
     * disk and handed out as a data URI, so dompdf and the desktop print window need no fetch.
     */
    public function saveSignature(int $businessId, int $userId, UploadedFile $file): ?string
    {
        $pref = UserPreference::query()->firstOrNew(['business_id' => $businessId, 'user_id' => $userId]);
        $old  = $pref->signature_path;

        // Through the file manager, in "CRM / Signatures", private and admin-only there
        $business = Business::query()->findOrFail($businessId);
        $folder   = $this->files->ensureFolderPath($business, FileManagerService::ADMIN_ONLY_FOLDER);
        $userName = (string) (User::query()->whereKey($userId)->value('name') ?? ('User #' . $userId));
        $stored   = $this->files->storeFile($business, $file, $folder?->id, $userId,
            'Signature of ' . mb_substr($userName, 0, 150), self::SIGNATURE_DISK, self::SIGNATURE_MANAGED_BY);

        $pref->signature_path = $stored->stored_path;
        $pref->save();

        if ($old && $old !== $stored->stored_path) {
            $this->deleteSignatureFile($businessId, $old);
        }

        return $this->signatureDataUri($businessId, $userId);
    }

    public function removeSignature(int $businessId, int $userId): void
    {
        $pref = UserPreference::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        if ($pref?->signature_path) {
            $this->deleteSignatureFile($businessId, $pref->signature_path);
            $pref->update(['signature_path' => null]);
        }
    }

    /** The file and its file-manager row (older signatures were stored without one). */
    private function deleteSignatureFile(int $businessId, string $path): void
    {
        $row = FileManagerFile::query()->where('business_id', $businessId)->where('stored_path', $path)->first();
        $row ? $this->files->deleteFile($row) : Storage::disk(self::SIGNATURE_DISK)->delete($path);
    }

    /** The user's signature as a data URI, or null when none is on file. */
    public function signatureDataUri(int $businessId, ?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }
        $path = UserPreference::query()->where('business_id', $businessId)->where('user_id', $userId)->value('signature_path');
        $disk = Storage::disk(self::SIGNATURE_DISK);
        if (! $path || ! $disk->exists($path)) {
            return null;
        }
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode((string) $disk->get($path));
    }

    private function clean(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}
