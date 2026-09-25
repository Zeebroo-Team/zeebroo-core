<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Property;
use Modules\Account\Models\Rental;
use Modules\Account\Services\BillService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\HRManagement\Services\HrHubSummaryService;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Pos\Models\StockAudit;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\Supplier;
use Modules\Service\Models\ServiceItem;
use Modules\Service\Models\ServiceRequest;
use Modules\Transaction\Models\LedgerTransaction;

/**
 * Assembles the data behind the web "Home" tab — a like-for-like port of the
 * Electron POS app's Home dashboard (KPI bar, Overview cards, and the
 * Business Flow / Today / Recent Activity / Analytics / Expenses / Profit /
 * Payroll / Orders / CRM subviews) using the same models the rest of the
 * web app already queries. Read-only aggregation only.
 */
class HomeOverviewService
{
    public function __construct(
        private readonly BillService $billService,
        private readonly RentalService $rentalService,
        private readonly LoanOverviewTooltipService $loanService,
        private readonly HrHubSummaryService $hrHubSummaryService,
    ) {}

    /** @return array<string, mixed> */
    public function forBusiness(Business $business): array
    {
        $today = $this->today($business);
        $expenses = $this->expenses($business);
        $flow = $this->businessFlow($business, $expenses);

        return [
            'kpis'        => $this->kpis($business, $today),
            'overview'    => $this->overviewSections($business),
            'flow'        => $flow,
            'flowChart'   => $this->businessFlowChart($business, $flow),
            'today'       => $today,
            'activity'    => $this->recentActivity($business),
            'analytics'   => $this->analytics($business),
            'expenses'    => $expenses,
            'profit'      => $this->profit($business, $today, $expenses),
            'payroll'     => $this->hrHubSummaryService->forBusiness($business),
            'orders'      => $this->orders($business),
            'crm'         => $this->crm($business),
            'rightPanel'  => $this->rightPanel($business, $today, $expenses),
        ];
    }

    /** @return array<string, mixed> */
    private function kpis(Business $business, array $today): array
    {
        return [
            'sales'     => $today['sales']['count'],
            'revenue'   => $today['sales']['revenue'],
            'products'  => Product::where('business_id', $business->id)->count(),
            'customers' => Customer::where('business_id', $business->id)->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function overviewSections(Business $business): array
    {
        $bizId = $business->id;

        return [
            [
                'key'   => 'point_of_sale',
                'title' => 'Sales & Point of Sale',
                'desc'  => 'Ring up sales, track orders and monitor daily performance',
                'icon'  => 'fa-cart-shopping',
                'color' => 'blue',
                'cards' => [
                    ['label' => 'Open POS', 'desc' => 'Start ringing up a sale at the register.', 'icon' => 'fa-cash-register', 'route' => 'pos.online'],
                    ['label' => "Today's Summary", 'desc' => "See today's sales count, revenue and top-selling items.", 'icon' => 'fa-sun', 'route' => 'home.index'],
                    ['label' => 'Orders', 'desc' => 'Browse the full history of sales and purchase orders.', 'icon' => 'fa-receipt', 'route' => 'pos.sales.index'],
                    ['label' => 'Recent Activity', 'desc' => 'A live feed of the latest transactions and changes.', 'icon' => 'fa-clock-rotate-left', 'route' => 'home.index'],
                ],
            ],
            [
                'key'   => 'inventory',
                'title' => 'Inventory',
                'desc'  => 'Manage products, stock levels and suppliers',
                'icon'  => 'fa-boxes-stacked',
                'color' => 'amber',
                'cards' => [
                    ['label' => 'Products', 'desc' => 'Browse, add and edit your product catalog.', 'icon' => 'fa-box', 'route' => 'product.index', 'stat' => Product::where('business_id', $bizId)->count()],
                    ['label' => 'Categories', 'desc' => 'Group products into categories.', 'icon' => 'fa-tags', 'route' => 'product.categories.index', 'stat' => ProductCategory::where('business_id', $bizId)->count()],
                    ['label' => 'Suppliers', 'desc' => 'Manage supplier contacts and the products you buy from each one.', 'icon' => 'fa-truck-field', 'route' => 'purchase.suppliers.index', 'stat' => Supplier::where('business_id', $bizId)->count()],
                    ['label' => 'Purchase Orders', 'desc' => 'Order new stock from your suppliers.', 'icon' => 'fa-cart-arrow-down', 'route' => 'purchase.index', 'stat' => Purchase::where('business_id', $bizId)->count()],
                    ['label' => 'Stock Audit', 'desc' => 'Count physical stock and reconcile it against system records.', 'icon' => 'fa-clipboard-check', 'route' => 'pos.stock-audits.index', 'stat' => StockAudit::where('business_id', $bizId)->count()],
                ],
            ],
            [
                'key'   => 'bill_management',
                'title' => 'Finance',
                'desc'  => 'Track bills, loans, rentals and profitability',
                'icon'  => 'fa-file-invoice-dollar',
                'color' => 'rose',
                'cards' => [
                    ['label' => 'Bills & Expenses', 'desc' => 'Track recurring bills and overdue payments.', 'icon' => 'fa-file-invoice', 'route' => 'account.bills.index', 'stat' => Bill::where('business_id', $bizId)->count()],
                    ['label' => 'Loans', 'desc' => 'Track loans, repayments and outstanding balances.', 'icon' => 'fa-hand-holding-dollar', 'route' => 'account.loans.index', 'stat' => Loan::where('business_id', $bizId)->count()],
                    ['label' => 'Rentals', 'desc' => 'Manage rented properties and monthly costs.', 'icon' => 'fa-house-chimney', 'route' => 'account.rentals.index', 'stat' => Rental::where('business_id', $bizId)->count()],
                    ['label' => 'Profit Report', 'desc' => 'Revenue vs. expenses and profit margin.', 'icon' => 'fa-sack-dollar', 'route' => 'home.index'],
                ],
            ],
            [
                'key'   => 'human_resources',
                'title' => 'HR & Payroll',
                'desc'  => 'Manage staff, departments and payroll cycles',
                'icon'  => 'fa-users',
                'color' => 'purple',
                'cards' => [
                    ['label' => 'Employees', 'desc' => 'Staff records, contact details and employment history.', 'icon' => 'fa-id-badge', 'route' => 'hr.employees.index', 'stat' => $business->employees()->count()],
                    ['label' => 'Departments', 'desc' => 'Organize your workforce into departments.', 'icon' => 'fa-sitemap', 'route' => 'hr.departments.index', 'stat' => $business->departments()->count()],
                    ['label' => 'Payroll Cycles', 'desc' => 'Run payroll and review payment status.', 'icon' => 'fa-money-check-dollar', 'route' => 'hr.payroll.index'],
                ],
            ],
            [
                'key'   => 'service_management',
                'title' => 'Services',
                'desc'  => 'Handle service requests and your service catalog',
                'icon'  => 'fa-screwdriver-wrench',
                'color' => 'teal',
                'cards' => [
                    ['label' => 'Service Requests', 'desc' => 'Track service jobs from request through to completion.', 'icon' => 'fa-list-check', 'route' => 'service.requests.index', 'stat' => ServiceRequest::where('business_id', $bizId)->count()],
                    ['label' => 'Service Catalog', 'desc' => 'Manage the services you offer, pricing and descriptions.', 'icon' => 'fa-book', 'route' => 'service.catalog.index', 'stat' => ServiceItem::where('business_id', $bizId)->count()],
                ],
            ],
            [
                'key'   => 'crm',
                'title' => 'CRM',
                'desc'  => 'Leads pipeline, contacts and follow-up tasks',
                'icon'  => 'fa-handshake',
                'color' => 'indigo',
                'cards' => [
                    ['label' => 'Relations & Pipeline', 'desc' => 'Browse every relation and move leads through pipeline stages.', 'icon' => 'fa-handshake', 'route' => 'crm.projects.index', 'stat' => Project::where('business_id', $bizId)->count()],
                    ['label' => 'Contacts', 'desc' => 'Every customer and lead, with their activity history.', 'icon' => 'fa-address-book', 'route' => 'crm.contacts.index', 'stat' => Customer::where('business_id', $bizId)->count()],
                    ['label' => 'Tasks', 'desc' => "See what's open, overdue or completed.", 'icon' => 'fa-list-check', 'route' => 'crm.tasks.index', 'stat' => Task::where('business_id', $bizId)->count()],
                ],
            ],
            [
                'key'   => null,
                'title' => 'Insights & Tools',
                'desc'  => 'Reports, customers and system settings',
                'icon'  => 'fa-chart-line',
                'color' => 'green',
                'cards' => [
                    ['label' => 'Analytics', 'desc' => 'Sales trends over 7, 30 or 90 days.', 'icon' => 'fa-chart-line', 'route' => 'home.index'],
                    ['label' => 'Business Flow', 'desc' => 'How money and goods move through your business.', 'icon' => 'fa-diagram-project', 'route' => 'account.finance.index'],
                    ['label' => 'Customers', 'desc' => 'Contact details, loyalty points and account balances.', 'icon' => 'fa-address-book', 'route' => 'crm.contacts.index', 'stat' => Customer::where('business_id', $bizId)->count()],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function businessFlow(Business $business, array $expenses): array
    {
        $monthlySales = (float) $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', now()->startOfMonth())
            ->sum('total');

        return [
            'income' => [
                'pos_sales_monthly' => round($monthlySales, 2),
            ],
            'expense' => [
                'bills_monthly'   => $expenses['bills']['monthly'],
                'loans_monthly'   => $expenses['loans']['monthly'],
                'rentals_monthly' => $expenses['rentals']['monthly'],
                'total_monthly'   => $expenses['total_monthly'],
            ],
            'bills'   => $expenses['bills'],
            'loans'   => $expenses['loans'],
            'rentals' => $expenses['rentals'],
            'assets'  => $this->assets($business),
        ];
    }

    /** @return array<string, mixed> */
    private function assets(Business $business): array
    {
        $typeLabels = Property::typeOptions();
        $properties = Property::where('business_id', $business->id)->latest()->get();

        $items = $properties->map(function (Property $property) use ($typeLabels) {
            $expired = false;
            $expiringSoon = false;
            if ($property->has_expiry && $property->expire_date) {
                $expired = $property->expire_date->isPast();
                $expiringSoon = ! $expired && $property->expire_date->lessThanOrEqualTo(now()->addDays(30));
            }

            return [
                'name'          => $property->property_name,
                'type'          => $typeLabels[$property->property_type] ?? $property->property_type,
                'cost'          => round((float) $property->cost, 2),
                'expired'       => $expired,
                'expiring_soon' => $expiringSoon,
            ];
        })->all();

        return ['count' => $properties->count(), 'items' => $items];
    }

    /**
     * Builds the node/edge geometry for the Business Flow diagram — a
     * server-rendered, static echo of the Electron app's React Flow chart
     * (same hub/leaf hierarchy, colors and coordinate layout), but laid out
     * with fixed-size boxes and bezier connector paths instead of a JS
     * diagramming library.
     *
     * @return array<string, mixed>
     */
    private function businessFlowChart(Business $business, array $flow): array
    {
        $offsetX = 220;
        $offsetY = 190;
        $dims = [
            'root' => [180, 44],
            'hub'  => [124, 38],
            'leaf' => [112, 32],
            'item' => [154, 46],
            'more' => [154, 28],
        ];

        $nodes = [];
        $edges = [];

        $addNode = function (string $id, string $kind, float $rawX, float $rawY, array $extra = []) use (&$nodes, $dims, $offsetX, $offsetY) {
            $type = match (true) {
                $kind === 'root' => 'root',
                str_starts_with($kind, 'hub-') => 'hub',
                str_starts_with($kind, 'leaf-') => 'leaf',
                $kind === 'item-more' => 'more',
                default => 'item',
            };
            [$w, $h] = $dims[$type];
            $nodes[$id] = array_merge([
                'id' => $id, 'kind' => $kind,
                'x' => $rawX + $offsetX, 'y' => $rawY + $offsetY, 'w' => $w, 'h' => $h,
            ], $extra);
        };

        $anchor = function (array $n, string $side): array {
            $cx = $n['x'] + $n['w'] / 2;
            $cy = $n['y'] + $n['h'] / 2;

            return match ($side) {
                'l' => [$n['x'], $cy],
                'r' => [$n['x'] + $n['w'], $cy],
                't' => [$cx, $n['y']],
                'b' => [$cx, $n['y'] + $n['h']],
                default => [$cx, $cy],
            };
        };

        $dirVec = fn (string $side): array => match ($side) {
            'l' => [-1, 0], 'r' => [1, 0], 't' => [0, -1], 'b' => [0, 1], default => [0, 0],
        };

        $palette = [
            'gray'   => '#64748b',
            'red'    => '#ef4444',
            'green'  => '#22c55e',
            'purple' => '#7c3aed',
            'orange' => '#ea580c',
            'amber'  => '#d97706',
        ];

        $addEdge = function (string $fromId, string $fromSide, string $toId, string $toSide, string $colorKey, bool $dashed = false) use (&$edges, &$nodes, $anchor, $dirVec, $palette) {
            $from = $nodes[$fromId];
            $to = $nodes[$toId];
            [$x1, $y1] = $anchor($from, $fromSide);
            [$x2, $y2] = $anchor($to, $toSide);
            [$dx1, $dy1] = $dirVec($fromSide);
            [$dx2, $dy2] = $dirVec($toSide);
            $dist = max(abs($x2 - $x1), abs($y2 - $y1));
            $off = max(30, min(110, $dist * 0.45));

            $edges[] = [
                'd' => sprintf(
                    'M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f',
                    $x1, $y1,
                    $x1 + $dx1 * $off, $y1 + $dy1 * $off,
                    $x2 + $dx2 * $off, $y2 + $dy2 * $off,
                    $x2, $y2
                ),
                'color'  => $palette[$colorKey],
                'key'    => $colorKey,
                'dashed' => $dashed,
            ];
        };

        $cGray = 'gray';
        $cRed = 'red';
        $cGreen = 'green';
        $cPurple = 'purple';
        $cOrange = 'orange';
        $cAmber = 'amber';

        $financeEnabled = $business->hasFeature('bill_management');
        $hrefOf = fn (string $routeName): ?string => Route::has($routeName) ? route($routeName) : null;

        $billsHref = $hrefOf('account.bills.index');
        $loansHref = $hrefOf('account.loans.index');
        $rentalsHref = $hrefOf('account.rentals.index');
        $propertiesHref = $hrefOf('account.properties.index');
        $salesHref = $hrefOf('pos.sales.index');

        // Root + core hubs
        $addNode('root', 'root', 500, 400, ['label' => $business->name]);
        $addNode('exp', 'hub-expense', 270, 400, ['label' => 'Expenses', 'href' => $hrefOf('account.finance.index')]);
        $addNode('inc', 'hub-income', 740, 200, ['label' => 'Income', 'href' => $salesHref]);
        $addEdge('root', 'l', 'exp', 'r', $cGray);
        $addEdge('root', 'r', 'inc', 'l', $cGray);

        if ($financeEnabled) {
            $addNode('assets', 'hub-assets', 740, 580, ['label' => 'Assets', 'badge' => $flow['assets']['count'] ?: null, 'href' => $propertiesHref]);
            $addEdge('root', 'b', 'assets', 't', $cAmber);
        }

        // Decorative expense-category leaves (static — same as the desktop chart)
        foreach ([
            ['e3', 30, -90, 'Employee Salary', null],
            ['e4', 150, -110, 'Modification', null],
            ['e5', 280, -120, 'Purchase Order', $hrefOf('purchase.index')],
            ['e6', 400, -110, 'Marketing', null],
            ['e7', 510, -90, 'Legal', null],
        ] as [$id, $x, $y, $label, $href]) {
            $addNode($id, 'leaf-decor', $x, $y, ['label' => $label, 'href' => $href]);
            $addEdge('exp', 't', $id, 'b', $cRed);
        }

        // Income leaves
        $addNode('i0', 'leaf-income', 740, 55, ['label' => 'POS Sales', 'href' => $salesHref]);
        $addNode('i1', 'leaf-income', 920, 120, ['label' => 'Quotations']);
        $addNode('i2', 'leaf-income', 930, 300, ['label' => 'Invoices']);
        $addNode('i3', 'leaf-income', 820, 420, ['label' => 'Credit Recovery']);
        $addEdge('inc', 't', 'i0', 'b', $cGreen);
        $addEdge('inc', 'r', 'i1', 'l', $cGreen);
        $addEdge('inc', 'r', 'i2', 'l', $cGreen);
        $addEdge('inc', 'b', 'i3', 't', $cGreen);

        if ($financeEnabled) {
            // Sub-hubs (Bills / Loans / Rentals) + their item columns
            $subHubs = [
                ['id' => 'hb', 'label' => 'Bills',   'kind' => 'hub-bills',   'y' => 140, 'color' => $cRed,    'items' => $flow['bills']['items'],   'itemKind' => 'item-bill',   'href' => $billsHref],
                ['id' => 'hl', 'label' => 'Loans',   'kind' => 'hub-loans',   'y' => 400, 'color' => $cPurple, 'items' => $flow['loans']['items'],   'itemKind' => 'item-loan',   'href' => $loansHref],
                ['id' => 'hr', 'label' => 'Rentals', 'kind' => 'hub-rentals', 'y' => 660, 'color' => $cOrange, 'items' => $flow['rentals']['items'], 'itemKind' => 'item-rental', 'href' => $rentalsHref],
            ];

            foreach ($subHubs as $hub) {
                $total = count($hub['items']);
                $addNode($hub['id'], $hub['kind'], 90, $hub['y'], ['label' => $hub['label'], 'badge' => $total ?: null, 'href' => $hub['href']]);
                $addEdge('exp', 'l', $hub['id'], 'r', $cRed);

                $shown = array_slice($hub['items'], 0, 5);
                $slots = count($shown) + ($total > 5 ? 1 : 0);
                if ($slots === 0) {
                    continue;
                }
                $step = 78;
                $startY = $hub['y'] - (($slots - 1) * $step) / 2;

                foreach ($shown as $i => $item) {
                    $itemId = "{$hub['id']}-{$i}";
                    $addNode($itemId, $hub['itemKind'], -180, $startY + $i * $step, array_merge($item, ['href' => $hub['href']]));
                    $addEdge($hub['id'], 'l', $itemId, 'r', $hub['color'], true);
                }
                if ($total > 5) {
                    $moreId = "{$hub['id']}-more";
                    $addNode($moreId, 'item-more', -180, $startY + count($shown) * $step, ['label' => '+'.($total - 5).' more', 'href' => $hub['href']]);
                    $addEdge($hub['id'], 'l', $moreId, 'r', $hub['color'], true);
                }
            }

            // Assets → property items, to the right of the Assets hub
            $properties = $flow['assets']['items'];
            $totalP = count($properties);
            $shownP = array_slice($properties, 0, 5);
            $slotsP = count($shownP) + ($totalP > 5 ? 1 : 0);
            if ($slotsP > 0) {
                $step = 78;
                $startY = 580 - (($slotsP - 1) * $step) / 2;
                foreach ($shownP as $i => $item) {
                    $itemId = "asset-{$i}";
                    $addNode($itemId, 'item-property', 910, $startY + $i * $step, array_merge($item, ['href' => $propertiesHref]));
                    $addEdge('assets', 'r', $itemId, 'l', $cAmber, true);
                }
                if ($totalP > 5) {
                    $moreId = 'asset-more';
                    $addNode($moreId, 'item-more', 910, $startY + count($shownP) * $step, ['label' => '+'.($totalP - 5).' more', 'href' => $propertiesHref]);
                    $addEdge('assets', 'r', $moreId, 'l', $cAmber, true);
                }
            }
        }

        $maxX = 0;
        $maxY = 0;
        foreach ($nodes as $n) {
            $maxX = max($maxX, $n['x'] + $n['w']);
            $maxY = max($maxY, $n['y'] + $n['h']);
        }

        return [
            'width'   => $maxX + 40,
            'height'  => $maxY + 40,
            'nodes'   => array_values($nodes),
            'edges'   => $edges,
            'palette' => $palette,
        ];
    }

    /** @return array<string, mixed> */
    private function today(Business $business): array
    {
        $start = now()->startOfDay();

        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start)
            ->with('items')
            ->get();

        $byMethod = [];
        foreach ($sales->groupBy('payment_method') as $method => $group) {
            $byMethod[$method] = [
                'count' => $group->count(),
                'total' => round((float) $group->sum('total'), 2),
            ];
        }

        $allItems = $sales->flatMap->items;

        $topProducts = $allItems
            ->groupBy('product_name')
            ->map(fn ($g) => [
                'name'    => $g->first()->product_name,
                'qty'     => round((float) $g->sum('quantity'), 2),
                'revenue' => round((float) $g->sum('line_total'), 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->take(5)
            ->all();

        $recentSales = $sales->sortByDesc('sold_at')->take(8)->values()->map(fn (Sale $s) => [
            'sale_number' => $s->sale_number,
            'total'       => round((float) $s->total, 2),
            'payment_method' => $s->payment_method,
            'sold_at'     => $s->sold_at,
            'items_count' => $s->items->count(),
        ])->all();

        return [
            'sales' => [
                'count'      => $sales->count(),
                'revenue'    => round((float) $sales->sum('total'), 2),
                'items_sold' => (int) $allItems->sum('quantity'),
                'by_method'  => $byMethod,
            ],
            'top_products' => $topProducts,
            'recent_sales' => $recentSales,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivity(Business $business): array
    {
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->latest('sold_at')
            ->limit(10)
            ->get()
            ->map(fn (Sale $s) => [
                'icon'      => 'fa-cart-shopping',
                'label'     => 'Sale '.$s->sale_number.' — '.number_format((float) $s->total, 2),
                'timestamp' => $s->sold_at,
            ]);

        $purchases = Purchase::where('business_id', $business->id)
            ->with('supplier')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Purchase $p) => [
                'icon'      => 'fa-cart-arrow-down',
                'label'     => 'Purchase order '.$p->po_number.' — '.($p->supplier->name ?? 'Supplier'),
                'timestamp' => $p->created_at,
            ]);

        $ledgerEntries = LedgerTransaction::where('business_id', $business->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (LedgerTransaction $t) => [
                'icon'      => 'fa-file-invoice-dollar',
                'label'     => 'Payment recorded — '.number_format((float) $t->amount, 2),
                'timestamp' => $t->created_at,
            ]);

        return $sales->concat($purchases)->concat($ledgerEntries)
            ->sortByDesc('timestamp')
            ->take(20)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function analytics(Business $business): array
    {
        $days = 30;
        $start = now()->copy()->subDays($days - 1)->startOfDay();

        $rows = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start)
            ->selectRaw('DATE(sold_at) as ymd, SUM(total) as revenue')
            ->groupBy('ymd')
            ->pluck('revenue', 'ymd');

        $labels = [];
        $revenue = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->copy()->subDays($i);
            $labels[] = $day->format('M j');
            $revenue[] = round((float) ($rows[$day->format('Y-m-d')] ?? 0), 2);
        }

        return [
            'period'  => $days,
            'labels'  => $labels,
            'revenue' => $revenue,
        ];
    }

    /** @return array<string, mixed> */
    private function expenses(Business $business): array
    {
        $bills = Bill::where('business_id', $business->id)->with('ledgerTransactions')->orderBy('name')->get();
        $billsMonthly = 0.0;
        $billsOverdue = 0;
        $billItems = [];
        foreach ($bills as $bill) {
            $overdue = $this->billService->billHasOverduePayments($bill);
            if ($overdue) {
                $billsOverdue++;
            }
            $billsMonthly += $bill->isOneTime() ? 0.0 : $this->monthlyEquivalent((float) $bill->recurring_cost, (string) $bill->recurring_type);
            $billItems[] = [
                'name'    => $bill->name,
                'cadence' => $bill->isOneTime() ? 'One-time' : (Bill::recurringTypes()[$bill->recurring_type] ?? $bill->recurring_type),
                'amount'  => $bill->amount_varies_by_usage ? null : round((float) $bill->recurring_cost, 2),
                'overdue' => $overdue,
            ];
        }

        $loans = Loan::where('business_id', $business->id)->orderBy('name')->get();
        $loansMonthly = 0.0;
        $loanItems = [];
        foreach ($loans as $loan) {
            $monthly = (float) ($this->loanService->summarizeLoan($loan)['approx_monthly'] ?? 0);
            $loansMonthly += $monthly;
            $loanItems[] = [
                'name'    => $loan->name,
                'monthly' => round($monthly, 2),
                'cadence' => Loan::recurringTypes()[$loan->recurring_type] ?? $loan->recurring_type,
            ];
        }

        $rentals = Rental::where('business_id', $business->id)->with(['ledgerTransactions', 'externalBillingMarks'])->get();
        $rentalsMonthly = 0.0;
        $rentalsOverdue = 0;
        $rentalItems = [];
        foreach ($rentals as $rental) {
            $overdue = $this->rentalService->rentalHasOverduePayments($rental);
            if ($overdue) {
                $rentalsOverdue++;
            }
            $monthly = $this->monthlyEquivalent((float) $rental->recurring_cost, (string) $rental->recurring_type);
            $rentalsMonthly += $monthly;
            $rentalItems[] = [
                'name'    => $rental->property_type,
                'monthly' => round($monthly, 2),
                'cadence' => Rental::recurringTypes()[$rental->recurring_type] ?? $rental->recurring_type,
                'overdue' => $overdue,
            ];
        }

        return [
            'bills'   => ['count' => $bills->count(), 'overdue' => $billsOverdue, 'monthly' => round($billsMonthly, 2), 'items' => $billItems],
            'loans'   => ['count' => $loans->count(), 'monthly' => round($loansMonthly, 2), 'items' => $loanItems],
            'rentals' => ['count' => $rentals->count(), 'overdue' => $rentalsOverdue, 'monthly' => round($rentalsMonthly, 2), 'items' => $rentalItems],
            'total_monthly' => round($billsMonthly + $loansMonthly + $rentalsMonthly, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function profit(Business $business, array $today, array $expenses): array
    {
        $start = now()->startOfMonth();

        $saleIds = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start)
            ->pluck('id');

        $revenue = (float) $business->sales()->whereIn('id', $saleIds)->sum('total');
        $cogs = (float) SaleItem::whereIn('pos_sale_id', $saleIds)
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as cogs')
            ->value('cogs');

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses['total_monthly'];

        return [
            'revenue'      => round($revenue, 2),
            'cogs'         => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses'     => $expenses['total_monthly'],
            'net_profit'   => round($netProfit, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function orders(Business $business): array
    {
        $recentSales = $business->sales()
            ->latest('sold_at')
            ->limit(10)
            ->get(['id', 'sale_number', 'total', 'status', 'sold_at']);

        $recentPurchases = Purchase::where('business_id', $business->id)
            ->with('supplier')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'sales'     => $recentSales,
            'purchases' => $recentPurchases,
        ];
    }

    /** @return array<string, mixed> */
    private function crm(Business $business): array
    {
        $bizId = $business->id;

        return [
            'projects_count' => Project::where('business_id', $bizId)->where('status', Project::STATUS_ACTIVE)->count(),
            'open_leads'     => Lead::where('business_id', $bizId)->whereNull('converted_at')->whereNull('lost_reason')->count(),
            'open_tasks'     => Task::where('business_id', $bizId)->where('status', Task::STATUS_PENDING)->count(),
            'overdue_tasks'  => Task::where('business_id', $bizId)->where('status', Task::STATUS_PENDING)->where('due_at', '<', now())->count(),
            'recent_contacts' => Customer::where('business_id', $bizId)->latest()->limit(5)->get(['id', 'name', 'created_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function rightPanel(Business $business, array $today, array $expenses): array
    {
        $bills = Bill::where('business_id', $business->id)->with('ledgerTransactions')->orderBy('name')->get();

        $upcoming = $bills->map(function (Bill $bill) {
            $overdue = $this->billService->billHasOverduePayments($bill);

            return [
                'name'    => $bill->name,
                'amount'  => $bill->amount_varies_by_usage ? null : round((float) $bill->recurring_cost, 2),
                'cadence' => $bill->isOneTime() ? 'One-time' : (Bill::recurringTypes()[$bill->recurring_type] ?? $bill->recurring_type),
                'overdue' => $overdue,
            ];
        })
            ->sortByDesc('overdue')
            ->take(5)
            ->values()
            ->all();

        return [
            'today' => [
                'sales'      => $today['sales']['count'],
                'revenue'    => $today['sales']['revenue'],
                'items_sold' => $today['sales']['items_sold'],
            ],
            'upcoming_bills' => $upcoming,
        ];
    }

    private function monthlyEquivalent(float $cost, string $recurringType): float
    {
        return match ($recurringType) {
            Bill::RECURRING_PER_DAY  => $cost * 30.0,
            Bill::RECURRING_PER_YEAR => $cost / 12.0,
            default                  => $cost,
        };
    }
}
