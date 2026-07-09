/**
 * Zeebroo POS — Guide Walkthrough Configuration
 *
 * Add new guided walkthroughs here — no engine code changes needed.
 *
 * Step types:
 *   walk_click      Move character to selector, highlight, click, wait
 *   walk_to         Move character to selector (no click)
 *   highlight       Add blue pulse ring to selector
 *   unhighlight     Remove pulse ring from selector
 *   bubble          Show chat bubble with text (wait ms, then close)
 *   walk_search     Walk to input, type {{var}}, dispatch input event, wait
 *   find_click_row  Find table row containing {{var}} text, walk+click it
 *   walk_to_field   Walk to resolved field_map field (with optional scroll)
 *   highlight_field Add pulse ring to resolved field_map field
 *   unhighlight_field Remove pulse ring from resolved field_map field
 *   wait_visible    Poll until element is visible (timeout ms)
 *   wait            Sleep ms milliseconds
 *
 * Template vars: {{varName}} is replaced from the vars captured by parse_patterns.
 * field_map: maps user-typed field names → product modal input IDs + labels.
 * {{fieldLabel}} is auto-populated from field_map when fieldName is captured.
 */

window.GUIDE_CONFIG = {

  /* ── Field map ──────────────────────────────────────────────────────────
     Maps any user-typed field name to a product modal input element.
     Add aliases freely — they all resolve to the same field.
  ────────────────────────────────────────────────────────────────────── */
  field_map: {
    "name":          { "id": "prod-f-name",        "label": "Name" },
    "title":         { "id": "prod-f-name",        "label": "Name" },
    "sku":           { "id": "prod-f-sku",         "label": "SKU" },
    "barcode":       { "id": "prod-f-sku",         "label": "SKU / Barcode" },
    "code":          { "id": "prod-f-sku",         "label": "SKU / Barcode" },
    "price":         { "id": "prod-f-price",       "label": "Selling Price" },
    "selling price": { "id": "prod-f-price",       "label": "Selling Price" },
    "cost":          { "id": "prod-f-price",       "label": "Selling Price" },
    "amount":        { "id": "prod-f-price",       "label": "Selling Price" },
    "stock":         { "id": "prod-f-stock",       "label": "Stock Quantity" },
    "quantity":      { "id": "prod-f-stock",       "label": "Stock Quantity" },
    "qty":           { "id": "prod-f-stock",       "label": "Stock Quantity" },
    "unit":          { "id": "prod-f-unit",        "label": "Unit" },
    "description":   { "id": "prod-f-description", "label": "Description" },
    "desc":          { "id": "prod-f-description", "label": "Description" },
    "details":       { "id": "prod-f-description", "label": "Description" },
    "category":      { "id": "prod-cat-input",     "label": "Category" },
    "categories":    { "id": "prod-cat-input",     "label": "Category" },
    "brand":         { "id": "prod-brand-input",   "label": "Brand" },
    "image":         { "id": "prod-img-choose",    "label": "Image" },
    "photo":         { "id": "prod-img-choose",    "label": "Image" },
    "status":        { "id": "prod-f-active",      "label": "Active Status" },
    "active":        { "id": "prod-f-active",      "label": "Active Status" }
  },

  /* ── Walkthroughs ───────────────────────────────────────────────────────
     Each entry is one guided walkthrough.
     Matching:
       intent_patterns  Array of substrings; matched with msg.includes() (case-insensitive)
       parse_patterns   Array of regexes with named groups; used when you need to
                        extract variables (e.g. product name, field name) from the message.
                        First pattern that matches wins. Named groups become template vars.
  ────────────────────────────────────────────────────────────────────── */
  walkthroughs: [

    /* ── Add New Product ─────────────────────────────────────────────── */
    {
      "id": "add_product",
      "intent_patterns": ["add new product", "new product", "create product", "add product"],
      "reply": "Sure! Follow me — I'll walk you through adding a new product right now.",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 750 },
        { "type": "walk_click",   "selector": ".inv-subnav-btn[data-inv-view='products']",   "wait": 900 },
        { "type": "walk_click",   "selector": "#inv-new-product-btn",                        "wait": 450 },
        { "type": "wait_visible", "selector": "#product-modal" },
        { "type": "walk_to",      "selector": "#prod-modal-save" },
        { "type": "highlight",    "selector": "#prod-modal-save" },
        { "type": "bubble",       "text": "Fill in the product details, then click Save Product when you're done!", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#prod-modal-save" }
      ]
    },

    /* ── Edit Product Field ──────────────────────────────────────────── */
    {
      "id": "edit_product",
      "parse_patterns": [
        "(?<productName>.+?)\\s+edit\\s+product\\s+change\\s+(?<fieldName>.+)",
        "edit\\s+product\\s+(?<productName>.+?)\\s+change\\s+(?<fieldName>.+)",
        "(?<productName>.+?)\\s+edit\\s+product\\s+update\\s+(?<fieldName>.+)",
        "update\\s+product\\s+(?<productName>.+?)\\s+change\\s+(?<fieldName>.+)"
      ],
      "reply": "Got it! I'll find \"{{productName}}\" and take you to the {{fieldLabel}} field.",
      "steps": [
        { "type": "walk_click",      "selector": "[data-tab='inventory']",                    "wait": 750 },
        { "type": "walk_click",      "selector": ".inv-subnav-btn[data-inv-view='products']", "wait": 900 },
        { "type": "walk_search",     "selector": "#inv-search", "value": "{{productName}}",  "wait": 1200 },
        { "type": "find_click_row",  "tbody": "#inv-tbody",     "cell": ".inv-name",
          "value": "{{productName}}", "wait": 700,
          "not_found": "I couldn't find \"{{productName}}\". Check the exact product name in Inventory → Products." },
        { "type": "wait_visible",    "selector": "#inv-detail-view" },
        { "type": "walk_click",      "selector": "#prod-edit-btn",  "wait": 400 },
        { "type": "wait_visible",    "selector": "#product-modal" },
        { "type": "walk_to_field",   "field": "{{fieldName}}",      "scroll": true },
        { "type": "highlight_field", "field": "{{fieldName}}" },
        { "type": "bubble",          "text": "Update the \"{{fieldLabel}}\" here, then click Save Product when you're done!", "wait": 3500 },
        { "type": "unhighlight_field", "field": "{{fieldName}}" },
        { "type": "walk_to",         "selector": "#prod-modal-save" },
        { "type": "highlight",       "selector": "#prod-modal-save" },
        { "type": "bubble",          "text": "Click Save Product to save your changes.", "wait": 3000 },
        { "type": "unhighlight",     "selector": "#prod-modal-save" }
      ]
    },

    /* ── Add New Category ────────────────────────────────────────────── */
    {
      "id": "add_category",
      "intent_patterns": ["add new category", "new category", "create category", "add category"],
      "reply": "Sure! Follow me — I'll walk you through adding a new category right now.",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 750 },
        { "type": "walk_click",   "selector": ".inv-subnav-btn[data-inv-view='categories']", "wait": 800 },
        { "type": "walk_click",   "selector": "#cat-add-btn",                                "wait": 450 },
        { "type": "wait_visible", "selector": "#cat-modal" },
        { "type": "walk_to",      "selector": "#cat-f-name" },
        { "type": "highlight",    "selector": "#cat-f-name" },
        { "type": "bubble",       "text": "Enter the category name here. You can also set a parent category and description below.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#cat-f-name" },
        { "type": "walk_to",      "selector": "#cat-modal-save" },
        { "type": "highlight",    "selector": "#cat-modal-save" },
        { "type": "bubble",       "text": "Click Save Category when you're done!", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#cat-modal-save" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME RIBBON — Quick Actions
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Open POS ────────────────────────────────────────────────────── */
    {
      "id": "open_pos",
      "intent_patterns": ["open pos", "go to pos", "start selling", "launch pos", "point of sale"],
      "reply": "Sure! Let me take you to the Point of Sale right now.",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",  "wait": 400 },
        { "type": "walk_click", "selector": "#rb-home-pos",       "wait": 600 },
        { "type": "walk_to",    "selector": "[data-tab='pos']" },
        { "type": "highlight",  "selector": "[data-tab='pos']" },
        { "type": "bubble",     "text": "You're in Point of Sale! Search or scan products to start ringing up a sale.", "wait": 3500 },
        { "type": "unhighlight","selector": "[data-tab='pos']" }
      ]
    },

    /* ── New Sale ─────────────────────────────────────────────────────── */
    {
      "id": "new_sale",
      "intent_patterns": ["new sale", "create sale", "start sale", "start new sale", "make a sale"],
      "reply": "Let me open the POS for a new sale!",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",   "wait": 400 },
        { "type": "walk_click", "selector": "#rb-home-new-sale",   "wait": 600 },
        { "type": "walk_to",    "selector": "[data-tab='pos']" },
        { "type": "highlight",  "selector": "[data-tab='pos']" },
        { "type": "bubble",     "text": "POS is open! Search for products by name or scan a barcode to add them to the cart.", "wait": 3500 },
        { "type": "unhighlight","selector": "[data-tab='pos']" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME RIBBON — Overview
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Dashboard ───────────────────────────────────────────────────── */
    {
      "id": "home_dashboard",
      "intent_patterns": ["go to dashboard", "home dashboard", "show dashboard", "view dashboard", "open dashboard"],
      "reply": "Taking you to the Home Dashboard!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-dashboard",      "wait": 500 },
        { "type": "walk_to",      "selector": "#home-view-flow" },
        { "type": "bubble",       "text": "This is your business dashboard — live KPIs, recent activity, bills, and a full business flow overview.", "wait": 3500 }
      ]
    },

    /* ── Analytics ───────────────────────────────────────────────────── */
    {
      "id": "view_analytics",
      "intent_patterns": ["view analytics", "show analytics", "analytics report", "analytics overview", "revenue analytics", "open analytics"],
      "reply": "Let me take you to the Analytics view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-analytics",      "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-analytics" },
        { "type": "walk_to",      "selector": "#han-period-btns" },
        { "type": "highlight",    "selector": "#han-period-btns" },
        { "type": "bubble",       "text": "Analytics is open! Use the 7D / 30D / 90D buttons to change the date range. Below you'll see KPI cards, a revenue trend chart, and upcoming expenses.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#han-period-btns" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME RIBBON — Operations
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Orders ──────────────────────────────────────────────────────── */
    {
      "id": "view_orders",
      "intent_patterns": ["view orders", "show orders", "orders summary", "open orders", "sales and purchase orders"],
      "reply": "Opening the Orders summary view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-orders",         "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-orders" },
        { "type": "walk_to",      "selector": "#hov-sales-count" },
        { "type": "highlight",    "selector": "#hov-sales-count" },
        { "type": "bubble",       "text": "Here's your Orders view — KPI strip shows total sales, revenue, and purchase order counts. Use the date and status filters to narrow down records.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#hov-sales-count" }
      ]
    },

    /* ── Customers ───────────────────────────────────────────────────── */
    {
      "id": "view_customers",
      "intent_patterns": ["view customers", "show customers", "customer list", "open customers", "customers"],
      "reply": "Opening the Customers panel!",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",         "wait": 400 },
        { "type": "walk_click", "selector": "#rb-home-customers",        "wait": 600 }
      ]
    },

    /* ── Suppliers ───────────────────────────────────────────────────── */
    {
      "id": "view_suppliers",
      "intent_patterns": ["view suppliers", "show suppliers", "supplier list", "open suppliers", "go to suppliers"],
      "reply": "Taking you to the Suppliers view in Inventory!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",                       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-suppliers",                      "wait": 600 },
        { "type": "walk_to",      "selector": ".inv-subnav-btn[data-inv-view='suppliers']" },
        { "type": "highlight",    "selector": ".inv-subnav-btn[data-inv-view='suppliers']" },
        { "type": "bubble",       "text": "You're in the Suppliers view. Add, edit, and manage all your suppliers and their contact details here.", "wait": 3500 },
        { "type": "unhighlight",  "selector": ".inv-subnav-btn[data-inv-view='suppliers']" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME RIBBON — Finance
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Expenses ────────────────────────────────────────────────────── */
    {
      "id": "view_expenses",
      "intent_patterns": ["view expenses", "show expenses", "expense report", "open expenses", "expense view"],
      "reply": "Opening the Expenses view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-expenses",       "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-expenses" },
        { "type": "walk_to",      "selector": "#exp-refresh" },
        { "type": "highlight",    "selector": "#exp-refresh" },
        { "type": "bubble",       "text": "This is the Expenses view — bills, rentals, loans, and recurring payments are all summarised here. Hit Refresh to reload the latest data.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#exp-refresh" }
      ]
    },

    /* ── Profit Report ───────────────────────────────────────────────── */
    {
      "id": "view_profit",
      "intent_patterns": ["profit report", "view profit", "show profit", "profit summary", "profit and loss", "open profit report"],
      "reply": "Opening the Profit Report!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-profit",         "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-profit" },
        { "type": "walk_to",      "selector": "#prf-period-select" },
        { "type": "highlight",    "selector": "#prf-period-select" },
        { "type": "bubble",       "text": "This is the Profit Report. Use the period selector to choose Last 7 / 30 / 90 Days or Last 12 Months and see your revenue vs expenses breakdown.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#prf-period-select" }
      ]
    },

    /* ── Payroll ─────────────────────────────────────────────────────── */
    {
      "id": "view_payroll",
      "intent_patterns": ["view payroll", "payroll report", "show payroll", "open payroll", "payroll summary"],
      "reply": "Opening the Payroll summary!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",       "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-payroll",        "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-payroll" },
        { "type": "walk_to",      "selector": "#prl-refresh" },
        { "type": "highlight",    "selector": "#prl-refresh" },
        { "type": "bubble",       "text": "This is the Payroll view — a summary of your payroll cycles and employee payment status. Hit Refresh to reload.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#prl-refresh" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME RIBBON — Tools
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Settings ────────────────────────────────────────────────────── */
    {
      "id": "open_settings",
      "intent_patterns": ["go to settings", "open settings", "settings", "show settings"],
      "reply": "Here's the Settings button — click it to access configuration options.",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='home']",        "wait": 400 },
        { "type": "walk_to",     "selector": "#rb-home-settings" },
        { "type": "highlight",   "selector": "#rb-home-settings" },
        { "type": "bubble",      "text": "This is the Settings button. Click it to configure your business, taxes, receipt templates, and more.", "wait": 3500 },
        { "type": "unhighlight", "selector": "#rb-home-settings" }
      ]
    },

    /* ── Help / Shortcuts ─────────────────────────────────────────────── */
    {
      "id": "open_help",
      "intent_patterns": ["help", "keyboard shortcuts", "show shortcuts", "open help", "shortcuts"],
      "reply": "Opening the help and keyboard shortcuts panel!",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",         "wait": 400 },
        { "type": "walk_click", "selector": "#rb-home-help",             "wait": 400 }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HOME PANEL TAB BUTTONS (inside the home content area)
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Today's Summary ─────────────────────────────────────────────── */
    {
      "id": "today_summary",
      "intent_patterns": ["today's summary", "daily summary", "today summary", "view today", "show today"],
      "reply": "Opening Today's Summary!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",                        "wait": 400 },
        { "type": "walk_click",   "selector": "#rb-home-daily-summary",                   "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-today" },
        { "type": "walk_to",      "selector": "#tds-refresh" },
        { "type": "highlight",    "selector": "#tds-refresh" },
        { "type": "bubble",       "text": "Today's Summary shows all sales, revenue, and key metrics for today. Hit Refresh to get the latest figures.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#tds-refresh" }
      ]
    },

    /* ── Recent Activity ─────────────────────────────────────────────── */
    {
      "id": "recent_activity",
      "intent_patterns": ["recent activity", "view activity", "transaction history", "recent transactions", "activity log"],
      "reply": "Let me show you the Recent Activity view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='home']",                           "wait": 400 },
        { "type": "walk_click",   "selector": ".home-tab-btn[data-home-view='activity']",    "wait": 500 },
        { "type": "wait_visible", "selector": "#home-view-activity" },
        { "type": "walk_to",      "selector": "#home-act-refresh" },
        { "type": "highlight",    "selector": "#home-act-refresh" },
        { "type": "bubble",       "text": "Recent Activity shows your latest transactions in chronological order. Hit Refresh to reload.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#home-act-refresh" }
      ]
    },

    /* ── Business Flow ───────────────────────────────────────────────── */
    {
      "id": "business_flow",
      "intent_patterns": ["business flow", "view flow", "show flow", "flow overview", "flow diagram"],
      "reply": "Opening the Business Flow overview!",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",                          "wait": 400 },
        { "type": "walk_click", "selector": ".home-tab-btn[data-home-view='flow']",       "wait": 500 },
        { "type": "walk_to",    "selector": "#home-view-flow" },
        { "type": "bubble",     "text": "Business Flow gives you a visual overview of your business pipeline — from inventory to sales, finance, and HR all in one place.", "wait": 4000 }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS RIBBON — Session Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── New Session ─────────────────────────────────────────────────── */
    {
      "id": "pos_new_session",
      "intent_patterns": ["new session", "start session", "open session", "create session", "new pos session"],
      "reply": "Let me show you how to start a new POS session!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='pos']",    "wait": 500 },
        { "type": "walk_to",      "selector": "#rb-new-session" },
        { "type": "highlight",    "selector": "#rb-new-session" },
        { "type": "bubble",       "text": "Click New Session to open a fresh POS session. Each session gets its own tab in the session bar — useful for managing multiple tills or cashiers.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#rb-new-session" },
        { "type": "walk_to",      "selector": "#pos-tab-add" },
        { "type": "highlight",    "selector": "#pos-tab-add" },
        { "type": "bubble",       "text": "You can also click the + button here to add a new session tab directly.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#pos-tab-add" }
      ]
    },

    /* ── Close Session ───────────────────────────────────────────────── */
    {
      "id": "pos_close_session",
      "intent_patterns": ["close session", "end session", "finish session", "close pos session"],
      "reply": "Here's how to close the current POS session.",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='pos']",      "wait": 500 },
        { "type": "walk_to",    "selector": "#rb-close-session" },
        { "type": "highlight",  "selector": "#rb-close-session" },
        { "type": "bubble",     "text": "Click Close Session to end the current POS session. You'll see a session summary with total sales and cash totals before it closes.", "wait": 4500 },
        { "type": "unhighlight","selector": "#rb-close-session" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS RIBBON — Sales Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Checkout ────────────────────────────────────────────────────── */
    {
      "id": "pos_checkout",
      "intent_patterns": ["checkout", "process payment", "pay now", "complete sale", "process sale"],
      "reply": "Let me show you the Checkout flow!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-checkout" },
        { "type": "highlight",   "selector": "#rb-checkout" },
        { "type": "bubble",      "text": "Click Checkout (or press F12) once you've added items to the cart. This opens the payment screen where you can choose cash, card, or other payment methods.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-checkout" },
        { "type": "walk_to",     "selector": "#cart-area" },
        { "type": "highlight",   "selector": "#checkout-btn" },
        { "type": "bubble",      "text": "The Checkout button is also at the bottom of the cart area — add products first, then hit Checkout to process payment.", "wait": 4000 },
        { "type": "unhighlight", "selector": "#checkout-btn" }
      ]
    },

    /* ── Return / Refund ─────────────────────────────────────────────── */
    {
      "id": "pos_return",
      "intent_patterns": ["return", "refund", "process return", "make refund", "pos refund", "pos return"],
      "reply": "Let me show you how to process a return or refund!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-return" },
        { "type": "highlight",   "selector": "#rb-return" },
        { "type": "bubble",      "text": "Click Return / Refund (or press F9) to process a return. You'll be able to search for the original sale and select which items to refund.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-return" }
      ]
    },

    /* ── Clear Cart ──────────────────────────────────────────────────── */
    {
      "id": "pos_clear_cart",
      "intent_patterns": ["clear cart", "empty cart", "remove all items", "reset cart", "clear all"],
      "reply": "Here's the Clear Cart button!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-clear-cart" },
        { "type": "highlight",   "selector": "#rb-clear-cart" },
        { "type": "bubble",      "text": "Click Clear Cart (or press F8) to remove all items from the current cart and start fresh. You'll be asked to confirm before anything is deleted.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-clear-cart" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS RIBBON — Find Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Search Products ─────────────────────────────────────────────── */
    {
      "id": "pos_search",
      "intent_patterns": ["search products", "find product", "search pos", "look up product", "pos search"],
      "reply": "Let me show you how to search for products in POS!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-search" },
        { "type": "highlight",   "selector": "#rb-search" },
        { "type": "bubble",      "text": "Click Search Products (or press F2) to focus the product search bar. Type a name, SKU, or barcode to instantly filter the product grid.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-search" },
        { "type": "walk_to",     "selector": "#product-search" },
        { "type": "highlight",   "selector": "#product-search" },
        { "type": "bubble",      "text": "This is the search bar — type here to filter products in real-time. Press Enter or click a product card to add it to the cart.", "wait": 4000 },
        { "type": "unhighlight", "selector": "#product-search" }
      ]
    },

    /* ── Scan Barcode ────────────────────────────────────────────────── */
    {
      "id": "pos_barcode",
      "intent_patterns": ["scan barcode", "barcode scanner", "scan item", "barcode scan", "use barcode"],
      "reply": "Here's how to use the barcode scanner in POS!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-barcode" },
        { "type": "highlight",   "selector": "#rb-barcode" },
        { "type": "bubble",      "text": "Click Scan Barcode (or press F3) to activate the barcode input mode. Then scan or type a barcode — the matching product is added to the cart instantly.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-barcode" }
      ]
    },

    /* ── Quick Add Product (POS) ─────────────────────────────────────── */
    {
      "id": "pos_quick_add_product",
      "intent_patterns": ["quick add product", "add item pos", "pos add product", "add product pos", "add item to cart"],
      "reply": "Let me show you the quick Add Product button in POS!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-add-product" },
        { "type": "highlight",   "selector": "#rb-add-product" },
        { "type": "bubble",      "text": "Click Add Product (or press F4) to open a quick-entry form — fill in a name and price to create a one-off item and add it straight to the cart without adding it to inventory.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-add-product" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS RIBBON — Customers Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Assign Customer to Sale ─────────────────────────────────────── */
    {
      "id": "pos_assign_customer",
      "intent_patterns": ["assign customer", "customer to sale", "add customer to cart", "pos customer", "select customer pos"],
      "reply": "Let me show you how to assign a customer to a sale!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-customers" },
        { "type": "highlight",   "selector": "#rb-customers" },
        { "type": "bubble",      "text": "Click Customers to open the customer panel. Select a customer to attach them to this sale — their loyalty points, credit balance, and purchase history will be linked.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#rb-customers" },
        { "type": "walk_to",     "selector": "#btn-customer" },
        { "type": "highlight",   "selector": "#btn-customer" },
        { "type": "bubble",      "text": "You can also press F10 or click the person icon in the cart header to assign a customer directly.", "wait": 3500 },
        { "type": "unhighlight", "selector": "#btn-customer" }
      ]
    },

    /* ── Accounts ────────────────────────────────────────────────────── */
    {
      "id": "pos_accounts",
      "intent_patterns": ["pos accounts", "wallet accounts", "account balance", "customer accounts"],
      "reply": "Here's the Accounts button in POS.",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-accounts" },
        { "type": "highlight",   "selector": "#rb-accounts" },
        { "type": "bubble",      "text": "The Accounts button gives quick access to customer wallet and credit account balances — useful when a customer wants to pay using store credit.", "wait": 4000 },
        { "type": "unhighlight", "selector": "#rb-accounts" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS RIBBON — Configure Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── POS Settings ────────────────────────────────────────────────── */
    {
      "id": "pos_settings",
      "intent_patterns": ["pos settings", "configure pos", "open pos settings", "pos configuration", "set up pos"],
      "reply": "Opening POS Settings!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#rb-pos-settings" },
        { "type": "highlight",   "selector": "#rb-pos-settings" },
        { "type": "bubble",      "text": "Click POS Settings to configure receipt printer, cash drawer, tax rates, tip options, and other point-of-sale preferences.", "wait": 4000 },
        { "type": "unhighlight", "selector": "#rb-pos-settings" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       POS INLINE FEATURES (cart header & product area)
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Park / Hold Sale ────────────────────────────────────────────── */
    {
      "id": "pos_park_sale",
      "intent_patterns": ["park sale", "hold sale", "pause sale", "hold cart", "put on hold"],
      "reply": "Let me show you how to park (hold) a sale!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#btn-park" },
        { "type": "highlight",   "selector": "#btn-park" },
        { "type": "bubble",      "text": "Click the Park button (or press F6) to hold the current cart without losing it. Useful when a customer needs to step away — you can serve another customer and recall this sale later.", "wait": 5000 },
        { "type": "unhighlight", "selector": "#btn-park" }
      ]
    },

    /* ── Recall Held Sale ────────────────────────────────────────────── */
    {
      "id": "pos_recall_sale",
      "intent_patterns": ["recall sale", "unhold sale", "retrieve held sale", "recall cart", "recall parked"],
      "reply": "Here's how to recall a parked sale!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#btn-recall" },
        { "type": "highlight",   "selector": "#btn-recall" },
        { "type": "bubble",      "text": "Click the Recall button (or press F7) to bring back a previously parked sale. If there are multiple held sales, you can choose which one to restore.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#btn-recall" }
      ]
    },

    /* ── Switch to Services Mode ─────────────────────────────────────── */
    {
      "id": "pos_services_mode",
      "intent_patterns": ["switch to services", "services mode", "service tab", "view services", "sell service"],
      "reply": "Let me switch POS to Services mode!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='pos']",               "wait": 500 },
        { "type": "walk_to",      "selector": ".pos-mode-btn[data-mode='services']" },
        { "type": "highlight",    "selector": ".pos-mode-btn[data-mode='services']" },
        { "type": "bubble",       "text": "Click Services in the mode switcher to browse and add service items (labour, subscriptions, etc.) to the cart instead of physical products.", "wait": 4000 },
        { "type": "unhighlight",  "selector": ".pos-mode-btn[data-mode='services']" }
      ]
    },

    /* ── Category Filter ─────────────────────────────────────────────── */
    {
      "id": "pos_category_filter",
      "intent_patterns": ["filter by category", "category filter", "filter products", "product category", "browse category"],
      "reply": "Let me show you the category filter in POS!",
      "steps": [
        { "type": "walk_click",  "selector": "[data-tab='pos']",     "wait": 500 },
        { "type": "walk_to",     "selector": "#category-filter" },
        { "type": "highlight",   "selector": "#category-filter" },
        { "type": "bubble",      "text": "Use the category chips here to filter the product grid by category. Click 'All' to see everything, or tap a specific category chip to narrow the view.", "wait": 4500 },
        { "type": "unhighlight", "selector": "#category-filter" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY RIBBON — Catalog Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── View Products ───────────────────────────────────────────────── */
    {
      "id": "inv_products",
      "intent_patterns": ["view products", "inventory products", "product list", "go to products", "show products"],
      "reply": "Let me take you to the Products view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-products",                          "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-list-view" },
        { "type": "walk_to",      "selector": "#inv-new-product-btn" },
        { "type": "highlight",    "selector": "#inv-new-product-btn" },
        { "type": "bubble",       "text": "This is your Products list. Search by name, filter by category or brand, or click New Product to add one.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#inv-new-product-btn" }
      ]
    },

    /* ── Refresh Products ────────────────────────────────────────────── */
    {
      "id": "inv_refresh",
      "intent_patterns": ["refresh inventory", "refresh products", "reload products", "reload inventory"],
      "reply": "Here's the Refresh button for the product list!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                    "wait": 500 },
        { "type": "walk_click",   "selector": ".inv-subnav-btn[data-inv-view='products']", "wait": 600 },
        { "type": "walk_to",      "selector": "#rb-refresh" },
        { "type": "highlight",    "selector": "#rb-refresh" },
        { "type": "bubble",       "text": "Click Refresh to reload the product list from the server and pick up any recent changes.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#rb-refresh" }
      ]
    },

    /* ── Clear Filters ───────────────────────────────────────────────── */
    {
      "id": "inv_clear_filters",
      "intent_patterns": ["clear filters", "reset filters", "remove filters", "clear product filters"],
      "reply": "Let me show you how to clear all product filters!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                    "wait": 500 },
        { "type": "walk_click",   "selector": ".inv-subnav-btn[data-inv-view='products']", "wait": 600 },
        { "type": "walk_to",      "selector": "#rb-clear-filters" },
        { "type": "highlight",    "selector": "#rb-clear-filters" },
        { "type": "bubble",       "text": "Click Clear Filters to remove any active stock, brand, or sort filters and show all products again.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#rb-clear-filters" }
      ]
    },

    /* ── Categories ──────────────────────────────────────────────────── */
    {
      "id": "inv_categories",
      "intent_patterns": ["inventory categories", "view categories", "manage categories", "product categories", "go to categories"],
      "reply": "Opening the Categories view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-categories",                          "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-categories-view" },
        { "type": "walk_to",      "selector": "#cat-add-btn" },
        { "type": "highlight",    "selector": "#cat-add-btn" },
        { "type": "bubble",       "text": "This is the Categories view. Click New Category to add one, or click any existing category to edit it.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#cat-add-btn" }
      ]
    },

    /* ── Units ───────────────────────────────────────────────────────── */
    {
      "id": "inv_units",
      "intent_patterns": ["units of measure", "view units", "manage units", "product units", "go to units"],
      "reply": "Opening the Units of Measure view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-units",                               "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-units-view" },
        { "type": "walk_to",      "selector": "#unit-add-btn" },
        { "type": "highlight",    "selector": "#unit-add-btn" },
        { "type": "bubble",       "text": "Units of measure (kg, pcs, litre, etc.) are managed here. Click New Unit to add a custom unit for your products.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#unit-add-btn" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY RIBBON — Stock Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Stock Audit ─────────────────────────────────────────────────── */
    {
      "id": "inv_stock_audit",
      "intent_patterns": ["stock audit", "inventory audit", "count stock", "audit inventory", "stock count"],
      "reply": "Let me take you to the Stock Audit view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-audit",                               "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-audit-view" },
        { "type": "walk_to",      "selector": "#audit-new-btn" },
        { "type": "highlight",    "selector": "#audit-new-btn" },
        { "type": "bubble",       "text": "Stock Audit lets you count your physical stock and reconcile it against your system records. Click New Audit to start a count session.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#audit-new-btn" }
      ]
    },

    /* ── Brands ──────────────────────────────────────────────────────── */
    {
      "id": "inv_brands",
      "intent_patterns": ["view brands", "manage brands", "product brands", "go to brands", "inventory brands"],
      "reply": "Opening the Brands view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-brands",                              "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-brands-view" },
        { "type": "walk_to",      "selector": "#brand-add-btn" },
        { "type": "highlight",    "selector": "#brand-add-btn" },
        { "type": "bubble",       "text": "Brands are managed here — they help you organise and filter products by manufacturer. Click New Brand to add one.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#brand-add-btn" }
      ]
    },

    /* ── Discounts ───────────────────────────────────────────────────── */
    {
      "id": "inv_discounts",
      "intent_patterns": ["view discounts", "manage discounts", "product discounts", "go to discounts", "inventory discounts"],
      "reply": "Opening the Discounts view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-discounts",                           "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-discounts-view" },
        { "type": "walk_to",      "selector": "#disc-add-btn" },
        { "type": "highlight",    "selector": "#disc-add-btn" },
        { "type": "bubble",       "text": "Discounts can be flat amounts or percentages and applied to products or categories at POS. Click New Discount to create one.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#disc-add-btn" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY RIBBON — Purchasing Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Purchase Orders ─────────────────────────────────────────────── */
    {
      "id": "inv_purchase_orders",
      "intent_patterns": ["purchase orders", "view purchase orders", "create purchase order", "new purchase order", "po list", "go to purchase orders"],
      "reply": "Taking you to Purchase Orders!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-orders",                                  "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-po-view" },
        { "type": "walk_to",      "selector": "#po-add-btn" },
        { "type": "highlight",    "selector": "#po-add-btn" },
        { "type": "bubble",       "text": "Purchase Orders let you formally order stock from a supplier. Click New PO to create an order — once received, you log it as a Goods Receipt.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#po-add-btn" }
      ]
    },

    /* ── Goods Receive ───────────────────────────────────────────────── */
    {
      "id": "inv_goods_receive",
      "intent_patterns": ["goods receive", "goods receipt", "receive stock", "grn", "receive goods", "stock receipt"],
      "reply": "Opening the Goods Receive view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-grn",                                 "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-grn-view" },
        { "type": "walk_to",      "selector": "#grn-search" },
        { "type": "highlight",    "selector": "#grn-search" },
        { "type": "bubble",       "text": "Goods Receive Notes (GRNs) record when stock arrives from a supplier. Search by GRN number, PO number, or supplier name here.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#grn-search" }
      ]
    },

    /* ── Cheques ─────────────────────────────────────────────────────── */
    {
      "id": "inv_cheques",
      "intent_patterns": ["cheques", "view cheques", "manage cheques", "cheque tracker", "go to cheques"],
      "reply": "Opening the Cheques tracker!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-cheques",                             "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-cheques-view" },
        { "type": "walk_to",      "selector": "#chq-summary" },
        { "type": "highlight",    "selector": "#chq-summary" },
        { "type": "bubble",       "text": "The Cheques view tracks all post-dated and pending cheques from suppliers. The summary shows total value, pending, overdue, and cleared counts.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#chq-summary" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY RIBBON — Suppliers Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Add New Supplier ────────────────────────────────────────────── */
    {
      "id": "inv_add_supplier",
      "intent_patterns": ["add supplier", "new supplier", "create supplier", "add new supplier"],
      "reply": "Let me walk you through adding a new supplier!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-suppliers",                           "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-suppliers-view" },
        { "type": "walk_click",   "selector": "#sup-add-btn",                                "wait": 400 },
        { "type": "wait_visible", "selector": "#sup-modal" },
        { "type": "walk_to",      "selector": "#sup-modal" },
        { "type": "bubble",       "text": "Fill in the supplier's name, contact details, and payment terms, then click Save to add them to your supplier list.", "wait": 4500 }
      ]
    },

    /* ── View Suppliers ──────────────────────────────────────────────── */
    {
      "id": "inv_view_suppliers",
      "intent_patterns": ["inventory suppliers", "manage suppliers", "supplier list inventory"],
      "reply": "Opening the Suppliers view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-suppliers",                           "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-suppliers-view" },
        { "type": "walk_to",      "selector": "#sup-add-btn" },
        { "type": "highlight",    "selector": "#sup-add-btn" },
        { "type": "bubble",       "text": "This is the Suppliers view. Click a supplier to see their details, purchase history, and outstanding payments. Click New Supplier to add one.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#sup-add-btn" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       INVENTORY RIBBON — Print Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Barcode Sheets ──────────────────────────────────────────────── */
    {
      "id": "inv_barcodes",
      "intent_patterns": ["barcode sheets", "print barcodes", "barcode labels", "print labels", "generate barcodes"],
      "reply": "Opening the Barcode Sheets view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='inventory']",                      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-inv-barcodes",                            "wait": 600 },
        { "type": "wait_visible", "selector": "#inv-barcodes-view" },
        { "type": "walk_to",      "selector": "#bc-search" },
        { "type": "highlight",    "selector": "#bc-search" },
        { "type": "bubble",       "text": "Search or browse products here, tick the ones you want, then choose label size and columns and click Print Barcodes to generate a printable sheet.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#bc-search" },
        { "type": "walk_to",      "selector": "#bc-print-btn" },
        { "type": "highlight",    "selector": "#bc-print-btn" },
        { "type": "bubble",       "text": "Once products are selected, Print Barcodes becomes active — click it to preview and print your label sheet.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#bc-print-btn" }
      ]
    }

  ]
};
