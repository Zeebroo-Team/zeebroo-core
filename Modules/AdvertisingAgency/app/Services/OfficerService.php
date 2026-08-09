<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Officer;
use Modules\Business\Models\Business;

class OfficerService
{
    public function list(Business $business, ?string $q = null)
    {
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
        return Officer::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => $data['password'],
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
        return $officer->fresh();
    }

    public function delete(Officer $officer): void
    {
        $officer->delete();
    }
}
