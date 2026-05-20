<?php

declare(strict_types=1);

namespace Modules\Business\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Modules\AppConnection\Models\UserAppConnection;

/** Google Business Profile (Business Information API) read-only helpers. */
final class GoogleBusinessProfileApiClient
{
    private const ACCOUNTS_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';

    private const INFO_BASE = 'https://mybusinessbusinessinformation.googleapis.com/v1';

    private const LOCATION_LIST_CACHE_TTL_SECONDS = 90;

    /**
     * Bypass cache after successful link so the dropdown can refresh sooner if needed.
     */
    public function forgetCachedLocationList(User $user): void
    {
        Cache::forget(self::flatLocationsCacheKey($user));
    }

    /** @internal */
    public static function flatLocationsCacheKey(User $user): string
    {
        return 'google_bp:flat_locations:'.((int) $user->getKey());
    }

    /**
     * One Business Information API read; avoids listing all accounts/locations again when linking.
     *
     * @return array{resource:string,title:string}|array{error:string}
     */
    public function verifyLocationForLink(User $user, string $locationResource): array
    {
        $token = $this->validAccessToken($user);
        if ($token === null) {
            return ['error' => __('Connect Google under App connections, then reconnect so Business Profile scopes are granted.')];
        }

        $name = trim($locationResource);
        if ($name === '' || ! preg_match('/^accounts\/[^\/]+\/locations\/[^\/]+$/', $name)) {
            return ['error' => __('Invalid location reference.')];
        }

        $url = self::INFO_BASE.'/'.$name;
        $res = $this->googleGet($token, $url, [
            'readMask' => 'name,title',
        ]);

        if (! $res->successful()) {
            return $this->mapProblem($res, __('Unable to verify this Google listing.'));
        }

        $jsonName = $res->json('name');
        $resourceName = $name;
        if (is_string($jsonName) && trim($jsonName) !== '') {
            $resourceName = trim($jsonName);
        }
        if (! preg_match('/^accounts\/[^\/]+\/locations\/[^\/]+$/', $resourceName)) {
            return ['error' => __('Google returned an unexpected listing response.')];
        }

        $title = $res->json('title');
        $titleStr = is_string($title) && trim($title) !== '' ? trim($title) : Str::limit($resourceName, 80);

        return [
            'resource' => $resourceName,
            'title' => $titleStr,
        ];
    }

    /**
     * @return array{locations: list<array{resource:string,title:string,account:string}>}|array{error:string}
     */
    public function listFlatLocations(User $user): array
    {
        $token = $this->validAccessToken($user);
        if ($token === null) {
            return ['error' => __('Connect Google under App connections, then reconnect so Business Profile scopes are granted.')];
        }

        $key = self::flatLocationsCacheKey($user);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['locations']) && is_array($cached['locations'])) {
            return $cached;
        }

        $out = $this->listFlatLocationsUncached($token);
        if (isset($out['locations'])) {
            Cache::put($key, $out, self::LOCATION_LIST_CACHE_TTL_SECONDS);
        }

        return $out;
    }

    /**
     * @return array{locations: list<array{resource:string,title:string,account:string}>}|array{error:string}
     */
    private function listFlatLocationsUncached(string $token): array
    {
        $flat = [];

        $pageToken = '';
        do {
            $query = ['pageSize' => 50];
            if ($pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }
            $accountsRes = $this->googleGet($token, self::ACCOUNTS_URL, $query);
            if (! $accountsRes->successful()) {
                return $this->mapProblem($accountsRes, __('Unable to load Google accounts.'));
            }

            $accounts = $accountsRes->json('accounts');
            foreach (is_array($accounts) ? $accounts : [] as $acc) {
                if (! is_array($acc) || empty($acc['name']) || ! is_string($acc['name'])) {
                    continue;
                }
                $chunk = $this->listLocationsForAccount($token, $acc['name']);
                if (isset($chunk['error'])) {
                    return $chunk;
                }
                foreach ($chunk['locations'] as $loc) {
                    $flat[] = $loc;
                }
            }

            $pt = $accountsRes->json('nextPageToken');
            $pageToken = is_string($pt) ? $pt : '';
        } while ($pageToken !== '');

        return ['locations' => $flat];
    }

    /** @param  array<string, mixed>  $query */
    private function googleGet(string $token, string $url, array $query = []): Response
    {
        try {
            return $this->sendGoogleGetWithRetries($token, $url, $query);
        } catch (Throwable $e) {
            Log::warning('Google Business Profile HTTP failure', [
                'message' => $e->getMessage(),
                'url' => $url,
                'exception' => $e::class,
            ]);

            return new Response(Http::psr7Response([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 503));
        }
    }

    /**
     * Builds a fresh client each attempt so Laravel’s PendingRequest carries no mutated state across retries.
     *
     * @param  array<string, mixed>  $query
     */
    private function sendGoogleGetWithRetries(string $token, string $url, array $query): Response
    {
        $attempt = 0;
        /** Exponential fallback in milliseconds (converted to microseconds in sleep helper). */
        $delayMs = 600;

        $response = Http::timeout(45)->withToken($token)->acceptJson()->get($url, $query);

        while ($response->status() === 429 && $attempt < 5) {
            $microseconds = $this->sleepMicrosecondsFor429Header($response, $delayMs);
            usleep($microseconds);

            $response = Http::timeout(45)->withToken($token)->acceptJson()->get($url, $query);
            $delayMs = min($delayMs * 2, 24_000);
            $attempt++;
        }

        return $response;
    }

    /** Sleep derived from Retry-After (seconds) or exponential backoff in milliseconds (max ~30s). */
    private function sleepMicrosecondsFor429Header(Response $response, int $exponentialDelayMs): int
    {
        $line = trim((string) $response->header('Retry-After'));
        if ($line !== '' && is_numeric($line)) {
            /** HTTP Retry-After is in whole seconds when numeric. */
            $seconds = max(1, min((int) $line, 120));

            return min($seconds * 1_000_000, 60 * 1_000_000);
        }

        /** $exponentialDelayMs approximates ms; bounded to [250ms, 30s]. */
        $boundedMs = max(250, min($exponentialDelayMs, 30_000));

        return $boundedMs * 1000;
    }

    /**
     * @return array{locations: list<array{resource:string,title:string,account:string}>}|array{error:string}
     */
    private function listLocationsForAccount(string $token, string $accountName): array
    {
        $locations = [];
        $pageToken = '';
        do {
            $parent = trim($accountName);
            $url = self::INFO_BASE.'/'.$parent.'/locations';
            $query = [
                'pageSize' => 100,
                'readMask' => 'name,title',
            ];
            if ($pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }
            $res = $this->googleGet($token, $url, $query);
            if (! $res->successful()) {
                return $this->mapProblem($res, __('Unable to load locations for Google account'));
            }

            /** @phpstan-ignore-next-line */
            $locList = $res->json('locations');
            foreach (is_array($locList) ? $locList : [] as $loc) {
                if (! is_array($loc) || empty($loc['name']) || ! is_string($loc['name'])) {
                    continue;
                }
                $locations[] = [
                    'resource' => $loc['name'],
                    'title' => is_string($loc['title'] ?? null) ? $loc['title'] : Str::limit($loc['name'], 80),
                    'account' => $parent,
                ];
            }

            $pt = $res->json('nextPageToken');
            $pageToken = is_string($pt) ? $pt : '';
        } while ($pageToken !== '');

        return ['locations' => $locations];
    }

    /** @return array{title:?string,description:string}|array{error:string} */
    public function fetchDescriptionForImport(User $user, string $locationResource): array
    {
        $token = $this->validAccessToken($user);
        if ($token === null) {
            return ['error' => __('Google is not connected or the session expired.')];
        }

        $name = trim($locationResource);
        $url = self::INFO_BASE.'/'.$name;

        $res = $this->googleGet($token, $url, [
            'readMask' => 'name,title,profile,websiteUri',
        ]);

        if (! $res->successful()) {
            return $this->mapProblem($res, __('Could not load this Business Profile location.'));
        }

        $title = null;
        if (is_string($res->json('title'))) {
            $title = trim((string) $res->json('title'));
            if ($title === '') {
                $title = null;
            }
        }

        $profile = $res->json('profile');
        $body = '';
        if (is_array($profile) && isset($profile['description']) && is_string($profile['description'])) {
            $body = trim($profile['description']);
        }

        $site = $res->json('websiteUri');
        if ($body === '' && is_string($site) && trim($site) !== '') {
            $body = 'Website: '.trim($site);
        }

        if ($body === '') {
            return ['error' => __('No description found on this Google Business Profile. Add one in Google, then import again.')];
        }

        return ['title' => $title, 'description' => $body];
    }

    public function userHasGoogleConnection(User $user): bool
    {
        return UserAppConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', UserAppConnection::PROVIDER_GOOGLE)
            ->exists();
    }

    private function validAccessToken(User $user): ?string
    {
        $conn = UserAppConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', UserAppConnection::PROVIDER_GOOGLE)
            ->first();

        if (! $conn instanceof UserAppConnection) {
            return null;
        }

        if ($this->needsRefresh($conn) && ! $this->refreshAccessToken($conn)) {
            return null;
        }

        $t = $conn->access_token;
        if (! is_string($t) || trim($t) === '') {
            return null;
        }

        return trim($t);
    }

    private function needsRefresh(UserAppConnection $conn): bool
    {
        if (! $conn->token_expires_at instanceof Carbon) {
            return false;
        }

        return $conn->token_expires_at->lte(now()->addSeconds(90));
    }

    private function refreshAccessToken(UserAppConnection $conn): bool
    {
        $rt = $conn->refresh_token;
        if (! is_string($rt) || $rt === '') {
            return false;
        }

        $clientId = (string) config('services.google.client_id', '');
        $secret = (string) config('services.google.client_secret', '');
        if ($clientId === '' || $secret === '') {
            return false;
        }

        $res = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $rt,
        ]);

        if (! $res->successful()) {
            return false;
        }

        $access = $res->json('access_token');
        if (! is_string($access) || $access === '') {
            return false;
        }

        $exp = $res->json('expires_in');
        /** @phpstan-ignore-next-line */
        $expiresAt = is_numeric($exp)
            ? now()->addSeconds((int) $exp)
            : now()->addHour();

        $conn->forceFill([
            'access_token' => $access,
            'token_expires_at' => $expiresAt,
        ])->save();

        return true;
    }

    /** @return array{error:string} */
    private function mapProblem(Response $response, string $fallback): array
    {
        $json = $response->json();
        $detail = '';

        if (is_array($json) && isset($json['error'])) {
            if (is_array($json['error']) && isset($json['error']['message'])) {
                $detail = ' '.(string) $json['error']['message'];
            } elseif (is_string($json['error'])) {
                $detail = ' '.$json['error'];
            }
        }

        if ($response->status() === 403 || str_contains(Str::lower((string) $detail), 'insufficient authentication scopes')) {
            $detail .= ' '.__('Reconnect Google in App connections to enable Business Profile access.');
        }
        if ($response->status() === 429) {
            $detail .= ' '.__('Wait a minute before trying again, or request a quota increase in Google Cloud for My Business Account Management API.');
        }

        return ['error' => $fallback.$detail.' (HTTP '.$response->status().')'];
    }
}
