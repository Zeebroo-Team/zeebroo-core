<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HRManagement\Services\HrWorkSettingsService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrSettingsApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly HrWorkSettingsService $service) {}

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => $this->service->get($business)]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'work_schedule'           => ['sometimes', 'array'],
            'work_schedule.*'         => ['array'],
            'work_schedule.*.enabled' => ['boolean'],
            'work_schedule.*.start'   => ['nullable', 'date_format:H:i'],
            'work_schedule.*.end'     => ['nullable', 'date_format:H:i'],
            'breaks'                  => ['sometimes', 'array', 'max:'.HrWorkSettingsService::MAX_BREAKS],
            'breaks.*.key'            => ['nullable', 'string', 'max:80'],
            'breaks.*.name'           => ['nullable', 'string', 'max:60'],
            'breaks.*.enabled'        => ['boolean'],
            'breaks.*.start'          => ['nullable', 'date_format:H:i'],
            'breaks.*.end'            => ['nullable', 'date_format:H:i'],
            'breaks.*.days'           => ['array'],
            'breaks.*.days.*'         => ['string', 'in:'.implode(',', HrWorkSettingsService::DAYS)],
        ]);

        $data = $this->service->save($business, $validated);

        return response()->json(['message' => 'HR settings saved.', 'data' => $data]);
    }
}
