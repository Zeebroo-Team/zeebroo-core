<?php

namespace Modules\Business\Services;

use App\Models\User;
use Modules\Business\Models\Business;

class BusinessService
{
    public function upsertForUser(User $user, array $data): Business
    {
        $business = Business::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $data['name'],
                'category' => $data['category'],
                'company_category_slug' => $data['company_category_slug'] ?? null,
                'description' => $data['description'] ?? null,
            ]
        );

        return $business;
    }
}
