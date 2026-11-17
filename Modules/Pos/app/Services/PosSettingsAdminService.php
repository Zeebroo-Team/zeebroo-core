<?php

namespace Modules\Pos\Services;

use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\Pos\Support\PosSettingsCatalog as Catalog;
use Modules\Settings\Services\SettingsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin → User → POS Settings: every POS setting of a business, grouped by the app it
 * affects. Edits go through PosSettingsService::saveForBusiness(), so an admin's change
 * gets the same clean-up as one made from the apps. Deleting removes the saved row, which
 * sends the apps back to the default.
 */
class PosSettingsAdminService
{
    /** Saved rows the catalog doesn't know (e.g. legacy keys) still count as POS settings under these. */
    private const OTHER_PREFIXES = ['pos.', 'pos_notifications.', 'tax.', 'invoice.', 'delivery.', 'courier.'];

    public function __construct(
        private readonly PosSettingsService $posSettings,
        private readonly PosNotificationService $notifications,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{rows: array<string, array>, groups: array<string, array>, other: list<string>}
     */
    public function panel(Business $business): array
    {
        $values = $this->posSettings->forBusiness($business)
            + ['large_sale_threshold' => $business->getSetting('pos_notifications.large_sale_threshold')];
        $saved = $this->settings->allForScope($business);

        $accounts = Account::query()
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->pluck('account_name', 'id');

        $rows = [];
        foreach (Catalog::definitions() as $field => $def) {
            $def['options'] = $this->optionsFor($def, $values[$field] ?? null, $accounts->all());
            $rows[$field] = $this->row($def, $values[$field] ?? null, $def['keys'] === [] || $saved->keys()->intersect($def['keys'])->isNotEmpty());
        }

        $groups = [];
        foreach (Catalog::SURFACES as $id => $surface) {
            $sections = [];
            foreach ($rows as $field => $row) {
                if (in_array($id, $row['surfaces'], true)) {
                    $sections[$row['section']][] = $field;
                }
            }
            $groups[$id] = $surface + ['sections' => $sections, 'count' => array_sum(array_map('count', $sections))];
        }

        $other = [];
        $types = $business->settings()->pluck('value_type', 'key');
        foreach ($saved as $key => $value) {
            if ($this->isOtherKey($key)) {
                $rows[$key] = $this->otherRow($key, $value, (string) ($types[$key] ?? 'string'));
                $other[] = $key;
            }
        }
        sort($other);

        return compact('rows', 'groups', 'other');
    }

    /** @throws ValidationException|NotFoundHttpException */
    public function update(Business $business, string $setting, mixed $value): void
    {
        $def = Catalog::find($setting);

        if ($def === null) {
            $this->settings->set($business, $setting, $this->validatedOther($business, $setting, $value));
        } else {
            $this->validate($business, $def, $value);

            if ($setting === 'large_sale_threshold') {
                $this->notifications->updateSettings($business, [$setting => $value]);
            } else {
                $this->posSettings->saveForBusiness($business, [$setting => $this->normalize($def, $value)]);
            }
        }

        $this->audit('updated', $business, $setting);
    }

    /** @throws ValidationException|NotFoundHttpException */
    public function delete(Business $business, string $setting): void
    {
        $def = Catalog::find($setting);

        if ($def === null) {
            $this->otherType($business, $setting);
            $this->settings->forget($business, $setting);
        } else {
            if (! $def['deletable']) {
                throw ValidationException::withMessages(['value' => "{$def['label']} is required and can't be deleted. Edit it instead."]);
            }
            if ($setting === 'receipt_logo_url') {
                // Also removes the uploaded file when it was one we stored.
                $this->posSettings->removeReceiptLogo($business);
            }
            foreach ($def['keys'] as $key) {
                $this->settings->forget($business, $key);
            }
        }

        $this->audit('deleted', $business, $setting);
    }

    private function row(array $def, mixed $value, bool $saved): array
    {
        return [
            'setting' => $def['field'],
            'key' => $def['keys'][0] ?? 'businesses.name',
            'label' => $def['label'],
            'section' => $def['section'],
            'type' => $def['type'],
            'surfaces' => $def['surfaces'],
            'value' => $value,
            'display' => $this->display($def, $value),
            'default_label' => $def['default_label'] ?? $this->display($def, $def['default']),
            'saved' => $saved,
            'deletable' => $def['deletable'],
        ] + array_intersect_key($def, array_flip(['options', 'min', 'max', 'step', 'unit', 'integer', 'nullable', 'required', 'help', 'warning']));
    }

    private function otherRow(string $key, mixed $value, string $type): array
    {
        $controls = ['boolean' => 'toggle', 'integer' => 'number', 'float' => 'number', 'json' => 'json'];
        $control = $controls[$type] ?? 'text';
        if ($control === 'json') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $def = ['field' => $key, 'type' => $control, 'unit' => null, 'options' => []];

        return [
            'setting' => $key,
            'key' => $key,
            'label' => $key,
            'section' => 'Other saved settings',
            'type' => $control,
            'surfaces' => [],
            'value' => $value,
            'display' => $this->display($def, $value),
            'default_label' => 'Not set',
            'saved' => true,
            'deletable' => true,
            'integer' => $type === 'integer',
            'help' => 'Saved for this business, but no POS app has this in its settings list.',
        ];
    }

    private function optionsFor(array $def, mixed $value, array $accounts): array
    {
        return match ($def['type']) {
            'currency' => Catalog::currencyOptions((string) $value),
            'timezone' => array_merge(
                [['value' => '', 'label' => 'Not set']],
                array_map(fn (string $tz) => ['value' => $tz, 'label' => $tz], DateTimeZone::listIdentifiers()),
            ),
            'account' => array_merge(
                [['value' => '', 'label' => 'None']],
                array_map(
                    fn (int $id, string $name) => ['value' => (string) $id, 'label' => $name],
                    array_keys($accounts),
                    array_values($accounts),
                ),
            ),
            default => $def['options'] ?? [],
        };
    }

    /** One line of plain text showing a value; the view escapes it. */
    private function display(array $def, mixed $value): string
    {
        $label = fn (mixed $v) => collect($def['options'] ?? [])->firstWhere('value', (string) $v)['label'] ?? (string) $v;

        return match ($def['type']) {
            'toggle' => $value ? 'On' : 'Off',
            'tax_rules' => collect($value ?: [])
                ->map(fn ($r) => $r['name'].' '.$r['value'].(($r['type'] ?? '') === 'percentage' ? '%' : ''))
                ->implode(', ') ?: 'None',
            'multiselect' => collect($value ?: [])->map($label)->implode(', ') ?: 'None',
            'courier' => collect($value ?: [])
                ->filter(fn ($service) => ! empty($service['enabled']))
                ->map(fn ($service, $key) => $label($key).' ('.($service['charge'] ?? 0).')')
                ->implode(', ') ?: 'None',
            'select', 'account', 'currency', 'timezone' => $value === null || $value === '' ? 'Not set' : $label($value),
            'number' => $value === null || $value === '' ? 'Not set' : $value.(isset($def['unit']) ? ' '.$def['unit'] : ''),
            default => $value === null || $value === '' ? 'Not set' : (string) $value,
        };
    }

    private function normalize(array $def, mixed $value): mixed
    {
        return $def['type'] === 'number' && ($def['integer'] ?? false) ? (int) $value : $value;
    }

    /** @throws ValidationException */
    private function validate(Business $business, array $def, mixed $value): void
    {
        $attributes = ['value' => $def['label']];

        $rules = match ($def['type']) {
            'toggle' => ['value' => ['required', 'boolean']],
            'select' => ['value' => ['required', Rule::in(array_column($def['options'], 'value'))]],
            'currency' => ['value' => ['required', 'regex:/^[A-Za-z]{3}$/']],
            'timezone' => ['value' => ['nullable', 'timezone:all']],
            // saveForBusiness() quietly ignores an account from another business; refuse it here instead.
            'account' => ['value' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('business_id', $business->id)]],
            'number' => ['value' => array_values(array_filter([
                ($def['nullable'] ?? false) ? 'nullable' : 'required',
                ($def['integer'] ?? false) ? 'integer' : 'numeric',
                isset($def['min']) ? 'min:'.$def['min'] : null,
                isset($def['max']) ? 'max:'.$def['max'] : null,
            ]))],
            'url' => ['value' => ['nullable', 'string', 'url:http,https', 'max:2048']],
            'multiselect' => [
                'value' => ['present', 'array'],
                'value.*' => [Rule::in(array_column($def['options'], 'value'))],
            ],
            'tax_rules' => [
                'value' => ['present', 'array'],
                'value.*.id' => ['nullable', 'string', 'max:36'],
                'value.*.name' => ['required', 'string', 'max:50'],
                'value.*.type' => ['required', 'in:percentage,flat'],
                'value.*.value' => ['required', 'numeric', 'min:0'],
            ],
            'courier' => [
                'value' => ['present', 'array'],
                'value.*.enabled' => ['required', 'boolean'],
                'value.*.charge' => ['nullable', 'numeric', 'min:0'],
            ],
            default => ['value' => array_values(array_filter([
                ($def['required'] ?? false) ? 'required' : 'nullable',
                'string',
                isset($def['max']) ? 'max:'.$def['max'] : null,
            ]))],
        };

        if ($def['type'] === 'tax_rules') {
            $attributes += ['value.*.name' => 'tax name', 'value.*.type' => 'tax type', 'value.*.value' => 'tax value'];
        }
        if ($def['type'] === 'courier') {
            $attributes += ['value.*.charge' => 'courier charge'];
        }

        Validator::make(['value' => $value], $rules, [], $attributes)->validate();
    }

    /** @throws ValidationException|NotFoundHttpException */
    private function validatedOther(Business $business, string $key, mixed $value): mixed
    {
        $type = $this->otherType($business, $key);

        $rules = match ($type) {
            'boolean' => ['required', 'boolean'],
            'integer' => ['required', 'integer'],
            'float' => ['required', 'numeric'],
            'json' => ['required', 'string', 'json'],
            default => ['nullable', 'string', 'max:2000'],
        };
        Validator::make(['value' => $value], ['value' => $rules], [], ['value' => $key])->validate();

        // Kept in the type the row already has, so the code reading it isn't surprised.
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => $this->decodedJson($key, (string) $value),
            default => (string) $value,
        };
    }

    /** @throws ValidationException */
    private function decodedJson(string $key, string $value): array
    {
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['value' => "{$key} must be a JSON list or object."]);
        }

        return $decoded;
    }

    /**
     * Stored type of a row outside the catalog. Anything else, such as mail credentials,
     * is not reachable from this screen.
     *
     * @throws NotFoundHttpException
     */
    private function otherType(Business $business, string $key): string
    {
        $type = $this->isOtherKey($key) ? $business->settings()->where('key', $key)->value('value_type') : null;
        if ($type === null) {
            throw new NotFoundHttpException('Unknown POS setting.');
        }

        return (string) $type;
    }

    private function isOtherKey(string $key): bool
    {
        return ! in_array($key, Catalog::claimedKeys(), true)
            && collect(self::OTHER_PREFIXES)->contains(fn (string $prefix) => str_starts_with($key, $prefix));
    }

    private function audit(string $action, Business $business, string $setting): void
    {
        Log::info("Admin {$action} a POS setting", [
            'admin_id' => Auth::id(),
            'business_id' => $business->id,
            'setting' => $setting,
        ]);
    }
}
