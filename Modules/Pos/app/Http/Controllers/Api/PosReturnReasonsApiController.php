<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Pos\Models\SaleReturn;

class PosReturnReasonsApiController extends Controller
{
    public function index(): JsonResponse
    {
        $reasons = collect(SaleReturn::REASONS)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values();

        return response()->json(['data' => $reasons]);
    }
}
