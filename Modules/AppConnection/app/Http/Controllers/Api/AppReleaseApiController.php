<?php

namespace Modules\AppConnection\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if (!empty($data['is_latest'])) {
            AppRelease::where('channel', $data['channel'])->update(['is_latest' => false]);
        }

        $release = AppRelease::updateOrCreate(
            ['version' => $data['version']],
            $data,
        );

        return response()->json(['data' => $release], $release->wasRecentlyCreated ? 201 : 200);
    }

    public function index(): JsonResponse
    {
        $releases = AppRelease::orderByDesc('release_date')->orderByDesc('id')->get();
        return response()->json(['data' => $releases]);
    }

    public function latest(): JsonResponse
    {
        $release = AppRelease::where('channel', 'stable')->where('is_latest', true)->first()
            ?? AppRelease::where('channel', 'stable')->orderByDesc('id')->first();

        return response()->json(['data' => $release]);
    }
}
