<?php

namespace Modules\DesignStudio\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookConnectionService
{
    private const API_VERSION = 'v21.0';
    private const BASE        = 'https://graph.facebook.com';
    private const DIALOG_BASE = 'https://www.facebook.com';

    private string $appId;
    private string $appSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->appId       = (string) config('services.facebook.client_id');
        $this->appSecret   = (string) config('services.facebook.client_secret');
        $this->redirectUri = url(config('services.facebook.redirect'));
    }

    /** Build the Facebook OAuth authorization URL. */
    public function authorizationUrl(string $state): string
    {
        $scope = implode(',', [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_manage_metadata',
        ]);

        return self::DIALOG_BASE . '/' . self::API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $this->redirectUri,
            'state'         => $state,
            'scope'         => $scope,
            'response_type' => 'code',
        ]);
    }

    /** Exchange authorization code → short-lived user token → long-lived user token. */
    public function exchangeCodeForLongLivedToken(string $code): array
    {
        // Step 1: code → short-lived user access token
        $short = Http::get(self::BASE . '/' . self::API_VERSION . '/oauth/access_token', [
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
        ])->throw()->json();

        if (empty($short['access_token'])) {
            throw new RuntimeException('Facebook did not return an access token.');
        }

        // Step 2: short-lived → long-lived (60-day) user token
        $long = Http::get(self::BASE . '/' . self::API_VERSION . '/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->appId,
            'client_secret'     => $this->appSecret,
            'fb_exchange_token' => $short['access_token'],
        ])->throw()->json();

        return $long; // ['access_token', 'token_type', 'expires_in']
    }

    /**
     * Fetch all pages the user manages.
     * Returns array of ['id', 'name', 'access_token', 'category', 'picture', 'fan_count'].
     */
    public function getUserPages(string $userAccessToken): array
    {
        $response = Http::get(self::BASE . '/' . self::API_VERSION . '/me/accounts', [
            'access_token' => $userAccessToken,
            'fields'       => 'id,name,access_token,category,picture,fan_count',
        ])->throw()->json();

        return $response['data'] ?? [];
    }

    /**
     * Exchange a page access token derived from a long-lived user token
     * for a never-expiring page access token.
     */
    public function getPageAccessToken(string $pageId, string $userAccessToken): string
    {
        $response = Http::get(self::BASE . '/' . self::API_VERSION . '/' . $pageId, [
            'fields'       => 'access_token',
            'access_token' => $userAccessToken,
        ])->throw()->json();

        return $response['access_token'] ?? throw new RuntimeException('Could not retrieve page access token.');
    }

    /** Verify a token is still valid. Returns token info or throws. */
    public function debugToken(string $token): array
    {
        return Http::get(self::BASE . '/' . self::API_VERSION . '/debug_token', [
            'input_token'  => $token,
            'access_token' => $this->appId . '|' . $this->appSecret,
        ])->throw()->json('data', []);
    }
}
