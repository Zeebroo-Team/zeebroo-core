<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Branch;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosSettingsService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductStockLayer;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Order;
use Modules\Restaurant\Models\RestaurantTable;

class PosSettingsApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosSettingsService $posSettings,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->posSettings->forBusiness($business);

        $includes = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));
        if (in_array('assignment_targets', $includes, true)) {
            $user = $request->user();

            $branches = $this->safeQuery(fn () => Branch::where('business_id', $business->id)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values());

            $departments = $this->safeQuery(function () use ($business) {
                if (! Schema::hasTable('hr_departments')) {
                    return collect();
                }
                return \Modules\HRManagement\Models\Department::where('business_id', $business->id)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values();
            });

            $properties = $this->safeQuery(function () use ($business, $user) {
                if (! Schema::hasTable('properties')) {
                    return collect();
                }
                return \Modules\Account\Models\Property::where('business_id', $business->id)
                    ->where('user_id', $user->id)->orderBy('property_name')
                    ->get(['id', 'property_name', 'property_type'])
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->property_name . ' · ' . $p->property_type])->values();
            });

            $employees = $this->safeQuery(function () use ($business) {
                if (! Schema::hasTable('hr_employees')) {
                    return collect();
                }
                return \Modules\HRManagement\Models\Employee::where('business_id', $business->id)
                    ->orderBy('full_name')->get(['id', 'full_name', 'employee_id'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name . ($e->employee_id ? '  #' . $e->employee_id : '')])->values();
            });

            $modifications = $this->safeQuery(function () use ($business) {
                if (! Schema::hasTable('modifications')) {
                    return collect();
                }
                return \Modules\Modification\Models\Modification::where('business_id', $business->id)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values();
            });

            $rentals = $this->safeQuery(function () use ($business, $user) {
                if (! Schema::hasTable('rentals')) {
                    return collect();
                }
                return \Modules\Account\Models\Rental::where('business_id', $business->id)
                    ->where('user_id', $user->id)->orderBy('property_type')
                    ->get(['id', 'property_type', 'purpose'])
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->property_type . ($r->purpose ? '  ·  ' . $r->purpose : '')])->values();
            });

            $data['assignment_targets'] = [
                'branches'      => $branches,
                'departments'   => $departments,
                'properties'    => $properties,
                'employees'     => $employees,
                'modifications' => $modifications,
                'rentals'       => $rentals,
            ];
        }

        return response()->json(['data' => $data]);
    }

    private function safeQuery(callable $fn): Collection
    {
        try {
            return $fn() ?? collect();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function features(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $all      = ['account_management','automation_editor','bill_management','crm','developers','event_management','human_resources','mail','point_of_sale','product_management','project_management','restaurant','service_management','social_media_campaign','stock_management'];
        $stored   = $business->getSetting('business.features') ?? [];
        $enabled  = array_values(array_filter($all, fn ($k) => ! empty($stored[$k])));

        // account_management is always enabled; event_management is user-controlled.
        if (! in_array('account_management', $enabled, true)) {
            $enabled[] = 'account_management';
        }
        return response()->json(['data' => $enabled]);
    }

    public function updateFeatures(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $all      = ['account_management','automation_editor','bill_management','crm','developers','event_management','human_resources','mail','point_of_sale','product_management','project_management','restaurant','service_management','social_media_campaign','stock_management'];
        $validated = $request->validate([
            'features'   => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);
        $input   = $validated['features'];
        $stored  = array_fill_keys($all, false);
        foreach ($all as $k) {
            $stored[$k] = (bool) ($input[$k] ?? false);
        }
        $stored['account_management'] = true;
        $business->setSetting('business.features', $stored);
        $enabled = array_values(array_filter($all, fn ($k) => $stored[$k]));
        return response()->json(['data' => $enabled]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'business_name'               => ['nullable', 'string', 'max:255'],
            'currency'                    => ['nullable', 'string', 'max:10'],
            'timezone'                    => ['nullable', 'string', 'max:80'],
            'default_deposit_account_id'  => ['nullable', 'integer', 'min:1'],
            'discount_field_enabled'      => ['nullable', 'boolean'],
            'checkout_modal_enabled'      => ['nullable', 'boolean'],
            'display_theme'               => ['nullable', 'string', 'in:light,dark,inherit'],
            'receipt_mode'                => ['nullable', 'string', 'in:bill,invoice'],
            'receipt_logo_url'            => ['nullable', 'string', 'max:1000'],
            'business_logo_url'           => ['nullable', 'string', 'max:1000'],
            'receipt_header'              => ['nullable', 'string', 'max:200'],
            'receipt_footer'              => ['nullable', 'string', 'max:200'],
            'show_business_name'          => ['nullable', 'boolean'],
            'show_business_address'       => ['nullable', 'boolean'],
            'receipt_address_line'        => ['nullable', 'string', 'max:300'],
            'receipt_language'            => ['nullable', 'string', 'in:en,si,ta'],
            'show_account_info'           => ['nullable', 'boolean'],
            'payment_settlement_mode'     => ['nullable', 'string', 'in:immediate,end_of_day'],
            'featured_products_limit'     => ['nullable', 'integer', 'min:0', 'max:200'],
            'featured_categories_limit'   => ['nullable', 'integer', 'min:0', 'max:200'],
            'show_service_bound_products' => ['nullable', 'boolean'],
            'dont_settle_to_account'     => ['nullable', 'boolean'],
            'stock_selection_mode'       => ['nullable', 'string', 'in:fifo,choose,last_price'],
            'choose_price'               => ['nullable', 'boolean'],
            // Branch
            'multi_warehouse_branch'  => ['nullable', 'boolean'],
            'branch_product_separate' => ['nullable', 'boolean'],
            'branch_stock_separate'   => ['nullable', 'boolean'],
            'branch_pos_separate'     => ['nullable', 'boolean'],
            // Tax
            'tax_enabled'          => ['nullable', 'boolean'],
            'tax_rules'            => ['nullable', 'array', 'max:20'],
            'tax_rules.*.id'       => ['nullable', 'string', 'max:36'],
            'tax_rules.*.name'     => ['required_with:tax_rules', 'string', 'max:50'],
            'tax_rules.*.type'     => ['required_with:tax_rules', 'string', 'in:percentage,flat'],
            'tax_rules.*.value'    => ['required_with:tax_rules', 'numeric', 'min:0'],
            // Invoice
            'invoice_prefix'      => ['nullable', 'string', 'max:20'],
            'invoice_next_number' => ['nullable', 'integer', 'min:1'],
            // Delivery
            'delivery_enabled'           => ['nullable', 'boolean'],
            'delivery_methods'           => ['nullable', 'array'],
            'delivery_methods.*'         => ['string', 'in:dhl,fedex,uber,pickme,koobiyo,pronto'],
            // Courier services
            'courier_services'           => ['nullable', 'array'],
            'courier_services.*.enabled' => ['nullable', 'boolean'],
            'courier_services.*.charge'  => ['nullable', 'numeric', 'min:0'],
            // Purchasing workflow
            'purchase_order_enabled'     => ['nullable', 'boolean'],
        ]);

        $this->posSettings->saveForBusiness($business, $validated);

        return response()->json([
            'message' => 'POS settings saved.',
            'data' => $this->posSettings->forBusiness($business),
        ]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $bizId    = $business->id;

        return response()->json([
            'data' => [
                'products'   => Product::where('business_id', $bizId)->max('updated_at'),
                'categories' => ProductCategory::where('business_id', $bizId)->max('updated_at'),
                'stock'      => ProductStockLayer::where('business_id', $bizId)->max('updated_at'),
                'tables'     => RestaurantTable::where('business_id', $bizId)->max('updated_at'),
                'menu_items' => MenuItem::where('business_id', $bizId)->max('updated_at'),
                'rst_orders' => Order::where('business_id', $bizId)->max('updated_at'),
            ],
        ]);
    }
}
