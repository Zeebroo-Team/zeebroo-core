<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\DesignAiChatApiController;
use Modules\Pos\Models\Sale;
use Modules\Product\Services\SaleCampaignService;
use Modules\Purchase\Services\PurchaseService;
use Modules\Sales\Models\Invoice;

readonly class PosAgentToolExecutor
{
    /**
     * Walkthrough IDs the agent can trigger, ported from the ids/descriptions
     * PosGuideChatApiController's old single-shot prompt used to enumerate —
     * now exposed as a tool enum instead of a free-standing prompt section.
     *
     * @var array<string, string>
     */
    private const WALKTHROUGH_DESCRIPTIONS = [
        'add_product' => 'add / create a new product in inventory',
        'edit_product' => "edit / update / change a product — also pass productName and fieldName",
        'add_category' => 'add a new category',
        'open_pos' => 'open / go to Point of Sale',
        'new_sale' => 'start a new sale',
        'home_dashboard' => 'view the main dashboard',
        'view_analytics' => 'view analytics, charts, revenue reports',
        'view_orders' => 'view orders, sales orders, purchase orders',
        'view_customers' => 'view the customer list',
        'view_suppliers' => 'view suppliers',
        'view_expenses' => 'view expenses / bills',
        'view_profit' => 'view profit report / profit & loss',
        'view_payroll' => 'view payroll / employee payments',
        'open_settings' => 'go to settings',
        'open_help' => 'help / keyboard shortcuts',
        'today_summary' => "today's sales summary",
        'recent_activity' => 'recent transactions / activity log',
        'business_flow' => 'business flow overview',
        'pos_new_session' => 'start a new POS session',
        'pos_close_session' => 'close / end a POS session',
        'pos_checkout' => 'checkout / process payment / complete a sale',
        'pos_return' => 'process a return or refund',
        'pos_clear_cart' => 'clear / empty the cart',
        'pos_search' => 'search for products in POS',
        'pos_barcode' => 'scan a barcode',
        'pos_quick_add_product' => 'quick-add an item directly to the POS cart',
        'pos_assign_customer' => 'assign a customer to a sale',
        'pos_accounts' => 'customer accounts / wallet balances',
        'pos_settings' => 'configure POS settings',
        'pos_park_sale' => 'park / hold a sale',
        'pos_recall_sale' => 'recall / restore a parked sale',
        'pos_services_mode' => 'switch POS to services mode',
        'pos_category_filter' => 'filter products by category in POS',
        'inv_products' => 'view the product list in inventory',
        'inv_refresh' => 'refresh the inventory product list',
        'inv_clear_filters' => 'clear inventory product filters',
        'inv_categories' => 'manage product categories in inventory',
        'inv_units' => 'manage units of measure in inventory',
        'inv_stock_audit' => 'do a stock audit / inventory count',
        'inv_brands' => 'manage product brands in inventory',
        'inv_discounts' => 'manage discounts in inventory',
        'inv_purchase_orders' => 'view / create purchase orders',
        'inv_goods_receive' => 'view goods receive notes / GRN',
        'inv_cheques' => 'view / manage supplier cheques',
        'inv_add_supplier' => 'add a new supplier',
        'inv_view_suppliers' => 'view the supplier list in inventory',
        'inv_barcodes' => 'print barcode label sheets',
        'fin_create_bill' => 'create / add a new bill in Finance',
        'fin_view_bills' => 'view the bills list in Finance',
        'fin_loans' => 'view / manage loans in Finance',
        'fin_rentals' => 'view / manage rentals in Finance',
        'fin_properties' => 'view / manage properties / assets in Finance',
        'fin_overview' => 'finance flow overview / finance dashboard',
        'fin_modifications' => 'view / manage property modifications in Finance',
        'fin_bill_detail' => "open a bill's detail page (payment history, transactions, pay a bill)",
        'fin_loan_detail' => "open a loan's detail page (repayment schedule, pay an installment)",
        'fin_rental_detail' => "open a rental's detail page (payment schedule, linked bills, land registry)",
        'fin_modification_detail' => "open a modification's detail page (cost breakdown, contractor, documents)",
        'fin_reports' => 'finance reports / profit analytics / sales reports',
        'hr_employees' => 'view the employees list in HR',
        'hr_add_employee' => 'add / hire a new employee',
        'hr_departments' => 'view / manage departments in HR',
        'hr_payroll_cycles' => 'view payroll cycles list in HR',
        'hr_new_payroll' => 'create / start a new payroll cycle',
        'hr_rule_sets' => 'view / manage payroll rule sets in HR',
        'hr_allowance_types' => 'view / manage allowance types in HR',
        'hr_employee_detail' => "open an employee's detail / profile page",
        'hr_payroll_detail' => "open a payroll cycle's detail page (salary sheet, finalize)",
        'hr_rule_set_detail' => "open a rule set's detail page (view/edit rules)",
        'rst_orders' => 'view the restaurant orders list (with status filter tabs)',
        'rst_new_order' => 'create a new restaurant order',
        'rst_order_detail' => "open a restaurant order's detail (status, items, payment)",
        'rst_tables' => 'view the restaurant floor plan / table layout',
        'rst_reservations' => 'view / manage restaurant reservations / table bookings',
        'rst_menu_items' => 'view the restaurant menu items list',
        'rst_add_menu_item' => 'add / create a new menu item / dish',
        'rst_menu_categories' => 'view / manage restaurant menu categories',
        'rst_ingredients' => 'view / manage kitchen ingredient stock',
        'rst_purchase_orders' => 'view / create restaurant ingredient purchase orders',
        'rst_kitchen' => 'view the kitchen display (KDS) / kitchen tickets',
        'rst_pos' => 'open the restaurant POS (table and takeaway orders)',
        'svc_requests' => 'view all service requests (with status filter chips)',
        'svc_pending_requests' => 'view pending service requests only',
        'svc_catalog' => 'view the service catalog list',
        'svc_new_service' => 'add / create a new service item',
        'svc_item_detail' => "open a service item's detail page (overview, employees, products tabs)",
        'svc_categories' => 'view / manage service categories',
        'svc_add_category' => 'add / create a new service category',
        'svc_refresh' => 'refresh / reload services data',
        'mail_inbox' => 'open / view the mail inbox',
        'mail_compose' => 'compose / write / send a new email',
        'mail_sent' => 'view sent emails / outbox',
        'mail_templates' => 'view / manage mail templates',
        'mail_new_template' => 'add / create a new mail template',
        'mail_filters' => 'view mail filters / inbox rules',
        'mail_scheduled' => 'view scheduled / pending emails',
        'mail_schedule_send' => 'schedule an email to send later',
        'ds_overview' => 'open / view the Design Studio panel overview',
        'ds_new_design' => 'create / add a new design project',
        'ds_proposals' => 'view project proposals list',
        'ds_new_proposal' => 'create / add a new project proposal',
        'ds_letterhead' => 'create / view / open the Letterhead design',
        'ds_business_profile' => 'create / view / open the Business Profile design',
        'ds_social_media' => 'filter / view Social Media designs',
        'ds_business_card' => 'filter / view Business Card designs',
        'ds_all_designs' => 'view all designs (no filter)',
    ];

    public function __construct(
        private SaleCampaignService $saleCampaigns,
        private PurchaseService $purchases,
        private PosGeminiClient $gemini,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function execute(Business $business, string $actorKey, string $name, array $args): array
    {
        try {
            return match ($name) {
                'pos_low_stock' => $this->lowStock($business, false),
                'pos_out_of_stock' => $this->lowStock($business, true),
                'pos_list_products' => $this->listProducts($business, $args),
                'pos_list_expiring_products' => $this->listExpiringProducts($business, $args),
                'pos_today_sales' => $this->todaySales($business),
                'pos_recent_sales' => $this->recentSales($business),
                'pos_top_products' => $this->topProducts($business),
                'pos_overdue_payments' => $this->overduePayments($business),
                'pos_list_suppliers' => $this->listSuppliers($business, $args),
                'pos_prepare_sale_campaign_draft' => $this->prepareSaleCampaignDraft($business, $actorKey, $args),
                'pos_confirm_sale_campaign_insert' => $this->confirmSaleCampaignInsert($business, $actorKey, $args),
                'pos_prepare_purchase_order_draft' => $this->preparePurchaseOrderDraft($business, $actorKey, $args),
                'pos_confirm_purchase_order_insert' => $this->confirmPurchaseOrderInsert($business, $actorKey, $args),
                'pos_generate_campaign_poster' => $this->generateCampaignPoster($business, $args),
                'pos_show_walkthrough' => $this->showWalkthrough($args),
                default => ['error' => 'Unknown tool: '.$name],
            };
        } catch (\Throwable $e) {
            return ['error' => 'Tool failed: '.$e->getMessage()];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function functionDeclarations(): array
    {
        $empty = ['type' => 'object', 'properties' => (object) [], 'required' => []];

        return [
            [
                'name' => 'pos_low_stock',
                'description' => 'Products with low stock (>0 and <=5 units), lowest first. Use for general "low stock" questions.',
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_out_of_stock',
                'description' => 'Products with zero stock quantity. Use for "out of stock" questions and as the base for reorder/purchase-order requests.',
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_list_products',
                'description' => 'General-purpose product listing/filtering. Use for requests like "my 20 lowest-stock products", "products under N qty", or "products in category X". Returns id, name, sku, stock, prices — use the returned product ids for campaign/purchase-order drafts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'max_stock_quantity' => ['type' => 'number', 'description' => 'Only include products with stock_quantity at or below this value.'],
                        'category' => ['type' => 'string', 'description' => 'Filter by category name (partial match).'],
                        'active_only' => ['type' => 'boolean', 'description' => 'Default true — only active products.'],
                        'sort' => ['type' => 'string', 'enum' => ['stock_asc', 'stock_desc', 'name'], 'description' => 'Default stock_asc — use stock_asc to get "lowest stock" products.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 20, max 100.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_list_expiring_products',
                'description' => 'Products with expiry tracking on whose exp_date falls within the given number of days from today. Use for "products expiring soon / in the next N months" requests.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'within_days' => ['type' => 'integer', 'description' => 'Look-ahead window in days, default 60 (~2 months).'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_today_sales',
                'description' => "Today's completed sales: transaction count, revenue, items sold, top products.",
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_recent_sales',
                'description' => 'Completed sales from the last 7 days with totals and payment methods.',
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_top_products',
                'description' => "Top selling products today by revenue.",
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_overdue_payments',
                'description' => 'All overdue payments on both sides: bills the business owes that are overdue ("owed_by_business"), and customer invoices overdue for payment ("owed_to_business"). Use for any "overdue payments" question and mention both if relevant.',
                'parameters' => $empty,
            ],
            [
                'name' => 'pos_list_suppliers',
                'description' => 'Active suppliers for this business, optionally filtered by name. Use to resolve a supplier mentioned by name before building a purchase order draft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_prepare_sale_campaign_draft',
                'description' => 'Prepare/update a draft discount sales campaign for specific products (call pos_list_products or pos_list_expiring_products first to resolve product_ids). Does NOT save anything — always show the user a summary and get explicit confirmation before calling pos_confirm_sale_campaign_insert.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string', 'description' => 'Existing draft id to continue updating, if any.'],
                        'name' => ['type' => 'string', 'description' => 'Campaign name.'],
                        'discount_type' => ['type' => 'string', 'enum' => ['flat', 'percentage']],
                        'discount_value' => ['type' => 'number', 'description' => 'e.g. 20 for 20% or a flat amount.'],
                        'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'starts_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default today.'],
                        'ends_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD, required unless is_long_term.'],
                        'is_long_term' => ['type' => 'boolean'],
                        'description' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_confirm_sale_campaign_insert',
                'description' => 'Actually creates the previously prepared sale campaign. Only call this after the user has explicitly confirmed the draft summary. Omit draft_id if you are not certain of it — the server tracks the most recently prepared draft automatically; never invent a draft_id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string', 'description' => 'Optional — omit unless you were given this exact id in this same turn.'],
                        'confirm' => ['type' => 'boolean'],
                    ],
                    'required' => ['confirm'],
                ],
            ],
            [
                'name' => 'pos_prepare_purchase_order_draft',
                'description' => 'Prepare/update a draft purchase order for specific products (e.g. all out-of-stock products), ordering the same quantity of each. Does NOT save anything — always show a summary (supplier, products, qty, unit cost, total) and get explicit confirmation before calling pos_confirm_purchase_order_insert. Note: creating a PO never changes stock by itself — stock only increases once goods are received via a GRN.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string'],
                        'supplier_id' => ['type' => 'integer', 'description' => 'Resolve via pos_list_suppliers first if the user named a supplier.'],
                        'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'quantity_each' => ['type' => 'number', 'description' => 'Units to order per product, e.g. 100.'],
                        'unit_cost_each' => ['type' => 'number', 'description' => 'Optional override; defaults to each product\'s own cost_price.'],
                        'purchase_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default today.'],
                        'expected_delivery_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, optional.'],
                        'notes' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'ordered'], 'description' => 'Default draft.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_confirm_purchase_order_insert',
                'description' => 'Actually creates the previously prepared purchase order. Only call this after the user has explicitly confirmed the draft summary. Omit draft_id if you are not certain of it — the server tracks the most recently prepared draft automatically; never invent a draft_id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'string', 'description' => 'Optional — omit unless you were given this exact id in this same turn.'],
                        'confirm' => ['type' => 'boolean'],
                        'place_order' => ['type' => 'boolean', 'description' => 'If true, immediately mark the created PO as "ordered" (sent to supplier) instead of leaving it as draft.'],
                    ],
                    'required' => ['confirm'],
                ],
            ],
            [
                'name' => 'pos_generate_campaign_poster',
                'description' => 'Generates a promotional poster design (Design Studio canvas commands) for a sale campaign. Pass campaign_id if the campaign was just created/confirmed, otherwise pass campaign_name/discount_summary/product_names directly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'campaign_id' => ['type' => 'integer'],
                        'campaign_name' => ['type' => 'string'],
                        'discount_summary' => ['type' => 'string', 'description' => 'e.g. "20% off" or "Save $5".'],
                        'product_names' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'valid_until' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'pos_show_walkthrough',
                'description' => 'Trigger a live on-screen walkthrough/demo of a specific app feature. Only call this when the user clearly wants a demonstration, not for data questions or write actions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'walkthrough_id' => [
                            'type' => 'string',
                            'enum' => array_keys(self::WALKTHROUGH_DESCRIPTIONS),
                        ],
                        'product_name' => ['type' => 'string', 'description' => 'Only for edit_product — the product name the user mentioned.'],
                        'field_name' => ['type' => 'string', 'description' => 'Only for edit_product — the field the user wants to change.'],
                    ],
                    'required' => ['walkthrough_id'],
                ],
            ],
        ];
    }

    // ── Read tools ──────────────────────────────────────────────────────

    private function lowStock(Business $business, bool $outOfStockOnly): array
    {
        $query = $business->products()
            ->where('is_active', true)
            ->select(['id', 'name', 'sku', 'stock_quantity']);

        if ($outOfStockOnly) {
            $query->where('stock_quantity', '<=', 0);
        } else {
            $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5);
        }

        $products = $query->orderBy('stock_quantity')->limit(20)->get();

        return [
            'type' => $outOfStockOnly ? 'out_of_stock' : 'low_stock',
            'count' => $products->count(),
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => round((float) $p->stock_quantity, 2),
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function listProducts(Business $business, array $args): array
    {
        $query = $business->products()
            ->select(['id', 'name', 'sku', 'stock_quantity', 'unit_price', 'cost_price', 'is_active']);

        if (isset($args['max_stock_quantity'])) {
            $query->where('stock_quantity', '<=', (float) $args['max_stock_quantity']);
        }

        if (! empty($args['category'])) {
            $category = trim((string) $args['category']);
            $query->whereHas('categories', fn ($q) => $q->where('name', 'like', "%{$category}%"));
        }

        if (! array_key_exists('active_only', $args) || (bool) $args['active_only']) {
            $query->where('is_active', true);
        }

        match ((string) ($args['sort'] ?? 'stock_asc')) {
            'stock_desc' => $query->orderByDesc('stock_quantity'),
            'name' => $query->orderBy('name'),
            default => $query->orderBy('stock_quantity'),
        };

        $limit = max(1, min(100, (int) ($args['limit'] ?? 20)));
        $products = $query->limit($limit)->get();

        return [
            'count' => $products->count(),
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => round((float) $p->stock_quantity, 2),
                'unit_price' => round((float) $p->unit_price, 2),
                'cost_price' => round((float) $p->cost_price, 2),
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function listExpiringProducts(Business $business, array $args): array
    {
        $days = max(1, min(365, (int) ($args['within_days'] ?? 60)));
        $until = now()->addDays($days)->endOfDay();

        $products = $business->products()
            ->where('track_expiry', true)
            ->whereNotNull('exp_date')
            ->whereBetween('exp_date', [now()->startOfDay(), $until])
            ->orderBy('exp_date')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'exp_date']);

        return [
            'within_days' => $days,
            'count' => $products->count(),
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => round((float) $p->stock_quantity, 2),
                'exp_date' => $p->exp_date?->format('Y-m-d'),
                'days_until_expiry' => $p->exp_date ? (int) now()->startOfDay()->diffInDays($p->exp_date, false) : null,
            ])->all(),
        ];
    }

    private function todaySales(Business $business): array
    {
        $today = now()->startOfDay();
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $today)
            ->with('items')
            ->get();

        $topProducts = $sales->flatMap->items
            ->groupBy('product_name')
            ->map(fn ($g) => ['name' => $g->first()->product_name, 'qty' => round((float) $g->sum('quantity'), 2), 'revenue' => round((float) $g->sum('line_total'), 2)])
            ->sortByDesc('revenue')->values()->take(5)->all();

        return [
            'count' => $sales->count(),
            'revenue' => round((float) $sales->sum('total'), 2),
            'items_sold' => (int) $sales->flatMap->items->sum('quantity'),
            'top_products' => $topProducts,
            'date' => now()->format('M j, Y'),
        ];
    }

    private function recentSales(Business $business): array
    {
        $since = now()->subDays(7)->startOfDay();
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $since)
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get(['id', 'sale_number', 'total', 'payment_method', 'sold_at']);

        return [
            'count' => $sales->count(),
            'revenue' => round((float) $sales->sum('total'), 2),
            'period' => 'last 7 days',
            'sales' => $sales->map(fn ($s) => [
                'number' => $s->sale_number,
                'total' => round((float) $s->total, 2),
                'method' => $s->payment_method,
                'date' => $s->sold_at?->format('M j, g:i A'),
            ])->all(),
        ];
    }

    private function topProducts(Business $business): array
    {
        $today = now()->startOfDay();
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $today)
            ->with('items')
            ->get();

        $top = $sales->flatMap->items
            ->groupBy('product_name')
            ->map(fn ($g) => [
                'name' => $g->first()->product_name,
                'qty' => round((float) $g->sum('quantity'), 2),
                'revenue' => round((float) $g->sum('line_total'), 2),
            ])
            ->sortByDesc('revenue')->values()->take(10)->all();

        return [
            'date' => now()->format('M j, Y'),
            'count' => count($top),
            'products' => $top,
        ];
    }

    private function overduePayments(Business $business): array
    {
        $bills = [];
        if (Schema::hasTable('bills')) {
            $billRows = Bill::where('business_id', $business->id)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->with('ledgerTransactions')
                ->get();

            foreach ($billRows as $b) {
                $amount = (float) ($b->recurring_cost ?? 0);
                $paid = (float) $b->ledgerTransactions->sum('amount');
                if ($amount > 0 && $paid < $amount) {
                    $bills[] = [
                        'name' => $b->name,
                        'amount' => $amount,
                        'paid' => round($paid, 2),
                        'due' => $b->due_date->format('M j, Y'),
                        'days_late' => (int) now()->diffInDays($b->due_date),
                    ];
                }
            }
        }

        $invoices = [];
        if (Schema::hasTable('invoices')) {
            $invoiceRows = Invoice::where('business_id', $business->id)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
                ->with('customer')
                ->get();

            foreach ($invoiceRows as $inv) {
                $invoices[] = [
                    'invoice_number' => $inv->invoice_number,
                    'customer' => $inv->customer?->name,
                    'total' => (float) $inv->total,
                    'due' => $inv->due_date->format('M j, Y'),
                    'days_late' => (int) now()->diffInDays($inv->due_date),
                ];
            }
        }

        return [
            'owed_by_business' => ['label' => 'Bills you owe that are overdue', 'count' => count($bills), 'items' => array_slice($bills, 0, 15)],
            'owed_to_business' => ['label' => 'Customer invoices overdue (money owed to you)', 'count' => count($invoices), 'items' => array_slice($invoices, 0, 15)],
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function listSuppliers(Business $business, array $args): array
    {
        $query = $business->suppliers()->where('is_active', true)->select(['id', 'name', 'contact_name', 'phone', 'email']);

        if (! empty($args['search'])) {
            $search = trim((string) $args['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        $rows = $query->orderBy('name')->limit(20)->get();

        return [
            'count' => $rows->count(),
            'suppliers' => $rows->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'contact' => $s->contact_name, 'phone' => $s->phone])->all(),
        ];
    }

    // ── Sale campaign write tools ──────────────────────────────────────

    /** @param  array<string, mixed>  $args */
    private function prepareSaleCampaignDraft(Business $business, string $actorKey, array $args): array
    {
        $incomingDraftId = trim((string) ($args['draft_id'] ?? ''));
        $draftId = $incomingDraftId !== '' ? $incomingDraftId : $this->latestDraftId($business, $actorKey, 'sale_campaign');
        $base = $draftId !== null ? $this->getDraft($business, $actorKey, 'sale_campaign', $draftId) : null;

        $productIds = array_values(array_unique(array_map(
            'intval',
            (array) ($args['product_ids'] ?? ($base['product_ids'] ?? []))
        )));
        $name = trim((string) ($args['name'] ?? $base['name'] ?? ''));
        $discountType = (string) ($args['discount_type'] ?? $base['discount_type'] ?? 'percentage');
        if (! in_array($discountType, ['flat', 'percentage'], true)) {
            $discountType = 'percentage';
        }
        $discountValue = isset($args['discount_value']) ? (float) $args['discount_value'] : (float) ($base['discount_value'] ?? 0);
        $isLongTerm = array_key_exists('is_long_term', $args) ? (bool) $args['is_long_term'] : (bool) ($base['is_long_term'] ?? false);
        $startsAt = $this->normalizeDate($args['starts_at'] ?? null) ?? ($base['starts_at'] ?? now()->toDateString());
        $endsAt = $isLongTerm ? null : ($this->normalizeDate($args['ends_at'] ?? null) ?? ($base['ends_at'] ?? null));
        $description = $this->nullableTrimmed($args['description'] ?? ($base['description'] ?? null));

        $products = $business->products()->whereIn('id', $productIds)->get(['id', 'name', 'sku', 'stock_quantity']);
        $resolvedIds = $products->pluck('id')->all();

        $missing = [];
        if ($name === '') {
            $missing[] = 'name';
        }
        if ($discountValue <= 0) {
            $missing[] = 'discount_value';
        }
        if (empty($resolvedIds)) {
            $missing[] = 'product_ids';
        }
        if (! $isLongTerm && $endsAt === null) {
            $missing[] = 'ends_at';
        }

        $draft = [
            'name' => $name,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'product_ids' => $resolvedIds,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_long_term' => $isLongTerm,
            'description' => $description,
        ];
        $draftId = $this->storeDraft($business, $actorKey, 'sale_campaign', $draft, $draftId);

        return [
            'draft_id' => $draftId,
            'ready_to_confirm' => $missing === [],
            'missing_fields' => $missing,
            'draft' => $draft,
            'products' => $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'stock' => round((float) $p->stock_quantity, 2)])->values()->all(),
            'message' => $missing === []
                ? 'Draft ready. Show the user a clear summary (products, discount, dates) and ask them to confirm before calling pos_confirm_sale_campaign_insert.'
                : 'Draft saved but incomplete — ask the user for the missing fields.',
        ];
    }

    /**
     * Resolve which draft to act on for a confirm call. The server's own
     * "latest draft of this type" pointer always wins over a model-supplied
     * draft_id: across separate HTTP requests, Gemini only ever sees the
     * plain-text conversation history (the raw tool-call/tool-result turns
     * from an earlier prepare step are never replayed), so a draft_id it
     * supplies days/requests later is frequently a fabricated guess rather
     * than the real id — trusting it caused confirmations to spuriously
     * report "draft expired" even though the real draft was still cached.
     */
    private function resolveDraftIdForConfirm(Business $business, string $actorKey, string $type, array $args): string
    {
        $latest = $this->latestDraftId($business, $actorKey, $type);
        if ($latest !== null) {
            return $latest;
        }

        return trim((string) ($args['draft_id'] ?? ''));
    }

    /** @param  array<string, mixed>  $args */
    private function confirmSaleCampaignInsert(Business $business, string $actorKey, array $args): array
    {
        $draftId = $this->resolveDraftIdForConfirm($business, $actorKey, 'sale_campaign', $args);
        if ($draftId === '') {
            return ['error' => 'No sale campaign draft found. Call pos_prepare_sale_campaign_draft first.'];
        }
        if (! (bool) ($args['confirm'] ?? false)) {
            return ['status' => 'cancelled', 'message' => 'Sale campaign creation cancelled — confirm was false.'];
        }

        $draft = $this->getDraft($business, $actorKey, 'sale_campaign', $draftId);
        if (! is_array($draft)) {
            return ['error' => 'Draft not found or expired.', 'message' => 'Ask the user to describe the campaign again.'];
        }

        $productIds = $draft['product_ids'] ?? [];
        if (empty($productIds) || trim((string) ($draft['name'] ?? '')) === '' || (float) ($draft['discount_value'] ?? 0) <= 0) {
            return ['error' => 'Draft is incomplete.', 'draft' => $draft];
        }

        $items = array_map(fn ($pid) => [
            'product_id' => $pid,
            'product_selling_unit_id' => null,
            'discount_type' => $draft['discount_type'],
            'discount_value' => $draft['discount_value'],
        ], $productIds);

        $data = [
            'file_manager_file_id' => null,
            'name' => $draft['name'],
            'description' => $draft['description'] ?? null,
            'mode' => 'individual',
            'discount_type' => null,
            'discount_value' => null,
            'is_long_term' => $draft['is_long_term'],
            'starts_at' => $draft['starts_at'],
            'ends_at' => $draft['is_long_term'] ? null : $draft['ends_at'],
            'is_active' => true,
            'items' => $items,
        ];

        $campaign = $this->saleCampaigns->create($business, $data);
        Cache::forget($this->draftKey($business, $actorKey, 'sale_campaign', $draftId));
        Cache::forget($this->latestDraftKey($business, $actorKey, 'sale_campaign'));

        return [
            'status' => 'inserted',
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'discount_type' => $draft['discount_type'],
                'discount_value' => $draft['discount_value'],
                'product_count' => count($productIds),
                'starts_at' => $draft['starts_at'],
                'ends_at' => $data['ends_at'],
            ],
            'message' => 'Sale campaign created and active.',
        ];
    }

    // ── Purchase order write tools ─────────────────────────────────────

    /** @param  array<string, mixed>  $args */
    private function preparePurchaseOrderDraft(Business $business, string $actorKey, array $args): array
    {
        $incomingDraftId = trim((string) ($args['draft_id'] ?? ''));
        $draftId = $incomingDraftId !== '' ? $incomingDraftId : $this->latestDraftId($business, $actorKey, 'purchase_order');
        $base = $draftId !== null ? $this->getDraft($business, $actorKey, 'purchase_order', $draftId) : null;

        $productIds = array_values(array_unique(array_map(
            'intval',
            (array) ($args['product_ids'] ?? ($base['product_ids'] ?? []))
        )));
        $quantityEach = isset($args['quantity_each']) ? (float) $args['quantity_each'] : (float) ($base['quantity_each'] ?? 0);
        $unitCostEachOverride = isset($args['unit_cost_each']) ? (float) $args['unit_cost_each'] : ($base['unit_cost_each'] ?? null);

        $supplierId = isset($args['supplier_id']) ? (int) $args['supplier_id'] : ($base['supplier_id'] ?? null);
        if ($supplierId !== null && ! $business->suppliers()->whereKey($supplierId)->exists()) {
            $supplierId = null;
        }

        $purchaseDate = $this->normalizeDate($args['purchase_date'] ?? null) ?? ($base['purchase_date'] ?? now()->toDateString());
        $expectedDelivery = $this->normalizeDate($args['expected_delivery_date'] ?? null) ?? ($base['expected_delivery_date'] ?? null);
        $notes = $this->nullableTrimmed($args['notes'] ?? ($base['notes'] ?? null));
        $status = (string) ($args['status'] ?? $base['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'ordered'], true)) {
            $status = 'draft';
        }

        $products = $business->products()->whereIn('id', $productIds)->get(['id', 'name', 'sku', 'cost_price']);
        $resolvedIds = $products->pluck('id')->all();

        $lines = $products->map(fn ($p) => [
            'product_id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'unit_cost' => round($unitCostEachOverride ?? (float) $p->cost_price, 2),
        ])->values()->all();

        $missing = [];
        if (empty($resolvedIds)) {
            $missing[] = 'product_ids';
        }
        if ($quantityEach <= 0) {
            $missing[] = 'quantity_each';
        }

        $draft = [
            'product_ids' => $resolvedIds,
            'quantity_each' => $quantityEach,
            'unit_cost_each' => $unitCostEachOverride,
            'supplier_id' => $supplierId,
            'purchase_date' => $purchaseDate,
            'expected_delivery_date' => $expectedDelivery,
            'notes' => $notes,
            'status' => $status,
        ];
        $draftId = $this->storeDraft($business, $actorKey, 'purchase_order', $draft, $draftId);

        return [
            'draft_id' => $draftId,
            'ready_to_confirm' => $missing === [],
            'missing_fields' => $missing,
            'draft' => $draft,
            'lines' => $lines,
            'estimated_total' => round(array_sum(array_map(fn ($l) => $l['unit_cost'] * $quantityEach, $lines)), 2),
            'note' => 'Creating this order does not change stock — stock only increases once the goods are received and a GRN is approved.',
            'message' => $missing === []
                ? 'Draft ready. Show the user a clear summary (supplier, products, qty, unit cost, total) and ask them to confirm before calling pos_confirm_purchase_order_insert.'
                : 'Draft saved but incomplete — ask the user for the missing fields.',
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function confirmPurchaseOrderInsert(Business $business, string $actorKey, array $args): array
    {
        $draftId = $this->resolveDraftIdForConfirm($business, $actorKey, 'purchase_order', $args);
        if ($draftId === '') {
            return ['error' => 'No purchase order draft found. Call pos_prepare_purchase_order_draft first.'];
        }
        if (! (bool) ($args['confirm'] ?? false)) {
            return ['status' => 'cancelled', 'message' => 'Purchase order creation cancelled — confirm was false.'];
        }

        $draft = $this->getDraft($business, $actorKey, 'purchase_order', $draftId);
        if (! is_array($draft)) {
            return ['error' => 'Draft not found or expired.', 'message' => 'Ask the user to describe the purchase order again.'];
        }

        $productIds = $draft['product_ids'] ?? [];
        $quantityEach = (float) ($draft['quantity_each'] ?? 0);
        if (empty($productIds) || $quantityEach <= 0) {
            return ['error' => 'Draft is incomplete.', 'draft' => $draft];
        }

        $products = $business->products()->whereIn('id', $productIds)->get(['id', 'cost_price']);
        $unitCostOverride = $draft['unit_cost_each'] ?? null;
        $items = $products->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => $quantityEach,
            'unit_cost' => round($unitCostOverride ?? (float) $p->cost_price, 2),
        ])->values()->all();

        $validated = [
            'supplier_id' => $draft['supplier_id'] ?? null,
            'reference' => null,
            'purchase_date' => $draft['purchase_date'],
            'expected_delivery_date' => $draft['expected_delivery_date'] ?? null,
            'status' => $draft['status'] ?? 'draft',
            'notes' => $draft['notes'] ?? null,
        ];

        try {
            $purchase = $this->purchases->create($business, $validated, $items);
        } catch (ValidationException $e) {
            return ['error' => 'Could not create purchase order.', 'details' => $e->errors()];
        }

        if ((bool) ($args['place_order'] ?? false) && ! $purchase->isOrdered()) {
            $purchase = $this->purchases->markOrdered($purchase);
        }

        Cache::forget($this->draftKey($business, $actorKey, 'purchase_order', $draftId));
        Cache::forget($this->latestDraftKey($business, $actorKey, 'purchase_order'));

        return [
            'status' => 'inserted',
            'purchase_order' => [
                'id' => $purchase->id,
                'po_number' => $purchase->po_number,
                'status' => $purchase->status,
                'items' => count($items),
                'total' => (float) $purchase->total,
            ],
            'note' => 'Stock will increase once these goods are received and the GRN is approved — not immediately.',
            'message' => 'Purchase order created.',
        ];
    }

    // ── Poster generation ──────────────────────────────────────────────

    /** @param  array<string, mixed>  $args */
    private function generateCampaignPoster(Business $business, array $args): array
    {
        $campaignId = isset($args['campaign_id']) ? (int) $args['campaign_id'] : null;
        $campaignName = trim((string) ($args['campaign_name'] ?? ''));
        $discountSummary = trim((string) ($args['discount_summary'] ?? ''));
        $productNames = array_values(array_filter(array_map('strval', (array) ($args['product_names'] ?? []))));
        $validUntil = trim((string) ($args['valid_until'] ?? ''));

        if ($campaignId) {
            $campaign = $business->saleCampaigns()->with('items.product')->find($campaignId);
            if ($campaign) {
                $campaignName = $campaignName !== '' ? $campaignName : $campaign->name;
                if ($discountSummary === '' && $campaign->mode === 'storewide' && $campaign->discount_value !== null) {
                    $discountSummary = trim($campaign->discount_value.' '.$campaign->discount_type);
                }
                if (empty($productNames)) {
                    $productNames = $campaign->items->map(fn ($i) => $i->product?->name)->filter()->values()->all();
                }
                if ($validUntil === '' && $campaign->ends_at) {
                    $validUntil = $campaign->ends_at->format('M j, Y');
                }
            }
        }

        if ($campaignName === '') {
            return ['error' => 'Need at least a campaign name (or campaign_id) to design a poster.'];
        }

        $canvasWidth = 794;
        $canvasHeight = 1123;
        $canvasHeader = "CANVAS: {$canvasWidth}px wide × {$canvasHeight}px tall. Use these exact values — do not output symbolic expressions.\n\n";
        $systemPrompt = $canvasHeader.DesignAiChatApiController::BASE_SYSTEM_PROMPT;

        $productsLine = empty($productNames)
            ? ''
            : ('Products included: '.implode(', ', array_slice($productNames, 0, 12)).(count($productNames) > 12 ? ', and more' : '')."\n");

        $prompt = "Design an EVENT POSTER-style promotional poster (use that recipe/format) for a sale campaign.\n"
            ."Campaign name: {$campaignName}\n"
            .($discountSummary !== '' ? "Discount: {$discountSummary}\n" : '')
            .$productsLine
            .($validUntil !== '' ? "Valid until: {$validUntil}\n" : '')
            .'Reply with the JSON format described in the system instructions: {"reply":"...","commands":[...]}.';

        $body = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'maxOutputTokens' => 8192,
                'temperature' => 0.5,
                // gemini-2.5-flash spends part of maxOutputTokens on invisible "thinking"
                // tokens before any visible text — this is a fixed-format JSON generation
                // task with no reasoning needed, so disable it to avoid truncated output.
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        $result = $this->gemini->generate($body);
        if (! $result['successful'] || ! is_array($result['json'])) {
            Log::warning('pos_generate_campaign_poster: Gemini call failed', ['result' => $result]);

            return ['error' => 'Poster generation is unavailable right now.'];
        }

        $finishReason = strtoupper((string) data_get($result['json'], 'candidates.0.finishReason', ''));
        $raw = data_get($result['json'], 'candidates.0.content.parts.0.text');
        if (! $raw) {
            Log::warning('pos_generate_campaign_poster: no text in Gemini response', ['finishReason' => $finishReason, 'json' => $result['json']]);

            return ['error' => 'Poster generation returned no content.'];
        }

        $cleaned = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim((string) $raw)));
        $parsed = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed) || empty($parsed['commands'])) {
            // Gemini sometimes appends stray trailing characters after a
            // otherwise-complete object (e.g. an extra "]}"). Extract just the
            // first balanced {...} object instead of a greedy last-"}" match.
            $balanced = $this->firstBalancedJsonObject($cleaned);
            if ($balanced !== null) {
                $parsed = json_decode($balanced, true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed) || empty($parsed['commands'])) {
            Log::warning('pos_generate_campaign_poster: could not parse Gemini output', [
                'finishReason' => $finishReason,
                'raw' => $raw,
            ]);

            return ['error' => 'Could not parse poster design output.'];
        }

        return [
            'status' => 'generated',
            'campaign_name' => $campaignName,
            'canvas' => ['width' => $canvasWidth, 'height' => $canvasHeight],
            'commands' => $parsed['commands'],
            'message' => 'Poster design generated — it will be shown to the user in the chat/design canvas.',
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function showWalkthrough(array $args): array
    {
        $id = trim((string) ($args['walkthrough_id'] ?? ''));
        if ($id === '' || ! array_key_exists($id, self::WALKTHROUGH_DESCRIPTIONS)) {
            return ['error' => 'Unknown walkthrough id.'];
        }

        return ['status' => 'ok', 'walkthrough_id' => $id];
    }

    // ── Draft cache helpers ─────────────────────────────────────────────

    private function draftKey(Business $business, string $actorKey, string $type, string $draftId): string
    {
        return "pos_agent:draft:{$type}:{$business->id}:{$actorKey}:{$draftId}";
    }

    private function latestDraftKey(Business $business, string $actorKey, string $type): string
    {
        return "pos_agent:draft_latest:{$type}:{$business->id}:{$actorKey}";
    }

    private function latestDraftId(Business $business, string $actorKey, string $type): ?string
    {
        $id = Cache::get($this->latestDraftKey($business, $actorKey, $type));

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    /** @return array<string, mixed>|null */
    private function getDraft(Business $business, string $actorKey, string $type, string $draftId): ?array
    {
        $draft = Cache::get($this->draftKey($business, $actorKey, $type, $draftId));
        if (is_array($draft)) {
            Cache::put($this->draftKey($business, $actorKey, $type, $draftId), $draft, now()->addHours(12));
            Cache::put($this->latestDraftKey($business, $actorKey, $type), $draftId, now()->addHours(12));
        }

        return is_array($draft) ? $draft : null;
    }

    /** @param  array<string, mixed>  $draft */
    private function storeDraft(Business $business, string $actorKey, string $type, array $draft, ?string $draftId = null): string
    {
        $draftId = $draftId !== null && $draftId !== '' ? $draftId : (string) Str::uuid();
        Cache::put($this->draftKey($business, $actorKey, $type, $draftId), $draft, now()->addHours(12));
        Cache::put($this->latestDraftKey($business, $actorKey, $type), $draftId, now()->addHours(12));

        return $draftId;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s === '' ? null : $s;
    }

    /**
     * Scans from the first "{" and returns the substring up to its matching
     * closing "}" (tracking string literals/escapes so braces inside strings
     * don't throw off the depth count), ignoring anything after that point.
     * Recovers from valid JSON followed by stray trailing characters, while
     * correctly returning null for genuinely truncated/unbalanced input.
     */
    private function firstBalancedJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $len = strlen($text); $i < $len; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
