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
2. **Dashboard** (`renderer/dashboard.html`) opens — an animated landing
   screen: a greeting banner with a live clock, then a tile grid for the main
   modules (Products & Categories, Barcodes, Stock, Reports & Summaries,
   Cashiers, Customers, Sales Management, POS). Tiles marked "Open" are wired
   up; the rest are flagged "Coming soon" (they show a toast when tapped) —
   say which one to build next and it'll get wired to the existing Laravel
   endpoints the same way the others were. The layout switches to a compact
   mode on short windows so every module stays visible at the 960x600 minimum.
3. **POS** (`renderer/pos.html`) — search/browse products, build a cart,
   pick Cash/Card, and complete a sale.
4. **Customers** (`renderer/customers.html`) — search by name/phone/email,
   filter by category or type, add a customer (modal form), view a
   customer's profile + recent sales (modal), or delete one.

Every screen's top bar has the same profile dropdown (avatar, name, email,
business, Language, Reload page, Restart app, Log out). "Dashboard" goes back to the
tile grid from any module screen; Log out returns to the auth window.

## Languages (English / Sinhala)

**Language** in the profile dropdown (or the globe button on the login screen)
opens a language window; picking one reloads the page in that language. The
choice is remembered across restarts.

- English text is the key. Static HTML is translated once at load by
  `renderer/js/i18n.js`; JS-rendered text (toasts, empty states, confirm
  dialogs, table rows) goes through `t('English text', { placeholders })`.
  Anything without a translation simply shows in English.
- Translations live in `renderer/js/lang/si.js` — to fix a Sinhala string edit
  its value; to translate a new string add a line keyed by the exact English
  text. Load order matters: `lang/si.js`, then `i18n.js`, must be the first
  scripts at the end of `<body>` (before any script that renders data).
- Server messages are translated when they match an entry or a pattern rule
  in `si.js`; other server text stays English.
- Electron's bundled ICU has no Sinhala data, so day/month names come from
  `si.js` (`__dateNames`).
- Sinhala uses a bundled Noto Sans Sinhala (`renderer/fonts/`, SIL OFL — see
  `OFL.txt` there) so it renders the same on macOS, Windows and Linux.
- Adding a language: create `js/lang/<code>.js` (same shape), add it to
  `LANGUAGES` in `i18n.js`, and include it in each page's script list.

The Sinhala wording was written without a native reviewer — worth a read-through
by a Sinhala speaker before release.

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
  css/navbar.css, js/navbar.js  Profile dropdown + language window used by every top bar
  js/i18n.js, js/lang/si.js     Translation engine and the Sinhala dictionary
  fonts/                        Bundled Noto Sans Sinhala (+ OFL licence)
  js/api.js              Thin wrapper over the api-request IPC bridge
```
