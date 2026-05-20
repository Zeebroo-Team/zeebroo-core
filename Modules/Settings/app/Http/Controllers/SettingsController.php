<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Business\Services\BusinessProfileSettingSync;
use Modules\Business\Support\BrandCompanyCategoryCatalog;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\Settings\Services\SettingsService;

class SettingsController extends Controller
{
    public function index(Request $request, SettingsService $settingsService)
    {
        return $this->user($request, $settingsService);
    }

    public function user(Request $request, SettingsService $settingsService)
    {
        if ($redirect = $this->redirectHrPortalOnlyFromUserSettings($request)) {
            return $redirect;
        }

        $user = $request->user();

        return $this->renderSettingsPage(
            scopeType: 'user',
            scopeModel: $user,
            title: 'User Settings',
            heading: 'User Settings',
            settingsService: $settingsService
        );
    }

    public function business(Request $request, SettingsService $settingsService)
    {
        if ($redirect = $this->redirectHrPortalOnlyFromBusinessSettings($request)) {
            return $redirect;
        }

        $business = $this->resolveBusinessScope($request);

        return $this->renderSettingsPage(
            scopeType: 'business',
            scopeModel: $business,
            title: 'Business Settings',
            heading: 'Business Settings',
            settingsService: $settingsService
        );
    }

    public function store(Request $request, SettingsService $settingsService)
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:user,business'],
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable'],
        ]);

        if ($validated['scope'] === 'business') {
            if ($redirect = $this->redirectHrPortalOnlyFromBusinessSettings($request)) {
                return $redirect;
            }
        } elseif ($validated['scope'] === 'user') {
            if ($redirect = $this->redirectHrPortalOnlyFromUserSettings($request)) {
                return $redirect;
            }
        }

        $user = $request->user();
        $scope = $validated['scope'] === 'business'
            ? $this->resolveBusinessScope($request)
            : $user;

        if (! $scope) {
            return redirect()->back()->withErrors(['scope' => 'Business not found for this user.']);
        }

        $definition = $this->findAugmentedDefinition($validated['scope'], $validated['key'], $scope);
        if (! $definition || ! $definition['is_enabled'] || $definition['is_disabled']) {
            return redirect()->back()->withErrors(['key' => 'Invalid or disabled setting field.']);
        }

        $value = $this->normalizeInputValue($request, $definition);

        if ($definition['required'] && ($value === null || $value === '')) {
            return redirect()->back()->withErrors(['value' => 'This setting field is required.']);
        }

        $settingsService->set($scope, $validated['key'], $value);

        $routeName = $validated['scope'] === 'business' ? 'settings.business' : 'settings.user';

        return redirect()->route($routeName)->with('status', 'Setting saved successfully.');
    }

    public function destroy(Request $request, SettingsService $settingsService)
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:user,business'],
            'key' => ['required', 'string', 'max:100'],
        ]);

        if ($validated['scope'] === 'business') {
            if ($redirect = $this->redirectHrPortalOnlyFromBusinessSettings($request)) {
                return $redirect;
            }
        } elseif ($validated['scope'] === 'user') {
            if ($redirect = $this->redirectHrPortalOnlyFromUserSettings($request)) {
                return $redirect;
            }
        }

        $user = $request->user();
        $scope = $validated['scope'] === 'business'
            ? $this->resolveBusinessScope($request)
            : $user;

        if (! $scope) {
            return redirect()->back()->withErrors(['scope' => 'Business not found for this user.']);
        }

        $definition = $this->findAugmentedDefinition($validated['scope'], $validated['key'], $scope);
        if (! $definition || ! $definition['is_enabled'] || $definition['is_disabled']) {
            return redirect()->back()->withErrors(['key' => 'Invalid or disabled setting field.']);
        }

        $settingsService->forget($scope, $validated['key']);

        $routeName = $validated['scope'] === 'business' ? 'settings.business' : 'settings.user';

        return redirect()->route($routeName)->with('status', 'Setting deleted successfully.');
    }

    public function bulkStore(Request $request, SettingsService $settingsService)
    {
        $validatedScope = $request->validate([
            'scope' => ['required', 'in:user,business'],
        ]);

        if ($validatedScope['scope'] === 'business') {
            if ($redirect = $this->redirectHrPortalOnlyFromBusinessSettings($request)) {
                return $redirect;
            }
        } elseif ($validatedScope['scope'] === 'user') {
            if ($redirect = $this->redirectHrPortalOnlyFromUserSettings($request)) {
                return $redirect;
            }
        }

        $user = $request->user();
        $scope = $validatedScope['scope'] === 'business'
            ? $this->resolveBusinessScope($request)
            : $user;

        if ($validatedScope['scope'] === 'business' && ! $scope) {
            return redirect()->back()->withErrors(['scope' => 'Business not found for this user.']);
        }

        $definitionsAll = $this->augmentDefinitionsForScope($validatedScope['scope'], $scope)
            ->filter(fn (array $definition) => $definition['is_enabled'] && ! $definition['is_disabled']);

        $allowedTabs = $definitionsAll->map(fn (array $d) => $this->resolveDefinitionTab($d))->unique()->values()->all();
        if (count($allowedTabs) === 0) {
            return redirect()->back()->withErrors(['settings' => 'No settings sections are available for this scope.']);
        }

        $validatedRest = $request->validate([
            'values' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'tab' => ['required', 'string', Rule::in($allowedTabs)],
        ]);

        $validated = array_merge($validatedScope, $validatedRest);

        $definitions = $definitionsAll->filter(
            fn (array $d) => $this->resolveDefinitionTab($d) === $validated['tab']
        )->values();

        $existingSettings = $settingsService->allForScope($scope);
        $uploadedFiles = $request->file('files', []);

        $inputValues = (array) ($validated['values'] ?? []);
        $inputFiles = (array) ($validated['files'] ?? []);
        $toSave = [];

        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $existingValue = $existingSettings->get($key);

            if ($definition['type'] === 'file') {
                $uploadedFile = is_array($uploadedFiles) && array_key_exists($key, $uploadedFiles)
                    ? $uploadedFiles[$key]
                    : null;
                if ($uploadedFile) {
                    $value = $uploadedFile->store(
                        "settings/{$validated['scope']}/{$scope->getKey()}",
                        'public'
                    );
                } else {
                    $value = $existingValue;
                }
            } else {
                if (array_key_exists($key, $inputValues)) {
                    $rawValue = $inputValues[$key];
                } elseif ($definition['type'] === 'checkbox') {
                    $rawValue = 0;
                } else {
                    $rawValue = $existingValue;
                }
                $value = $this->normalizeRawValue($rawValue, $definition);
            }

            if ($definition['required'] && ($value === null || $value === '')) {
                return redirect()->back()->withErrors(['value' => "The {$definition['name']} field is required."]);
            }

            $toSave[$key] = $value;
        }

        $settingsService->setMany($scope, $toSave);

        if (
            $validated['scope'] === 'business'
            && $validated['tab'] === 'brand'
            && $scope instanceof Business
        ) {
            $freshAll = $settingsService->allForScope($scope);
            app(BusinessProfileSettingSync::class)->applyBrandTabFromSettings($scope, $freshAll);
        }

        $routeName = $validated['scope'] === 'business' ? 'settings.business' : 'settings.user';
        $callbackUrl = route($routeName).'?'.http_build_query(['tab' => $validated['tab']]);

        return redirect()->to($callbackUrl)->with('status', 'Settings saved successfully.');
    }

    private function redirectHrPortalOnlyFromBusinessSettings(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->isHrPortalOnlyUser()) {
            return redirect()->route('hr.portal.dashboard')->with('status', __('Business settings are not available for your employee portal account.'));
        }

        return null;
    }

    private function redirectHrPortalOnlyFromUserSettings(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->isHrPortalOnlyUser()) {
            return redirect()->route('hr.portal.dashboard')->with('status', __('User settings are not available for your employee portal account.'));
        }

        return null;
    }

    private function resolveBusinessScope(Request $request): ?Business
    {
        return Business::currentForNavbar($request->user());
    }

    /** @return Collection<int, array<string,mixed>> */
    private function augmentDefinitionsForScope(string $scopeType, ?Model $scopeModel = null): Collection
    {
        $definitions = $this->getDefinitionsByScope($scopeType);

        if ($scopeType !== 'business') {
            return $definitions;
        }

        return $definitions->map(function (array $definition) use ($scopeModel): array {
            if ($definition['key'] === BusinessProfileSettingSync::KEY_CATEGORY_SLUG) {
                return [
                    ...$definition,
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => $definition['placeholder'] ?: 'Select category…',
                    'options' => BrandCompanyCategoryCatalog::options(),
                ];
            }

            if (($definition['key'] ?? '') === 'hr.head_employee_id' && $scopeModel instanceof Business) {
                $opts = [['label' => '— Not set —', 'value' => '']];
                foreach ($scopeModel->employees()->orderBy('full_name')->orderBy('id')->get() as $emp) {
                    $opts[] = [
                        'label' => $emp->full_name.' ('.$emp->employee_id.')',
                        'value' => (string) $emp->getKey(),
                    ];
                }

                return [
                    ...$definition,
                    'type' => 'select',
                    'options' => $opts,
                    'required' => false,
                ];
            }

            return $definition;
        });
    }

    private function renderSettingsPage(
        string $scopeType,
        ?Model $scopeModel,
        string $title,
        string $heading,
        SettingsService $settingsService
    ) {
        $definitions = $this->augmentDefinitionsForScope($scopeType, $scopeModel);

        $settings = $scopeModel ? $settingsService->allForScope($scopeModel) : collect();

        if ($scopeModel instanceof Business) {
            app(BusinessProfileSettingSync::class)->hydrateBusinessSettingsUi($scopeModel, $settings);
        }

        $tabs = $this->buildTabs($definitions, $settings);

        $businessHolidays = collect();
        $hrPayrollOptedIn = false;
        if ($scopeModel instanceof Business) {
            $businessHolidays = $scopeModel->hrHolidays()->orderBy('holiday_date')->orderBy('id')->get();
            $hrPayrollOptedIn = app(HrPayrollSettingsService::class)->optedIn($scopeModel);
        }

        return view('settings::index', [
            'title' => $title,
            'heading' => $heading,
            'scopeType' => $scopeType,
            'hasScope' => (bool) $scopeModel,
            'scopeModel' => $scopeModel,
            'tabs' => $tabs,
            'businessHolidays' => $businessHolidays,
            'hrPayrollOptedIn' => $hrPayrollOptedIn,
        ]);
    }

    private function buildTabs(Collection $definitions, Collection $settings): Collection
    {
        if ($definitions->isEmpty()) {
            return collect();
        }

        return $definitions
            ->filter(fn (array $definition) => $definition['is_enabled'] && ! $definition['is_disabled'])
            ->map(function (array $definition) use ($settings): array {
                $tab = $this->resolveDefinitionTab($definition);
                $key = $definition['key'];
                $hasValue = $settings->has($key);
                $currentValue = $hasValue ? $settings->get($key) : ($definition['default'] ?? null);

                return [
                    ...$definition,
                    'tab' => $tab,
                    'value' => $currentValue,
                ];
            })
            ->groupBy('tab')
            ->map(fn (Collection $items) => $items->values());
    }

    private function resolveDefinitionTab(array $definition): string
    {
        return (string) (($definition['tab'] ?? '') !== ''
            ? $definition['tab']
            : (explode('.', (string) ($definition['key'] ?? ''))[0] ?: 'general'));
    }

    private function getDefinitionsByScope(string $scopeType): Collection
    {
        $path = base_path('Modules/Settings/database/seeders/settings-fields.json');
        if (! File::exists($path)) {
            return collect();
        }

        $payload = json_decode((string) File::get($path), true);
        $definitions = is_array($payload[$scopeType] ?? null) ? $payload[$scopeType] : [];

        return collect($definitions)
            ->filter(fn ($definition) => is_array($definition) && isset($definition['key']))
            ->map(function (array $definition): array {
                return [
                    'tab' => (string) ($definition['tab'] ?? ''),
                    'key' => (string) ($definition['key'] ?? ''),
                    'name' => (string) ($definition['name'] ?? ($definition['key'] ?? '')),
                    'type' => (string) ($definition['type'] ?? 'text'),
                    'default' => $definition['default'] ?? null,
                    'min' => array_key_exists('min', $definition) && is_numeric($definition['min']) ? (int) $definition['min'] : null,
                    'max' => array_key_exists('max', $definition) && is_numeric($definition['max']) ? (int) $definition['max'] : null,
                    'options' => is_array($definition['options'] ?? null) ? $definition['options'] : [],
                    'required' => (bool) ($definition['required'] ?? false),
                    'description' => (string) ($definition['description'] ?? ''),
                    'placeholder' => (string) ($definition['placeholder'] ?? ''),
                    'is_enabled' => (bool) ($definition['is_enabled'] ?? true),
                    'is_disabled' => (bool) ($definition['is_disabled'] ?? false),
                ];
            })
            ->values();
    }

    private function findAugmentedDefinition(string $scopeType, string $key, ?Model $scopeModel = null): ?array
    {
        return $this->augmentDefinitionsForScope($scopeType, $scopeModel)
            ->first(fn (array $definition) => $definition['key'] === $key);
    }

    private function normalizeInputValue(Request $request, array $definition): mixed
    {
        return $this->normalizeRawValue($request->input('value'), $definition);
    }

    private function normalizeRawValue(mixed $rawValue, array $definition): mixed
    {
        $type = $definition['type'];

        if ($type === 'checkbox') {
            return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === 'number') {
            if ($rawValue === null || $rawValue === '') {
                return isset($definition['default']) && is_numeric($definition['default'])
                    ? (int) $definition['default']
                    : 0;
            }
            if (! is_numeric($rawValue)) {
                return isset($definition['default']) && is_numeric($definition['default'])
                    ? (int) $definition['default']
                    : 0;
            }
            $num = (int) $rawValue;
            $min = $definition['min'] ?? null;
            $max = $definition['max'] ?? null;
            if (is_int($min)) {
                $num = max($min, $num);
            }
            if (is_int($max)) {
                $num = min($max, $num);
            }

            return $num;
        }

        if ($type === 'select') {
            $allowed = collect($definition['options'])
                ->map(fn ($option) => is_array($option) ? ($option['value'] ?? null) : null)
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (string) $value)
                ->values();

            $needle = $rawValue === null || $rawValue === '' ? '' : (string) $rawValue;

            if ($allowed->isNotEmpty() && ! $allowed->contains($needle)) {
                return $definition['default'] ?? null;
            }
        }

        if ($type === 'file') {
            if (is_string($rawValue) && $rawValue !== '' && Storage::disk('public')->exists($rawValue)) {
                return $rawValue;
            }

            return null;
        }

        return $rawValue;
    }
}
