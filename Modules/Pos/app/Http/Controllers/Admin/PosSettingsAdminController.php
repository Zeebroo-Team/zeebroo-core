<?php

namespace Modules\Pos\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosSettingsAdminService;

/** Admin → User → POS Settings tab. Logic lives in PosSettingsAdminService. */
class PosSettingsAdminController extends Controller
{
    public function __construct(private readonly PosSettingsAdminService $settings) {}

    public function index(Business $business): JsonResponse
    {
        return $this->panel($business);
    }

    public function update(Request $request, Business $business, string $setting): JsonResponse
    {
        $this->settings->update($business, $setting, $request->input('value'));

        return $this->panel($business, 'Setting saved.');
    }

    public function destroy(Business $business, string $setting): JsonResponse
    {
        $this->settings->delete($business, $setting);

        return $this->panel($business, 'Saved value deleted.');
    }

    /** The whole panel comes back each time: one setting can show under several apps. */
    private function panel(Business $business, ?string $message = null): JsonResponse
    {
        return response()->json([
            'html' => view('pos::admin.settings.panel', ['business' => $business] + $this->settings->panel($business))->render(),
            'message' => $message,
        ]);
    }
}
