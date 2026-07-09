<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class PosGuideChatApiController extends Controller
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a friendly animated guide character inside Zeebroo POS — a business management desktop application.
You can physically walk across the screen and demonstrate features live.

RESPONSE FORMAT — always respond with ONLY valid JSON, no markdown, no code fences, no extra text:
{"reply":"your message","walkthrough":null}

reply: 1–2 friendly sentences. If triggering a demo say "Follow me!" style. For questions, answer concisely.
walkthrough: one of the IDs below when the user wants a demo, otherwise null.

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

Only set walkthrough when the user clearly wants a demonstration. For general knowledge questions set null.
Topics: POS, Inventory, Sales, Finance, HR, Restaurant, business operations.
If completely unrelated to business/Zeebroo, politely redirect.
PROMPT;

    /** Models to try in order until one succeeds. */
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
            return response()->json(['reply' => 'The AI assistant is not configured. Please contact your administrator.']);
        }

        // Prefer the model set in .env, then fall back through the list
        $envModel = config('services.gemini.model');
        $models   = $envModel
            ? array_unique(array_merge([$envModel], self::MODELS))
            : self::MODELS;

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => self::SYSTEM_PROMPT]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $request->input('message')]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 300,
                'temperature'     => 0.2,   // low temp for reliable JSON output
            ],
        ];

        foreach ($models as $model) {
            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );

            if ($response->status() === 429) {
                continue;
            }

            if (! $response->successful()) {
                break;
            }

            $raw = $response->json('candidates.0.content.parts.0.text');
            if (! $raw) {
                continue;
            }

            // Strip markdown code fences Gemini sometimes adds despite instructions
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
            $data    = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['reply'])) {
                return response()->json([
                    'reply'       => trim($data['reply']),
                    'walkthrough' => $data['walkthrough'] ?? null,
                    'productName' => $data['productName'] ?? null,
                    'fieldName'   => $data['fieldName']   ?? null,
                ]);
            }

            // Fallback: Gemini returned plain text instead of JSON
            return response()->json(['reply' => trim($raw), 'walkthrough' => null]);
        }

        return response()->json(['reply' => 'Sorry, I could not get a response right now. Please try again.', 'walkthrough' => null]);
    }
}
