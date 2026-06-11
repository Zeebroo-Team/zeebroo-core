<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', '/design-studio/facebook/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        /** Sign-in / sign-up (register this URI in Google Cloud OAuth “Authorized redirect URIs”). */
        'auth_redirect' => env('GOOGLE_AUTH_REDIRECT_URI'),
        /** Request Google Business Profile (My Business) API scope during Google OAuth — enable APIs in GCP and complete OAuth verification as required. */
        'business_manage_scope' => filter_var(env('GOOGLE_BUSINESS_PROFILE_SCOPE', false), FILTER_VALIDATE_BOOLEAN),
        /** Use consent to obtain refresh tokens / new scopes after changing GOOGLE_BUSINESS_PROFILE_SCOPE */
        'oauth_prompt' => env('GOOGLE_OAUTH_PROMPT', 'select_account'),
    ],

];
