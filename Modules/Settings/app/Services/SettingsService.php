<?php

namespace Modules\Settings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Modules\Settings\Models\Setting;

class SettingsService
{
    public function get(Model $scope, string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()
            ->where('scope_type', $scope->getMorphClass())
            ->where('scope_id', $scope->getKey())
            ->where('key', $key)
            ->first();

        return $setting ? $this->decode($setting->value, $setting->value_type) : $default;
    }

    public function allForScope(Model $scope): Collection
    {
        return Setting::query()
            ->where('scope_type', $scope->getMorphClass())
            ->where('scope_id', $scope->getKey())
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [
                $setting->key => $this->decode($setting->value, $setting->value_type),
            ]);
    }

    public function set(Model $scope, string $key, mixed $value): Setting
    {
        [$storedValue, $type] = $this->encode($value);

        return Setting::query()->updateOrCreate(
            [
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
                'key' => $key,
            ],
            [
                'value' => $storedValue,
                'value_type' => $type,
            ]
        );
    }

    public function setMany(Model $scope, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($scope, (string) $key, $value);
        }
    }

    public function forget(Model $scope, string $key): void
    {
        Setting::query()
            ->where('scope_type', $scope->getMorphClass())
            ->where('scope_id', $scope->getKey())
            ->where('key', $key)
            ->delete();
    }

    public function getDefaultSettingsByScope(string $scopeType): array
    {
        $path = base_path('Modules/Settings/database/seeders/settings-fields.json');
        if (!File::exists($path)) {
            return [];
        }

        $payload = json_decode((string) File::get($path), true);
        $definitions = is_array($payload[$scopeType] ?? null) ? $payload[$scopeType] : [];
        $defaults = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $defaults[$key] = $definition['default'] ?? '';
        }

        return $defaults;
    }

    private function encode(mixed $value): array
    {
        if (is_bool($value)) {
            return [$value ? '1' : '0', 'boolean'];
        }

        if (is_int($value)) {
            return [(string) $value, 'integer'];
        }

        if (is_float($value)) {
            return [(string) $value, 'float'];
        }

        if (is_array($value)) {
            return [json_encode($value, JSON_UNESCAPED_UNICODE), 'json'];
        }

        return [(string) $value, 'string'];
    }

    private function decode(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === '1',
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
