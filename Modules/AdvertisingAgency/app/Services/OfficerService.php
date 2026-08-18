<?php

namespace Modules\AdvertisingAgency\Services;

use App\Models\User;
use Modules\AdvertisingAgency\Models\Officer;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;

class OfficerService
{
    public function list(Business $business, ?string $q = null)
    {
        // Ensure the officer BusinessRole exists so it appears in Settings → Roles
        // even for businesses that had officers created before this feature was added
        if (Officer::where('business_id', $business->id)->exists()) {
            BusinessRole::firstOrCreate(
                ['business_id' => $business->id, 'slug' => 'officer'],
                [
                    'name'        => 'Officer',
                    'color'       => '#8b5cf6',
                    'description' => 'Advertising Agency officer role.',
                    'permissions' => [],
                    'is_system'   => false,
                    'sort_order'  => 21,
                    'business_id' => $business->id,
                ]
            );
        }

        return Officer::query()
            ->where('business_id', $business->id)
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->get();
    }

    public function create(Business $business, array $data): Officer
    {
        // Find or create a system user for this officer
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // User already exists — optionally update the password if provided
            // $user->update() goes through Eloquent so the 'hashed' cast applies; pass plain text
            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }
        } else {
            // User::create() goes through Eloquent → 'hashed' cast auto-hashes; no Hash::make() needed
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $data['password'],
            ]);
        }

        // Ensure the 'officer' BusinessRole template exists so it shows in Settings → Roles
        BusinessRole::firstOrCreate(
            ['business_id' => $business->id, 'slug' => 'officer'],
            [
                'name'        => 'Officer',
                'color'       => '#8b5cf6',
                'description' => 'Advertising Agency officer role.',
                'permissions' => [],
                'is_system'   => false,
                'sort_order'  => 21,
                'business_id' => $business->id,
            ]
        );

        // Add or update the business-member record with role 'officer'
        BusinessMember::updateOrCreate(
            [
                'business_id' => $business->id,
                'user_id'     => $user->id,
            ],
            [
                'role'   => 'officer',
                'status' => 'active',
            ]
        );

        return Officer::create([
            'business_id' => $business->id,
            'user_id'     => $user->id,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => $data['password'], // goes through the mutator
        ]);
    }

    public function update(Officer $officer, array $data): Officer
    {
        $fill = [
            'name'  => $data['name'],
            'email' => $data['email'],
        ];
        if (! empty($data['password'])) {
            $fill['password'] = $data['password'];
        }
        $officer->update($fill);

        // Keep the linked system user in sync (use Eloquent so the 'hashed' cast applies)
        if ($officer->user_id) {
            $user = User::find($officer->user_id);
            if ($user) {
                $userFill = [
                    'name'  => $data['name'],
                    'email' => $data['email'],
                ];
                if (! empty($data['password'])) {
                    $userFill['password'] = $data['password']; // cast handles hashing
                }
                $user->update($userFill);
            }
        }

        return $officer->fresh();
    }

    public function delete(Officer $officer): void
    {
        // Remove the business-member record so the user loses officer access
        if ($officer->user_id) {
            BusinessMember::where('business_id', $officer->business_id)
                ->where('user_id', $officer->user_id)
                ->where('role', 'officer')
                ->delete();
        }

        $officer->delete();
    }
}
