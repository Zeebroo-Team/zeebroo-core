<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Auth\Models\AffiliateDocument;
use Modules\Auth\Models\AffiliateProfile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Affiliate settings: profile (name, email, password), contact and address,
 * payout bank account, and ID card or passport copies.
 *
 * ID copies live on the private "local" disk and are only ever streamed
 * through routes that check the viewer is the affiliate or an admin.
 */
class AffiliateProfileService
{
    public const DISK = 'local';

    public const DOCUMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public const DOCUMENT_MAX_KB = 5120;

    public const TABS = ['profile', 'contact', 'payout', 'identity'];

    public function profileFor(User $user): AffiliateProfile
    {
        return AffiliateProfile::query()->firstOrNew(['user_id' => $user->id]);
    }

    /** @return Collection<string, AffiliateDocument> keyed by type */
    public function documentsFor(User $user): Collection
    {
        return AffiliateDocument::query()->where('user_id', $user->id)->get()->keyBy('type');
    }

    /**
     * Which tabs are complete, for the check marks on the tab bar.
     *
     * @return array{profile: bool, contact: bool, payout: bool, identity: bool}
     */
    public function completion(User $user, ?AffiliateProfile $profile = null, ?Collection $documents = null): array
    {
        $profile ??= $this->profileFor($user);
        $documents ??= $this->documentsFor($user);

        $requiredDocs = array_keys(AffiliateDocument::SLOTS[$profile->id_type] ?? []);

        return [
            'profile' => filled($user->name) && filled($user->email),
            'contact' => $profile->hasContact(),
            'payout' => $profile->hasPayout(),
            'identity' => filled($profile->id_type) && filled($profile->id_number)
                && $requiredDocs !== [] && collect($requiredDocs)->every(fn ($type) => $documents->has($type)),
        ];
    }

    /** @param  array{name: string, email: string, password?: ?string}  $data */
    public function updateAccount(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
    }

    public function updateContact(User $user, array $data): AffiliateProfile
    {
        return $this->saveProfile($user, $this->only($data, [
            'phone', 'phone_alt', 'address_line1', 'address_line2', 'city', 'district', 'postal_code', 'country',
        ]));
    }

    public function updatePayout(User $user, array $data): AffiliateProfile
    {
        $fields = $this->only($data, ['bank_name', 'bank_branch', 'account_holder_name', 'account_number']);
        $fields['account_number'] = preg_replace('/\s+/', '', (string) $fields['account_number']) ?: null;

        return $this->saveProfile($user, $fields);
    }

    /**
     * Saves the ID type and number plus any uploaded copies. Switching the ID
     * type removes the copies of the other type, so no stale ID is kept.
     *
     * @param  array<string, UploadedFile|null>  $files  keyed by document type
     */
    public function updateIdentity(User $user, string $idType, string $idNumber, array $files): AffiliateProfile
    {
        $profile = $this->saveProfile($user, [
            'id_type' => $idType,
            'id_number' => strtoupper(trim($idNumber)),
        ]);

        $allowed = array_keys(AffiliateDocument::SLOTS[$idType] ?? []);

        AffiliateDocument::query()
            ->where('user_id', $user->id)
            ->whereNotIn('type', $allowed)
            ->get()
            ->each(fn (AffiliateDocument $doc) => $this->removeDocument($doc));

        foreach ($files as $type => $file) {
            if ($file instanceof UploadedFile && in_array($type, $allowed, true)) {
                $this->storeDocument($user, $type, $file);
            }
        }

        return $profile;
    }

    public function storeDocument(User $user, string $type, UploadedFile $file): AffiliateDocument
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs(
            'affiliate-documents/'.$user->id,
            $type.'-'.Str::random(24).'.'.$extension,
            self::DISK,
        );

        $existing = AffiliateDocument::query()->where('user_id', $user->id)->where('type', $type)->first();
        $oldPath = $existing?->path;

        $document = AffiliateDocument::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            [
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'size_bytes' => (int) $file->getSize(),
            ],
        );

        if ($oldPath && $oldPath !== $path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        return $document;
    }

    public function deleteDocument(User $user, string $type): bool
    {
        $document = AffiliateDocument::query()->where('user_id', $user->id)->where('type', $type)->first();
        if ($document === null) {
            return false;
        }

        $this->removeDocument($document);

        return true;
    }

    /** Streams a stored copy inline (image preview or PDF), never cached. */
    public function documentResponse(User $user, string $type): StreamedResponse
    {
        $document = AffiliateDocument::query()->where('user_id', $user->id)->where('type', $type)->first();
        abort_if($document === null || ! Storage::disk(self::DISK)->exists($document->path), 404);

        $filename = Str::slug($user->name ?: 'affiliate').'-'.$document->type.'.'.pathinfo($document->path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->response($document->path, $filename, [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function removeDocument(AffiliateDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
    }

    private function saveProfile(User $user, array $fields): AffiliateProfile
    {
        return DB::transaction(function () use ($user, $fields): AffiliateProfile {
            $profile = $this->profileFor($user);
            $profile->fill($fields);
            $profile->save();

            return $profile;
        });
    }

    /** Trimmed values for the given keys; empty strings become null. */
    private function only(array $data, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
            $out[$key] = $value === '' ? null : $value;
        }

        return $out;
    }
}
