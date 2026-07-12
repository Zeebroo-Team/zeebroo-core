<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Product\Models\Product;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;

class PosGuideChatApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a friendly animated guide character inside Zeebroo POS — a business management desktop application.
You can physically walk across the screen and demonstrate features live.
You can also fetch live business data to answer questions.

RESPONSE FORMAT — always respond with ONLY valid JSON, no markdown, no code fences, no extra text:
{"reply":"your message","walkthrough":null,"dataQuery":null}

reply: 1–2 friendly sentences. For data queries say "Let me check that for you!" style.
walkthrough: one of the WALKTHROUGH IDs below when the user wants a demo, otherwise null.
dataQuery: one of the DATA QUERY IDs below when the user wants live data, otherwise null.

DATA QUERY IDs (use when user asks for live business data/numbers/lists):
  low_stock          – products with low stock (≤ 5 units)
  out_of_stock       – products with zero stock
  today_sales        – today's sales totals and top products
  recent_sales       – recent sales transactions (last 7 days)
  expenses_summary   – monthly expenses summary (bills + rentals)
  overdue_bills      – overdue unpaid bills
  top_products       – top selling products today

WALKTHROUGH IDs (use when user wants to see, do, or learn something):
  add_product          – add / create a new product in inventory
  edit_product         – edit / update / change a product  →  also include "productName" and "fieldName" string fields
  add_category         – add a new category
  open_pos             – open / go to Point of Sale
  new_sale             – start a new sale
  home_dashboard       – view the main dashboard
  view_analytics       – view analytics, charts, revenue reports
  view_orders          – view orders, sales orders, purchase orders
  view_customers       – view the customer list
  view_suppliers       – view suppliers
  view_expenses        – view expenses / bills
  view_profit          – view profit report / profit & loss
  view_payroll         – view payroll / employee payments
  open_settings        – go to settings
  open_help            – help / keyboard shortcuts
  today_summary        – today's sales summary
  recent_activity      – recent transactions / activity log
  business_flow        – business flow overview
  pos_new_session      – start a new POS session
  pos_close_session    – close / end a POS session
  pos_checkout         – checkout / process payment / complete a sale
  pos_return           – process a return or refund
  pos_clear_cart       – clear / empty the cart
  pos_search           – search for products in POS
  pos_barcode          – scan a barcode
  pos_quick_add_product– quick-add an item directly to the POS cart
  pos_assign_customer  – assign a customer to a sale
  pos_accounts         – customer accounts / wallet balances
  pos_settings         – configure POS settings
  pos_park_sale        – park / hold a sale
  pos_recall_sale      – recall / restore a parked sale
  pos_services_mode    – switch POS to services mode
  pos_category_filter  – filter products by category in POS
  inv_products         – view the product list in inventory
  inv_refresh          – refresh the inventory product list
  inv_clear_filters    – clear inventory product filters
  inv_categories       – manage product categories in inventory
  inv_units            – manage units of measure in inventory
  inv_stock_audit      – do a stock audit / inventory count
  inv_brands           – manage product brands in inventory
  inv_discounts        – manage discounts in inventory
  inv_purchase_orders  – view / create purchase orders
  inv_goods_receive    – view goods receive notes / GRN
  inv_cheques          – view / manage supplier cheques
  inv_add_supplier     – add a new supplier
  inv_view_suppliers   – view the supplier list in inventory
  inv_barcodes         – print barcode label sheets
  fin_create_bill      – create / add a new bill in Finance
  fin_view_bills       – view the bills list in Finance
  fin_loans            – view / manage loans in Finance
  fin_rentals          – view / manage rentals in Finance
  fin_properties       – view / manage properties / assets in Finance
  fin_overview         – finance flow overview / finance dashboard
  fin_modifications    – view / manage property modifications in Finance
  fin_bill_detail      – open a bill's detail page (payment history, transactions, pay a bill)
  fin_loan_detail      – open a loan's detail page (repayment schedule, pay an installment)
  fin_rental_detail    – open a rental's detail page (payment schedule, linked bills, land registry)
  fin_modification_detail – open a modification's detail page (cost breakdown, contractor, documents)
  fin_reports          – finance reports / profit analytics / sales reports
  hr_employees         – view the employees list in HR
  hr_add_employee      – add / hire a new employee
  hr_departments       – view / manage departments in HR
  hr_payroll_cycles    – view payroll cycles list in HR
  hr_new_payroll       – create / start a new payroll cycle
  hr_rule_sets         – view / manage payroll rule sets in HR
  hr_allowance_types   – view / manage allowance types in HR
  hr_employee_detail   – open an employee's detail / profile page
  hr_payroll_detail    – open a payroll cycle's detail page (salary sheet, finalize)
  hr_rule_set_detail   – open a rule set's detail page (view/edit rules)
  rst_orders           – view the restaurant orders list (with status filter tabs)
  rst_new_order        – create a new restaurant order
  rst_order_detail     – open a restaurant order's detail (status, items, payment)
  rst_tables           – view the restaurant floor plan / table layout
  rst_reservations     – view / manage restaurant reservations / table bookings
  rst_menu_items       – view the restaurant menu items list
  rst_add_menu_item    – add / create a new menu item / dish
  rst_menu_categories  – view / manage restaurant menu categories
  rst_ingredients      – view / manage kitchen ingredient stock
  rst_purchase_orders  – view / create restaurant ingredient purchase orders
  rst_kitchen          – view the kitchen display (KDS) / kitchen tickets
  rst_pos              – open the restaurant POS (table and takeaway orders)
  svc_requests         – view all service requests (with status filter chips)
  svc_pending_requests – view pending service requests only
  svc_catalog          – view the service catalog list
  svc_new_service      – add / create a new service item
  svc_item_detail      – open a service item's detail page (overview, employees, products tabs)
  svc_categories       – view / manage service categories
  svc_add_category     – add / create a new service category
  svc_refresh          – refresh / reload services data

Only set walkthrough when the user clearly wants a demonstration.
Only set dataQuery when the user is asking for live numbers, lists, or status of their business data.
Never set both walkthrough and dataQuery at the same time.
Topics: POS, Inventory, Sales, Finance, HR, Restaurant, business operations.
If completely unrelated to business/Zeebroo, politely redirect.
PROMPT;

    private const DATA_SYSTEM_PROMPT = <<<'PROMPT'
You are a friendly assistant inside Zeebroo POS. The user asked a data question and we fetched the live data.
Write a concise, friendly HTML summary using only these tags: <b>, <br>, <ul>, <li>, <span>.
No <html>, <body>, <head>, <div>, <p>, <table> tags. Keep it under 200 words.
Reply with ONLY the HTML string — no JSON, no code fences, no extra text.
PROMPT;

    private const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:500']);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['reply' => null, 'walkthrough' => null], 503);
        }

        $envModel = config('services.gemini.model');
        $models   = $envModel
            ? array_unique(array_merge([$envModel], self::MODELS))
            : self::MODELS;

        // ── Pass 1: intent detection ──────────────────────────────────────────
        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $request->input('message')]]]],
            'generationConfig'  => ['maxOutputTokens' => 600, 'temperature' => 0.2],
        ];

        $data = null;
        foreach ($models as $model) {
            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );
            if ($response->status() === 429) continue;
            if (!$response->successful()) break;

            $raw     = $response->json('candidates.0.content.parts.0.text');
            if (!$raw) continue;

            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
            $parsed  = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['reply'])) {
                $data = $parsed;
                break;
            }

            if (preg_match('/"reply"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $cleaned, $m)) {
                $data = ['reply' => stripslashes($m[1]), 'walkthrough' => null, 'dataQuery' => null];
                if (preg_match('/"dataQuery"\s*:\s*"([^"]+)"/', $cleaned, $dm)) {
                    $data['dataQuery'] = $dm[1];
                }
                break;
            }

            if (!str_starts_with(ltrim($cleaned), '{')) {
                $data = ['reply' => trim($raw), 'walkthrough' => null, 'dataQuery' => null];
                break;
            }
        }

        if (!$data) {
            return response()->json(['reply' => null, 'walkthrough' => null], 503);
        }

        // ── Pass 2: data fetch + format (if dataQuery present) ────────────────
        $dataQuery = $data['dataQuery'] ?? null;
        if ($dataQuery) {
            try {
                $business = $this->businessOrAbort($request);
                $rawData  = $this->fetchData($dataQuery, $business);
                $htmlReply = $this->formatWithGemini($models, $apiKey, $request->input('message'), $dataQuery, $rawData);
                return response()->json([
                    'reply'      => $htmlReply,
                    'walkthrough'=> null,
                    'isHtml'     => true,
                ]);
            } catch (\Throwable $e) {
                // Fall through to plain reply if data fetch fails
            }
        }

        return response()->json([
            'reply'       => trim($data['reply']),
            'walkthrough' => $data['walkthrough'] ?? null,
            'productName' => $data['productName'] ?? null,
            'fieldName'   => $data['fieldName']   ?? null,
        ]);
    }

    // ── Data fetchers ─────────────────────────────────────────────────────────

    private function fetchData(string $query, Business $business): array
    {
        return match ($query) {
            'low_stock'       => $this->fetchLowStock($business, false),
            'out_of_stock'    => $this->fetchLowStock($business, true),
            'today_sales'     => $this->fetchTodaySales($business),
            'recent_sales'    => $this->fetchRecentSales($business),
            'expenses_summary'=> $this->fetchExpensesSummary($business),
            'overdue_bills'   => $this->fetchOverdueBills($business),
            'top_products'    => $this->fetchTopProducts($business),
            default           => [],
        };
    }

    private function fetchLowStock(Business $business, bool $outOfStockOnly): array
    {
        $query = Product::where('business_id', $business->id)
            ->where('track_stock', true)
            ->select(['id', 'name', 'sku', 'stock_quantity']);

        if ($outOfStockOnly) {
            $query->where('stock_quantity', '<=', 0);
        } else {
            $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5);
        }

        $products = $query->orderBy('stock_quantity')->limit(20)->get();

        return [
            'type'     => $outOfStockOnly ? 'out_of_stock' : 'low_stock',
            'count'    => $products->count(),
            'products' => $products->map(fn ($p) => [
                'name'  => $p->name,
                'sku'   => $p->sku,
                'stock' => round((float) $p->stock_quantity, 2),
            ])->all(),
        ];
    }

    private function fetchTodaySales(Business $business): array
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
            'type'         => 'today_sales',
            'count'        => $sales->count(),
            'revenue'      => round((float) $sales->sum('total'), 2),
            'items_sold'   => (int) $sales->flatMap->items->sum('quantity'),
            'top_products' => $topProducts,
            'date'         => now()->format('M j, Y'),
        ];
    }

    private function fetchRecentSales(Business $business): array
    {
        $since = now()->subDays(7)->startOfDay();
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $since)
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get(['id', 'sale_number', 'total', 'payment_method', 'sold_at']);

        return [
            'type'    => 'recent_sales',
            'count'   => $sales->count(),
            'revenue' => round((float) $sales->sum('total'), 2),
            'period'  => 'last 7 days',
            'sales'   => $sales->map(fn ($s) => [
                'number'  => $s->sale_number,
                'total'   => round((float) $s->total, 2),
                'method'  => $s->payment_method,
                'date'    => $s->sold_at?->format('M j, g:i A'),
            ])->all(),
        ];
    }

    private function fetchExpensesSummary(Business $business): array
    {
        $month      = now()->month;
        $year       = now()->year;
        $monthLabel = now()->format('F Y');

        $bills = [];
        $billsTotal = 0.0;
        $overdueCount = 0;

        if (\Illuminate\Support\Facades\Schema::hasTable('bills')) {
            $billRows = Bill::where('business_id', $business->id)->get();
            foreach ($billRows as $b) {
                $amount = (float) ($b->recurring_cost ?? 0);
                $paid   = (float) ($b->ledgerTransactions()->whereYear('occurrence_date', $year)->whereMonth('occurrence_date', $month)->sum('amount') ?? 0);
                $isOverdue = $amount > 0 && $paid < $amount && $b->due_date && $b->due_date->isPast();
                if ($isOverdue) $overdueCount++;
                $bills[] = ['name' => $b->name, 'amount' => $amount, 'paid' => round($paid, 2), 'overdue' => $isOverdue];
                $billsTotal += $amount;
            }
        }

        return [
            'type'          => 'expenses_summary',
            'month'         => $monthLabel,
            'bills_total'   => round($billsTotal, 2),
            'bills_count'   => count($bills),
            'overdue_count' => $overdueCount,
            'bills'         => array_slice($bills, 0, 10),
        ];
    }

    private function fetchOverdueBills(Business $business): array
    {
        $overdue = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('bills')) {
            $billRows = Bill::where('business_id', $business->id)
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->with('ledgerTransactions')
                ->get();

            foreach ($billRows as $b) {
                $amount = (float) ($b->recurring_cost ?? 0);
                $paid   = (float) $b->ledgerTransactions->sum('amount');
                if ($amount > 0 && $paid < $amount) {
                    $overdue[] = [
                        'name'      => $b->name,
                        'amount'    => $amount,
                        'paid'      => round($paid, 2),
                        'due'       => $b->due_date->format('M j, Y'),
                        'days_late' => (int) now()->diffInDays($b->due_date),
                    ];
                }
            }
        }

        return [
            'type'    => 'overdue_bills',
            'count'   => count($overdue),
            'bills'   => array_slice($overdue, 0, 10),
        ];
    }

    private function fetchTopProducts(Business $business): array
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
                'name'    => $g->first()->product_name,
                'qty'     => round((float) $g->sum('quantity'), 2),
                'revenue' => round((float) $g->sum('line_total'), 2),
            ])
            ->sortByDesc('revenue')->values()->take(10)->all();

        return [
            'type'     => 'top_products',
            'date'     => now()->format('M j, Y'),
            'count'    => count($top),
            'products' => $top,
        ];
    }

    // ── Gemini pass 2: format data into HTML ──────────────────────────────────

    private function formatWithGemini(array $models, string $apiKey, string $userMessage, string $dataQuery, array $rawData): string
    {
        $dataJson = json_encode($rawData, JSON_PRETTY_PRINT);
        $prompt   = "User asked: \"{$userMessage}\"\n\nLive data fetched ({$dataQuery}):\n{$dataJson}\n\nWrite a friendly HTML summary of this data for the user.";

        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::DATA_SYSTEM_PROMPT]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig'  => ['maxOutputTokens' => 500, 'temperature' => 0.3],
        ];

        foreach ($models as $model) {
            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );
            if ($response->status() === 429) continue;
            if (!$response->successful()) break;

            $raw = $response->json('candidates.0.content.parts.0.text');
            if (!$raw) continue;

            return trim(preg_replace('/^```(?:html)?\s*|\s*```$/s', '', trim($raw)));
        }

        // Gemini unavailable — format in PHP as fallback
        return $this->formatDataFallback($dataQuery, $rawData);
    }

    private function formatDataFallback(string $dataQuery, array $data): string
    {
        return match ($dataQuery) {
            'low_stock' => $this->fallbackLowStock($data, false),
            'out_of_stock' => $this->fallbackLowStock($data, true),
            'today_sales' => "<b>Today's Sales — {$data['date']}</b><br>"
                . "{$data['count']} transactions &middot; Revenue: <b>{$data['revenue']}</b><br>"
                . "{$data['items_sold']} items sold",
            'recent_sales' => "<b>Recent Sales ({$data['period']})</b><br>"
                . "{$data['count']} sales &middot; Total: <b>{$data['revenue']}</b>",
            'expenses_summary' => "<b>Expenses — {$data['month']}</b><br>"
                . "{$data['bills_count']} bills &middot; Total: <b>{$data['bills_total']}</b>"
                . ($data['overdue_count'] > 0 ? "<br><span style='color:#ef4444'>{$data['overdue_count']} overdue</span>" : ''),
            'overdue_bills' => $data['count'] === 0
                ? '<b>No overdue bills!</b> All caught up.'
                : "<b>{$data['count']} overdue bill(s)</b><br>"
                    . implode('<br>', array_map(fn ($b) => "• {$b['name']} — {$b['amount']} (due {$b['due']})", $data['bills'])),
            'top_products' => "<b>Top Products — {$data['date']}</b><br>"
                . implode('<br>', array_map(fn ($p) => "• {$p['name']}: {$p['qty']} sold", $data['products'])),
            default => 'Here is your data summary.',
        };
    }

    private function fallbackLowStock(array $data, bool $outOnly): string
    {
        $label = $outOnly ? 'Out of Stock' : 'Low Stock';
        if ($data['count'] === 0) return "<b>No {$label} products!</b> All stocked up.";
        $items = implode('<br>', array_map(
            fn ($p) => "• {$p['name']}" . ($p['sku'] ? " ({$p['sku']})" : '') . " — <b>{$p['stock']}</b> left",
            $data['products']
        ));
        return "<b>{$label} Alert — {$data['count']} product(s)</b><br>{$items}";
    }
}
