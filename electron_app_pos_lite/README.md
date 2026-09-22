# Zeebroo POS Lite

A minimal Electron POS client. It talks to the same Laravel POS API used by
the full desktop app (`Modules/Pos/routes/api.php`, prefix `/api/v1/pos`) —
no separate backend was added for this lite version because every endpoint
it needs (`auth/token`, `auth/register`, `auth/business-categories`,
`businesses`, `online/products`, `online/checkout`, `customers`,
`customer-categories`) already exists.

## Flow

1. **Auth window** (`renderer/auth.html`) — Login and Sign Up tabs in one
   small window. On success the app fetches the user's business, saves the
   token + business id to `userData/config.json`, then...
2. **Dashboard** (`renderer/dashboard.html`) opens — an attractive tile grid
   for the main POS modules: Products & Categories, Barcodes, Stock, Reports
   & Summaries, Cashiers, Customers, Sales Management, and POS. **POS** and
   **Customers** are wired up; the rest are flagged "Coming soon" (they show
   a toast when tapped) — say which one to build next and it'll get wired to
   the existing Laravel endpoints the same way these two were.
3. **POS** (`renderer/pos.html`) — search/browse products, build a cart,
   pick Cash/Card, and complete a sale.
4. **Customers** (`renderer/customers.html`) — search by name/phone/email,
   filter by category or type, add a customer (modal form), view a
   customer's profile + recent sales (modal), or delete one.

"Dashboard" goes back to the tile grid from either screen; Logout returns to
the auth window from any screen.

## Run

```bash
cd electron_app_pos_lite
npm install
npm start
```

Make sure the Laravel app is running locally first (default API base URL is
`http://localhost:8000/api/v1/pos`, set in `config.js`):

```bash
php artisan serve
```

## Structure

```text
main.js               Electron main process — windows, local config, API proxy
preload.js            contextBridge surface exposed to renderers
config.js             API_BASE_URL
renderer/
  auth.html/css/js       Login + Sign Up
  dashboard.html/css/js  Tile-grid landing screen (active + "coming soon" modules)
  pos.html/css/js        Product grid, cart, checkout
  customers.html/css/js  Customer list, search/filter, add/view/delete
  js/api.js              Thin wrapper over the api-request IPC bridge
```
