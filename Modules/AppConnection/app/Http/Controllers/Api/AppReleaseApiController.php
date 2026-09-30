<?php

namespace Modules\AppConnection\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AppConnection\Models\AppRelease;

class AppReleaseApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $key = config('services.zeebroo.api_key');
        if (!$key || $request->header('X-Api-Key') !== $key) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'app'          => ['nullable', Rule::in(array_keys(AppRelease::APPS))],
            'version'      => ['required', 'string', 'max:32'],
            'release_date' => ['required', 'date'],
            'channel'      => ['required', 'in:stable,beta,alpha,rc'],
            'is_latest'    => ['boolean'],
            'notes'        => ['nullable', 'array'],
            'notes.*'      => ['string'],
            'windows_url'  => ['nullable', 'url'],
            'macos_url'    => ['nullable', 'url'],
            'linux_url'    => ['nullable', 'url'],
        ]);
        $data['app'] = $data['app'] ?? AppRelease::APP_MAIN;

        if (!empty($data['is_latest'])) {
            AppRelease::where('app', $data['app'])->where('channel', $data['channel'])->update(['is_latest' => false]);
        }

        $release = AppRelease::updateOrCreate(
            ['app' => $data['app'], 'version' => $data['version']],
            $data,
        );

        return response()->json(['data' => $release], $release->wasRecentlyCreated ? 201 : 200);
    }

    // ?app=main|lite — omit for every app
    public function index(Request $request): JsonResponse
    {
        $releases = AppRelease::query()
            ->when($this->requestedApp($request, null), fn($q, $app) => $q->where('app', $app))
            ->orderByDesc('release_date')->orderByDesc('id')->get();

        return response()->json(['data' => $releases]);
    }

    // ?app=main|lite — defaults to main so existing desktop clients keep working unchanged
    public function latest(Request $request): JsonResponse
    {
        try {
            $release = AppRelease::latestStable($this->requestedApp($request, AppRelease::APP_MAIN));
        } catch (\Exception $e) {
            $release = null;
        }

        return response()->json(['data' => $release]);
    }

    private function requestedApp(Request $request, ?string $default): ?string
    {
        $app = $request->query('app');

        return is_string($app) && array_key_exists($app, AppRelease::APPS) ? $app : $default;
    }
}
