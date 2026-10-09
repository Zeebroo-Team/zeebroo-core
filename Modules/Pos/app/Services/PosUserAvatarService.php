<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Profile photo for the signed-in user ("My Profile" modal in pos-desktop).
 * Files live on the `public` disk under user-avatars/{userId}/ and are served
 * through PosMediaApiController so the response carries CORS headers.
 */
class PosUserAvatarService
{
    public const DIR = 'user-avatars';

    /** Stores the new photo, removes the previous one, and returns the public URL. */
    public function upload(User $user, UploadedFile $file): string
    {
        $oldPath = $user->avatar_path;

        $path = $file->store(self::DIR.'/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        $this->deleteFile($oldPath);

        return (string) $this->url($user);
    }

    public function remove(User $user): void
    {
        $this->deleteFile($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();
    }

    public function url(User $user): ?string
    {
        return $user->avatarUrl();
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
