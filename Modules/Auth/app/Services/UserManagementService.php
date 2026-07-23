<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserManagementService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->withCount(['businesses', 'accounts'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$data['role']]);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return $user;
    }

    /**
     * True if demoting $user away from 'admin' would leave the app with zero admins.
     */
    public function wouldRemoveLastAdmin(User $user, string $newRole): bool
    {
        return $user->hasRole('admin') && $newRole !== 'admin' && User::role('admin')->count() <= 1;
    }

    /**
     * Returns a reason the user cannot be deleted, or null if deletion is safe.
     * Businesses/accounts cascade-delete everything they own, so ownership must be cleared first.
     */
    public function undeletableReason(User $user): ?string
    {
        if ($user->businesses()->exists() || $user->accounts()->exists()) {
            return __('Cannot delete a user who owns a business or account. Transfer or remove those first.');
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return __('Cannot delete the last remaining admin.');
        }

        return null;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
