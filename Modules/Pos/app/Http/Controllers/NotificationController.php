<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Services\PosNotificationService;

class NotificationController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(private readonly PosNotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->syncForBusiness($business);

        $status = (string) $request->query('status', 'all');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));

        return response()->json($this->service->list($business, $status, $limit));
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->markRead($business, $id);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markUnread(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->markUnread($business, $id);

        return response()->json(['message' => 'Notification marked as unread.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->markAllRead($business);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->delete($business, $id);

        return response()->json(['message' => 'Notification deleted.']);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $this->service->clearAll($business);

        return response()->json(['message' => 'All notifications cleared.']);
    }

    public function settingsShow(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        return response()->json(['data' => $this->service->getSettings($business)]);
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $business = $this->businessOrJson($request);
        if ($business instanceof JsonResponse) {
            return $business;
        }

        $validated = $request->validate([
            'large_sale_threshold' => 'nullable|numeric|min:0',
        ]);

        $this->service->updateSettings($business, $validated);

        return response()->json(['data' => $this->service->getSettings($business)]);
    }

    /**
     * Same business resolution as requireBusiness(), but returns a JSON 422
     * instead of a redirect — every action here is called from fetch(), not
     * a full page navigation.
     */
    private function businessOrJson(Request $request): Business|JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'No business selected.'], 422);
        }

        return $business;
    }
}
