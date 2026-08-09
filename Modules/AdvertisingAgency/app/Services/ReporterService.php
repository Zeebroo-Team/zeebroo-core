<?php

namespace Modules\AdvertisingAgency\Services;

use Modules\AdvertisingAgency\Models\Reporter;
use Modules\Business\Models\Business;

class ReporterService
{
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

    public function create(Business $business, array $data): Reporter
    {
        return Reporter::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => $data['password'],
        ]);
    }

    public function update(Reporter $reporter, array $data): Reporter
    {
        $fill = [
            'name'  => $data['name'],
            'email' => $data['email'],
        ];
        if (! empty($data['password'])) {
            $fill['password'] = $data['password'];
        }

        $reporter->update($fill);

        return $reporter->fresh();
    }

    public function delete(Reporter $reporter): void
    {
        $reporter->delete();
    }
}
