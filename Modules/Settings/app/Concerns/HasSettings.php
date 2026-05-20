<?php

namespace Modules\Settings\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingsService;

trait HasSettings
{
    public function settings(): MorphMany
    {
        return $this->morphMany(Setting::class, 'scope', 'scope_type', 'scope_id');
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($this, $key, $default);
    }

    public function setSetting(string $key, mixed $value): Setting
    {
        return app(SettingsService::class)->set($this, $key, $value);
    }
}
