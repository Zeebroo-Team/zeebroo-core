<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\CRM\Models\UserPreference;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use Modules\HRManagement\Models\Employee;

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
            // Data URI of the signature image printed in "Approved by" (quotations they approve), or null
            'signature'   => $this->signatureDataUri($businessId, $userId),
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
