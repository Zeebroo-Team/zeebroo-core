# POS Online API

REST API mirroring the **Online POS** web terminal. Base URL:

```
https://your-app.test/api/v1/pos
```

Interactive docs (Swagger UI): **`GET /api/v1/pos/docs`**

OpenAPI spec: **`GET /api/v1/pos/docs/openapi.yaml`**

---

## Authentication

### 1. Get a token

```http
POST /api/v1/pos/auth/token
Content-Type: application/json

{
  "email": "you@example.com",
  "password": "your-password",
  "device_name": "pos-terminal-1"
}
```

Response:

```json
{
  "token_type": "Bearer",
  "access_token": "1|....",
  "user": { "id": 1, "name": "...", "email": "..." }
}
```

### 2. Use the token

```http
Authorization: Bearer {access_token}
Accept: application/json
```

### 3. Revoke token

```http
POST /api/v1/pos/auth/revoke
Authorization: Bearer {access_token}
```

---

## Business context

Pass the active business on every protected request:

| Method | Example |
|--------|---------|
| Header (recommended) | `X-Business-Id: 12` |
| Query string | `?business_id=12` |

If omitted, the API falls back to the user's selected/latest business (same as the web navbar).

---

## Endpoints

### Businesses (desktop / multi-store login)

```http
GET /api/v1/pos/businesses
Authorization: Bearer {access_token}
```

Returns `{ "data": [ { "id": 1, "name": "Mario Pvt Ltd" }, ... ] }` for the authenticated user.

### Bootstrap (single call to load the terminal)

```http
GET /api/v1/pos/online/bootstrap?q=cola&category=3
```

Returns: `business`, `currency`, `categories`, `products`, `accounts`, `today` summary, `settings`, `product_units`.

### Catalog

| Method | Path | Description |
|--------|------|-------------|
| GET | `/online/categories` | Active categories with sellable products |
| GET | `/online/products` | Product cards (`q`, `category` filters) |
| GET | `/online/products/sku/{sku}` | Lookup by barcode/SKU |
| POST | `/online/products` | Quick-create product |

### Checkout

```http
POST /api/v1/pos/online/checkout
```

Body:

```json
{
  "items": [
    { "product_id": 5, "quantity": 2 },
    { "product_id": 8, "quantity": 1, "product_stock_layer_id": 42 }
  ],
  "payment_method": "cash",
  "channel": "online",
  "credit_account_id": 3,
  "amount_tendered": 500.00,
  "discount_percent": 10,
  "notes": "Walk-in customer"
}
```

- **`payment_method`**: `cash` | `card` | `credit`
- **`credit_account_id`**: required for `cash` and `card` (deposit account)
- **`amount_tendered`**: required for `cash`
- **`product_stock_layer_id`**: optional; use when selling from a specific stock batch (multi-price stock)

Response `201` includes full `sale` object (items, totals, change, etc.).

### Settings

| Method | Path |
|--------|------|
| GET | `/online/settings` |
| PUT or PATCH | `/online/settings` |

Body fields: `default_deposit_account_id`, `discount_field_enabled`, `display_theme` (`light` | `dark`).

### Sales

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sales` | List sales (`q`, `channel=online\|retail`) |
| GET | `/sales/{id}` | Sale detail |
| POST | `/sales/{id}/void` | Void sale and restore stock |

---

## Product card shape

Each product in the catalog includes stock **layers** (batches) when applicable:

```json
{
  "id": 5,
  "name": "Mineral water",
  "sku": "W500",
  "unit_sell_price": 120.00,
  "stock_quantity": 48,
  "layer_count": 2,
  "requires_layer_pick": true,
  "has_multiple_prices": true,
  "layers": [
    {
      "id": 10,
      "label": "May 1, 2026 · GRN-0042",
      "quantity_remaining": 20,
      "unit_cost": 80,
      "unit_sell_price": 120
    }
  ]
}
```

When `requires_layer_pick` is true, the client must let the cashier choose a layer and send `product_stock_layer_id` on checkout.

---

## Errors

| Status | Meaning |
|--------|---------|
| 401 | Missing/invalid Bearer token |
| 403 | Forbidden |
| 404 | Not found (e.g. unknown SKU) |
| 422 | Validation error (`message` + `errors` object) |

---

## Example flow (mobile app)

1. `POST /auth/token` → save `access_token`
2. `GET /online/bootstrap` with `X-Business-Id`
3. User builds cart locally
4. `GET /online/products/sku/ABC123` on scan (optional)
5. `POST /online/checkout` with cart items
6. Display receipt from response `data` (sale)

---

## Web parity

This API exposes the same business logic as:

- `GET /pos/online` (bootstrap data split into endpoints)
- `POST /pos/checkout`
- `POST /pos/products`
- `POST /pos/settings`

The web UI remains available; clients can use either session cookies or API tokens.

---

## POS Desktop (Qt)

The **Qt 6 desktop client** is pulled in as a git submodule at [`pos-desktop/`](../../../pos-desktop) from [Zeebroo-Team/zeebroo-pos-desktop](https://github.com/Zeebroo-Team/zeebroo-pos-desktop). The CMake project is under **`pos-desktop/pos-desktop/`** (same layout as the standalone repo).

- Same REST API as this document

### Clone Zeebroo Core with the submodule

```bash
git clone --recurse-submodules https://github.com/Zeebroo-Team/zeebroo-core.git
cd zeebroo-core
```

If you already cloned without submodules:

```bash
git submodule update --init --recursive
```

### Work on the desktop app

```bash
cd pos-desktop/pos-desktop
# see https://github.com/Zeebroo-Team/zeebroo-pos-desktop for build steps (CMake + Qt 6)
```

To bump the pinned submodule in Zeebroo Core: commit and push inside `pos-desktop` on the `zeebroo-pos-desktop` repo, then in this repo run `git add pos-desktop` and commit the updated submodule pointer.
