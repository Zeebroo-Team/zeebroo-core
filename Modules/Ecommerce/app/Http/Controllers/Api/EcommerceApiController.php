<?php

namespace Modules\Ecommerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Listing;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\PaymentGateway;
use Modules\Ecommerce\Models\Slide;
use Modules\Ecommerce\Models\Store;
use Modules\Ecommerce\Services\ListingService;
use Modules\Ecommerce\Services\OrderService;
use Modules\Ecommerce\Services\PaymentGatewayService;
use Modules\Ecommerce\Services\SlideService;
use Modules\Ecommerce\Services\StoreService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Desktop (Electron) management API for the business's online store.
 * Every endpoint is gated by the `ecom_access` member permission.
 */
class EcommerceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly StoreService          $stores,
        private readonly ListingService        $listings,
        private readonly OrderService          $orders,
        private readonly PaymentGatewayService $gateways,
        private readonly SlideService          $slides,
    ) {}

    private function ecomBusiness(Request $request): Business
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'ecom_access');

        return $business;
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $store    = $this->stores->forBusiness($business);

        $recent = Order::where('business_id', $business->id)
            ->withCount('items')
            ->orderByDesc('placed_at')->orderByDesc('id')
            ->limit(8)->get();

        return response()->json(['data' => array_merge($this->stores->dashboard($business), [
            'store'         => $this->fmtStore($store),
            'recent_orders' => $recent->map(fn ($o) => $this->orders->format($o))->values(),
        ])]);
    }

    // ── Store settings (website) ─────────────────────────────────────────────

    public function storeShow(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);

        return response()->json(['data' => $this->fmtStore($this->stores->forBusiness($business))]);
    }

    public function storeUpdate(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $store    = $this->stores->forBusiness($business);

        $validated = $request->validate([
            'name'                  => 'sometimes|required|string|max:150',
            'slug'                  => ['sometimes', 'required', 'string', 'max:70', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'tagline'               => 'nullable|string|max:200',
            'about'                 => 'nullable|string|max:5000',
            'logo_url'              => 'nullable|url|max:500',
            'banner_url'            => 'nullable|url|max:500',
            'theme_color'           => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'template'              => ['sometimes', Rule::in(array_keys(Store::TEMPLATES))],
            'contact_email'         => 'nullable|email|max:150',
            'contact_phone'         => 'nullable|string|max:40',
            'address'               => 'nullable|string|max:500',
            'currency'              => 'nullable|string|max:10',
            'is_published'          => 'sometimes|boolean',
            'cod_enabled'           => 'sometimes|boolean',
            'bank_transfer_enabled' => 'sometimes|boolean',
            'bank_transfer_details' => 'nullable|string|max:2000',
            'shipping_flat_rate'    => 'sometimes|numeric|min:0',
            'free_shipping_over'    => 'nullable|numeric|min:0',
            'min_order_amount'      => 'nullable|numeric|min:0',
            'announcement'          => 'nullable|string|max:255',
            'social_links'          => 'nullable|array',
            'social_links.*'        => 'nullable|url|max:300',
            'seo_title'             => 'nullable|string|max:160',
            'seo_description'       => 'nullable|string|max:320',
        ], [
            'slug.regex' => 'The web address may only contain lowercase letters, numbers and single dashes.',
        ]);

        if (array_key_exists('slug', $validated)
            && Store::where('slug', $validated['slug'])->where('id', '!=', $store->id)->exists()) {
            return response()->json([
                'message' => 'That web address is already taken.',
                'errors'  => ['slug' => ['That web address is already taken.']],
            ], 422);
        }

        $store = $this->stores->update($store, $validated);

        return response()->json(['data' => $this->fmtStore($store)]);
    }

    // ── Online products (listings) ───────────────────────────────────────────

    public function listingIndex(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $list     = $this->listings->list($business, [
            'search' => (string) $request->query('q', ''),
            'state'  => (string) $request->query('state', ''),
        ]);

        return response()->json(['data' => $list->map(fn ($l) => $this->listings->format($l))->values()]);
    }

    public function listingCandidates(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $products = $this->listings->candidates($business, trim((string) $request->query('q', '')));

        return response()->json(['data' => $products->map(fn ($p) => [
            'id'             => $p->id,
            'name'           => $p->name,
            'sku'            => $p->sku,
            'unit_price'     => (float) $p->unit_price,
            'stock_quantity' => (float) $p->stock_quantity,
            'image_url'      => $p->imageUrl(),
        ])->values()]);
    }

    public function listingStore(Request $request): JsonResponse
    {
        $business  = $this->ecomBusiness($request);
        $validated = $request->validate([
            'product_ids'   => 'required|array|min:1|max:500',
            'product_ids.*' => 'integer',
        ]);

        $added = $this->listings->publish($business, $validated['product_ids']);

        return response()->json(['data' => ['added' => $added]], 201);
    }

    public function listingUpdate(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $listing  = Listing::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'is_published'     => 'sometimes|boolean',
            'is_featured'      => 'sometimes|boolean',
            'online_price'     => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'title'            => 'nullable|string|max:200',
            'description'      => 'nullable|string|max:5000',
            'sort_order'       => 'sometimes|integer|min:0',
        ]);

        return response()->json(['data' => $this->listings->format($this->listings->update($listing, $validated))]);
    }

    public function listingDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        Listing::where('business_id', $business->id)->where('id', $id)->firstOrFail()->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    public function orderIndex(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $page     = $this->orders->list($business, [
            'status'   => (string) $request->query('status', ''),
            'search'   => (string) $request->query('q', ''),
            'per_page' => min(100, max(5, (int) $request->query('per_page', 25))),
        ]);

        return response()->json([
            'data' => collect($page->items())->map(fn ($o) => $this->orders->format($o))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function orderShow(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $order    = Order::where('business_id', $business->id)->where('id', $id)->with('items')->firstOrFail();

        return response()->json(['data' => $this->orders->format($order, true)]);
    }

    public function orderUpdate(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $order    = Order::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'status'         => ['sometimes', Rule::in(Order::STATUSES)],
            'payment_status' => ['sometimes', Rule::in(Order::PAYMENT_STATUSES)],
            'tracking_no'    => 'nullable|string|max:80',
            'internal_note'  => 'nullable|string|max:2000',
        ]);

        return response()->json(['data' => $this->orders->format($this->orders->update($order, $validated), true)]);
    }

    // ── Payment gateways ─────────────────────────────────────────────────────

    public function gatewayIndex(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $store    = $this->stores->forBusiness($business);

        return response()->json(['data' => [
            'currency' => $store->currency,
            'offline'  => [
                'cod_enabled'           => $store->cod_enabled,
                'bank_transfer_enabled' => $store->bank_transfer_enabled,
                'bank_transfer_details' => $store->bank_transfer_details,
            ],
            'gateways' => $this->gateways->all($business)->map(fn ($g) => $this->gateways->format($g))->values(),
        ]]);
    }

    public function gatewayUpdate(Request $request, string $gateway): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        abort_unless(isset(PaymentGateway::CATALOG[$gateway]), 404);

        $meta  = PaymentGateway::CATALOG[$gateway];
        $rules = ['is_enabled' => 'sometimes|boolean', 'is_sandbox' => 'sometimes|boolean'];
        foreach (array_merge($meta['public'], $meta['secret']) as $f) {
            $rules[$f] = 'nullable|string|max:255';
        }
        $validated = $request->validate($rules);

        if (! empty($validated['is_enabled'])) {
            $currency = strtoupper((string) $this->stores->forBusiness($business)->currency);
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                return response()->json([
                    'message' => 'Set a 3-letter ISO currency code (e.g. LKR, USD) in Store Settings before enabling online payments.',
                    'errors'  => ['currency' => ['A 3-letter ISO currency code is required.']],
                ], 422);
            }
        }

        $row = $this->gateways->update($business, $gateway, $validated);

        return response()->json(['data' => $this->gateways->format($row)]);
    }

    // ── Website templates ────────────────────────────────────────────────────

    public function templateIndex(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $store    = $this->stores->forBusiness($business);

        return response()->json(['data' => [
            'current'   => $store->template ?: 'classic',
            'templates' => collect(Store::TEMPLATES)->map(fn ($t, $k) => ['key' => $k] + $t)->values(),
        ]]);
    }

    // ── Homepage slider ──────────────────────────────────────────────────────

    private function slideRules(bool $creating): array
    {
        return [
            'image_url'   => ($creating ? 'required' : 'sometimes|required') . '|url|max:500',
            'title'       => 'nullable|string|max:150',
            'subtitle'    => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:40',
            'button_link' => 'nullable|url|max:500',
            'is_active'   => 'sometimes|boolean',
        ];
    }

    public function slideIndex(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);

        return response()->json(['data' => $this->slides->list($business)->map(fn ($s) => $this->slides->format($s))->values()]);
    }

    public function slideStore(Request $request): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        if (Slide::where('business_id', $business->id)->count() >= 10) {
            return response()->json(['message' => 'A store can have up to 10 slides.'], 422);
        }
        $slide = $this->slides->create($business, $request->validate($this->slideRules(true)));

        return response()->json(['data' => $this->slides->format($slide)], 201);
    }

    public function slideUpdate(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        $slide    = Slide::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        return response()->json(['data' => $this->slides->format($this->slides->update($slide, $request->validate($this->slideRules(false))))]);
    }

    public function slideDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->ecomBusiness($request);
        Slide::where('business_id', $business->id)->where('id', $id)->firstOrFail()->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function slideReorder(Request $request): JsonResponse
    {
        $business  = $this->ecomBusiness($request);
        $validated = $request->validate(['ids' => 'required|array|max:50', 'ids.*' => 'integer']);
        $this->slides->reorder($business, $validated['ids']);

        return response()->json(['data' => $this->slides->list($business)->map(fn ($s) => $this->slides->format($s))->values()]);
    }

    // ── Formatting ───────────────────────────────────────────────────────────

    private function fmtStore(Store $s): array
    {
        return [
            'id'                    => $s->id,
            'name'                  => $s->name,
            'slug'                  => $s->slug,
            'url'                   => $s->publicUrl(),
            'tagline'               => $s->tagline,
            'about'                 => $s->about,
            'logo_url'              => $s->logo_url,
            'banner_url'            => $s->banner_url,
            'theme_color'           => $s->theme_color,
            'template'              => $s->template ?: 'classic',
            'contact_email'         => $s->contact_email,
            'contact_phone'         => $s->contact_phone,
            'address'               => $s->address,
            'currency'              => $s->currency,
            'is_published'          => $s->is_published,
            'cod_enabled'           => $s->cod_enabled,
            'bank_transfer_enabled' => $s->bank_transfer_enabled,
            'bank_transfer_details' => $s->bank_transfer_details,
            'shipping_flat_rate'    => (float) $s->shipping_flat_rate,
            'free_shipping_over'    => $s->free_shipping_over !== null ? (float) $s->free_shipping_over : null,
            'min_order_amount'      => $s->min_order_amount !== null ? (float) $s->min_order_amount : null,
            'announcement'          => $s->announcement,
            'social_links'          => $s->social_links ?? (object) [],
            'seo_title'             => $s->seo_title,
            'seo_description'       => $s->seo_description,
        ];
    }
}
