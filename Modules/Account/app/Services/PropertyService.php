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

        $property = Property::create($data);

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('property.created', $business, [
                'event'    => 'property.created',
                'property' => [
                    'id'            => $property->id,
                    'property_name' => $property->property_name,
                    'property_type' => $property->property_type,
                    'cost'          => (float) $property->cost,
                    'has_expiry'    => (bool) $property->has_expiry,
                    'expire_date'   => $property->expire_date?->toDateString(),
                    'created_at'    => $property->created_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}

        return $property;
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
