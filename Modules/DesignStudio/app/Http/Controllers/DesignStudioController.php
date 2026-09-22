<?php

namespace Modules\DesignStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\DesignStudio\Models\Design;
use Modules\DesignStudio\Models\SocialMediaConnection;
use Modules\Settings\Services\SettingsService;

class DesignStudioController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $quickstartTypes = ['letterhead', 'company-profile', 'social-media', 'business-card', 'custom', 'sales-campaign', 'hire-designer'];

        // My Designs: only free designs (not linked to a quickstart card)
        $designs = Design::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($quickstartTypes) {
                $q->whereNull('type')->orWhereNotIn('type', $quickstartTypes);
            })
            ->orderByDesc('updated_at')
            ->get();

        // Separate query for quickstart cards — keyed by type.
        // orderBy ASC so keyBy (last-wins) picks the most-recently-updated design per type.
        $designsByType = Design::query()
            ->where('business_id', $business->id)
            ->whereIn('type', $quickstartTypes)
            ->orderBy('updated_at')
            ->get()
            ->keyBy('type');

        $mainBranch = $business->branches()->first();

        return view('designstudio::hub.index', [
            'business'      => $business,
            'designs'       => $designs,
            'designsByType' => $designsByType,
            'mainBranch'    => $mainBranch,
        ]);
    }

    public function letterheadLinks(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $letterhead = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first();

        $allDocTypes = ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation'];
        $enabled     = (array) get_settings('design_studio.lh_links', $allDocTypes, $business);

        return view('designstudio::hub.letterhead-links', [
            'business'   => $business,
            'letterhead' => $letterhead,
            'enabled'    => array_values(array_intersect($enabled, $allDocTypes)),
        ]);
    }

    public function toggleLetterheadLink(Request $request): JsonResponse|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $allDocTypes = ['po', 'grn', 'hr_payslip', 'hr_salary_sheet', 'sales_quotation'];
        $raw         = $request->input('enabled', []);
        $enabled     = array_values(array_intersect((array) $raw, $allDocTypes));

        app(SettingsService::class)->set($business, 'design_studio.lh_links', $enabled);

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function socialMedia(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $designs = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'social-media')
            ->orderByDesc('updated_at')
            ->get();

        $facebookPages = SocialMediaConnection::query()
            ->where('business_id', $business->id)
            ->where('platform', 'facebook')
            ->orderBy('name')
            ->get();

        return view('designstudio::hub.social-media', [
            'business'      => $business,
            'designs'       => $designs,
            'facebookPages' => $facebookPages,
        ]);
    }

    public function letterhead(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $letterhead = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'letterhead')
            ->latest('updated_at')
            ->first();

        return view('designstudio::hub.letterhead', [
            'business'   => $business,
            'letterhead' => $letterhead,
        ]);
    }

    public function companyProfile(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $companyProfile = Design::query()
            ->where('business_id', $business->id)
            ->where('type', 'company-profile')
            ->latest('updated_at')
            ->first();

        return view('designstudio::hub.company-profile', [
            'business'       => $business,
            'companyProfile' => $companyProfile,
        ]);
    }

    public function typeIndex(Request $request, string $type): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $config = self::typePageConfig()[$type] ?? null;
        abort_if($config === null, 404);

        $designs = Design::query()
            ->where('business_id', $business->id)
            ->where('type', $type)
            ->orderByDesc('updated_at')
            ->get();

        return view('designstudio::hub.type', [
            'business' => $business,
            'designs'  => $designs,
            'type'     => $type,
            'config'   => $config,
        ]);
    }

    /**
     * Icon/color/preset-size config for the generic per-type design gallery
     * pages, mirroring the Electron app's design-list view for these types.
     */
    public static function typePageConfig(): array
    {
        return [
            'business-card' => [
                'label' => 'Business Card',
                'icon'  => 'fa-id-card',
                'color' => '#f59e0b',
                'desc'  => 'Professional business cards for networking and branding.',
                'presets' => [
                    ['label' => 'Standard Card', 'w' => 1050, 'h' => 600],
                ],
            ],
            'custom' => [
                'label' => 'Custom Design',
                'icon'  => 'fa-paintbrush',
                'color' => '#10b981',
                'desc'  => 'Start from a blank canvas — posters, flyers, certificates and more.',
                'presets' => [
                    ['label' => 'Poster / Flyer', 'w' => 1200, 'h' => 1600],
                    ['label' => 'Certificate',    'w' => 1600, 'h' => 1200],
                    ['label' => 'Webinar Banner', 'w' => 1200, 'h' => 800],
                ],
            ],
            'sales-campaign' => [
                'label' => 'Sales Campaign',
                'icon'  => 'fa-bullhorn',
                'color' => '#ef4444',
                'desc'  => 'Promos, discounts and offers for your sales campaigns.',
                'presets' => [
                    ['label' => 'Campaign Post', 'w' => 1080, 'h' => 1080],
                ],
            ],
            'hire-designer' => [
                'label' => 'Hire a Designer',
                'icon'  => 'fa-user-tie',
                'color' => '#0284c7',
                'desc'  => 'Job posting posters to find your next design hire.',
                'presets' => [
                    ['label' => 'Job Poster', 'w' => 1200, 'h' => 1600],
                ],
            ],
        ];
    }

    public function resolveBusiness(Request $request): Business|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $business = Business::query()
            ->where('user_id', $user->id)
            ->first();

        if ($business === null) {
            return redirect()->route('business.create');
        }

        return $business;
    }
}
