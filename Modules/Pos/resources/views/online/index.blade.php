@php
    $posWalkingCustomer   = (bool) ($posWalkingCustomer ?? session('pos_walking_customer', true));
    $posSettings          = $posSettings ?? [];
    $posShellClass        = $posShellClass ?? '';
    $discountFieldEnabled = (bool) ($posSettings['discount_field_enabled'] ?? false);
    $checkoutModalEnabled = (bool) ($posSettings['checkout_modal_enabled'] ?? false);
    $defaultDepositAccountId = $defaultDepositAccountId ?? null;
@endphp
@include('pos::partials.pos-shell-and-modal-styles')
@extends('theme::layouts.app', [
    'title' => 'Online retail POS',
    'heading' => 'Online retail POS',
    'minimalAppShell' => $posWalkingCustomer,
])

@section('content')
@php
    $formatQty = static function (float $value): string {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    };
    $onlineRouteParams = static function (?int $catId = null) use ($search): array {
        return array_filter([
            'category' => $catId,
            'q' => filled($search) ? $search : null,
        ], fn ($v) => $v !== null && $v !== '');
    };
    $posProductCatalog = collect($products)->keyBy('id')->map(static function (array $p): array {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'sku' => $p['sku'] ?? '',
            'unit' => $p['unit'] ?? '',
            'layers' => $p['layers'] ?? [],
            'selling_units' => $p['selling_units'] ?? [],
            'unit_sell_price' => $p['unit_sell_price'],
            'stock_quantity' => $p['stock_quantity'],
        ];
    })->all();
@endphp
<style>
.pos-online{max-width:100%;margin:0;display:flex;flex-direction:column;gap:0;}
.pos-online__top{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--border);border-radius:12px;background:var(--card);margin-bottom:10px;min-width:0;}
.pos-online__brand{display:flex;align-items:center;gap:8px;min-width:0;flex-shrink:0;}
.pos-online__brand-icon{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--primary);font-size:15px;flex-shrink:0;}
.pos-online__brand h1{margin:0;font-size:14px;font-weight:800;letter-spacing:-.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:min(180px,22vw);}
.pos-online__brand p{display:none;}
.pos-online__stats{display:flex;flex-wrap:nowrap;gap:6px;flex-shrink:0;}
.pos-online__stat{padding:5px 8px;border-radius:8px;border:1px solid var(--border);font-size:10px;background:color-mix(in srgb,var(--card) 94%,transparent);white-space:nowrap;}
.pos-online__stat strong{color:var(--text);}
.pos-online__top-fields{display:flex;flex:1 1 auto;align-items:center;gap:8px;min-width:0;}
.pos-online__top-fields .pos-online__scan-row{flex:1 1 0;min-width:0;margin:0;flex-wrap:nowrap;}
.pos-online__top-fields .pos-online__scan-row input{flex:1 1 auto;min-width:72px;}
.pos-online__top-fields .pos-online__scan-row button{flex-shrink:0;white-space:nowrap;}
.pos-online__actions{display:flex;flex-wrap:nowrap;gap:6px;align-items:center;flex-shrink:0;margin-left:auto;}
.pos-online__link{padding:7px 10px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:0;min-width:34px;min-height:34px;box-sizing:border-box;}
.pos-online__link:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-online__body{flex:1;min-height:0;}
.pos-online__sale-panel,.pos-online__catalog,.pos-online__checkout{display:flex;flex-direction:column;min-height:0;overflow:hidden;}
.pos-online:not(.pos-online--walking) .pos-three-panel__center{border:none;background:transparent;overflow:visible;}
.pos-online__sale-head,.pos-online__checkout-head,.pos-online__cats-bar{padding:10px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-online__cats-bar{flex-shrink:0;}
.pos-online__sale-head strong{margin:0;font-size:13px;font-weight:700;color:var(--text);}
.pos-online__sale-body{flex:1;min-height:0;overflow:auto;padding:10px 12px 8px;-webkit-overflow-scrolling:touch;}
.pos-online:not(.pos-online--walking) .pos-online__catalog{overflow:visible;border:none;background:transparent;}
.pos-online:not(.pos-online--walking) .pos-online__cats-bar{border:1px solid var(--border);border-radius:12px 12px 0 0;border-bottom:0;}
.pos-online:not(.pos-online--walking) .pos-online__catalog-body{border:1px solid var(--border);border-top:0;border-radius:0 0 12px 12px;background:var(--card);overflow:hidden;}
.pos-online:not(.pos-online--walking) .pos-online__catalog:not(:has(.pos-online__cats-bar)) .pos-online__catalog-body{border-radius:12px;border-top:1px solid var(--border);}
.pos-online__scan-row{display:flex;gap:6px;align-items:center;}
.pos-online__scan-row input[type="search"],.pos-online__scan-row input[type="text"]{box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-online__scan-row button{padding:8px 10px;font-size:11px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;}
.pos-online__cats{display:flex;flex-wrap:wrap;gap:6px;}
.pos-online__cat{padding:6px 10px;font-size:11px;font-weight:700;border-radius:999px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);text-decoration:none;white-space:nowrap;}
.pos-online__catalog{display:flex;flex-direction:column;min-height:0;}
.pos-online__catalog-body{flex:1;min-height:0;display:flex;flex-direction:column;min-width:0;}
.pos-online__catalog-main{display:flex;flex-direction:column;flex:1;min-height:0;min-width:0;}
.pos-online__cat.is-active,.pos-online__cat:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--text);background:color-mix(in srgb,var(--primary) 10%,transparent);}
.pos-online__grid-wrap{flex:1;min-height:0;overflow:auto;padding:12px;}
.pos-online__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:10px;}
.pos-online__item{border:1px solid var(--border);border-radius:11px;padding:10px;background:color-mix(in srgb,var(--card) 96%,transparent);cursor:pointer;text-align:left;display:flex;flex-direction:column;gap:6px;transition:border-color .15s ease,transform .15s ease;}
.pos-online__item:hover:not([disabled]){border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pos-online__item[disabled],.pos-online__item.is-out{opacity:.5;cursor:not-allowed;}
.pos-online__item img{width:100%;aspect-ratio:1/1;border-radius:8px;object-fit:cover;background:color-mix(in srgb,var(--border) 35%,transparent);}
.pos-online__item__ph{width:100%;aspect-ratio:1/1;border-radius:8px;display:grid;place-items:center;background:color-mix(in srgb,var(--border) 35%,transparent);color:var(--muted);font-size:22px;}
.pos-online__item__name{font-size:13px;font-weight:700;line-height:1.3;color:var(--text);}
.pos-online__item__meta{font-size:10px;color:var(--muted);line-height:1.35;}
.pos-online__item__price{font-size:14px;font-weight:800;color:var(--text);}
.pos-online__checkout-body{padding:0;display:flex;flex-direction:column;min-height:0;flex:1;overflow:hidden;}
.pos-online__cart-list{display:flex;flex-direction:column;gap:8px;}
.pos-online__sale-panel .pos-online__cart-list{min-height:80px;}
.pos-online__cart-empty{margin:0;padding:20px 12px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed var(--border);border-radius:10px;}
.pos-online__line{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-online__line__name{font-size:13px;font-weight:700;color:var(--text);}
.pos-online__line__sub{font-size:10px;color:var(--muted);margin-top:2px;word-break:break-word;}
.pos-online__line__ctrl{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end;}
.pos-online__line__ctrl input{width:76px;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:center;box-sizing:border-box;}
.pos-online__line__rm{width:28px;height:28px;padding:0;border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));border-radius:7px;background:transparent;color:#f87171;cursor:pointer;}
.pos-online__total{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid var(--border);margin-bottom:10px;font-size:18px;font-weight:800;color:var(--text);}
.pos-online__field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pos-online__field select,.pos-online__field textarea{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-online__field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;cursor:pointer;}
.pos-online__field textarea{min-height:52px;resize:vertical;font-family:inherit;}
.pos-online__checkout{display:grid;gap:8px;}
.pos-online__pay-btn{width:100%;padding:12px 14px;font-size:15px;font-weight:800;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);cursor:pointer;margin-top:4px;}
.pos-online__pay-btn:disabled,.pos-online__pay-btn.is-pay-blocked{opacity:.5;cursor:not-allowed;}
.pos-online__banner{margin:0 0 10px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);font-size:13px;}
.pos-online__banner--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.pos-online__banner--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
.content--minimal .content-inner{max-width:100%;padding:12px 14px 18px;}
.content--pos-only .content-inner{padding:8px 10px 12px;}
.pos-online:not(.pos-online--walking){min-height:calc(100vh - 120px);}
.pos-online:not(.pos-online--walking) .pos-online__scroll{display:contents;}
body.pos-walking-active .pos-online__scroll{padding:0;}
body.pos-walking-active .pos-online__top{background:var(--card);}
body.pos-walking-active .pos-online__top-fields{gap:6px;}
body.pos-walking-active .pos-online__top-fields .pos-online__scan-row input{padding:6px 8px;font-size:12px;}
body.pos-walking-active .pos-online__top-fields .pos-online__scan-row button{padding:6px 8px;font-size:10px;}
.pos-shell--light.pos-online--walking .pos-online__top,.pos-shell--dark.pos-online--walking .pos-online__top{background:var(--pos-card);}
</style>

<div class="pos-shell pos-online @if($posWalkingCustomer) pos-online--walking @endif {{ $posShellClass }}" data-pos-online>
    <header class="pos-online__top">
        <div class="pos-online__brand">
            <span class="pos-online__brand-icon"><i class="fa fa-store" aria-hidden="true"></i></span>
            <h1>@if($posWalkingCustomer) POS · {{ $business->name }}@else Online retail POS @endif</h1>
        </div>
        <div class="pos-online__stats" aria-label="Today's summary">
            <span class="pos-online__stat"><strong>{{ (int) ($today['online_count'] ?? 0) }}</strong> sales</span>
            <span class="pos-online__stat"><strong>{{ number_format((float) ($today['online_total'] ?? 0), 2) }}</strong>{{ filled($currency) ? ' '.$currency : '' }}</span>
        </div>
        <div class="pos-online__top-fields" aria-label="Search and scan">
            <form method="get" action="{{ route('pos.online') }}" class="pos-online__scan-row" id="pos-online-search-form">
                @if($categoryId)
                    <input type="hidden" name="category" value="{{ $categoryId }}">
                @endif
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name…" autocomplete="off" id="pos-online-search">
                <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
            </form>
            <div class="pos-online__scan-row">
                <input type="text" id="pos-sku-scan" placeholder="SKU / scan…" autocomplete="off" aria-label="SKU scanner">
                <button type="button" id="pos-sku-add" aria-label="Add SKU"><i class="fa fa-barcode"></i></button>
            </div>
        </div>
        <div class="pos-online__actions">
            <button type="button" class="pos-online__link" data-pos-add-product-open title="Add product" aria-label="Add product"><i class="fa fa-plus" aria-hidden="true"></i></button>
            @include('pos::partials.pos-settings-modal', ['posSettings' => $posSettings, 'accounts' => $accounts, 'hasAccounts' => $hasAccounts])
            @include('pos::partials.pos-keyboard-shortcuts')
            @include('pos::partials.pos-fullscreen-button')
            @include('pos::partials.walking-customer-toggle')
            @unless($posWalkingCustomer)
                <a href="{{ route('pos.index') }}" class="pos-online__link" title="Hub"><i class="fa fa-gauge-high"></i></a>
                <a href="{{ route('pos.sales.index') }}" class="pos-online__link" title="Sales"><i class="fa fa-receipt"></i></a>
            @endunless
        </div>
    </header>

    <div class="pos-online__scroll">
    {{-- POS flash status intentionally hidden in POS flow (modal shows completion) --}}
    @if($errors->any())
        <div class="pos-online__banner pos-online__banner--err" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(empty($products))
        <div class="pos-online__banner pos-online__banner--err" role="alert" data-pos-empty-products-banner>
            No products available.
            <button type="button" class="pcat-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;" data-pos-add-product-open>Add a product</button>
            or adjust filters.
        </div>
    @endif

    <div class="pos-online__body pos-three-panel">
        <aside class="pos-three-panel__left pos-online__sale-panel" aria-label="Current sale">
            <div class="pos-online__sale-head">
                <strong>Current sale</strong>
            </div>
            <div class="pos-online__sale-body">
                <div id="pos-cart-items" class="pos-online__cart-list">
                    <p class="pos-online__cart-empty" id="pos-cart-empty">Add products from the catalog or scan a SKU.</p>
                </div>
            </div>
            @include('pos::partials.pos-sale-clear-footer')
        </aside>

        <section class="pos-three-panel__center pos-online__catalog" aria-label="Product catalog">
            @if($categories->isNotEmpty())
                <nav class="pos-online__cats-bar pos-online__cats" aria-label="Categories">
                    <a href="{{ route('pos.online', $onlineRouteParams()) }}" @class(['pos-online__cat', 'is-active' => !$categoryId])>All</a>
                    @foreach($categories as $category)
                        <a href="{{ route('pos.online', $onlineRouteParams((int) $category->id)) }}" @class(['pos-online__cat', 'is-active' => (int) $categoryId === (int) $category->id])>{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
            <div class="pos-online__catalog-body">
            <div class="pos-online__catalog-main">
            <div class="pos-online__grid-wrap">
                <div class="pos-online__grid" id="pos-online-products">
                    @foreach($products as $product)
                        @php $outOfStock = (float) $product['stock_quantity'] <= 0; @endphp
                        <button
                            type="button"
                            class="pos-online__item @if($outOfStock) is-out @endif"
                            data-pos-product
                            data-product-id="{{ $product['id'] }}"
                            data-product-name="{{ e($product['name']) }}"
                            data-product-sku="{{ e($product['sku'] ?? '') }}"
                            data-unit-price="{{ $product['unit_sell_price'] !== null ? number_format((float) $product['unit_sell_price'], 2, '.', '') : '0' }}"
                            data-stock="{{ $formatQty((float) $product['stock_quantity']) }}"
                            data-product-layers='@json($product['layers'] ?? [])'
                            data-selling-units='@json($product['selling_units'] ?? [])'
                            @if($outOfStock) disabled @endif
                        >
                            @if($product['image_url'])
                                <img src="{{ $product['image_url'] }}" alt="" loading="lazy">
                            @else
                                <div class="pos-online__item__ph"><i class="fa fa-box" aria-hidden="true"></i></div>
                            @endif
                            <span class="pos-online__item__name">{{ $product['name'] }}</span>
                            <span class="pos-online__item__meta">
                                {{ filled($product['sku'] ?? null) ? $product['sku'].' · ' : '' }}{{ $formatQty((float) $product['stock_quantity']) }} in stock
                                @if(!empty($product['layer_count']) && (int) $product['layer_count'] > 1)
                                    · {{ (int) $product['layer_count'] }} batches
                                @endif
                            </span>
                            <span class="pos-online__item__price">
                                @if(!empty($product['has_multiple_prices']) && count($product['layers'] ?? []) > 1)
                                    @php
                                        $prices = collect($product['layers'])->pluck('unit_sell_price')->map(fn ($p) => (float) $p);
                                        $minP = $prices->min();
                                        $maxP = $prices->max();
                                    @endphp
                                    {{ number_format($minP, 2) }}–{{ number_format($maxP, 2) }}{{ filled($currency) ? ' '.$currency : '' }}
                                @elseif($product['unit_sell_price'] !== null)
                                    {{ number_format((float) $product['unit_sell_price'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}
                                @else
                                    —
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
            </div>
            @include('pos::partials.pos-cart-totals-bar', [
                'discountFieldEnabled' => $discountFieldEnabled,
                'checkoutModalEnabled' => $checkoutModalEnabled,
                'currency' => $currency,
            ])
            </div>
        </section>

        @if($checkoutModalEnabled)
            @include('pos::partials.pos-checkout-modal', [
                'defaultDepositAccountId' => $defaultDepositAccountId,
                'currency'               => $currency,
                'channel'                => 'online',
                'discountFieldEnabled'   => $discountFieldEnabled,
            ])
        @else
            <section class="pos-three-panel__right pos-online__checkout" aria-label="Checkout">
                <div class="pos-online__checkout-head">
                    <strong style="font-size:14px;">Checkout</strong>
                </div>
                <div class="pos-online__checkout-body">
                    @include('pos::partials.pos-checkout-panel', ['defaultDepositAccountId' => $defaultDepositAccountId, 'currency' => $currency, 'channel' => 'online'])
                </div>
            </section>
        @endif
    </div>
    </div>
</div>

@include('pos::partials.pos-add-product-modal', ['productUnits' => $productUnits ?? collect(), 'currency' => $currency])
@include('pos::partials.pos-stock-layer-picker', ['currency' => $currency])
@include('pos::partials.pos-selling-unit-picker', ['currency' => $currency])
@include('pos::partials.pos-cart-layers-script')

@once
@include('pos::partials.beep-audio')
<script>
(function () {
    const currencySuffix = @json(filled($currency) ? ' '.$currency : '');
    const productsBySku = @json(collect($products)->filter(fn ($p) => filled($p['sku'] ?? null))->keyBy('sku'));
    const posProductCatalog = @json($posProductCatalog);
    const cart = new Map();

    window.initPosStockLayerPicker({ currencySuffix: currencySuffix });

    const productsEl = document.getElementById('pos-online-products');
    const skuInput = document.getElementById('pos-sku-scan');
    const skuBtn = document.getElementById('pos-sku-add');
    const cartItemsEl = document.getElementById('pos-cart-items');
    const cartEmptyEl = document.getElementById('pos-cart-empty');
    const cartTotalEl = document.getElementById('pos-cart-total');
    const completeBtn = document.getElementById('pos-complete-sale');
    const clearBtn = document.getElementById('pos-clear-cart');
    const openCheckoutBtn = document.getElementById('pos-open-checkout-modal');
    const checkoutForm = document.getElementById('pos-checkout-form');
    const cartSummaryEl = document.getElementById('pos-cart-summary');
    const cartSubtotalEl = document.getElementById('pos-cart-subtotal');
    const discountPercentEl = document.getElementById('pos-discount-percent');
    const discountAmountRow = document.getElementById('pos-discount-amount-row');
    const cartDiscountEl = document.getElementById('pos-cart-discount');
    const discountEnabled = @json($discountFieldEnabled);

    function money(n) {
        return Number(n || 0).toFixed(2) + currencySuffix;
    }

    async function addProductFromButton(btn) {
        const added = await window.posAddProductWithUnit(btn, cart, posProductCatalog, currencySuffix);
        if (!added) return false;
        renderCart();
        if (typeof window.playPosBeep === 'function') {
            window.playPosBeep();
        }
        return true;
    }

    async function addBySku(rawSku) {
        const sku = String(rawSku || '').trim();
        if (!sku) return;
        const card = productsBySku[sku];
        if (!card) {
            window.alert('No product found for SKU: ' + sku);
            return;
        }
        const btn = productsEl?.querySelector('[data-product-id="' + card.id + '"]');
        if (btn) {
            await addProductFromButton(btn);
            return;
        }
        const fakeBtn = document.createElement('button');
        fakeBtn.dataset.productId = String(card.id);
        fakeBtn.dataset.productName = card.name || '';
        fakeBtn.dataset.productSku = card.sku || '';
        fakeBtn.dataset.unitPrice = String(card.unit_sell_price || 0);
        fakeBtn.dataset.stock = String(card.stock_quantity || 0);
        const added = await window.posAddProductWithUnit(fakeBtn, cart, posProductCatalog, currencySuffix);
        if (added) {
            renderCart();
            if (typeof window.playPosBeep === 'function') {
                window.playPosBeep();
            }
        }
    }

    function renderCart() {
        cartItemsEl.querySelectorAll('[data-cart-row]').forEach((el) => el.remove());
        if (cart.size === 0) {
            cartEmptyEl.hidden = false;
            if (clearBtn) clearBtn.disabled = true;
            if (openCheckoutBtn) openCheckoutBtn.disabled = true;
            completeBtn.disabled = true;
            if (cartSummaryEl) cartSummaryEl.hidden = true;
            cartTotalEl.textContent = money(0);
            window.posPaymentSyncTotal?.(0, false);
            return;
        }
        cartEmptyEl.hidden = true;
        if (clearBtn) clearBtn.disabled = false;
        if (openCheckoutBtn) openCheckoutBtn.disabled = false;
        if (cartSummaryEl) cartSummaryEl.hidden = false;
        let subtotal = 0;
        let index = 0;
        cart.forEach((row) => {
            const lineTotal = row.quantity * row.unitPrice;
            subtotal += lineTotal;
            const wrap = document.createElement('div');
            wrap.className = 'pos-online__line';
            wrap.dataset.cartRow = row.cartKey;
            wrap.innerHTML =
                '<div><div class="pos-online__line__name"></div><div class="pos-online__line__sub"></div></div>' +
                '<div class="pos-online__line__ctrl">' +
                    '<input type="number" min="0.001" step="any" data-qty aria-label="Quantity">' +
                    '<strong data-line-total style="min-width:68px;text-align:right;font-size:12px;"></strong>' +
                    '<button type="button" class="pos-online__line__rm" data-remove aria-label="Remove">&times;</button>' +
                '</div>';
            wrap.querySelector('.pos-online__line__name').textContent = row.name;
            let subLine = (row.sku ? row.sku + ' · ' : '') + money(row.unitPrice) + (row.sellingUnitLabel ? ' / ' + row.sellingUnitLabel : ' each');
            if (row.layerLabel) subLine += ' · ' + row.layerLabel;
            wrap.querySelector('.pos-online__line__sub').textContent = subLine;
            const qtyInput = wrap.querySelector('[data-qty]');
            qtyInput.value = String(row.quantity);
            wrap.querySelector('[data-line-total]').textContent = money(lineTotal);
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'items[' + index + '][product_id]';
            idInput.value = String(row.id);
            idInput.setAttribute('form', 'pos-checkout-form');
            wrap.appendChild(idInput);
            const qtyHidden = document.createElement('input');
            qtyHidden.type = 'hidden';
            qtyHidden.name = 'items[' + index + '][quantity]';
            qtyHidden.value = String(row.quantity * (row.sellingUnitFactor || 1.0));
            qtyHidden.setAttribute('form', 'pos-checkout-form');
            wrap.appendChild(qtyHidden);
            if (row.layerId) {
                const layerInput = document.createElement('input');
                layerInput.type = 'hidden';
                layerInput.name = 'items[' + index + '][product_stock_layer_id]';
                layerInput.value = String(row.layerId);
                layerInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(layerInput);
            }
            if (row.sellingUnitLabel) {
                const suLabel = document.createElement('input');
                suLabel.type = 'hidden';
                suLabel.name = 'items[' + index + '][selling_unit_label]';
                suLabel.value = row.sellingUnitLabel;
                suLabel.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(suLabel);
                const suFactor = document.createElement('input');
                suFactor.type = 'hidden';
                suFactor.name = 'items[' + index + '][selling_unit_factor]';
                suFactor.value = String(row.sellingUnitFactor || 1.0);
                suFactor.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(suFactor);
            }
            qtyInput.addEventListener('change', function () {
                let qty = parseFloat(qtyInput.value);
                if (!Number.isFinite(qty) || qty <= 0) {
                    cart.delete(row.cartKey);
                    renderCart();
                    return;
                }
                const maxStock = parseFloat(row.stock);
                if (Number.isFinite(maxStock) && qty > maxStock) qty = maxStock;
                row.quantity = qty;
                renderCart();
            });
            wrap.querySelector('[data-remove]').addEventListener('click', function () {
                cart.delete(row.cartKey);
                renderCart();
            });
            cartItemsEl.appendChild(wrap);
            index += 1;
        });
        const discountPct = discountEnabled && discountPercentEl
            ? Math.min(100, Math.max(0, parseFloat(discountPercentEl.value) || 0))
            : 0;
        const discountAmt = discountPct > 0 ? Math.round(subtotal * discountPct / 100 * 100) / 100 : 0;
        const total = Math.max(0, Math.round((subtotal - discountAmt) * 100) / 100);
        if (cartSubtotalEl) cartSubtotalEl.textContent = money(subtotal);
        if (discountAmountRow) discountAmountRow.hidden = discountAmt <= 0.001;
        if (cartDiscountEl) cartDiscountEl.textContent = money(discountAmt);
        cartTotalEl.textContent = money(total);
        window.posPaymentSyncTotal?.(total, true);
    }

    productsEl?.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-pos-product]');
        if (btn) void addProductFromButton(btn);
    });

    skuBtn?.addEventListener('click', function () {
        void addBySku(skuInput?.value);
        if (skuInput) skuInput.value = '';
        skuInput?.focus();
    });

    skuInput?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            void addBySku(skuInput.value);
            skuInput.value = '';
        }
    });

    discountPercentEl?.addEventListener('input', renderCart);

    function clearCart() {
        cart.clear();
        renderCart();
        skuInput?.focus();
    }

    clearBtn?.addEventListener('click', clearCart);

    window.initPosPaymentField?.({
        currencySuffix: currencySuffix,
        completeBtn: completeBtn,
        checkoutForm: checkoutForm,
    });
    window.initPosKeyboardShortcuts?.({
        skuInput: skuInput,
        searchInput: document.getElementById('pos-online-search'),
        cartItemsEl: cartItemsEl,
        discountEnabled: discountEnabled,
        clearCart: clearCart,
    });
    window.initPosAddProductModal?.({
        productsEl: productsEl,
        productsBySku: productsBySku,
        currencySuffix: currencySuffix,
        gridVariant: 'online',
        storeUrl: @json(route('pos.products.store')),
        onProductAdded: function (btn) {
            void addProductFromButton(btn);
        },
    });
    renderCart();
    skuInput?.focus();

    // Listen for print completion and reset
    document.addEventListener('pos-clear-cart-and-reset', function () {
        clearCart();
    });
})();
</script>
@endonce

@if($printSale)
    @include('pos::partials.pos-sale-completed-modal', ['completedSale' => $printSale, 'currency' => $currency, 'business' => $business])
@endif
@endsection
