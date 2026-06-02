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
    'title' => 'Point of sale',
    'heading' => 'Point of sale',
    'minimalAppShell' => $posWalkingCustomer,
])

@section('content')
@include('product::partials.catalog-hub-styles')
@php
    $formatQty = static function (float $value): string {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    };
    $posProductCatalog = collect($products)->keyBy('id')->map(static function (array $p): array {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'sku' => $p['sku'] ?? '',
            'layers' => $p['layers'] ?? [],
            'unit_sell_price' => $p['unit_sell_price'],
            'stock_quantity' => $p['stock_quantity'],
        ];
    })->all();
@endphp
<style>
.pos-page{max-width:100%;margin:0;}
.pos-layout{flex:1;min-height:calc(100vh - 220px);}
.pos-page:not(.pos-page--walking) .pos-three-panel__center{border:none;background:transparent;overflow:visible;}
.pos-register__sale-panel,.pos-register__catalog{display:flex;flex-direction:column;min-height:0;}
.pos-register__catalog-body{flex:1;min-height:0;display:flex;flex-direction:column;}
.pos-register__sale-head{padding:10px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-register__sale-head h2{margin:0;font-size:14px;font-weight:800;}
.pos-register__sale-body{flex:1;min-height:0;overflow:auto;padding:10px 12px 8px;}
.pos-register__browse{padding:10px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-register__browse-title{margin:0 0 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.pos-page:not(.pos-page--walking) .pos-register__catalog{overflow:visible;border:none;background:transparent;}
.pos-page:not(.pos-page--walking) .pos-register__sale-panel{border:1px solid var(--border);border-radius:12px;}
.pos-page:not(.pos-page--walking) .pos-register__browse{border:1px solid var(--border);border-radius:12px 12px 0 0;border-bottom:0;}
.pos-page:not(.pos-page--walking) .pos-register__catalog .pos-panel__body{border:1px solid var(--border);border-top:0;border-radius:0 0 12px 12px;background:var(--card);}
.pos-panel{border:1px solid var(--border);border-radius:12px;background:var(--card);overflow:hidden;}
.pos-panel__head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-panel__head h2{margin:0;font-size:14px;font-weight:800;}
.pos-panel__body{padding:12px;}
.pos-fixed-cart > .pos-panel__body{padding:0;overflow:hidden;display:flex;flex-direction:column;min-height:0;}
.pos-search{display:flex;gap:8px;flex-wrap:wrap;}
.pos-search input{flex:1 1 180px;min-width:0;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-search button,.pos-btn{padding:8px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;}
.pos-search button:hover,.pos-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);}
.pos-btn--primary:disabled,.pos-btn--primary.is-pay-blocked{opacity:.55;cursor:not-allowed;}
.pos-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;max-height:min(62vh,680px);overflow:auto;padding-right:2px;}
.pos-product{border:1px solid var(--border);border-radius:10px;padding:10px;background:color-mix(in srgb,var(--card) 96%,transparent);cursor:pointer;text-align:left;display:flex;flex-direction:column;gap:6px;transition:border-color .15s ease,transform .15s ease;}
.pos-product:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pos-product[disabled],.pos-product.is-disabled{opacity:.55;cursor:not-allowed;transform:none;}
.pos-product__img{width:100%;aspect-ratio:1/1;border-radius:8px;object-fit:cover;background:color-mix(in srgb,var(--border) 35%,transparent);}
.pos-product__placeholder{width:100%;aspect-ratio:1/1;border-radius:8px;display:grid;place-items:center;background:color-mix(in srgb,var(--border) 35%,transparent);color:var(--muted);font-size:22px;}
.pos-product__name{font-size:13px;font-weight:700;color:var(--text);line-height:1.3;}
.pos-product__meta{font-size:11px;color:var(--muted);line-height:1.35;}
.pos-product__price{font-size:13px;font-weight:800;color:var(--text);}
.pos-cart-list{display:flex;flex-direction:column;gap:8px;}
.pos-register__sale-panel .pos-cart-list{min-height:80px;}
.pos-cart-empty{margin:0;padding:18px 12px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed var(--border);border-radius:10px;}
.pos-cart-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-cart-row__name{font-size:13px;font-weight:700;color:var(--text);}
.pos-cart-row__sub{font-size:11px;color:var(--muted);margin-top:2px;}
.pos-cart-row__controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end;}
.pos-cart-row__controls input{width:72px;box-sizing:border-box;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:center;}
.pos-cart-row__remove{width:28px;height:28px;padding:0;border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));border-radius:7px;background:transparent;color:#f87171;cursor:pointer;font-size:14px;line-height:1;}
.pos-totals{border-top:1px solid var(--border);padding-top:10px;display:grid;gap:6px;margin-bottom:12px;}
.pos-totals__row{display:flex;justify-content:space-between;gap:10px;font-size:13px;}
.pos-totals__row--grand{font-size:16px;font-weight:800;color:var(--text);}
.pos-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pos-field select,.pos-field input,.pos-field textarea{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.pos-field textarea{min-height:56px;resize:vertical;font-family:inherit;}
.pos-checkout-grid{display:grid;gap:10px;}
.pos-banner{margin:0 0 12px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);font-size:13px;}
.pos-banner--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
.pos-banner--err{border-color:color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);}
.pos-page:not(.pos-page--walking) .pos-page__scroll{display:contents;}
body.pos-walking-active .pos-page__scroll{padding:10px;}
.pos-page__top{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:8px;width:100%;min-width:0;}
.pos-page__top-search{flex:1 1 auto;min-width:0;margin:0;}
.pos-page__top-search .pos-search{flex-wrap:nowrap;}
.pos-page__top-search .pos-search input{flex:1 1 auto;min-width:80px;}
.pos-page__top-actions{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;flex-shrink:0;}
body.pos-walking-active .pos-page__top-search .pos-search input{padding:6px 8px;font-size:12px;}
body.pos-walking-active .pos-page__top-search .pos-search button{padding:6px 8px;font-size:10px;}
</style>

<div class="pos-shell pos-page @if($posWalkingCustomer) pos-page--walking @endif {{ $posShellClass }}">
    <div class="pcat-page-card card" style="max-width:100%;padding:14px;">
        <div class="pcat-toolbar pos-page__top" style="margin-bottom:12px;">
            <form method="get" action="{{ route('pos.register') }}" class="pos-page__top-search pos-search" id="pos-register-search-form">
                <input type="search" name="q" id="pos-register-search" value="{{ $search }}" placeholder="Search name or SKU…" autocomplete="off">
                <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
                @if(filled($search))
                    <a href="{{ route('pos.register') }}" class="pos-btn" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;" title="Clear">×</a>
                @endif
            </form>
            <div class="pos-page__top-actions">
                <button type="button" class="pos-btn" data-pos-add-product-open title="Add product" aria-label="Add product"><i class="fa fa-plus"></i></button>
                @include('pos::partials.pos-settings-modal', ['posSettings' => $posSettings, 'accounts' => $accounts, 'hasAccounts' => $hasAccounts])
                @include('pos::partials.pos-keyboard-shortcuts')
                @include('pos::partials.pos-fullscreen-button')
                @include('pos::partials.walking-customer-toggle')
            </div>
        </div>
        @unless($posWalkingCustomer)
            @include('pos::partials.pos-hub-nav')
        @endunless

        <div class="pos-page__scroll">
        {{-- POS flash status intentionally hidden in POS flow (modal shows completion) --}}
        @if($errors->any())
            <div class="pos-banner pos-banner--err" role="alert">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
            Sell products for <strong style="color:var(--text);">{{ $business->name }}</strong>.
            Prices use the oldest stock batch first (FIFO) with each batch's selling price.
        </p>

        @if(empty($products))
            <div class="pos-banner pos-banner--err" role="alert" data-pos-empty-products-banner>
                No active products found.
                <button type="button" class="pcat-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;" data-pos-add-product-open>Add a product</button>
                or receive stock before using the register.
            </div>
        @endif

        <div class="pos-layout pos-three-panel">
            <aside class="pos-three-panel__left pos-panel pos-register__sale-panel" aria-label="Current sale">
                <div class="pos-register__sale-head">
                    <h2>Current sale</h2>
                </div>
                <div class="pos-register__sale-body">
                    <div id="pos-cart-items" class="pos-cart-list">
                        <p class="pos-cart-empty" id="pos-cart-empty">Tap a product to add it to the cart.</p>
                    </div>
                </div>
                @include('pos::partials.pos-sale-clear-footer')
            </aside>

            <section class="pos-three-panel__center pos-panel pos-register__catalog" aria-label="Product catalog">
                <div class="pos-register__catalog-body">
                <div class="pos-panel__body">
                    <div class="pos-products" id="pos-products">
                        @foreach($products as $product)
                            @php
                                $outOfStock = (float) $product['stock_quantity'] <= 0;
                                $price = $product['unit_sell_price'];
                            @endphp
                            <button
                                type="button"
                                class="pos-product @if($outOfStock) is-disabled @endif"
                                data-pos-product
                                data-product-id="{{ $product['id'] }}"
                                data-product-name="{{ e($product['name']) }}"
                                data-product-sku="{{ e($product['sku'] ?? '') }}"
                                data-unit-price="{{ $price !== null ? number_format((float) $price, 2, '.', '') : '0' }}"
                                data-stock="{{ $formatQty((float) $product['stock_quantity']) }}"
                                data-product-layers='@json($product['layers'] ?? [])'
                                @if($outOfStock) disabled @endif
                            >
                                @if($product['image_url'])
                                    <img src="{{ $product['image_url'] }}" alt="" class="pos-product__img" loading="lazy">
                                @else
                                    <div class="pos-product__placeholder"><i class="fa fa-box" aria-hidden="true"></i></div>
                                @endif
                                <span class="pos-product__name">{{ $product['name'] }}</span>
                                <span class="pos-product__meta">
                                    @if(filled($product['sku'])){{ $product['sku'] }} · @endif
                                    Stock {{ $formatQty((float) $product['stock_quantity']) }}
                                    @if(!empty($product['layer_count']) && (int) $product['layer_count'] > 1)
                                        · {{ (int) $product['layer_count'] }} batches
                                    @elseif($product['has_layers'])
                                        · batch
                                    @endif
                                </span>
                                <span class="pos-product__price">
                                    @if(!empty($product['has_multiple_prices']) && count($product['layers'] ?? []) > 1)
                                        @php
                                            $prices = collect($product['layers'])->pluck('unit_sell_price')->map(fn ($p) => (float) $p);
                                        @endphp
                                        {{ number_format($prices->min(), 2) }}–{{ number_format($prices->max(), 2) }}{{ filled($currency) ? ' '.$currency : '' }}
                                    @elseif($price !== null)
                                        {{ number_format((float) $price, 2) }}{{ filled($currency) ? ' '.$currency : '' }}
                                    @else
                                        <span class="muted">No price</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
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
                    'channel'                => 'retail',
                    'discountFieldEnabled'   => $discountFieldEnabled,
                ])
            @else
                <section class="pos-three-panel__right pos-panel pos-fixed-cart" aria-label="Checkout">
                    <div class="pos-panel__head">
                        <h2>Checkout</h2>
                    </div>
                    <div class="pos-panel__body">
                        @include('pos::partials.pos-checkout-panel', ['defaultDepositAccountId' => $defaultDepositAccountId, 'currency' => $currency, 'channel' => 'retail'])
                    </div>
                </section>
            @endif
        </div>
        </div>
    </div>
</div>

@include('pos::partials.pos-add-product-modal', ['productUnits' => $productUnits ?? collect(), 'currency' => $currency])
@include('pos::partials.pos-stock-layer-picker', ['currency' => $currency])
@include('pos::partials.pos-cart-layers-script')

@once
@include('pos::partials.beep-audio')
<script>
(function () {
    const currencySuffix = @json(filled($currency) ? ' '.$currency : '');
    const posProductCatalog = @json($posProductCatalog);
    const cart = new Map();
    window.initPosStockLayerPicker({ currencySuffix: currencySuffix });
    const productsEl = document.getElementById('pos-products');
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
            wrap.className = 'pos-cart-row';
            wrap.dataset.cartRow = row.cartKey;
            wrap.innerHTML =
                '<div>' +
                    '<div class="pos-cart-row__name"></div>' +
                    '<div class="pos-cart-row__sub"></div>' +
                '</div>' +
                '<div class="pos-cart-row__controls">' +
                    '<input type="number" min="0.001" step="any" inputmode="decimal" data-qty aria-label="Quantity">' +
                    '<strong data-line-total style="min-width:72px;text-align:right;font-size:12px;"></strong>' +
                    '<button type="button" class="pos-cart-row__remove" data-remove aria-label="Remove">&times;</button>' +
                '</div>';

            wrap.querySelector('.pos-cart-row__name').textContent = row.name;
            let subLine = (row.sku ? row.sku + ' · ' : '') + money(row.unitPrice) + ' each';
            if (row.layerLabel) subLine += ' · ' + row.layerLabel;
            subLine += ' · stock ' + row.stock;
            wrap.querySelector('.pos-cart-row__sub').textContent = subLine;

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
            qtyHidden.value = String(row.quantity);
            qtyHidden.dataset.qtyHidden = '1';
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

            qtyInput.addEventListener('change', function () {
                let qty = parseFloat(qtyInput.value);
                if (!Number.isFinite(qty) || qty <= 0) {
                    cart.delete(row.cartKey);
                    renderCart();
                    return;
                }
                const maxStock = parseFloat(row.stock);
                if (Number.isFinite(maxStock) && qty > maxStock) {
                    qty = maxStock;
                }
                row.quantity = qty;
                qtyInput.value = String(qty);
                qtyHidden.value = String(qty);
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
        if (!btn || btn.disabled) return;
        void window.posAddProductFromButton(btn, cart, posProductCatalog).then(function (added) {
            if (!added) return;
            renderCart();
            if (typeof window.playPosBeep === 'function') {
                window.playPosBeep();
            }
        });
    });

    clearBtn?.addEventListener('click', clearCart);

    discountPercentEl?.addEventListener('input', renderCart);

    function clearCart() {
        cart.clear();
        renderCart();
        document.getElementById('pos-register-search')?.focus();
    }

    window.initPosPaymentField?.({
        currencySuffix: currencySuffix,
        completeBtn: completeBtn,
        checkoutForm: checkoutForm,
    });
    window.initPosKeyboardShortcuts?.({
        searchInput: document.getElementById('pos-register-search'),
        cartItemsEl: cartItemsEl,
        discountEnabled: discountEnabled,
        clearCart: clearCart,
    });
    window.initPosAddProductModal?.({
        productsEl: productsEl,
        productsBySku: {},
        currencySuffix: currencySuffix,
        gridVariant: 'register',
        storeUrl: @json(route('pos.products.store')),
        onProductAdded: function (btn) {
            void window.posAddProductFromButton(btn, cart, posProductCatalog).then(function (added) {
                if (!added) return;
                renderCart();
                if (typeof window.playPosBeep === 'function') {
                    window.playPosBeep();
                }
            });
        },
    });
    renderCart();
    document.getElementById('pos-register-search')?.focus();

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
