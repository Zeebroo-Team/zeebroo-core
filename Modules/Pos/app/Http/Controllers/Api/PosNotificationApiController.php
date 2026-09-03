<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosNotificationService;

class PosNotificationApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PosNotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->syncForBusiness($business);

        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));

        return response()->json($this->service->list($business, $status, $limit));
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->markRead($business, $id);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markUnread(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->markUnread($business, $id);

        return response()->json(['message' => 'Notification marked as unread.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->markAllRead($business);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->delete($business, $id);

        return response()->json(['message' => 'Notification deleted.']);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->service->clearAll($business);

        return response()->json(['message' => 'All notifications cleared.']);
    }

    public function settingsShow(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json(['data' => $this->service->getSettings($business)]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'large_sale_threshold' => 'nullable|numeric|min:0',
        ]);

        $this->service->updateSettings($business, $validated);

        return response()->json(['data' => $this->service->getSettings($business)]);
    }
}
