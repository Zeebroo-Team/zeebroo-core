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
    }

    /* ── Add more walkthroughs below ─────────────────────────────────── */
    /*
    {
      "id": "example",
      "intent_patterns": ["your trigger phrase"],
      "reply": "Let me show you!",
      "steps": [
        { "type": "walk_click", "selector": "#some-button", "wait": 500 },
        { "type": "bubble", "text": "Click here to proceed.", "wait": 3000 }
      ]
    }
    */

  ]
};
