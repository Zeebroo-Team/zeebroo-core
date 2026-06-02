# POS Module Detailed Overview

## Module Summary
The **Pos module** (`Modules/Pos/`) is a comprehensive point-of-sale system for the SociBiz platform, handling retail and online transactions, product management, inventory tracking with FIFO batch pricing, and payment settlement. The module uses **Laravel Blade templates** with **vanilla JavaScript** and **Vite** for frontend bundling—no Vue or React components.

**Location**: `Modules/Pos/`

---

## 1. MODAL IMPLEMENTATIONS AND STRUCTURE

### 1.1 Modal Architecture Pattern
All modals follow a consistent custom implementation pattern using:
- **Vanilla JavaScript** event listeners
- **CSS visibility/opacity transitions** (no library dependencies)
- **ARIA attributes** for accessibility
- **Data attributes** for state management

### 1.2 Modal Types and Implementation Details

#### A. Print Bill Modal (`pos-print-bill-modal.blade.php`)
**Purpose**: Displays completed sale details with print functionality  
**Z-index**: 210 (higher priority)

**Structure**:
```
pos-bill-modal
├── pos-bill-modal__backdrop (click to close)
├── pos-bill-modal__panel
│   ├── pos-bill-modal__head (title + close button)
│   ├── pos-bill-modal__body
│   │   ├── pos-bill-modal__meta (transaction metadata)
│   │   ├── pos-bill-receipt-table (items table)
│   │   └── Notes display
│   └── pos-bill-modal__foot (action buttons)
```

**Key Features**:
- Stores serialized bill data in `data-pos-bill-print` attribute as JSON
- Generates HTML print layout dynamically
- Opens print window for thermal or standard printer support
- Accessible via `Escape` key or backdrop click
- Contains full transaction details: business name, sale number, items, totals, payment method, change amount

**JavaScript Initialization**:
```javascript
billData = JSON.parse(modal.getAttribute('data-pos-bill-print') || '{}')
// buildPrintHtml() generates HTML for window.open()
// Escapes HTML for security (replaces &, <, >, ")
```

**Print Data Structure** (passed from controller):
```php
$posBillPrintData = [
    'businessName' => $business->name,
    'saleNumber' => $printSale->sale_number,
    'soldAt' => $printSale->sold_at->format('M j, Y g:i A'),
    'payment' => $printSale->paymentMethodLabel(),
    'account' => $printSale->creditAccount?->deductOptionLabel(),
    'channel' => $printSale->channelLabel(), // 'retail' or 'online'
    'currency' => trim($currencyLabel),
    'subtotal' => number_format($subtotal, 2),
    'discountPercent' => $discountPercent,
    'discountAmount' => $discountAmount,
    'total' => number_format($printSale->total, 2),
    'amountPaid' => number_format($printSale->amount_paid, 2),
    'amountTendered' => $printSale->amount_tendered,
    'changeAmount' => $printSale->change_amount,
    'notes' => $printSale->notes,
    'items' => [ /* formatted item array */ ]
]
```

#### B. Settings Modal (`pos-settings-modal.blade.php`)
**Purpose**: Configure POS display, payment, and print settings  
**Z-index**: 200

**Tabs**:
1. **General**: Theme toggle (light/dark mode with CSS variables)
2. **Sales**: Deposit account selection, discount field toggle
3. **Print Layout**: Receipt header/footer text, business info display options

**State Management**:
- Tab switching via `data-psm-tab` attribute
- Uses `is-active` class for active panel
- Arrow keys for keyboard navigation (ArrowLeft/Right)
- Theme value synced to hidden input for form submission

**Key Code Pattern**:
```javascript
tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
        var target = tab.dataset.psmTab;
        // Toggle active class on matching tab and panel
        // Update aria-selected attribute
    });
});
```

#### C. Add Product Modal (`pos-add-product-modal.blade.php`)
**Purpose**: Quick product creation during sales  
**Z-index**: 320 (highest for modals)

**Fields**:
- Product name (required, max 255 chars)
- SKU/code (optional, with generate button)
- Unit price (decimal)
- Stock on hand (optional)
- Product unit (if units configured)

**Features**:
- Form validation with inline error messages (`data-pos-add-product-error`)
- Auto-shows errors under each field
- Banner message for general errors
- API endpoint: `POST /pos/online/products`
- On success: Modal closes, product added to catalog and cart

#### D. Stock Layer Picker Modal (`pos-stock-layer-picker.blade.php`)
**Purpose**: Select specific batch when product has multiple stock layers (FIFO pricing)  
**Context**: Triggered when product has multiple layers and user adds to cart

**Implementation**: Conditional display based on product batch availability

### 1.3 Modal State Management Pattern

All modals use consistent pattern:
```javascript
function setOpen(open) {
    modal.classList.toggle('is-open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.documentElement.classList.toggle('pos-MODAL-NAME-open', open);
    // Prevents scrolling
}
```

**Styling Pattern**:
```css
.pos-MODAL-NAME {
    position: fixed;
    inset: 0;
    z-index: XXX;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .2s ease, visibility .2s ease;
}
.pos-MODAL-NAME.is-open {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.pos-MODAL-NAME__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(3px);
}
```

---

## 2. PRINT FUNCTIONALITY & TEMPLATES

### 2.1 Print Implementation Overview

**Trigger Flow**:
1. Sale completed via `PosController::checkout()`
2. Sale ID stored in session: `session('pos_print_sale_id')`
3. Next page load, controller pulls session value
4. `pos-print-bill-modal.blade.php` receives `$printSale` data
5. Modal auto-opens with bill data

### 2.2 Print HTML Generation

**Location**: `pos-print-bill-modal.blade.php` → JavaScript `buildPrintHtml()`

**Print Document Structure**:
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{Sale Number}</title>
    <style>
        /* Printer-friendly CSS */
        body { font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
        /* Minimal margins and sizes */
    </style>
</head>
<body>
    <h1>{Business Name}</h1>
    <p class="meta">{Sale #} · {Date} {Time}<br/>{Payment Method}</p>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Amount</th></tr></thead>
        <tbody>{Item rows}</tbody>
        <tfoot>{Totals with discount}</tfoot>
    </table>
    <p>Thank you · Printed {Current Date/Time}</p>
</body>
</html>
```

### 2.3 Blade Print Variables

Available in `pos-print-bill-modal.blade.php`:
- `$printSale`: Sale model with loaded relationships (items, user, creditAccount, ledgerTransactions)
- `$business`: Business model
- `$currency`: Currency symbol/code
- `$posSettings`: POS configuration array

**Data Calculations**:
```php
$subtotal = (float) ($printSale->subtotal ?? $printSale->items->sum(fn ($i) => (float) $i->line_total));
$discountAmount = (float) ($printSale->discount_amount ?? 0);
$discountPercent = (float) ($printSale->discount_percent ?? 0);
```

### 2.4 Print Settings Configuration

From `PosSettingsService`, configurable in settings modal:
- `receipt_header`: Custom header text (max 200 chars)
- `receipt_footer`: Custom footer text (default: "Thank you for your purchase!")
- `show_business_name`: Display business name on receipt (boolean)
- `show_business_address`: Display business address on receipt (boolean)

### 2.5 Print Actions

**Print Button Handler**:
```javascript
document.getElementById('pos-bill-btn-print').addEventListener('click', function () {
    const w = window.open('', '_blank');
    w.document.write(buildPrintHtml());
    w.document.close();
    w.print();
});
```

**Additional Actions**:
- "View receipt": Link to full receipt page (`pos.sales.show`)
- "New sale": Closes modal, clears cart, returns to register

### 2.6 Thermal Printer Support

**No explicit thermal printer implementation found**, but:
- Print layout is designed for 80mm thermal receipts (narrow width)
- HTML print template is printer-agnostic
- Works with standard browser print dialog + thermal printer driver
- `window.open()` → `print()` allows user to select printer

**For thermal integration**, you would:
1. Detect thermal printer via JavaScript
2. Auto-select printer and skip print dialog
3. Or use Electron/Desktop integration for direct thermal printing

---

## 3. FRONTEND FRAMEWORK & ARCHITECTURE

### 3.1 Technology Stack

| Component | Technology |
|-----------|-----------|
| **Template Engine** | Laravel Blade (PHP) |
| **JavaScript** | Vanilla JS (No framework) |
| **Bundler** | Vite 4.0.0 |
| **CSS** | Custom CSS + CSS variables |
| **HTTP Client** | Axios (available, minimal usage) |
| **Build** | `npm run build` → Vite compilation |

### 3.2 Vite Configuration

**File**: `Modules/Pos/vite.config.js`  
**Scripts**:
```json
{
  "dev": "vite",
  "build": "vite build"
}
```

**Dependencies**:
- `laravel-vite-plugin`: ^0.7.5
- `axios`: ^1.1.2
- `sass`: ^1.69.5
- `postcss`: ^8.3.7

### 3.3 Vanilla JavaScript Pattern

The POS module uses a **function-based initialization pattern** rather than frameworks:

```javascript
// Pattern 1: IIFE with data-attributes
(function () {
    const cart = new Map();
    window.initPosStockLayerPicker({ /* config */ });
    // Event listeners setup
})();

// Pattern 2: Window-scoped initializer functions
window.initPosPaymentField = function (options) {
    // Called from Blade with data passed as options
};

// Pattern 3: Data attributes for behavior
<button data-pos-product></button>
<div data-pos-numpad="money"></div>
```

### 3.4 CSS Architecture

**Approach**: 
- **Inline styles** in Blade templates for component-specific styling
- **Scoped CSS classes** with BEM naming (e.g., `.pos-cart-row__name`)
- **CSS variables** for theme colors
  ```css
  --pos-bg, --pos-card, --pos-text, --pos-muted, --pos-border, --pos-primary
  ```

**Theme System**:
- Light/Dark mode via CSS variables
- Applied via `.pos-shell--light` or `.pos-shell--dark`
- Overrides: `--pos-bg`, `--pos-card`, `--pos-text`, etc.

### 3.5 File Organization

```
resources/
├── assets/
│   ├── js/
│   │   └── app.js (empty - compiled by Vite)
│   └── sass/
└── views/
    ├── register/
    │   ├── index.blade.php (retail POS main page)
    │   └── show.blade.php (sale detail)
    ├── sales/
    │   ├── index.blade.php (sales history)
    │   └── show.blade.php (receipt view)
    ├── online/
    │   └── index.blade.php (online POS variant)
    ├── components/
    │   └── layouts/
    └── partials/ (16 modal/component partials)
```

---

## 4. SALES & TRANSACTIONS HANDLING

### 4.1 Transaction Flow

```
User adds products to cart
  ↓
Selects payment method (Cash/Card/Credit)
  ↓
Enters payment amount (if cash) or selects account
  ↓
Submits checkout form via POST /pos/checkout
  ↓
PosController::checkout() validates & calls SaleService::checkout()
  ↓
DB::transaction() wrapper:
  1. Create pos_sales record
  2. For each item: consume stock via FIFO/layer
  3. Create pos_sale_items records
  4. Calculate discount
  5. Settle payment (ledger entry)
  ↓
Redirect with session flag 'pos_print_sale_id'
  ↓
Page reloads → Modal displays bill
```

### 4.2 Sale Model (`Modules/Pos/Models/Sale.php`)

**Database Table**: `pos_sales`

**Key Fields**:
```php
$fillable = [
    'business_id',
    'user_id',
    'sale_number',          // e.g., "S-001"
    'status',               // 'completed' | 'void'
    'payment_method',       // 'cash' | 'card' | 'credit'
    'channel',              // 'retail' | 'online'
    'credit_account_id',    // For depositing cash/card
    'subtotal',             // Before discount
    'discount_percent',     // 0-100
    'discount_amount',      // Calculated
    'total',                // After discount
    'amount_paid',          // Actual amount processed
    'amount_tendered',      // For cash (what customer gave)
    'change_amount',        // For cash (amount returned)
    'notes',                // Optional notes
    'sold_at',              // Timestamp
];

// Constants
const STATUS_COMPLETED = 'completed';
const STATUS_VOID = 'void';
const PAYMENT_CASH = 'cash';
const PAYMENT_CARD = 'card';
const PAYMENT_CREDIT = 'credit';
const CHANNEL_RETAIL = 'retail';
const CHANNEL_ONLINE = 'online';
```

**Relationships**:
```php
public function business(): BelongsTo       // Parent business
public function user(): BelongsTo           // User who created sale
public function creditAccount(): BelongsTo  // Account receiving payment
public function items(): HasMany            // SaleItem (line items)
public function ledgerTransactions(): MorphMany // Payment settlement records
```

### 4.3 Sale Item Model (`Modules/Pos/Models/SaleItem.php`)

**Database Table**: `pos_sale_items`

**Fields**:
```php
$fillable = [
    'pos_sale_id',
    'product_id',
    'product_stock_layer_id',  // Batch consumed
    'product_name',            // Snapshot of name
    'sku',                     // Snapshot of SKU
    'quantity',
    'unit_cost',               // From batch
    'unit_sell_price',         // From batch
    'line_total',              // qty × unit_sell_price
    'sort_order',
];
```

### 4.4 SaleService (`Modules/Pos/Services/SaleService.php`)

**Key Methods**:

#### A. `checkout()` - Main transaction method
```php
public function checkout(
    Business $business,
    User $user,
    array $items,           // [{ product_id, quantity, product_stock_layer_id? }]
    string $paymentMethod,  // 'cash|card|credit'
    ?int $creditAccountId,
    ?float $amountPaid,
    ?string $notes,
    string $channel = Sale::CHANNEL_RETAIL,
    ?float $discountPercent = null,
    ?float $amountTendered = null,
): Sale
```

**Process**:
1. **Normalize cart items**: Resolve product IDs, validate quantities
2. **Validate payment method**: Ensure valid method
3. **DB::transaction()**:
   - Create Sale record (initial totals = 0)
   - For each line:
     - Consume stock via FIFO or specific layer
     - Create SaleItem record
     - Add to subtotal
   - Calculate discount: `subtotal × (discountPercent / 100)`
   - Final total: `subtotal - discountAmount`
   - If cash: Calculate change from tendered amount
   - Call `SalePaymentSettlementService::settle()` to create ledger entry
   - Update sale with final amounts

#### B. `listForBusiness()` - Get sales with search
```php
public function listForBusiness(Business $business, ?string $search = null): Collection
// Returns ordered by sold_at DESC, id DESC
// Supports search by sale_number or notes (LIKE)
```

#### C. `todaySummaryForBusiness()` - Daily totals
```php
public function todaySummaryForBusiness(Business $business): array
// Returns: [count, total, online_count, online_total]
// Filters: STATUS_COMPLETED, today's date range
```

#### D. `void()` - Reverse a sale
```php
public function void(Sale $sale, Business $business): void
// Sets status to 'void'
// Returns stock to inventory
// Reverses ledger entries
```

### 4.5 Stock Consumption Service

**File**: `Modules/Pos/Services/SaleStockConsumptionService.php`

#### A. `consumeFromLayer()` - Use specific batch
```php
public function consumeFromLayer(Product $product, int $layerId, float $quantity): array
// Locks product and layer for update
// Reduces layer.quantity_remaining
// Reduces product.stock_quantity
// Returns: [{ product_stock_layer_id, quantity, unit_cost, unit_sell_price }]
```

#### B. `consumeFifo()` - First-In-First-Out
```php
public function consumeFifo(Product $product, float $quantity): array
// Finds oldest layer with available stock
// Consumes quantity across layers if needed
// FIFO ensures oldest inventory sold first
// Example: 100 units needed
//   - Layer 1 (oldest): 60 units → consumed
//   - Layer 2: 40 units consumed
//   - Returns two allocation entries
```

**FIFO Algorithm**:
```php
$layers = ProductStockLayer::query()
    ->where('product_id', $product->id)
    ->where('quantity_remaining', '>', 0)
    ->orderBy('received_at')  // Oldest first
    ->orderBy('id')
    ->lockForUpdate()
    ->get();

foreach ($layers as $layer) {
    if ($needed <= 0) break;
    $toConsume = min($needed, $layer->quantity_remaining);
    // ... consume from layer
    $needed -= $toConsume;
}
```

### 4.6 Payment Settlement Service

**File**: `Modules/Pos/Services/SalePaymentSettlementService.php`

**Method**: `settle()`
```php
public function settle(
    Sale $sale,
    Business $business,
    User $user,
    int $creditAccountId,      // Account to credit
    ?float $amount = null,     // Amount to deposit
    string $paymentMethod = Sale::PAYMENT_CASH,
): ?LedgerTransaction
```

**Process**:
1. Validate sale belongs to business
2. Skip if sale total ≤ $0.005 (money tolerance)
3. Lock account for update (prevent race conditions)
4. Call `AccountService::applyBalanceAddition()` to increase account balance
5. Create `LedgerTransaction` record with metadata:
   ```php
   [
       'payment_method' => 'cash|card|credit',
       'sale_number' => $sale->sale_number,
       'settlement_source' => 'pos_sale',
       'direction' => 'income',
   ]
   ```

### 4.7 API Endpoints

**File**: `routes/api.php`

**Checkout via API**:
```
POST /api/v1/pos/online/checkout
Content-Type: application/json
Authorization: Bearer {token}

{
  "items": [
    { "product_id": 1, "quantity": 2, "product_stock_layer_id": null }
  ],
  "payment_method": "cash",
  "channel": "online",
  "credit_account_id": 5,
  "amount_tendered": 100,
  "discount_percent": 10,
  "notes": "Customer notes"
}

Response:
{
  "message": "Sale S-001 completed.",
  "data": { /* formatted sale data */ }
}
```

### 4.8 Form Validation

**Controller**: `PosController::checkout()`

```php
$request->validate([
    'items' => ['required', 'array', 'min:1'],
    'items.*.product_id' => ['required', 'integer', 'min:1'],
    'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
    'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
    'payment_method' => ['required', 'string', 'in:cash,card,credit'],
    'channel' => ['nullable', 'string', 'in:retail,online'],
    'credit_account_id' => [
        'nullable', 'integer', 'min:1',
        Rule::requiredIf(in_array($request->input('payment_method'), ['cash', 'card']))
    ],
    'amount_tendered' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
    'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
    'notes' => ['nullable', 'string', 'max:2000'],
]);
```

---

## 5. THERMAL PRINTER IMPLEMENTATIONS

### 5.1 Current Status

**Finding**: **No explicit thermal printer implementation** in the codebase.

The print system is printer-agnostic:
- Browser's native `window.print()` → User selects printer
- HTML receipt optimized for narrow widths (thermal standard)
- Works with any printer driver installed on the system

### 5.2 Print System Design for Thermal

**Thermal-Friendly HTML**:
- **Max width**: ~420px (80mm thermal standard)
- **Font**: `system-ui, -apple-system, Segoe UI, Roboto, sans-serif`
- **Line height**: 1.4 (compact for paper)
- **No images** in default template (improve speed)
- **Table-based layout** (simple, no complex CSS)

### 5.3 Implementation Recommendations

#### Option 1: Browser Print Dialog (Current)
**Pros**: Platform-independent, no extra dependencies  
**Cons**: Requires manual printer selection

#### Option 2: ESC/POS Direct Printing (JavaScript)
Add ESC/POS library (e.g., `escpos-buffer`):
```javascript
import EscPos from 'escpos-buffer';

const buffer = new EscPos();
buffer.text('BUSINESS NAME');
buffer.text('Sale #' + billData.saleNumber);
// ... build receipt
buffer.print();  // Send to thermal printer
```

**Requirements**:
- Browser support for Serial API or USB HID
- Printer driver compatibility

#### Option 3: Backend Rendering (Laravel)
Use PHP library like `Mike42/escpos-php`:
```php
require_once '/vendor/mike42/escpos-php/autoload.php';

$connector = new WindowsPrintConnector("Thermal Printer Name");
$printer = new Printer($connector);

$printer->text("RECEIPT\n");
$printer->text($sale->sale_number . "\n");
// ... build receipt
$printer->close();
```

#### Option 4: Electron Desktop App
The workspace has a `desktop/` folder with CMake build. For direct thermal:
```javascript
// In Electron main process
const { exec } = require('child_process');
const receiptHtml = /* generate */;

// Write to temp file
fs.writeFileSync('/tmp/receipt.html', receiptHtml);

// Send to thermal printer via lp/lpr command
exec(`lp -d "Thermal Printer" /tmp/receipt.html`);
```

### 5.4 Future Integration Points

**To add thermal support**:

1. **Add printer detection endpoint**:
   ```php
   // In PosController or new ThermalPrinterController
   Route::get('/pos/printers', [PosController::class, 'getPrinters']);
   ```

2. **Modify print modal**:
   ```javascript
   // Instead of window.open() + print dialog:
   if (window.thermalPrinterAvailable) {
       window.printToThermal(billHtml);
   } else {
       window.open(...).print();
   }
   ```

3. **Add to POS settings**:
   - Thermal printer name/address
   - Auto-print option (skip dialog)
   - Receipt width settings

---

## 6. CODE PATTERNS & CONVENTIONS

### 6.1 Naming Conventions

| Element | Pattern | Example |
|---------|---------|---------|
| Data attributes | `data-{module}-{action}` | `data-pos-product`, `data-pos-numpad-key` |
| CSS classes | `pos-{component}__{element}--{modifier}` | `pos-cart-row__name`, `pos-btn--primary` |
| Window functions | `window.init{Component}` | `window.initPosPaymentField()` |
| Element IDs | `pos-{element}-{purpose}` | `pos-cart-items`, `pos-settings-modal` |

### 6.2 Component Structure

**Blade Partial Pattern**:
```blade
@php
    // Destructure options
    $setting1 = $setting1 ?? 'default';
@endphp

@once
<style>
    /* Component styles */
</style>
@endonce

<!-- HTML markup -->
<div id="component-id" class="component-class">
    <!-- Content -->
</div>

@once
<script>
// Only renders once even if partial included multiple times
window.initComponent = function(options) {
    // Setup
};
</script>
@endonce
```

### 6.3 Service Layer Pattern

```php
class ServiceName
{
    private const MONEY_TOLERANCE = 0.005;
    
    public function __construct(
        private readonly DependencyService $dependency,
    ) {}
    
    public function mainMethod(/* params */): ResultType {
        return DB::transaction(function () {
            // Locked query: ->lockForUpdate()
            // Validation: throw ValidationException
            // Return typed result
        });
    }
}
```

### 6.4 Form Pattern

**Blade**:
```blade
<form method="post" action="{{ route('pos.checkout') }}" id="form-id">
    @csrf
    <input type="hidden" name="field" form="form-id">
</form>

<button type="submit" form="form-id">Submit</button>
```

**JavaScript**:
```javascript
const form = document.getElementById('form-id');
form.addEventListener('submit', function(e) {
    e.preventDefault();
    // Validate client-side
    // Submit via fetch or native form
});
```

---

## 7. ROUTE STRUCTURE

### Web Routes (`routes/web.php`)
```
GET  /pos                          → PosController::index       (hub/dashboard)
GET  /pos/online                   → PosController::online      (online POS)
GET  /pos/register                 → PosController::register    (retail register)
POST /pos/walking-customer         → PosController::toggleWalkingCustomer
POST /pos/settings                 → PosController::saveSettings
POST /pos/checkout                 → PosController::checkout
POST /pos/products                 → PosProductController::store
GET  /pos/sales                    → SaleController::index
GET  /pos/sales/{sale}             → SaleController::show
POST /pos/sales/{sale}/void        → SaleController::void
```

### API Routes (`routes/api.php`)
```
POST /api/v1/pos/auth/token
POST /api/v1/pos/auth/revoke
GET  /api/v1/pos/businesses
GET  /api/v1/pos/online/bootstrap
GET  /api/v1/pos/online/categories
GET  /api/v1/pos/online/products
GET  /api/v1/pos/online/products/sku/{sku}
POST /api/v1/pos/online/products
POST /api/v1/pos/online/checkout
GET  /api/v1/pos/online/settings
PUT  /api/v1/pos/online/settings
GET  /api/v1/pos/sales
GET  /api/v1/pos/sales/{sale}
POST /api/v1/pos/sales/{sale}/void
```

---

## 8. DIRECTORY STRUCTURE SUMMARY

```
Modules/Pos/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── PosController.php              (Main POS pages)
│   │   │   ├── PosProductController.php       (Quick product add)
│   │   │   ├── SaleController.php             (Sales history & detail)
│   │   │   ├── Api/
│   │   │   │   ├── PosCheckoutApiController.php
│   │   │   │   ├── PosCatalogApiController.php
│   │   │   │   ├── PosSettingsApiController.php
│   │   │   │   └── ... (7 more API controllers)
│   │   │   └── Concerns/
│   │   │       └── ResolvesPosBusiness.php    (Middleware logic)
│   ├── Models/
│   │   ├── Sale.php                          (Transaction record)
│   │   └── SaleItem.php                      (Line item)
│   ├── Services/
│   │   ├── SaleService.php                   (Transaction orchestration)
│   │   ├── SalePaymentSettlementService.php  (Ledger entry creation)
│   │   ├── SaleStockConsumptionService.php   (FIFO inventory)
│   │   ├── PosCatalogService.php             (Product listing)
│   │   ├── PosSettingsService.php            (Settings management)
│   │   ├── PosOnlineApiService.php           (API formatting)
│   │   └── PosProductQuickCreateService.php  (Quick add product)
│   └── Providers/
│       └── PosServiceProvider.php            (Module provider)
├── config/
│   └── (module configuration)
├── database/
│   ├── factories/
│   ├── migrations/
│   │   └── *_create_pos_tables.php
│   └── seeders/
├── resources/
│   ├── assets/
│   │   ├── js/
│   │   │   └── app.js                        (Vite entry, mostly empty)
│   │   └── sass/
│   ├── views/
│   │   ├── register/
│   │   │   ├── index.blade.php               (Retail POS main UI)
│   │   │   └── show.blade.php                (Sale receipt detail)
│   │   ├── sales/
│   │   │   ├── index.blade.php               (Sales history list)
│   │   │   └── show.blade.php                (Sale view page)
│   │   ├── online/
│   │   │   └── index.blade.php               (Online POS variant)
│   │   ├── hub/
│   │   │   └── index.blade.php               (Dashboard)
│   │   ├── components/
│   │   │   └── layouts/
│   │   ├── api/
│   │   ├── partials/                        (16 reusable components)
│   │   │   ├── pos-shell-and-modal-styles.blade.php
│   │   │   ├── pos-print-bill-modal.blade.php
│   │   │   ├── pos-settings-modal.blade.php
│   │   │   ├── pos-add-product-modal.blade.php
│   │   │   ├── pos-payment-field.blade.php
│   │   │   ├── pos-numpad.blade.php
│   │   │   ├── pos-keyboard-shortcuts.blade.php
│   │   │   ├── pos-stock-layer-picker.blade.php
│   │   │   ├── pos-cart-totals-bar.blade.php
│   │   │   ├── pos-cart-layers-script.blade.php
│   │   │   ├── pos-fullscreen-button.blade.php
│   │   │   ├── walking-customer-toggle.blade.php
│   │   │   ├── pos-hub-nav.blade.php
│   │   │   ├── beep-audio.blade.php
│   │   │   ├── pos-sale-clear-footer.blade.php
│   │   │   └── pos-three-panel-styles.blade.php
│   │   └── views/
├── routes/
│   ├── web.php                              (11 web routes)
│   └── api.php                              (13 API endpoints)
├── tests/
├── docs/
├── module.json                              (Module metadata)
├── package.json                             (JS dependencies: Vite, Axios, Sass)
├── composer.json                           (PHP dependencies)
└── vite.config.js                          (Vite configuration)
```

---

## 9. KEY TAKEAWAYS

### Strengths
1. **Clean separation of concerns**: Services handle business logic, controllers orchestrate
2. **Transaction safety**: All checkout operations wrapped in `DB::transaction()`
3. **Inventory accuracy**: FIFO ensures proper stock allocation
4. **Accessibility**: ARIA attributes, keyboard navigation, semantic HTML
5. **Printer flexibility**: Works with any system printer, no dependencies
6. **Responsive design**: Three-panel layout adapts to walking customer mode

### Areas for Enhancement
1. **Thermal printer integration**: Currently relies on browser print dialog
2. **Real-time stock sync**: No WebSocket updates
3. **Offline capability**: No service worker/offline mode
4. **Mobile experience**: POS walking customer mode, but could improve on mobile devices
5. **Payment integration**: Currently deposits to accounts; no credit card processors
6. **Analytics**: No built-in reporting/dashboards beyond sales list

---

## 10. DATABASE TABLES

**Core Tables**:
- `pos_sales`: Main transaction records
- `pos_sale_items`: Line items per sale
- Related: `products`, `product_stock_layers`, `accounts`, `ledger_transactions`

**Migrations**: `database/migrations/`
- `2026_XX_XX_create_pos_tables.php`

