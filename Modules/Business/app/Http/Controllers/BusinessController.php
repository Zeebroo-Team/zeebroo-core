<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Business\Services\BranchService;
use Modules\Business\Services\BusinessBrandCopyGeneratorService;
use Modules\Business\Services\BusinessProfileSettingSync;
use Modules\Business\Services\BusinessService;
use Modules\Business\Services\GoogleBusinessProfileApiClient;
use Modules\Business\Support\BrandCompanyCategoryCatalog;
use Modules\Business\Support\LogoGenerationCatalog;
use Modules\Settings\Services\SettingsService;

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService,
        private readonly BranchService $branchService,
        private readonly BusinessProfileSettingSync $businessProfileSettingSync,
        private readonly SettingsService $settingsService,
        private readonly GoogleBusinessProfileApiClient $googleBusinessProfileApiClient,
    ) {}

    public function profile(Request $request): ViewContract|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);
        $business->load(['branches' => static fn ($q) => $q->orderBy('name')->orderBy('id')]);

        $brandFeatures = [];
        $rawFeatures = $business->brand_features;
        if (is_array($rawFeatures)) {
            foreach ($rawFeatures as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $brandFeatures[] = [
                    'title' => (string) ($item['title'] ?? ''),
                    'content' => (string) ($item['content'] ?? ''),
                ];
            }
        }

        return view('business::profile', [
            'title' => 'Business profile',
            'heading' => 'Business profile',
            'business' => $business,
            'logoAiCategories' => LogoGenerationCatalog::categories(),
            'logoAiStyles' => LogoGenerationCatalog::styles(),
            'logoAiBackgrounds' => LogoGenerationCatalog::backgrounds(),
            'brandCategories' => BrandCompanyCategoryCatalog::options(),
            'brandFeatures' => $brandFeatures,
            'googleBp' => [
                'oauthConnected' => $this->googleBusinessProfileApiClient->userHasGoogleConnection($request->user()),
                'manageScopeConfigured' => (bool) config('services.google.business_manage_scope'),
                'linkedResource' => $business->google_location_resource,
                'linkedTitle' => $business->google_location_title_cache,
            ],
        ]);
    }

    public function updateBrand(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $slugKeys = array_column(BrandCompanyCategoryCatalog::options(), 'value');

        $validated = $request->validate([
            'company_category_slug' => ['required', 'string', Rule::in($slugKeys)],
            'short_description' => ['nullable', 'string', 'max:360'],
            'description' => ['nullable', 'string', 'max:6000'],
            'feature_items' => ['nullable', 'array', 'max:12'],
            'feature_items.*.title' => ['nullable', 'string', 'max:140'],
            'feature_items.*.content' => ['nullable', 'string', 'max:2000'],
        ]);

        $rows = [];
        foreach ($validated['feature_items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $t = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['content'] ?? ''));
            if ($t === '' && $body === '') {
                continue;
            }
            $rows[] = [
                'title' => $t !== '' ? $t : 'Feature',
                'content' => $body,
            ];
        }
        if (count($rows) > 12) {
            $rows = array_slice($rows, 0, 12);
        }

        $slug = $validated['company_category_slug'];
        $label = BrandCompanyCategoryCatalog::labelsByValue()[$slug] ?? $slug;

        $short = isset($validated['short_description']) ? trim((string) $validated['short_description']) : '';
        $long = isset($validated['description']) ? trim((string) $validated['description']) : '';

        $business->update([
            'company_category_slug' => $slug,
            'category' => $label,
            'short_description' => $short === '' ? null : $short,
            'description' => $long === '' ? null : $long,
            'brand_features' => $rows === [] ? null : $rows,
        ]);

        /** @var Business $synced */
        $synced = $business->fresh();
        $this->businessProfileSettingSync->mirrorModelToSettings($this->settingsService, $synced);

        return redirect()->route('business.profile')->with('status', 'Brand profile saved.');
    }

    public function generateBrandCopy(Request $request, BusinessBrandCopyGeneratorService $generator): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return response()->json(['error' => 'No business selected.'], 404);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $slugKeys = array_column(BrandCompanyCategoryCatalog::options(), 'value');

        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(['short', 'full', 'both'])],
            'company_category_slug' => ['required', 'string', Rule::in($slugKeys)],
            'hint' => ['nullable', 'string', 'max:2000'],
            'existing_short_description' => ['nullable', 'string', 'max:360'],
            'existing_description' => ['nullable', 'string', 'max:6000'],
        ]);

        $result = $generator->generate(
            $business,
            $validated['kind'],
            isset($validated['hint']) ? (string) $validated['hint'] : null,
            $validated['existing_short_description'] ?? null,
            $validated['existing_description'] ?? null,
            $validated['company_category_slug'],
        );

        return response()->json($result, isset($result['error']) ? 422 : 200);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        $path = $validated['logo']->store('business-logos/'.$business->id, 'public');
        $oldPath = $business->logo_path;
        $business->update(['logo_path' => $path]);
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('business.profile')->with('status', 'Logo updated.');
    }

    public function storeCreatorLogo(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'creator_png' => ['required', 'string', 'max:4_000_000'],
        ]);

        $payload = $validated['creator_png'];
        if (str_contains($payload, ',')) {
            $payload = substr($payload, strpos($payload, ',') + 1);
        }

        $binary = base64_decode($payload, true);
        if ($binary === false) {
            return redirect()->route('business.profile')->withErrors(['creator_png' => 'Invalid image data.']);
        }

        $length = strlen($binary);
        if ($length < 32 || $length > 2_500_000) {
            return redirect()->route('business.profile')->withErrors(['creator_png' => 'Image size not allowed.']);
        }

        if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return redirect()->route('business.profile')->withErrors(['creator_png' => 'Only PNG is supported.']);
        }

        $meta = @getimagesizefromstring($binary);
        if ($meta === false || (($meta['mime'] ?? '') !== 'image/png')) {
            return redirect()->route('business.profile')->withErrors(['creator_png' => 'Could not validate PNG image.']);
        }

        [$w, $h] = $meta;
        if ($w > 1024 || $h > 1024 || $w < 16 || $h < 16) {
            return redirect()->route('business.profile')->withErrors(['creator_png' => 'Image must be between 16×16 and 1024×1024 pixels.']);
        }

        $path = 'business-logos/'.$business->id.'/creator-'.(string) Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);

        $oldPath = $business->logo_path;
        $business->update(['logo_path' => $path]);
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('business.profile')->with('status', 'Generated logo saved.');
    }

    public function destroyLogo(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Create a business profile first.']);
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $oldPath = $business->logo_path;
        $business->update(['logo_path' => null]);
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('business.profile')->with('status', 'Logo removed.');
    }

    public function acknowledgeWarehouseIntro(Request $request): RedirectResponse
    {
        $business = Business::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business profile found.']);
        }

        if (Business::query()->whereKey($business->id)->whereNotNull('warehouse_branch_intro_acknowledged_at')->exists()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'multi_warehouse_branch' => ['required', Rule::in(['0', '1'])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $enabled = $validated['multi_warehouse_branch'] === '1';

        $branchData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        $updatedRows = 0;

        DB::transaction(function () use ($business, $enabled, &$updatedRows, $branchData): void {
            $business->setSetting('business.multi_warehouse_branch', $enabled);
            $updatedRows = Business::query()
                ->whereKey($business->id)
                ->whereNull('warehouse_branch_intro_acknowledged_at')
                ->update([
                    'warehouse_branch_intro_acknowledged_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->branchService->create($business, $branchData);
        });

        /** @var int|string $bizId */
        $bizId = $business->getKey();
        if ($updatedRows > 0) {
            session()->put('warehouse_intro_ack.'.$bizId, true);
        }

        $status = $enabled
            ? 'Multi-location mode on—we saved your choice and primary location.'
            : 'Single location saved—we captured your premises details and hid branch management shortcuts.';

        return redirect()->route('dashboard')->with('status', $status);
    }

    public function storeOnboarding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_category_slug' => [
                'required',
                'string',
                'max:64',
                Rule::exists('business_categories', 'slug')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = $validated['company_category_slug'];
        $label = BrandCompanyCategoryCatalog::labelsByValue()[$slug] ?? $slug;

        $this->businessService->upsertForUser($request->user(), [
            'name' => $validated['name'],
            'category' => $label,
            'company_category_slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('dashboard')->with('status', 'Business profile saved.');
    }
}
