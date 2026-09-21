<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserManagementService
{
    /**
     * @param  array{search?: ?string, role?: ?string, status?: ?string}  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;
        $package = $filters['package'] ?? null;
        $owns = $filters['owns'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $sort = $filters['sort'] ?? 'newest';

        return User::query()
            ->with(['roles', 'businesses.package', 'businesses.featureOverrides', 'businesses.payments' => fn ($q) => $q->latest('created_at')])
            ->withCount(['businesses', 'accounts'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($package === 'none', fn ($q) => $q->whereDoesntHave('businesses', fn ($b) => $b->whereNotNull('package_id')))
            ->when($package && $package !== 'none', fn ($q) => $q->whereHas('businesses', fn ($b) => $b->where('package_id', $package)))
            ->when($owns === 'business', fn ($q) => $q->has('businesses'))
            ->when($owns === 'no_business', fn ($q) => $q->doesntHave('businesses'))
            ->when($owns === 'accounts', fn ($q) => $q->has('accounts'))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('created_at'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when(! in_array($sort, ['oldest', 'name'], true), fn ($q) => $q->orderByDesc('created_at'))
            ->paginate($perPage)
            ->withQueryString();
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

    /**
     * Returns a reason $user cannot be disabled, or null if it's safe.
     */
    public function cannotDeactivateReason(User $user): ?string
    {
        if ($user->hasRole('admin') && User::role('admin')->where('is_active', true)->count() <= 1) {
            return __('Cannot disable the last remaining active admin.');
        }

        return null;
    }

    /**
     * Flips the account's active flag. Disabling also revokes every API token so an
     * already-signed-in pos-desktop (Electron) client is logged out immediately.
     */
    public function toggleActive(User $user): User
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return $user;
    }
}
