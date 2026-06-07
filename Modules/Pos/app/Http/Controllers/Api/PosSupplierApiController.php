<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\Supplier;

class PosSupplierApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'email', 'phone']);

        return response()->json([
            'data' => $suppliers->map(fn (Supplier $s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'contact_name' => $s->contact_name,
                'email'        => $s->email,
                'phone'        => $s->phone,
            ])->values(),
        ]);
    }
}
