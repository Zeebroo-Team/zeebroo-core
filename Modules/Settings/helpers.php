<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Settings\Services\SettingsService;

if (!function_exists('scope_setting')) {
    function scope_setting(Model $scope, string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($scope, $key, $default);
    }
}

if (!function_exists('get_settings')) {
    function get_settings(string $key, mixed $default = null, ?Model $scope = null): mixed
    {
        if ($scope instanceof Model) {
            return scope_setting($scope, $key, $default);
        }

        $user = Auth::user();
        if (!$user instanceof \App\Models\User) {
            return $default;
        }

        $business = $user->businesses()->latest()->first();
        if ($business instanceof \Illuminate\Database\Eloquent\Model) {
            $businessValue = scope_setting($business, $key, null);
            if ($businessValue !== null) {
                return $businessValue;
            }
        }

        return scope_setting($user, $key, $default);
    }
}
