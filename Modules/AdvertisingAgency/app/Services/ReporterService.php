<?php

namespace Modules\AdvertisingAgency\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Spatie\Permission\Models\Role;

class ReporterService
{
    private const ROLE_SLUG  = 'reporter';
    private const ROLE_LABEL = 'Reporter';

    // ── List ────────────────────────────────────────────────────────────────

    public function list(Business $business, ?string $q = null)
    {
        return Reporter::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get();
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function create(Business $business, array $data): Reporter
    {
        return DB::transaction(function () use ($business, $data) {

            // 1. Create the Reporter record
            $reporter = Reporter::create([
                'business_id' => $business->id,
                'name'        => $data['name'],
                'email'       => $data['email'],
                'password'    => $data['password'],   // hashed by model mutator
            ]);

            // 2. Ensure the Spatie app-level "reporter" role exists
            Role::firstOrCreate(
                ['name' => self::ROLE_SLUG, 'guard_name' => 'web']
            );

            // 3. Find or create the system User account
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => $data['password'],  // hashed by User 'hashed' cast
                ]
            );

            // Always make sure the Spatie role is attached
            if (! $user->hasRole(self::ROLE_SLUG)) {
                $user->assignRole(self::ROLE_SLUG);
            }

            // 4. Ensure the per-business "reporter" BusinessRole template exists
            BusinessRole::firstOrCreate(
                ['business_id' => $business->id, 'slug' => self::ROLE_SLUG],
                [
                    'name'        => self::ROLE_LABEL,
                    'color'       => '#3b82f6',
                    'description' => 'Reporter role for the Advertising Agency module.',
                    'permissions' => [],   // no extra business permissions by default
                    'is_system'   => false,
                    'sort_order'  => 20,
                    'business_id' => $business->id,
                ]
            );

            // 5. Add as a BusinessMember (idempotent)
            BusinessMember::firstOrCreate(
                ['business_id' => $business->id, 'user_id' => $user->id],
                [
                    'role'       => self::ROLE_SLUG,
                    'status'     => 'active',
                    'invited_by' => null,
                ]
            );

            // 6. Link user_id back to the reporter row
            $reporter->update(['user_id' => $user->id]);

            return $reporter->fresh();
        });
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function update(Reporter $reporter, array $data): Reporter
    {
        return DB::transaction(function () use ($reporter, $data) {

            $fill = [
                'name'  => $data['name'],
                'email' => $data['email'],
            ];
            if (! empty($data['password'])) {
                $fill['password'] = $data['password'];
            }
            $reporter->update($fill);

            // Sync the linked User account if it exists
            if ($reporter->user_id) {
                $user = User::find($reporter->user_id);
                if ($user) {
                    $user->name  = $data['name'];
                    $user->email = $data['email'];
                    if (! empty($data['password'])) {
                        $user->password = $data['password'];
                    }
                    $user->save();
                }
            }

            return $reporter->fresh();
        });
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function delete(Reporter $reporter): void
    {
        DB::transaction(function () use ($reporter) {

            // Remove the BusinessMember link so the user loses business access
            if ($reporter->user_id) {
                BusinessMember::where('business_id', $reporter->business_id)
                    ->where('user_id', $reporter->user_id)
                    ->delete();
            }

            $reporter->delete();
        });
    }
}
