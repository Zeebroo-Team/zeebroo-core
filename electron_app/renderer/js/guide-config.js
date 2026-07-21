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
      "intent_patterns": ["add new product", "new product", "create product", "add product",
                          "need to add product", "want to add product", "how to add product",
                          "help me add product", "i want to add", "how do i add product",
                          "create new product", "i need to add"],
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
      "intent_patterns": ["add new category", "new category", "create category", "add category",
                          "need to add category", "want to add category", "how to add category",
                          "help me add category", "how do i add category"],
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
      "intent_patterns": ["open pos", "go to pos", "start selling", "launch pos", "point of sale",
                          "how to open pos",
                          "i want to go to pos",
                          "how do i open pos",
                          "need to go to pos",
                          "want to sell something"],
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
      "intent_patterns": ["new sale", "create sale", "start sale", "start new sale", "make a sale",
                          "how to start a sale",
                          "i want to make a sale",
                          "how do i start a sale",
                          "need to make a sale",
                          "want to create a sale"],
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
      "intent_patterns": ["go to dashboard", "home dashboard", "show dashboard", "view dashboard", "open dashboard",
                          "how to go to dashboard",
                          "i want to view dashboard",
                          "how do i go to dashboard",
                          "need to see dashboard"],
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
      "intent_patterns": ["view analytics", "show analytics", "analytics report", "analytics overview", "revenue analytics", "open analytics",
                          "how to view analytics",
                          "i want to see analytics",
                          "how do i view analytics",
                          "need to see revenue report",
                          "want to view reports"],
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
      "intent_patterns": ["view orders", "show orders", "orders summary", "open orders", "sales and purchase orders",
                          "how to view orders",
                          "i want to see orders",
                          "how do i view orders",
                          "need to see sales orders"],
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
      "intent_patterns": ["view customers", "show customers", "customer list", "open customers", "customers",
                          "how to view customers",
                          "i want to see customers",
                          "how do i open customers",
                          "need customer list"],
      "reply": "Opening the Customers panel!",
      "steps": [
        { "type": "walk_click", "selector": "[data-tab='home']",         "wait": 400 },
        { "type": "walk_click", "selector": "#rb-home-customers",        "wait": 600 }
      ]
    },

    /* ── Suppliers ───────────────────────────────────────────────────── */
    {
      "id": "view_suppliers",
      "intent_patterns": ["view suppliers", "show suppliers", "supplier list", "open suppliers", "go to suppliers",
                          "how to view suppliers",
                          "i want to see suppliers",
                          "how do i view suppliers"],
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
      "intent_patterns": ["view expenses", "show expenses", "expense report", "open expenses", "expense view",
                          "how to view expenses",
                          "i want to see expenses",
                          "how do i view expenses",
                          "need to check expenses"],
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
      "intent_patterns": ["profit report", "view profit", "show profit", "profit summary", "profit and loss", "open profit report",
                          "how to view profit",
                          "i want to see profit",
                          "how do i view profit report",
                          "need to see profit"],
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
      "intent_patterns": ["view payroll", "payroll report", "show payroll", "open payroll", "payroll summary",
                          "how to view payroll",
                          "i want to see payroll",
                          "how do i view payroll",
                          "need to check payroll"],
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
      "intent_patterns": ["go to settings", "open settings", "settings", "show settings",
                          "how to open settings",
                          "i want to configure settings",
                          "how do i access settings",
                          "need to change settings"],
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
      "intent_patterns": ["help", "keyboard shortcuts", "show shortcuts", "open help", "shortcuts",
                          "how to get help",
                          "i need help",
                          "show me shortcuts",
                          "how do i get help",
                          "where is help"],
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
      "intent_patterns": ["today's summary", "daily summary", "today summary", "view today", "show today",
                          "how to view today's summary",
                          "i want to see today",
                          "how do i see today summary",
                          "need to see daily summary"],
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
      "intent_patterns": ["recent activity", "view activity", "transaction history", "recent transactions", "activity log",
                          "how to view activity",
                          "i want to see recent activity",
                          "how do i view transactions",
                          "need to see transaction history"],
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
      "intent_patterns": ["business flow", "view flow", "show flow", "flow overview", "flow diagram",
                          "how to view business flow",
                          "i want to see business flow",
                          "how do i go to flow"],
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
      "intent_patterns": ["new session", "start session", "open session", "create session", "new pos session",
                          "how to start session",
                          "i want to start new session",
                          "how do i create session",
                          "need a new session"],
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
      "intent_patterns": ["close session", "end session", "finish session", "close pos session",
                          "how to close session",
                          "i want to end session",
                          "how do i end session",
                          "need to close session"],
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
      "intent_patterns": ["checkout", "process payment", "pay now", "complete sale", "process sale",
                          "how to checkout",
                          "i want to checkout",
                          "how do i checkout",
                          "need to process payment",
                          "want to process sale",
                          "i need to checkout",
                          "how to process payment"],
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
      "intent_patterns": ["return", "refund", "process return", "make refund", "pos refund", "pos return",
                          "how to process return",
                          "i want to refund",
                          "how do i refund",
                          "need to process refund",
                          "want to make a return",
                          "how to make refund"],
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
      "intent_patterns": ["clear cart", "empty cart", "remove all items", "reset cart", "clear all",
                          "how to clear the cart",
                          "i want to clear cart",
                          "how do i clear cart",
                          "need to empty cart"],
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
      "intent_patterns": ["search products", "find product", "search pos", "look up product", "pos search",
                          "how to search products",
                          "i want to search",
                          "how do i search",
                          "need to find a product",
                          "want to look up product"],
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
      "intent_patterns": ["scan barcode", "barcode scanner", "scan item", "barcode scan", "use barcode",
                          "how to scan barcode",
                          "i want to scan",
                          "how do i scan barcode",
                          "need to scan an item"],
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
      "intent_patterns": ["quick add product", "add item pos", "pos add product", "add product pos", "add item to cart",
                          "how to add item to pos",
                          "i want to add item",
                          "need to add item to cart"],
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
      "intent_patterns": ["assign customer", "customer to sale", "add customer to cart", "pos customer", "select customer pos",
                          "how to add customer to sale",
                          "i want to assign customer",
                          "how do i assign customer",
                          "need to add customer"],
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
      "intent_patterns": ["pos accounts", "wallet accounts", "account balance", "customer accounts",
                          "how to view accounts",
                          "i want to check account balance",
                          "how do i view customer accounts"],
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
      "intent_patterns": ["pos settings", "configure pos", "open pos settings", "pos configuration", "set up pos",
                          "how to configure pos",
                          "i want to configure pos",
                          "how do i open pos settings",
                          "need to change pos settings"],
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
      "intent_patterns": ["park sale", "hold sale", "pause sale", "hold cart", "put on hold",
                          "how to hold a sale",
                          "i want to park sale",
                          "how do i park sale",
                          "need to hold cart",
                          "want to pause sale"],
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
      "intent_patterns": ["recall sale", "unhold sale", "retrieve held sale", "recall cart", "recall parked",
                          "how to recall sale",
                          "i want to recall",
                          "how do i recall sale",
                          "need to retrieve held sale"],
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
      "intent_patterns": ["switch to services", "services mode", "service tab", "view services", "sell service",
                          "how to switch to services",
                          "i want to sell services",
                          "how do i sell a service",
                          "need to add service"],
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
      "intent_patterns": ["filter by category", "category filter", "filter products", "product category", "browse category",
                          "how to filter by category",
                          "i want to filter products",
                          "how do i filter",
                          "need to filter inventory"],
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
      "intent_patterns": ["view products", "inventory products", "product list", "go to products", "show products",
                          "how to view products",
                          "i want to see product list",
                          "how do i go to products",
                          "need to see products"],
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
      "intent_patterns": ["refresh inventory", "refresh products", "reload products", "reload inventory",
                          "how to refresh products",
                          "i want to reload products",
                          "how do i refresh inventory"],
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
      "intent_patterns": ["clear filters", "reset filters", "remove filters", "clear product filters",
                          "how to clear filters",
                          "i want to reset filters",
                          "how do i remove filters",
                          "need to clear product filters"],
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
      "intent_patterns": ["inventory categories", "view categories", "manage categories", "product categories", "go to categories",
                          "how to add category",
                          "i want to manage categories",
                          "how do i view categories",
                          "need to go to categories"],
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
      "intent_patterns": ["units of measure", "view units", "manage units", "product units", "go to units",
                          "how to add unit",
                          "i want to add unit of measure",
                          "how do i manage units",
                          "need to add unit"],
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
      "intent_patterns": ["stock audit", "inventory audit", "count stock", "audit inventory", "stock count",
                          "how to do stock audit",
                          "i want to count stock",
                          "how do i audit stock",
                          "need to do stock count",
                          "want to do inventory count"],
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
      "intent_patterns": ["view brands", "manage brands", "product brands", "go to brands", "inventory brands",
                          "how to add brand",
                          "i want to add brand",
                          "how do i add a brand",
                          "need to create brand",
                          "want to manage brands"],
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
      "intent_patterns": ["view discounts", "manage discounts", "product discounts", "go to discounts", "inventory discounts",
                          "how to add discount",
                          "i want to add discount",
                          "how do i create discount",
                          "need to create discount",
                          "want to add a promotion"],
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
      "intent_patterns": ["purchase orders", "view purchase orders", "create purchase order", "new purchase order", "po list", "go to purchase orders",
                          "how to create purchase order",
                          "i want to make a purchase order",
                          "how do i create a po",
                          "need to order stock",
                          "want to create po"],
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
      "intent_patterns": ["goods receive", "goods receipt", "receive stock", "grn", "receive goods", "stock receipt",
                          "how to receive goods",
                          "i want to record delivery",
                          "how do i do grn",
                          "need to receive stock",
                          "want to log delivery"],
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
      "intent_patterns": ["cheques", "view cheques", "manage cheques", "cheque tracker", "go to cheques",
                          "how to view cheques",
                          "i want to manage cheques",
                          "how do i view cheques",
                          "need to check cheques"],
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
      "intent_patterns": ["add supplier", "new supplier", "create supplier", "add new supplier",
                          "how to add supplier",
                          "i need to add a supplier",
                          "how do i add supplier",
                          "want to create supplier",
                          "need to add new supplier"],
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
      "intent_patterns": ["inventory suppliers", "manage suppliers", "supplier list inventory",
                          "how to view suppliers inventory",
                          "i want to manage suppliers",
                          "how do i go to suppliers",
                          "need supplier list"],
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
      "intent_patterns": ["barcode sheets", "print barcodes", "barcode labels", "print labels", "generate barcodes",
                          "how to print barcodes",
                          "i want to print labels",
                          "how do i print barcode labels",
                          "need to generate barcodes",
                          "want to print product labels"],
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
    },

    /* ═══════════════════════════════════════════════════════════════════
       FINANCE RIBBON — Bills & Loans Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Create Bill ─────────────────────────────────────────────────── */
    {
      "id": "fin_create_bill",
      "intent_patterns": ["create bill", "new bill", "add bill", "create a bill",
                          "need to create bill", "want to create bill", "how to create bill",
                          "how do i create bill", "i need to add a bill", "add new bill"],
      "reply": "Let me walk you through creating a new bill!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-create-bill",         "wait": 600 },
        { "type": "wait_visible", "selector": "#bill-modal" },
        { "type": "walk_to",      "selector": "#bill-modal" },
        { "type": "bubble",       "text": "This is the Create Bill form. Fill in the bill name, category, amount, due date, and supplier, then click Save to record it.", "wait": 5000 }
      ]
    },

    /* ── View Bills ──────────────────────────────────────────────────── */
    {
      "id": "fin_view_bills",
      "intent_patterns": ["view bills", "show bills", "bills list", "open bills",
                          "manage bills", "go to bills", "finance bills",
                          "how to view bills", "i want to see bills", "need to see bills"],
      "reply": "Opening the Bills view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-bills-list",          "wait": 600 },
        { "type": "wait_visible", "selector": "#finance-list-view" },
        { "type": "walk_to",      "selector": "#btn-finance-create-bill" },
        { "type": "highlight",    "selector": "#btn-finance-create-bill" },
        { "type": "bubble",       "text": "This is the Bills view — all your recurring and one-off bills are listed here. Search by name or click Create Bill to add a new one.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#btn-finance-create-bill" }
      ]
    },

    /* ── Loans ───────────────────────────────────────────────────────── */
    {
      "id": "fin_loans",
      "intent_patterns": ["view loans", "show loans", "loans list", "open loans",
                          "manage loans", "go to loans", "add loan", "new loan",
                          "how to view loans", "i want to see loans", "need to manage loans",
                          "how to add loan", "want to add loan"],
      "reply": "Opening the Loans view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-loans",               "wait": 600 },
        { "type": "wait_visible", "selector": "#loans-list-view" },
        { "type": "walk_to",      "selector": "#btn-loan-create" },
        { "type": "highlight",    "selector": "#btn-loan-create" },
        { "type": "bubble",       "text": "The Loans view tracks all your loan facilities — principal, interest, and repayment schedule. The stats bar shows total active facilities and monthly outflow. Click Add Loan to record a new one.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-loan-create" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       FINANCE RIBBON — Assets & Liabilities Group
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Rentals ─────────────────────────────────────────────────────── */
    {
      "id": "fin_rentals",
      "intent_patterns": ["view rentals", "show rentals", "rentals list", "open rentals",
                          "manage rentals", "go to rentals", "add rental", "new rental",
                          "how to view rentals", "i want to see rentals", "need to manage rentals",
                          "how to add rental", "want to add rental"],
      "reply": "Opening the Rentals view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rentals",             "wait": 600 },
        { "type": "wait_visible", "selector": "#rentals-list-view" },
        { "type": "walk_to",      "selector": "#btn-rental-create" },
        { "type": "highlight",    "selector": "#btn-rental-create" },
        { "type": "bubble",       "text": "Rentals tracks your property and equipment rental commitments — due dates, overdue payments, and monthly costs. Click Add Rental to record a new lease.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-rental-create" }
      ]
    },

    /* ── Properties ──────────────────────────────────────────────────── */
    {
      "id": "fin_properties",
      "intent_patterns": ["view properties", "show properties", "properties list", "open properties",
                          "manage properties", "go to properties", "add property", "new property",
                          "how to view properties", "i want to see properties", "need to manage properties",
                          "how to add property", "want to add property"],
      "reply": "Opening the Properties view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-properties",          "wait": 600 },
        { "type": "wait_visible", "selector": "#properties-list-view" },
        { "type": "walk_to",      "selector": "#btn-property-create" },
        { "type": "highlight",    "selector": "#btn-property-create" },
        { "type": "bubble",       "text": "Properties lists all your owned assets — buildings, vehicles, equipment — with total value and lease expiry alerts. Click Add Property to record a new asset.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-property-create" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       FINANCE SUB-NAVIGATION (inside Finance panel)
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Finance Overview ────────────────────────────────────────────── */
    {
      "id": "fin_overview",
      "intent_patterns": ["finance overview", "financial overview", "finance flow",
                          "go to finance", "open finance", "view finance",
                          "how to view finance", "i want to see finance overview"],
      "reply": "Opening the Finance Overview!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-fin='flow']", "wait": 600 },
        { "type": "wait_visible", "selector": "#finance-flow-view" },
        { "type": "walk_to",      "selector": "#finance-flow-view" },
        { "type": "bubble",       "text": "The Finance Overview shows a live flow diagram of all your financial commitments — bills, loans, rentals, and properties — in one view.", "wait": 4500 }
      ]
    },

    /* ── Modifications ───────────────────────────────────────────────── */
    {
      "id": "fin_modifications",
      "intent_patterns": ["modifications", "view modifications", "property modifications",
                          "manage modifications", "go to modifications", "add modification",
                          "how to add modification", "want to add modification", "need to track modifications"],
      "reply": "Opening the Modifications view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",                          "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-fin='modifications']",     "wait": 600 },
        { "type": "wait_visible", "selector": "#modifications-list-view" },
        { "type": "walk_to",      "selector": "#btn-modification-create" },
        { "type": "highlight",    "selector": "#btn-modification-create" },
        { "type": "bubble",       "text": "Modifications tracks renovation, repair, and upgrade costs for your properties and assets. Click Add Modification to log a new one with estimated cost and completion date.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-modification-create" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       FINANCE DETAIL PAGES — full item view walkthroughs
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Bill Detail ─────────────────────────────────────────────────── */
    {
      "id": "fin_bill_detail",
      "intent_patterns": ["view bill detail", "open bill detail", "see bill details",
                          "bill details", "bill detail page", "bill payment history",
                          "how to view a bill", "how to open a bill", "check a bill",
                          "bill info", "view bill info", "bill transactions",
                          "how to pay a bill", "make bill payment", "record bill payment"],
      "reply": "Let me show you the Bill detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",     "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-bills-list",           "wait": 700 },
        { "type": "wait_visible", "selector": "#finance-list-view" },
        { "type": "walk_to",      "selector": "#finance-cards-area" },
        { "type": "bubble",       "text": "Click on any bill card to open its full detail page. I'll open the first one for you now!", "wait": 2500 },
        { "type": "walk_click",   "selector": ".bill-card",               "wait": 700 },
        { "type": "wait_visible", "selector": "#bill-detail-view",        "timeout": 3000 },
        { "type": "walk_to",      "selector": "#bd-hero-name" },
        { "type": "highlight",    "selector": "#bd-hero-name" },
        { "type": "bubble",       "text": "The header shows the bill name, category, total amount, and current status at a glance.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#bd-hero-name" },
        { "type": "highlight",    "selector": "#bd-inv-tabs" },
        { "type": "bubble",       "text": "Use the tabs to switch between Overview (summary, linked properties) and Transactions (payment schedule and history). Click a Pay button on any row to record a payment.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#bd-inv-tabs" },
        { "type": "highlight",    "selector": "#bill-detail-back" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Bills list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#bill-detail-back" }
      ]
    },

    /* ── Loan Detail ─────────────────────────────────────────────────── */
    {
      "id": "fin_loan_detail",
      "intent_patterns": ["view loan detail", "open loan detail", "see loan details",
                          "loan details", "loan detail page", "loan repayment",
                          "how to view a loan", "how to open a loan", "check a loan",
                          "loan info", "view loan info", "loan schedule",
                          "how to pay loan", "make loan payment", "record loan payment",
                          "loan repayment schedule"],
      "reply": "Let me show you the Loan detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",     "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-loans",                "wait": 700 },
        { "type": "wait_visible", "selector": "#loans-list-view" },
        { "type": "walk_to",      "selector": "#loans-cards-area" },
        { "type": "bubble",       "text": "Click on any loan card to open its full detail page. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": ".lm-card[data-loan-id]",   "wait": 700 },
        { "type": "wait_visible", "selector": "#loan-detail-view",        "timeout": 3000 },
        { "type": "walk_to",      "selector": "#ld-hero-name" },
        { "type": "highlight",    "selector": "#ld-hero-name" },
        { "type": "bubble",       "text": "The header shows the loan name, lender, principal amount, interest rate, and repayment status.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#ld-hero-name" },
        { "type": "highlight",    "selector": "#ld-inv-tabs" },
        { "type": "bubble",       "text": "The tabs give you Overview (loan summary) and Schedule (monthly installment table with Pay buttons for each due date).", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#ld-inv-tabs" },
        { "type": "highlight",    "selector": "#loan-detail-back" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Loans list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#loan-detail-back" }
      ]
    },

    /* ── Rental Detail ───────────────────────────────────────────────── */
    {
      "id": "fin_rental_detail",
      "intent_patterns": ["view rental detail", "open rental detail", "see rental details",
                          "rental details", "rental detail page", "rental payment history",
                          "how to view a rental", "how to open a rental", "check a rental",
                          "rental info", "view rental info", "rental schedule",
                          "how to pay rental", "make rental payment", "record rental payment",
                          "lease details", "rent details"],
      "reply": "Let me show you the Rental detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",      "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rentals",               "wait": 700 },
        { "type": "wait_visible", "selector": "#rentals-list-view" },
        { "type": "walk_to",      "selector": "#rentals-cards-area" },
        { "type": "bubble",       "text": "Click on any rental card to open its full detail page. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": ".rm-card[data-rental-id]",  "wait": 700 },
        { "type": "wait_visible", "selector": "#rental-detail-view",       "timeout": 3000 },
        { "type": "walk_to",      "selector": "#rd-hero-name" },
        { "type": "highlight",    "selector": "#rd-hero-name" },
        { "type": "bubble",       "text": "The header shows the property type, landlord, monthly rent amount, and lease status.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#rd-hero-name" },
        { "type": "highlight",    "selector": "#rd-inv-tabs" },
        { "type": "bubble",       "text": "The tabs give you Overview, Payment Schedule (with Pay buttons per due date), Linked Bills, and Land Registry info — all in one place.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rd-inv-tabs" },
        { "type": "highlight",    "selector": "#rental-detail-back" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Rentals list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#rental-detail-back" }
      ]
    },

    /* ── Modification Detail ─────────────────────────────────────────── */
    {
      "id": "fin_modification_detail",
      "intent_patterns": ["view modification detail", "open modification detail", "see modification details",
                          "modification details", "modification detail page",
                          "how to view a modification", "how to open a modification",
                          "check a modification", "modification info",
                          "renovation details", "repair details", "upgrade details"],
      "reply": "Let me show you the Modification detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",                       "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-fin='modifications']",  "wait": 700 },
        { "type": "wait_visible", "selector": "#modifications-list-view" },
        { "type": "walk_to",      "selector": "#modifications-cards-area" },
        { "type": "bubble",       "text": "Click on any modification card to open its full detail page. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": ".mm-card[data-mod-id]",                      "wait": 700 },
        { "type": "wait_visible", "selector": "#modification-detail-view",                  "timeout": 3000 },
        { "type": "walk_to",      "selector": "#modification-detail-view" },
        { "type": "highlight",    "selector": "#modification-detail-view" },
        { "type": "bubble",       "text": "The Modification detail page shows the project name, linked property, cost breakdown, contractor info, completion status, and all associated documents.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#modification-detail-view" },
        { "type": "highlight",    "selector": "#modification-detail-back" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Modifications list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#modification-detail-back" }
      ]
    },

    /* ── Finance Reports (coming soon) ───────────────────────────────── */
    {
      "id": "fin_reports",
      "intent_patterns": ["finance reports", "profit analytics", "sales reports", "financial reports",
                          "how to view financial reports", "i want to see finance reports",
                          "profit and loss report", "finance analytics"],
      "reply": "Let me show you where the Finance Reports buttons are!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='finance']",    "wait": 500 },
        { "type": "walk_to",      "selector": "[data-page='finance'] .ribbon-group-label" },
        { "type": "highlight",    "selector": "[data-page='finance'] .ribbon-group:last-child" },
        { "type": "bubble",       "text": "The Profit Analytics and Sales Reports buttons are in the Reports group on the Finance ribbon. These are being prepared — they will be available in an upcoming update.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "[data-page='finance'] .ribbon-group:last-child" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HR — LIST VIEWS
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Employees List ──────────────────────────────────────────────── */
    {
      "id": "hr_employees",
      "intent_patterns": ["view employees", "show employees", "employees list", "open employees",
                          "go to employees", "manage employees", "staff list", "staff members",
                          "how to view employees", "i want to see employees", "see all employees"],
      "reply": "Opening the Employees view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-employees",          "wait": 600 },
        { "type": "wait_visible", "selector": "#employees-list-view" },
        { "type": "walk_to",      "selector": "#emp-add-btn" },
        { "type": "highlight",    "selector": "#emp-add-btn" },
        { "type": "bubble",       "text": "The Employees view lists all your staff. The stats bar shows headcount by employment type. Search by name, ID, or department, and click Add Employee to hire someone new.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#emp-add-btn" }
      ]
    },

    /* ── Add Employee ────────────────────────────────────────────────── */
    {
      "id": "hr_add_employee",
      "intent_patterns": ["add employee", "new employee", "create employee", "hire employee",
                          "add staff", "add a new employee", "how to add employee",
                          "how to hire", "want to add employee", "need to add employee",
                          "onboard employee", "add new staff"],
      "reply": "Let me show you how to add a new employee!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-employees",          "wait": 600 },
        { "type": "wait_visible", "selector": "#employees-list-view" },
        { "type": "walk_click",   "selector": "#emp-add-btn",           "wait": 600 },
        { "type": "wait_visible", "selector": "#emp-create-modal" },
        { "type": "walk_to",      "selector": "#emp-create-modal" },
        { "type": "highlight",    "selector": "#emp-create-modal" },
        { "type": "bubble",       "text": "Fill in the employee's name, ID, department, job title, employment type, and salary details. All required fields are marked. Click Save Employee when done.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#emp-create-modal" }
      ]
    },

    /* ── Departments ─────────────────────────────────────────────────── */
    {
      "id": "hr_departments",
      "intent_patterns": ["view departments", "show departments", "departments list", "open departments",
                          "go to departments", "manage departments", "add department", "new department",
                          "how to view departments", "i want to see departments", "create department"],
      "reply": "Opening the Departments view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-departments",        "wait": 600 },
        { "type": "wait_visible", "selector": "#departments-list-view" },
        { "type": "walk_to",      "selector": "#dept-add-btn" },
        { "type": "highlight",    "selector": "#dept-add-btn" },
        { "type": "bubble",       "text": "Departments organises your company structure. Each department shows how many employees belong to it. Click Add Department to create a new one.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#dept-add-btn" }
      ]
    },

    /* ── Payroll Cycles ──────────────────────────────────────────────── */
    {
      "id": "hr_payroll_cycles",
      "intent_patterns": ["view payroll", "show payroll", "payroll list", "payroll cycles",
                          "open payroll", "go to payroll", "manage payroll",
                          "how to view payroll", "i want to see payroll", "see payroll cycles",
                          "create payroll cycle", "new payroll cycle", "add payroll"],
      "reply": "Opening Payroll Cycles!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-hr-payroll",         "wait": 600 },
        { "type": "wait_visible", "selector": "#payroll-list-view" },
        { "type": "walk_to",      "selector": "#payroll-add-btn" },
        { "type": "highlight",    "selector": "#payroll-add-btn" },
        { "type": "bubble",       "text": "Payroll Cycles tracks each monthly pay run — Draft → Computed → Finalized. The stats bar shows how many cycles are in each state. Click New Cycle to start a pay run.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#payroll-add-btn" }
      ]
    },

    /* ── New Payroll Cycle ───────────────────────────────────────────── */
    {
      "id": "hr_new_payroll",
      "intent_patterns": ["new payroll cycle", "create payroll cycle", "start payroll",
                          "run payroll", "process payroll", "how to create payroll",
                          "how to run payroll", "want to run payroll", "need to run payroll",
                          "add payroll cycle", "generate payroll"],
      "reply": "Let me show you how to create a new payroll cycle!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-hr-payroll",         "wait": 600 },
        { "type": "wait_visible", "selector": "#payroll-list-view" },
        { "type": "walk_click",   "selector": "#payroll-add-btn",       "wait": 600 },
        { "type": "wait_visible", "selector": "#payroll-create-modal" },
        { "type": "walk_to",      "selector": "#payroll-create-modal" },
        { "type": "highlight",    "selector": "#payroll-create-modal" },
        { "type": "bubble",       "text": "Select the pay period month and year, choose a rule set, then click Create Cycle. The system will add all active employees and calculate their salaries automatically.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#payroll-create-modal" }
      ]
    },

    /* ── Rule Sets ───────────────────────────────────────────────────── */
    {
      "id": "hr_rule_sets",
      "intent_patterns": ["view rule sets", "show rule sets", "rule sets list", "open rule sets",
                          "go to rule sets", "manage rule sets", "add rule set", "new rule set",
                          "how to view rule sets", "salary rules", "payroll rules",
                          "compensation rules", "hr rules"],
      "reply": "Opening Rule Sets!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",                      "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-hr='rule-sets']", "wait": 600 },
        { "type": "wait_visible", "selector": "#rule-sets-list-view" },
        { "type": "walk_to",      "selector": "#rs-add-btn" },
        { "type": "highlight",    "selector": "#rs-template-btn" },
        { "type": "bubble",       "text": "Rule Sets define how salaries are calculated — base pay, allowances, deductions, and overtime. Click Install Template to load a ready-made set, or New Rule Set to build your own.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rs-template-btn" },
        { "type": "highlight",    "selector": "#rs-add-btn" }
      ]
    },

    /* ── Allowance Types ─────────────────────────────────────────────── */
    {
      "id": "hr_allowance_types",
      "intent_patterns": ["view allowance types", "show allowances", "allowance types",
                          "open allowances", "manage allowances", "add allowance type",
                          "new allowance type", "how to view allowances",
                          "transport allowance", "meal allowance", "hr allowances"],
      "reply": "Opening Allowance Types!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",                             "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-hr='allowance-types']",  "wait": 600 },
        { "type": "wait_visible", "selector": "#allowance-types-view" },
        { "type": "walk_to",      "selector": "#at-add-btn" },
        { "type": "highlight",    "selector": "#at-add-btn" },
        { "type": "bubble",       "text": "Allowance Types defines the categories of extra pay your employees can receive — transport, meals, housing, and so on. These types are used when building Rule Sets. Click Add Allowance Type to create a new one.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#at-add-btn" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       HR — DETAIL PAGES
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Employee Detail ─────────────────────────────────────────────── */
    {
      "id": "hr_employee_detail",
      "intent_patterns": ["view employee detail", "open employee detail", "see employee details",
                          "employee detail page", "employee profile", "employee info",
                          "how to view an employee", "how to open an employee",
                          "check employee details", "employee overview"],
      "reply": "Let me show you the Employee detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",          "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-employees",             "wait": 600 },
        { "type": "wait_visible", "selector": "#employees-list-view" },
        { "type": "walk_to",      "selector": "#emp-cards-area" },
        { "type": "bubble",       "text": "Click on any employee card to open their full profile. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": "#emp-cards-area .lm-card",  "wait": 700 },
        { "type": "wait_visible", "selector": "#employee-detail-view",     "timeout": 3000 },
        { "type": "walk_to",      "selector": "#emp-detail-name" },
        { "type": "highlight",    "selector": "#emp-detail-name" },
        { "type": "bubble",       "text": "The header shows the employee's name, ID, joining date, employment type, department, and job title at a glance.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#emp-detail-name" },
        { "type": "highlight",    "selector": "#employee-detail-view .inv-tabs" },
        { "type": "bubble",       "text": "The Overview tab shows salary breakdown, allowances, and deductions. The Personal tab holds contact info, emergency contacts, and documents.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#employee-detail-view .inv-tabs" },
        { "type": "highlight",    "selector": "#emp-back-btn" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Employees list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#emp-back-btn" }
      ]
    },

    /* ── Payroll Cycle Detail ────────────────────────────────────────── */
    {
      "id": "hr_payroll_detail",
      "intent_patterns": ["view payroll detail", "open payroll detail", "payroll cycle detail",
                          "payroll details", "see payroll cycle", "payroll cycle info",
                          "how to view a payroll cycle", "salary sheet", "payroll sheet",
                          "how to finalize payroll", "compute payroll", "payroll employees"],
      "reply": "Let me show you the Payroll Cycle detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",              "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-hr-payroll",               "wait": 600 },
        { "type": "wait_visible", "selector": "#payroll-list-view" },
        { "type": "walk_to",      "selector": "#payroll-cycles-area" },
        { "type": "bubble",       "text": "Click on any payroll cycle card to open its detail page. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": "#payroll-cycles-area .lm-card", "wait": 700 },
        { "type": "wait_visible", "selector": "#payroll-cycle-detail-view",    "timeout": 3000 },
        { "type": "walk_to",      "selector": "#pd-cycle-name" },
        { "type": "highlight",    "selector": "#pd-cycle-name" },
        { "type": "bubble",       "text": "The header shows the cycle name, pay period, status, and total net pay across all employees.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#pd-cycle-name" },
        { "type": "highlight",    "selector": "#pd-inv-tabs" },
        { "type": "bubble",       "text": "Use the tabs: Overview (summary and status actions), Employees (individual salaries, add/remove staff), and Salary Sheet (printable breakdown of every line item).", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#pd-inv-tabs" },
        { "type": "highlight",    "selector": "#payroll-back-btn" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Payroll Cycles list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#payroll-back-btn" }
      ]
    },

    /* ── Rule Set Detail ─────────────────────────────────────────────── */
    {
      "id": "hr_rule_set_detail",
      "intent_patterns": ["view rule set detail", "open rule set detail", "rule set details",
                          "rule set info", "see a rule set", "how to view a rule set",
                          "salary rule detail", "payroll rule detail", "compensation rule detail"],
      "reply": "Let me show you the Rule Set detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='hr']",                      "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-hr='rule-sets']", "wait": 600 },
        { "type": "wait_visible", "selector": "#rule-sets-list-view" },
        { "type": "walk_to",      "selector": "#rs-cards-area" },
        { "type": "bubble",       "text": "Click on any rule set card to open its full configuration. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": "#rs-cards-area .lm-card",              "wait": 700 },
        { "type": "wait_visible", "selector": "#rule-set-detail-view",                "timeout": 3000 },
        { "type": "walk_to",      "selector": "#rs-hero-name" },
        { "type": "highlight",    "selector": "#rs-hero-name" },
        { "type": "bubble",       "text": "The header shows the rule set name, currency, effective date, and how many rules it contains.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#rs-hero-name" },
        { "type": "highlight",    "selector": "#rs-inv-tabs" },
        { "type": "bubble",       "text": "The Overview tab shows a summary. The Rules tab lists every individual calculation rule — base salary, allowances, deductions, overtime — and lets you add, edit, or remove them.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rs-inv-tabs" },
        { "type": "highlight",    "selector": "#rs-back-btn" },
        { "type": "bubble",       "text": "Click the back arrow to return to the Rule Sets list.", "wait": 3000 },
        { "type": "unhighlight",  "selector": "#rs-back-btn" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       RESTAURANT — LIST / OPERATIONAL VIEWS
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Orders ──────────────────────────────────────────────────────── */
    {
      "id": "rst_orders",
      "intent_patterns": ["view restaurant orders", "show orders", "restaurant orders",
                          "orders list", "go to orders", "open orders", "manage orders",
                          "how to view orders", "i want to see orders", "see all orders",
                          "pending orders", "preparing orders", "order status"],
      "reply": "Opening Restaurant Orders!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-orders",           "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-orders-view" },
        { "type": "walk_to",      "selector": "#rst-status-tabs" },
        { "type": "highlight",    "selector": "#rst-status-tabs" },
        { "type": "bubble",       "text": "The Orders view shows all your restaurant orders. Use the status tabs to filter by Pending, Preparing, Ready, Served, Paid, or Cancelled. Click any order card to see its details.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rst-status-tabs" },
        { "type": "highlight",    "selector": "#rst-new-order-btn" },
        { "type": "bubble",       "text": "Click New Order to open the order creation form and start taking a table or takeaway order.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#rst-new-order-btn" }
      ]
    },

    /* ── New Order ───────────────────────────────────────────────────── */
    {
      "id": "rst_new_order",
      "intent_patterns": ["new restaurant order", "create order", "take order", "add order",
                          "start order", "place order", "new order", "create new order",
                          "how to create order", "how to take order", "want to take order",
                          "need to take order", "open new order"],
      "reply": "Let me show you how to create a new order!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-new-order",        "wait": 700 },
        { "type": "wait_visible", "selector": "#rst-order-modal" },
        { "type": "walk_to",      "selector": "#rst-order-modal" },
        { "type": "highlight",    "selector": "#rst-order-modal" },
        { "type": "bubble",       "text": "The new order form lets you select a table or takeaway, search and add menu items, set quantities, and add notes. Click Place Order when you're ready to send it to the kitchen.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rst-order-modal" }
      ]
    },

    /* ── Order Detail ────────────────────────────────────────────────── */
    {
      "id": "rst_order_detail",
      "intent_patterns": ["view order detail", "open order detail", "see order details",
                          "order details", "order detail page", "check order",
                          "how to view an order", "how to open an order",
                          "order info", "update order status", "mark order ready",
                          "mark order served", "pay order", "close order"],
      "reply": "Let me show you the Order detail view!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-orders",           "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-orders-view" },
        { "type": "walk_to",      "selector": "#rst-orders-area" },
        { "type": "bubble",       "text": "Click on any order card to open its full detail. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": "#rst-orders-area .rst-order-card", "wait": 700 },
        { "type": "wait_visible", "selector": "#rst-od-modal",            "timeout": 3000 },
        { "type": "walk_to",      "selector": "#rst-od-modal" },
        { "type": "highlight",    "selector": "#rst-od-modal" },
        { "type": "bubble",       "text": "The Order detail shows all items ordered, quantities, notes, and totals. Use the status buttons to move the order through Pending → Preparing → Ready → Served, then process payment.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rst-od-modal" }
      ]
    },

    /* ── Tables / Floor Plan ─────────────────────────────────────────── */
    {
      "id": "rst_tables",
      "intent_patterns": ["view tables", "floor plan", "restaurant tables", "table layout",
                          "go to tables", "manage tables", "add table", "table map",
                          "how to view tables", "how to add table", "want to add table",
                          "table availability", "which tables are free", "open tables"],
      "reply": "Opening the Restaurant Floor Plan!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-tables",           "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-tables-view" },
        { "type": "walk_to",      "selector": "#rt-floor-wrap" },
        { "type": "highlight",    "selector": "#rt-floor-wrap" },
        { "type": "bubble",       "text": "The Floor Plan shows all your tables colour-coded: green = available, red = occupied, amber = reserved, grey = inactive. Click any table to open an order for it.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rt-floor-wrap" },
        { "type": "highlight",    "selector": "#rt-edit-btn" },
        { "type": "bubble",       "text": "Click Edit Layout to drag and rearrange tables, then Add Table to create new ones. Save Layout when you're done.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#rt-edit-btn" }
      ]
    },

    /* ── Reservations ────────────────────────────────────────────────── */
    {
      "id": "rst_reservations",
      "intent_patterns": ["view reservations", "restaurant reservations", "reservations list",
                          "open reservations", "manage reservations", "add reservation",
                          "new reservation", "book table", "table booking",
                          "how to add reservation", "how to book a table",
                          "want to add reservation", "need to book"],
      "reply": "Opening Reservations!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-reservations",     "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-reservations-view" },
        { "type": "walk_to",      "selector": "#rst-add-reservation-btn" },
        { "type": "highlight",    "selector": "#rst-add-reservation-btn" },
        { "type": "bubble",       "text": "Reservations tracks upcoming table bookings by guest name, party size, date, and time. Click Add Reservation to record a new booking.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#rst-add-reservation-btn" }
      ]
    },

    /* ── Menu Items ──────────────────────────────────────────────────── */
    {
      "id": "rst_menu_items",
      "intent_patterns": ["view menu items", "menu items", "restaurant menu", "view menu",
                          "go to menu", "manage menu", "menu list", "show menu items",
                          "how to view menu", "i want to see menu", "see all menu items"],
      "reply": "Opening Menu Items!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-menu-items",       "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-menu-items-view" },
        { "type": "walk_to",      "selector": "#rst-mi-add-btn" },
        { "type": "highlight",    "selector": "#rst-mi-add-btn" },
        { "type": "bubble",       "text": "Menu Items shows your full menu with images, prices, and categories. Filter by category using the dropdown, or search by name. Click Add Menu Item to create a new dish.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rst-mi-add-btn" }
      ]
    },

    /* ── Add Menu Item ───────────────────────────────────────────────── */
    {
      "id": "rst_add_menu_item",
      "intent_patterns": ["add menu item", "new menu item", "create menu item", "add dish",
                          "new dish", "add food item", "how to add menu item",
                          "want to add menu item", "need to add dish", "create dish"],
      "reply": "Let me show you how to add a new menu item!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-menu-items",       "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-menu-items-view" },
        { "type": "walk_click",   "selector": "#rst-mi-add-btn",          "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-mi-modal" },
        { "type": "walk_to",      "selector": "#rst-mi-modal" },
        { "type": "highlight",    "selector": "#rst-mi-modal" },
        { "type": "bubble",       "text": "Enter the dish name, category, price, description, and upload an image. You can also link ingredients for stock deduction when ordered. Click Save when done.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rst-mi-modal" }
      ]
    },

    /* ── Menu Categories ─────────────────────────────────────────────── */
    {
      "id": "rst_menu_categories",
      "intent_patterns": ["view menu categories", "menu categories", "restaurant categories",
                          "go to categories", "manage menu categories", "add menu category",
                          "new category", "create category", "how to add category",
                          "menu category list", "food categories"],
      "reply": "Opening Menu Categories!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",            "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-rst='menu-categories']", "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-menu-cats-view" },
        { "type": "walk_to",      "selector": "#rst-mc-add-btn" },
        { "type": "highlight",    "selector": "#rst-mc-add-btn" },
        { "type": "bubble",       "text": "Menu Categories groups your dishes — Starters, Main Course, Desserts, Beverages, etc. Click Add Category to create a new group. Categories appear as filters in the POS and menu item form.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rst-mc-add-btn" }
      ]
    },

    /* ── Ingredients / Stock ─────────────────────────────────────────── */
    {
      "id": "rst_ingredients",
      "intent_patterns": ["view ingredients", "restaurant ingredients", "ingredient stock",
                          "stock in", "restaurant stock", "go to ingredients",
                          "manage ingredients", "add ingredient", "stock management",
                          "how to view ingredients", "ingredient list", "kitchen stock"],
      "reply": "Opening Ingredients!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-ingredients",      "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-ingredients-view" },
        { "type": "walk_to",      "selector": "#rst-ingr-add-btn" },
        { "type": "highlight",    "selector": "#rst-ingr-add-btn" },
        { "type": "bubble",       "text": "Ingredients tracks your kitchen stock — current quantity, unit, low-stock threshold, and cost per unit. Items turn red when stock falls below the threshold. Click Add Ingredient to add a new one.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rst-ingr-add-btn" }
      ]
    },

    /* ── Purchase Orders (Restaurant) ────────────────────────────────── */
    {
      "id": "rst_purchase_orders",
      "intent_patterns": ["restaurant purchase orders", "rst purchase orders",
                          "kitchen purchase orders", "ingredient purchase orders",
                          "go to purchase orders", "manage purchase orders",
                          "add purchase order", "new purchase order",
                          "how to create purchase order", "order ingredients"],
      "reply": "Opening Restaurant Purchase Orders!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",  "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-rst-purchase-orders",  "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-po-view" },
        { "type": "walk_to",      "selector": "#rst-po-new-btn" },
        { "type": "highlight",    "selector": "#rst-po-new-btn" },
        { "type": "bubble",       "text": "Restaurant Purchase Orders lets you order ingredients from suppliers. Each order tracks quantities, costs, and delivery status. Click New Purchase Order to raise a new one.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#rst-po-new-btn" }
      ]
    },

    /* ── Kitchen Display ─────────────────────────────────────────────── */
    {
      "id": "rst_kitchen",
      "intent_patterns": ["kitchen display", "kitchen screen", "kds", "kitchen display system",
                          "go to kitchen", "open kitchen", "kitchen orders",
                          "kitchen view", "how to use kitchen display",
                          "kitchen ticket", "kitchen board"],
      "reply": "Let me show you the Kitchen Display!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='restaurant']",             "wait": 500 },
        { "type": "walk_click",   "selector": ".fin-subnav-btn[data-rst='kitchen']", "wait": 600 },
        { "type": "wait_visible", "selector": "#rst-kitchen-view" },
        { "type": "walk_to",      "selector": "#kds-grid" },
        { "type": "highlight",    "selector": "#kds-grid" },
        { "type": "bubble",       "text": "The Kitchen Display shows live order tickets for your kitchen staff. Each ticket shows the table, items, and how long the order has been waiting. Filter by Pending, Preparing, or Ready status.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#kds-grid" },
        { "type": "highlight",    "selector": "#rb-rst-kitchen" },
        { "type": "bubble",       "text": "You can also open the Kitchen Display as a full-screen window on a separate monitor by clicking the Kitchen Display ribbon button.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#rb-rst-kitchen" }
      ]
    },

    /* ── Restaurant POS ──────────────────────────────────────────────── */
    {
      "id": "rst_pos",
      "intent_patterns": ["restaurant pos", "rst pos", "open restaurant pos",
                          "restaurant point of sale", "table pos", "takeaway pos",
                          "go to restaurant pos", "how to use restaurant pos",
                          "restaurant cashier", "serve tables"],
      "reply": "Opening the Restaurant POS!",
      "steps": [
        { "type": "walk_click",   "selector": "#rb-rst-pos",              "wait": 700 },
        { "type": "wait_visible", "selector": "#panel-rst-pos" },
        { "type": "walk_to",      "selector": "#rst-pos-view" },
        { "type": "highlight",    "selector": "#rst-pos-view" },
        { "type": "bubble",       "text": "The Restaurant POS lets you take table orders and takeaway orders side by side. Each tab represents a table or takeaway slot — add items, split bills, and process payment all from here.", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#rst-pos-view" }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       SERVICES — RIBBON & VIEWS
       ═══════════════════════════════════════════════════════════════════ */

    /* ── Service Requests ────────────────────────────────────────────── */
    {
      "id": "svc_requests",
      "intent_patterns": ["service requests", "view requests", "show requests",
                          "open service requests", "all requests", "manage requests",
                          "how to view service requests", "i want to see requests",
                          "service request list", "client requests"],
      "reply": "Opening Service Requests!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-requests",        "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-requests-view" },
        { "type": "walk_to",      "selector": "#svc-chip-bar" },
        { "type": "highlight",    "selector": "#svc-chip-bar" },
        { "type": "bubble",       "text": "Service Requests tracks every job booked for a customer. Filter by status — Pending, In Progress, Completed, or Cancelled — using these chips.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#svc-chip-bar" },
        { "type": "highlight",    "selector": "#svc-req-search" },
        { "type": "bubble",       "text": "Search by request number, title, or customer name to find a specific job quickly.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#svc-req-search" }
      ]
    },

    /* ── Pending Requests ────────────────────────────────────────────── */
    {
      "id": "svc_pending_requests",
      "intent_patterns": ["pending requests", "pending service requests", "view pending",
                          "show pending requests", "open pending", "requests pending",
                          "how to view pending requests", "what jobs are pending",
                          "jobs waiting", "unstarted requests"],
      "reply": "Opening Pending Service Requests!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-req-pending",     "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-requests-view" },
        { "type": "walk_to",      "selector": "#svc-req-tbody" },
        { "type": "highlight",    "selector": "#svc-req-tbody" },
        { "type": "bubble",       "text": "These are all the jobs that haven't started yet. Click Start on a row to move it to In Progress, or Cancel to remove it from the queue.", "wait": 4500 },
        { "type": "unhighlight",  "selector": "#svc-req-tbody" }
      ]
    },

    /* ── Service Catalog ─────────────────────────────────────────────── */
    {
      "id": "svc_catalog",
      "intent_patterns": ["service catalog", "view catalog", "services list", "view services",
                          "open catalog", "show services", "manage services",
                          "how to view services", "i want to see service catalog",
                          "all services", "service list"],
      "reply": "Opening the Service Catalog!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-catalog",         "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-catalog-view" },
        { "type": "walk_to",      "selector": "#btn-new-service" },
        { "type": "highlight",    "selector": "#btn-new-service" },
        { "type": "bubble",       "text": "The Service Catalog lists every service you offer — name, price, duration, categories, and active status. Click any row to see its full detail, or New Service to add one.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-new-service" }
      ]
    },

    /* ── New Service ─────────────────────────────────────────────────── */
    {
      "id": "svc_new_service",
      "intent_patterns": ["new service", "add service", "create service", "add new service",
                          "create new service", "how to add service", "want to add service",
                          "need to add service", "how to create a service"],
      "reply": "Let me show you how to add a new service!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-new-item",        "wait": 700 },
        { "type": "wait_visible", "selector": "#svc-new-modal" },
        { "type": "walk_to",      "selector": "#svc-new-modal" },
        { "type": "highlight",    "selector": "#svc-new-modal" },
        { "type": "bubble",       "text": "Enter the service name, price, duration, description, and assign it to categories. You can also link products (consumables used during the job) and employees who perform it. Click Save when done.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#svc-new-modal" }
      ]
    },

    /* ── Service Item Detail ─────────────────────────────────────────── */
    {
      "id": "svc_item_detail",
      "intent_patterns": ["service detail", "view service detail", "open service detail",
                          "service info", "service overview", "see service details",
                          "how to view a service", "service employees", "service products",
                          "edit service", "service configuration"],
      "reply": "Let me show you the Service detail page!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-catalog",         "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-catalog-view" },
        { "type": "walk_to",      "selector": "#svc-itm-tbody" },
        { "type": "bubble",       "text": "Click on any service row to open its full detail. I'll open the first one for you!", "wait": 2500 },
        { "type": "walk_click",   "selector": "#svc-itm-tbody .svc-itm-row", "wait": 700 },
        { "type": "wait_visible", "selector": "#svc-item-detail",        "timeout": 3000 },
        { "type": "walk_to",      "selector": "#svc-detail-name" },
        { "type": "highlight",    "selector": "#svc-detail-name" },
        { "type": "bubble",       "text": "The header shows the service name, price, duration, and active status at a glance.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#svc-detail-name" },
        { "type": "highlight",    "selector": "#svc-item-detail .svc-detail-tab" },
        { "type": "bubble",       "text": "Use the tabs to switch between Overview (description, price, categories), Employees (who can perform this service), and Products (consumables used per job).", "wait": 5500 },
        { "type": "unhighlight",  "selector": "#svc-item-detail .svc-detail-tab" },
        { "type": "highlight",    "selector": "#svc-detail-edit-btn" },
        { "type": "bubble",       "text": "Click Edit to update the service details, or use the back arrow to return to the catalog.", "wait": 3500 },
        { "type": "unhighlight",  "selector": "#svc-detail-edit-btn" }
      ]
    },

    /* ── Service Categories ──────────────────────────────────────────── */
    {
      "id": "svc_categories",
      "intent_patterns": ["service categories", "view service categories", "show categories",
                          "open service categories", "manage categories", "service tags",
                          "how to view service categories", "i want to see categories"],
      "reply": "Opening Service Categories!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-categories",      "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-categories-view" },
        { "type": "walk_to",      "selector": "#btn-new-category" },
        { "type": "highlight",    "selector": "#btn-new-category" },
        { "type": "bubble",       "text": "Service Categories groups your services — Hair Care, Massage, Cleaning, etc. Categories appear as filters on the catalog and in the POS services mode. Click New Category to add one.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#btn-new-category" }
      ]
    },

    /* ── Add Category ────────────────────────────────────────────────── */
    {
      "id": "svc_add_category",
      "intent_patterns": ["add service category", "new service category", "create service category",
                          "add category", "new category", "create category",
                          "how to add service category", "want to add category"],
      "reply": "Let me show you how to add a new service category!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-categories",      "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-categories-view" },
        { "type": "walk_click",   "selector": "#btn-new-category",       "wait": 600 },
        { "type": "wait_visible", "selector": "#svc-new-cat-modal" },
        { "type": "walk_to",      "selector": "#svc-new-cat-modal" },
        { "type": "highlight",    "selector": "#svc-new-cat-modal" },
        { "type": "bubble",       "text": "Enter the category name and an optional description, then click Save. The new category will be available when adding services and for filtering the catalog.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#svc-new-cat-modal" }
      ]
    },

    /* ── Refresh ─────────────────────────────────────────────────────── */
    {
      "id": "svc_refresh",
      "intent_patterns": ["refresh services", "reload services", "refresh service requests",
                          "update services", "sync services", "refresh catalog"],
      "reply": "Refreshing Services data!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='services']",   "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-svc-refresh",         "wait": 400 },
        { "type": "bubble",       "text": "All services and requests have been refreshed with the latest data from the server.", "wait": 3000 }
      ]
    },

    /* ═══════════════════════════════════════════════════════════════════
       MAIL
    ═══════════════════════════════════════════════════════════════════ */

    /* ── Inbox ────────────────────────────────────────────────────────── */
    {
      "id": "mail_inbox",
      "intent_patterns": ["open inbox", "view inbox", "check inbox", "mail inbox", "show inbox",
                          "check emails", "view emails", "see emails", "read emails",
                          "how to view mail", "open mail inbox"],
      "reply": "Opening your Mail inbox!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-inbox",       "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-inbox-view" },
        { "type": "walk_to",      "selector": "#mail-inbox-view" },
        { "type": "highlight",    "selector": "#mail-inbox-view" },
        { "type": "bubble",       "text": "This is your Mail inbox. You can see all incoming emails here — unread ones are shown in bold with a blue envelope icon. Use the search bar to filter, or click All/Unread/Read to narrow down messages. Click any row to open the full message.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#mail-inbox-view" }
      ]
    },

    /* ── Compose ─────────────────────────────────────────────────────── */
    {
      "id": "mail_compose",
      "intent_patterns": ["compose email", "write email", "send email", "new email", "compose mail",
                          "send mail", "write mail", "how to send email", "how to compose mail",
                          "want to send email", "want to compose"],
      "reply": "Let me open the compose window for you!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-compose",     "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-compose-view" },
        { "type": "walk_to",      "selector": "#mail-compose-view" },
        { "type": "highlight",    "selector": "#mail-compose-view" },
        { "type": "bubble",       "text": "Compose a new email here. Fill in the recipient address, subject, and your message. You can also pick a saved template to pre-fill the content. Click Send Now to send immediately, or Schedule to set a future send time.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#mail-compose-view" },
        { "type": "walk_to",      "selector": "#mail-compose-to" },
        { "type": "highlight",    "selector": "#mail-compose-to" },
        { "type": "bubble",       "text": "Start by entering the recipient's email address here.", "wait": 4000 },
        { "type": "unhighlight",  "selector": "#mail-compose-to" }
      ]
    },

    /* ── Sent ────────────────────────────────────────────────────────── */
    {
      "id": "mail_sent",
      "intent_patterns": ["view sent mail", "sent emails", "sent messages", "see sent",
                          "show sent mail", "outbox", "sent box", "how to view sent emails"],
      "reply": "Opening your Sent mail!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-sent",        "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-sent-view" },
        { "type": "walk_to",      "selector": "#mail-sent-view" },
        { "type": "highlight",    "selector": "#mail-sent-view" },
        { "type": "bubble",       "text": "The Sent view shows all emails you have sent from this business mailbox. Use the search to find a specific sent message. Click the eye icon or any row to view the full email content.", "wait": 5000 },
        { "type": "unhighlight",  "selector": "#mail-sent-view" }
      ]
    },

    /* ── Templates ───────────────────────────────────────────────────── */
    {
      "id": "mail_templates",
      "intent_patterns": ["mail templates", "email templates", "view templates", "manage templates",
                          "create template", "new template", "add template", "email template",
                          "how to manage mail templates", "how to create email template"],
      "reply": "Opening Mail Templates!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",        "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-templates",       "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-templates-view" },
        { "type": "walk_to",      "selector": "#btn-new-mail-template" },
        { "type": "highlight",    "selector": "#btn-new-mail-template" },
        { "type": "bubble",       "text": "Mail Templates save your frequently used emails — Welcome emails, Invoice reminders, Follow-ups. Once saved, you can pick a template when composing to auto-fill the subject and body. Click New Template to create one.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#btn-new-mail-template" }
      ]
    },

    /* ── New Template ────────────────────────────────────────────────── */
    {
      "id": "mail_new_template",
      "intent_patterns": ["add mail template", "create mail template", "new mail template",
                          "add email template", "create email template"],
      "reply": "Let me show you how to create a new mail template!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",        "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-templates",       "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-templates-view" },
        { "type": "walk_click",   "selector": "#btn-new-mail-template",   "wait": 500 },
        { "type": "wait_visible", "selector": "#mail-tmpl-modal" },
        { "type": "walk_to",      "selector": "#mail-tmpl-modal" },
        { "type": "highlight",    "selector": "#mail-tmpl-modal" },
        { "type": "bubble",       "text": "Give the template a name (e.g. 'Invoice Reminder'), an optional default subject, and the message body. Click Save Template when done — it will appear in the template list and in the Compose dropdown.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#mail-tmpl-modal" }
      ]
    },

    /* ── Filters ─────────────────────────────────────────────────────── */
    {
      "id": "mail_filters",
      "intent_patterns": ["mail filters", "email filters", "view filters", "inbox filters",
                          "auto filters", "filter rules", "mail rules", "email rules",
                          "how to view mail filters", "manage mail filters"],
      "reply": "Opening Mail Filters!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-filters",     "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-filters-view" },
        { "type": "walk_to",      "selector": "#mail-filters-view" },
        { "type": "highlight",    "selector": "#mail-filters-view" },
        { "type": "bubble",       "text": "Mail Filters run automatically when new emails arrive. Each filter checks the From address or Subject for a keyword and then takes an action — Mark as Read or Delete. Filters are applied in order of their sort number. To add or change filters, use the web portal under Settings → Mail.", "wait": 7000 },
        { "type": "unhighlight",  "selector": "#mail-filters-view" }
      ]
    },

    /* ── Scheduled ───────────────────────────────────────────────────── */
    {
      "id": "mail_scheduled",
      "intent_patterns": ["scheduled emails", "scheduled mail", "view scheduled", "pending emails",
                          "emails scheduled", "schedule email", "show scheduled mail",
                          "how to view scheduled emails", "manage scheduled mail"],
      "reply": "Opening Scheduled Mail!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",    "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-scheduled",   "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-scheduled-view" },
        { "type": "walk_to",      "selector": "#mail-scheduled-view" },
        { "type": "highlight",    "selector": "#mail-scheduled-view" },
        { "type": "bubble",       "text": "Scheduled Emails are messages you queued to send at a future date and time. You can cancel any scheduled email by clicking the X button on its row — this removes it before it is sent.", "wait": 6000 },
        { "type": "unhighlight",  "selector": "#mail-scheduled-view" }
      ]
    },

    /* ── Schedule Email ──────────────────────────────────────────────── */
    {
      "id": "mail_schedule_send",
      "intent_patterns": ["schedule email", "schedule send", "send later", "send email later",
                          "schedule mail", "how to schedule email", "want to schedule email",
                          "delayed send email"],
      "reply": "Let me show you how to schedule an email!",
      "steps": [
        { "type": "walk_click",   "selector": "[data-tab='mail']",       "wait": 500 },
        { "type": "walk_click",   "selector": "#rb-mail-compose",        "wait": 600 },
        { "type": "wait_visible", "selector": "#mail-compose-view" },
        { "type": "walk_to",      "selector": "#mail-compose-schedule" },
        { "type": "highlight",    "selector": "#mail-compose-schedule" },
        { "type": "bubble",       "text": "Fill in the To, Subject, and Message, then click Schedule (the clock button). You can pick any future date and time. The email will be held and sent automatically at the scheduled time. View or cancel pending scheduled emails in the Scheduled tab.", "wait": 7000 },
        { "type": "unhighlight",  "selector": "#mail-compose-schedule" }
      ]
    }

  ]
};
