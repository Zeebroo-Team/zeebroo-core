<?php

namespace Modules\Business\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;

class BranchService
{
    public function listForBusiness(?Business $business): Collection
    {
        if (!$business instanceof Business) {
            return new Collection();
        }

        return $business->branches()->get();
    }

    public function create(Business $business, array $data): Branch
    {
        return $business->branches()->create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->fill($data);
        $branch->save();

        return $branch->refresh();
    }

    public function delete(Branch $branch): bool
    {
        return (bool) $branch->delete();
    }
}
