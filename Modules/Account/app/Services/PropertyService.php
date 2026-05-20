<?php

namespace Modules\Account\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Account\Models\Property;
use Modules\Business\Models\Business;

class PropertyService
{
    public function listForBusiness(Business $business): Collection
    {
        return Property::query()
            ->where('business_id', $business->id)
            ->latest()
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, Business $business, array $data): Property
    {
        $data['user_id'] = $user->id;
        $data['business_id'] = $business->id;

        return Property::create($data);
    }

    public function deleteForUser(User $user, Property $property): bool
    {
        $businessIds = $user->businesses()->pluck('id')->all();
        if ((int) $property->user_id !== (int) $user->id || ! in_array((int) $property->business_id, $businessIds, true)) {
            return false;
        }

        $property->delete();

        return true;
    }
}
