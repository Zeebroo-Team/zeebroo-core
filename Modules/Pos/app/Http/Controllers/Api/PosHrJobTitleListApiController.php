<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\JobTitle;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrJobTitleListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_job_titles')) {
            return response()->json(['data' => []]);
        }

        $jobTitles = JobTitle::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $jobTitles->map(fn (JobTitle $jt) => [
                'id'   => $jt->id,
                'name' => $jt->name,
            ])->values(),
        ]);
    }
}
