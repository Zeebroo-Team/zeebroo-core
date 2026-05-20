<?php

declare(strict_types=1);

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;
use Modules\Business\Models\Business;
use Modules\Business\Services\BusinessProfileSettingSync;
use Modules\Business\Services\GoogleBusinessProfileApiClient;
use Modules\Settings\Services\SettingsService;

final class BusinessGoogleBusinessProfileController extends Controller
{
    public function locations(Request $request, GoogleBusinessProfileApiClient $client): JsonResponse
    {
        $biz = Business::currentForNavbar($request->user());
        if (! $biz instanceof Business) {
            return response()->json(['error' => __('No business selected.')], 404);
        }
        abort_unless((int) $biz->user_id === (int) $request->user()->id, 403);

        if ($request->boolean('fresh')) {
            $client->forgetCachedLocationList($request->user());
        }

        $out = $client->listFlatLocations($request->user());

        return response()->json($out, isset($out['error']) ? 422 : 200);
    }

    public function link(Request $request, GoogleBusinessProfileApiClient $client): JsonResponse
    {
        $biz = Business::currentForNavbar($request->user());
        if (! $biz instanceof Business) {
            return response()->json(['error' => __('No business selected.')], 404);
        }
        abort_unless((int) $biz->user_id === (int) $request->user()->id, 403);

        if (! $client->userHasGoogleConnection($request->user())) {
            return response()->json(['error' => __('Connect Google first in App connections.')], 422);
        }

        $resource = trim((string) $request->input('location_resource'));

        $data = validator(
            ['location_resource' => $resource],
            [
                'location_resource' => [
                    'required',
                    'regex:/^accounts\/[^\/]+\/locations\/[^\/]+$/',
                ],
            ],
        )->validated();

        $resource = $data['location_resource'];

        $verified = $client->verifyLocationForLink($request->user(), $resource);
        if (isset($verified['error'])) {
            return response()->json(['error' => (string) $verified['error']], 422);
        }

        $listTitle = (string) ($verified['title'] ?? '');
        $ttl = Str::limit($listTitle, 250, '');

        try {
            $biz->forceFill([
                'google_location_resource' => $verified['resource'],
                'google_location_title_cache' => $ttl,
                'google_location_linked_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => __('Could not save the listing link. If you upgraded the app, run migrations (`php artisan migrate`), then retry.'),
            ], 500);
        }

        $client->forgetCachedLocationList($request->user());

        return response()->json(['linked' => true, 'resource' => $verified['resource'], 'title' => $ttl]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $biz = Business::currentForNavbar($request->user());
        if (! $biz instanceof Business) {
            return response()->json(['error' => __('No business selected.')], 404);
        }
        abort_unless((int) $biz->user_id === (int) $request->user()->id, 403);

        try {
            $biz->forceFill([
                'google_location_resource' => null,
                'google_location_title_cache' => null,
                'google_location_linked_at' => null,
            ])->save();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => __('Could not unlink. Run migrations (`php artisan migrate`) if columns are missing, then retry.'),
            ], 500);
        }

        return response()->json(['unlinked' => true]);
    }

    public function importDescription(
        Request $request,
        GoogleBusinessProfileApiClient $client,
        BusinessProfileSettingSync $sync,
        SettingsService $settingsService,
    ): JsonResponse {
        $biz = Business::currentForNavbar($request->user());
        if (! $biz instanceof Business) {
            return response()->json(['error' => __('No business selected.')], 404);
        }
        abort_unless((int) $biz->user_id === (int) $request->user()->id, 403);

        $linked = trim((string) ($biz->google_location_resource ?? ''));
        if ($linked === '') {
            return response()->json(['error' => __('Link a Google Business Profile first.')], 422);
        }

        if (! $client->userHasGoogleConnection($request->user())) {
            return response()->json(['error' => __('Google is not connected.')], 422);
        }

        $overwriteName = filter_var($request->input('overwrite_name'), FILTER_VALIDATE_BOOLEAN);

        $snap = $client->fetchDescriptionForImport($request->user(), $linked);
        if (isset($snap['error'])) {
            return response()->json(['error' => (string) $snap['error']], 422);
        }

        $body = preg_replace('/\s+/u', ' ', strip_tags(trim((string) ($snap['description'] ?? '')))) ?: '';
        if ($body === '') {
            return response()->json(['error' => __('Nothing to import.')], 422);
        }

        $short = Str::limit($body, 340, '');

        $upd = [
            'short_description' => $short,
            'description' => $body,
        ];

        if ($overwriteName && is_string($snap['title'])) {
            $n = trim((string) $snap['title']);
            if ($n !== '') {
                $upd['name'] = Str::limit($n, 255, '');
            }
        }

        if (isset($snap['title']) && is_string($snap['title']) && trim((string) $snap['title']) !== '') {
            $upd['google_location_title_cache'] = Str::limit(trim((string) $snap['title']), 250, '');
        }

        $fresh = null;
        try {
            $biz->update($upd);
            $fresh = $biz->fresh();
            if (! $fresh instanceof Business) {
                throw new \RuntimeException('business_not_reloaded_after_import');
            }
            $sync->mirrorModelToSettings($settingsService, $fresh);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => __('Could not import. Run migrations (`php artisan migrate`) if needed, then retry.'),
            ], 500);
        }

        if (! $fresh instanceof Business) {
            return response()->json([
                'error' => __('Import finished but profile could not be reloaded.'),
            ], 500);
        }

        return response()->json([
            'short_description' => $fresh->short_description,
            'description' => $fresh->description,
            'name' => $fresh->name,
        ]);

    }

}
