@php
    $posWalkingCustomer   = (bool) ($posWalkingCustomer ?? session('pos_walking_customer', true));
    $posSettings          = $posSettings ?? [];
    $posShellClass        = $posShellClass ?? '';
    $discountFieldEnabled = (bool) ($posSettings['discount_field_enabled'] ?? false);
    $checkoutModalEnabled = (bool) ($posSettings['checkout_modal_enabled'] ?? false);
    $defaultDepositAccountId = $defaultDepositAccountId ?? null;
    $branchId             = $branchId ?? null;
    $mode                 = $mode ?? 'products';
    $quickFilter          = $quickFilter ?? '';
    $productsMeta         = $productsMeta ?? null;
    $campaignGroups       = $campaignGroups ?? [];
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
    $registerParams = static function (array $overrides = []) use ($mode, $categoryId, $search, $quickFilter, $branchId): array {
        $params = array_merge([
            'mode' => $mode,
            'category' => $categoryId,
            'q' => filled($search) ? $search : null,
            'filter' => $quickFilter !== '' ? $quickFilter : null,
            'branch' => $branchId,
        ], $overrides);
        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    };
    $catalogSource = $mode === 'campaign'
        ? collect($campaignGroups)->flatMap(fn (array $g) => $g['products'])
        : collect($mode === 'products' || $mode === 'rental' || $mode === 'dynamic' ? $products : []);
    $posProductCatalog = $catalogSource->keyBy('id')->map(static function (array $p): array {
        return [
            'id'                  => $p['id'],
            'name'                => $p['name'],
            'sku'                 => $p['sku'] ?? '',
            'unit'                => $p['unit'] ?? '',
            'layers'              => $p['layers'] ?? [],
            'selling_units'       => $p['selling_units'] ?? [],
            'unit_sell_price'     => $p['discounted_sell_price'] ?? $p['unit_sell_price'],
            'original_sell_price' => $p['discounted_sell_price'] !== null ? $p['unit_sell_price'] : null,
            'discount'            => $p['discount'] ?? null,
            'stock_quantity'      => $p['stock_quantity'],
            'is_rental'                    => (bool) ($p['is_rental'] ?? false),
            'rental_daily_rate'            => $p['rental_daily_rate'] ?? null,
            'rental_max_days'              => $p['rental_max_days'] ?? null,
            'is_dynamic_pricing'           => (bool) ($p['is_dynamic_pricing'] ?? false),
            'dynamic_price_qty_linked'     => (bool) ($p['dynamic_price_qty_linked'] ?? false),
            'is_subscription'              => (bool) ($p['is_subscription'] ?? false),
            'subscription_recurring_period' => $p['subscription_recurring_period'] ?? null,
            'has_warranty'                 => (bool) ($p['has_warranty'] ?? false),
            'warranty_duration'            => $p['warranty_duration'] ?? null,
        ];
    })->all();
    $modeTabs = [
        'products' => ['label' => 'Products', 'icon' => 'fa-box'],
        'services' => ['label' => 'Services', 'icon' => 'fa-screwdriver-wrench'],
        'rental'   => ['label' => 'Rental', 'icon' => 'fa-box-open'],
        'dynamic'  => ['label' => 'Dynamic', 'icon' => 'fa-chart-line'],
        'campaign' => ['label' => 'Campaign', 'icon' => 'fa-bullhorn'],
    ];
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
.pos-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;}
.pos-product{border:1px solid var(--border);border-radius:10px;padding:10px;background:color-mix(in srgb,var(--card) 96%,transparent);cursor:pointer;text-align:left;display:flex;flex-direction:column;gap:6px;transition:border-color .15s ease,transform .15s ease;position:relative;}
.pos-product:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pos-product[disabled],.pos-product.is-disabled{opacity:.55;cursor:not-allowed;transform:none;}
.pos-product__img{width:100%;aspect-ratio:1/1;border-radius:8px;object-fit:cover;background:color-mix(in srgb,var(--border) 35%,transparent);}
.pos-product__placeholder{width:100%;aspect-ratio:1/1;border-radius:8px;display:grid;place-items:center;background:color-mix(in srgb,var(--border) 35%,transparent);color:var(--muted);font-size:22px;}
.pos-product__placeholder--service{color:color-mix(in srgb,#8b5cf6 70%,var(--muted));background:color-mix(in srgb,#8b5cf6 12%,transparent);}
.pos-product__name{font-size:13px;font-weight:700;color:var(--text);line-height:1.3;}
.pos-product__meta{font-size:11px;color:var(--muted);line-height:1.35;}
.pos-product__price{font-size:13px;font-weight:800;color:var(--text);}
.pos-product__price-was{font-size:11px;color:var(--muted);text-decoration:line-through;font-weight:600;margin-right:2px;}
.pos-product__discount-badge{display:inline-block;font-size:9px;font-weight:700;padding:1px 4px;border-radius:4px;background:color-mix(in srgb,#f59e0b 18%,transparent);color:#b45309;border:1px solid color-mix(in srgb,#f59e0b 40%,transparent);vertical-align:middle;line-height:1.4;margin-left:2px;}
.pos-product__campaign-tag{display:inline-block;align-self:flex-start;font-size:9px;font-weight:700;padding:2px 6px;border-radius:999px;background:color-mix(in srgb,#8b5cf6 16%,transparent);color:#7c3aed;border:1px solid color-mix(in srgb,#8b5cf6 35%,transparent);}
.pos-svc-badge{display:inline-block;font-size:9px;font-weight:700;padding:1px 5px;border-radius:4px;background:color-mix(in srgb,#8b5cf6 14%,transparent);color:#8b5cf6;border:1px solid color-mix(in srgb,#8b5cf6 30%,transparent);}
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

/* ── Stats strip ─────────────────────────────────────────────────── */
.pos-stats{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
.pos-stats__tile{flex:1 1 140px;min-width:120px;padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-stats__tile-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.pos-stats__tile-value{font-size:16px;font-weight:800;color:var(--text);margin-top:2px;}
.pos-stats__tile-value--profit{color:#16a34a;}

/* ── Session tabs ────────────────────────────────────────────────── */
.pos-session-tabs{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:10px;}
.pos-session-tab{display:inline-flex;align-items:center;gap:4px;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--card) 92%,transparent);overflow:hidden;}
.pos-session-tab.is-active{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
.pos-session-tab__label{padding:7px 10px;font-size:12px;font-weight:700;border:none;background:transparent;color:var(--text);cursor:pointer;}
.pos-session-tab__count{display:inline-block;font-size:10px;font-weight:700;padding:0 5px;border-radius:999px;background:color-mix(in srgb,var(--primary) 20%,transparent);margin-left:4px;}
.pos-session-tab__close{padding:6px 8px;border:none;border-left:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:13px;line-height:1;}
.pos-session-tab__close:hover{color:#f87171;}
.pos-session-add{width:30px;height:30px;border-radius:8px;border:1px dashed var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:15px;line-height:1;}
.pos-session-add:hover{color:var(--text);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));}

/* ── Mode tabs ───────────────────────────────────────────────────── */
.pos-mode-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.pos-mode-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);text-decoration:none;}
.pos-mode-tab.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);}
.pos-mode-tab:hover{color:var(--text);}

/* ── Category / quick-filter chips ───────────────────────────────── */
.pos-chip-row{display:flex;align-items:center;gap:6px;overflow-x:auto;scrollbar-width:none;padding:2px 0 10px;}
.pos-chip-row::-webkit-scrollbar{display:none;}
.pos-chip{padding:6px 10px;font-size:11px;font-weight:700;border-radius:999px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--muted);text-decoration:none;white-space:nowrap;flex-shrink:0;}
.pos-chip.is-active,.pos-chip:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--text);background:color-mix(in srgb,var(--primary) 10%,transparent);}

/* ── Campaign grouped view ───────────────────────────────────────── */
.pos-campaign-group{margin-bottom:16px;}
.pos-campaign-group__head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;}
.pos-campaign-group__head h3{margin:0;font-size:13px;font-weight:800;color:var(--text);}
.pos-campaign-group__more{font-size:11px;color:var(--muted);}

/* ── Pager ───────────────────────────────────────────────────────── */
.pos-pager{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0 2px;}
.pos-pager a,.pos-pager span{min-width:30px;text-align:center;padding:6px 8px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid var(--border);color:var(--text);text-decoration:none;}
.pos-pager a:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));}
.pos-pager .is-current{background:color-mix(in srgb,var(--primary) 16%,transparent);border-color:color-mix(in srgb,var(--primary) 50%,var(--border));}

/* ── SKU scan ────────────────────────────────────────────────────── */
.pos-scan-row{display:flex;gap:6px;flex-shrink:0;}
.pos-scan-row input{box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);width:130px;}
</style>

<div class="pos-shell pos-page @if($posWalkingCustomer) pos-page--walking @endif {{ $posShellClass }}">
    <div class="pcat-page-card card" style="max-width:100%;padding:14px;">
        <div class="pcat-toolbar pos-page__top" style="margin-bottom:12px;">
            <form method="get" action="{{ route('pos.register') }}" class="pos-page__top-search pos-search" id="pos-register-search-form">
                @if($mode !== 'products') <input type="hidden" name="mode" value="{{ $mode }}"> @endif
                @if($categoryId) <input type="hidden" name="category" value="{{ $categoryId }}"> @endif
                @if(filled($quickFilter)) <input type="hidden" name="filter" value="{{ $quickFilter }}"> @endif
                @if($branchId) <input type="hidden" name="branch" value="{{ $branchId }}"> @endif
                <input type="search" name="q" id="pos-register-search" value="{{ $search }}" placeholder="Search name or SKU…" autocomplete="off">
                <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
                @if(filled($search))
                    <a href="{{ route('pos.register', $registerParams(['q' => null])) }}" class="pos-btn" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;" title="Clear">×</a>
                @endif
            </form>
            <div class="pos-scan-row">
                <input type="text" id="pos-sku-scan" placeholder="SKU / scan…" autocomplete="off" aria-label="SKU scanner">
                <button type="button" id="pos-sku-add" class="pos-btn" aria-label="Add SKU" title="Scan barcode (F3)"><i class="fa fa-barcode"></i></button>
            </div>
            <div class="pos-page__top-actions">
                @include('pos::partials.pos-register-session-widget', ['currency' => $currency])
                <button type="button" class="pos-btn" data-pos-add-product-open title="Add product" aria-label="Add product"><i class="fa fa-plus"></i></button>
                <button type="button" class="pos-btn" id="posReturnModalOpen" title="Return / Refund" aria-label="Return / Refund"><i class="fa fa-rotate-left"></i></button>
                @include('pos::partials.pos-settings-modal', ['posSettings' => $posSettings, 'accounts' => $accounts, 'hasAccounts' => $hasAccounts])
                @include('pos::partials.pos-receipt-editor-modal', ['posSettings' => $posSettings, 'business' => $business, 'currency' => $currency])
                @include('pos::partials.pos-keyboard-shortcuts')
                @include('pos::partials.pos-fullscreen-button')
                <a href="{{ route('dashboard') }}" class="pos-btn" title="Dashboard" aria-label="Dashboard" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;"><i class="fa fa-house"></i></a>
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

        <div class="pos-stats" aria-label="Today's summary">
            <div class="pos-stats__tile">
                <div class="pos-stats__tile-label">Sales today</div>
                <div class="pos-stats__tile-value">{{ (int) ($today['count'] ?? 0) }} · {{ number_format((float) ($today['total'] ?? 0), 2) }}{{ filled($currency) ? ' '.$currency : '' }}</div>
            </div>
            <div class="pos-stats__tile">
                <div class="pos-stats__tile-label">Gross profit</div>
                <div class="pos-stats__tile-value pos-stats__tile-value--profit">{{ number_format((float) ($today['gross_profit'] ?? 0), 2) }}{{ filled($currency) ? ' '.$currency : '' }}</div>
            </div>
            <div class="pos-stats__tile">
                <div class="pos-stats__tile-label">Cash balance</div>
                <div class="pos-stats__tile-value" id="pos-stat-cash">—</div>
            </div>
            <div class="pos-stats__tile">
                <div class="pos-stats__tile-label">Items sold</div>
                <div class="pos-stats__tile-value">{{ (int) ($today['items_sold'] ?? 0) }}</div>
            </div>
        </div>

        <div class="pos-session-tabs" id="pos-session-tabs">
            <button type="button" class="pos-session-add" id="pos-session-add" title="New session" aria-label="New session">+</button>
        </div>

        @if(empty($products) && empty($serviceItems) && empty($campaignGroups))
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
                    <div class="pos-mode-tabs" role="tablist" aria-label="Catalog mode">
                        @foreach($modeTabs as $tabKey => $tab)
                            <a href="{{ route('pos.register', $registerParams(['mode' => $tabKey, 'filter' => null, 'category' => null, 'page' => null])) }}"
                               class="pos-mode-tab @if($mode === $tabKey) is-active @endif" role="tab" aria-selected="{{ $mode === $tabKey ? 'true' : 'false' }}">
                                <i class="fa {{ $tab['icon'] }}" aria-hidden="true"></i> {{ $tab['label'] }}
                            </a>
                        @endforeach
                    </div>

                    @if(in_array($mode, ['products', 'rental', 'dynamic'], true))
                        <div class="pos-chip-row" id="pos-chip-row">
                            <a href="{{ route('pos.register', $registerParams(['category' => null, 'filter' => null, 'page' => null])) }}" class="pos-chip @if(!$categoryId && $quickFilter === '') is-active @endif">All</a>
                            @if($mode === 'products')
                                <a href="{{ route('pos.register', $registerParams(['filter' => 'recent', 'category' => null, 'page' => null])) }}" class="pos-chip @if($quickFilter === 'recent') is-active @endif"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i> Recent</a>
                                <a href="{{ route('pos.register', $registerParams(['filter' => 'discount', 'category' => null, 'page' => null])) }}" class="pos-chip @if($quickFilter === 'discount') is-active @endif"><i class="fa fa-tag" aria-hidden="true"></i> Discount</a>
                            @endif
                            @foreach($categories as $category)
                                <a href="{{ route('pos.register', $registerParams(['category' => (int) $category->id, 'page' => null])) }}" class="pos-chip @if((int) $categoryId === (int) $category->id) is-active @endif">{{ $category->name }}</a>
                            @endforeach
                        </div>
                    @endif

                    <div id="pos-catalog-wrap">
                        @if($mode === 'campaign')
                            @forelse($campaignGroups as $group)
                                <div class="pos-campaign-group">
                                    <div class="pos-campaign-group__head">
                                        <h3><i class="fa fa-bullhorn" aria-hidden="true"></i> {{ $group['name'] }}</h3>
                                        @if($group['product_count'] > count($group['products']))
                                            <span class="pos-campaign-group__more">+{{ $group['product_count'] - count($group['products']) }} more</span>
                                        @endif
                                    </div>
                                    <div class="pos-products">
                                        @foreach($group['products'] as $product)
                                            @include('pos::partials.pos-product-card', ['product' => $product, 'currency' => $currency, 'formatQty' => $formatQty])
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="muted" style="font-size:13px;">No active sale campaigns right now.</p>
                            @endforelse
                        @elseif($mode === 'services')
                            <div class="pos-products" id="pos-products">
                                @forelse($serviceItems as $svc)
                                    <button
                                        type="button"
                                        class="pos-product"
                                        data-pos-service
                                        data-service-id="{{ $svc['id'] }}"
                                        data-service-name="{{ e($svc['name']) }}"
                                        data-unit-price="{{ number_format((float) $svc['price'], 2, '.', '') }}"
                                        data-has-warranty="{{ !empty($svc['has_warranty']) ? '1' : '0' }}"
                                        data-custom-requirement-enabled="{{ !empty($svc['custom_requirement_enabled']) ? '1' : '0' }}"
                                        data-custom-requirement-fields='@json($svc['custom_requirement_fields'] ?? [])'
                                        data-duration-label="{{ $svc['duration_label'] ?? '' }}"
                                    >
                                        <div class="pos-product__placeholder pos-product__placeholder--service"><i class="fa fa-wrench" aria-hidden="true"></i></div>
                                        <span class="pos-product__name">{{ $svc['name'] }}</span>
                                        <span class="pos-product__meta"><span class="pos-svc-badge">Service</span></span>
                                        <span class="pos-product__price">{{ number_format((float) $svc['price'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span>
                                    </button>
                                @empty
                                    <p class="muted" style="font-size:13px;">No active services found.</p>
                                @endforelse
                            </div>
                        @else
                            <div class="pos-products" id="pos-products">
                                @forelse($products as $product)
                                    @include('pos::partials.pos-product-card', ['product' => $product, 'currency' => $currency, 'formatQty' => $formatQty])
                                @empty
                                    <p class="muted" style="font-size:13px;">
                                        @if($mode === 'rental') No rental products found. @else No dynamic-priced products found. @endif
                                    </p>
                                @endforelse
                            </div>

                            @if($productsMeta && $productsMeta['last_page'] > 1)
                                <nav class="pos-pager" aria-label="Pagination">
                                    @for($p = 1; $p <= $productsMeta['last_page']; $p++)
                                        @if($p === $productsMeta['current_page'])
                                            <span class="is-current">{{ $p }}</span>
                                        @else
                                            <a href="{{ route('pos.register', $registerParams(['page' => $p])) }}">{{ $p }}</a>
                                        @endif
                                    @endfor
                                </nav>
                            @endif
                        @endif
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
                    'branchId'               => $branchId,
                ])
            @else
                <section class="pos-three-panel__right pos-panel pos-fixed-cart" aria-label="Checkout">
                    <div class="pos-panel__head">
                        <h2>Checkout</h2>
                    </div>
                    <div class="pos-panel__body">
                        @include('pos::partials.pos-checkout-panel', ['defaultDepositAccountId' => $defaultDepositAccountId, 'currency' => $currency, 'channel' => 'retail', 'branchId' => $branchId])
                    </div>
                </section>
            @endif
        </div>
        </div>
    </div>
</div>

@include('pos::partials.pos-add-product-modal', ['productUnits' => $productUnits ?? collect(), 'currency' => $currency])
@include('pos::partials.pos-stock-layer-picker', ['currency' => $currency])
@include('pos::partials.pos-selling-unit-picker', ['currency' => $currency])
@include('pos::partials.pos-rental-details-picker', ['currency' => $currency])
@include('pos::partials.pos-dynamic-price-picker', ['currency' => $currency])
@include('pos::partials.pos-service-details-picker')
@include('pos::partials.pos-cart-layers-script')
@include('pos::partials.pos-return-modal', [
    'accounts'           => $accounts,
    'currency'           => $currency,
    'saleLookupUrl'      => route('pos.sale-lookup'),
    'modalReturnBaseUrl' => url('pos/online/modal-return'),
    'modalReturnOpenUrl' => route('pos.online.modal-return-open'),
    'returnsListUrl'     => route('pos.returns.index'),
])

@once
@include('pos::partials.beep-audio')
<script>
(function () {
    const currencySuffix = @json(filled($currency) ? ' '.$currency : '');
    const productsBySku = @json(collect($posProductCatalog)->filter(fn ($p) => filled($p['sku'] ?? null))->keyBy('sku'));
    const posProductCatalog = @json($posProductCatalog);
    const discountEnabled = @json($discountFieldEnabled);
    const SESSION_STORAGE_KEY = 'pos-register-sessions-v1';

    window.initPosStockLayerPicker({ currencySuffix: currencySuffix });
    window.initPosRentalDetailsPicker?.({ currencySuffix: currencySuffix });
    window.initPosDynamicPricePicker?.({ currencySuffix: currencySuffix });
    window.initPosServiceDetailsPicker?.();

    const productsEl = document.getElementById('pos-products');
    const catalogWrapEl = document.getElementById('pos-catalog-wrap');
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
    const sessionTabsEl = document.getElementById('pos-session-tabs');
    const sessionAddBtn = document.getElementById('pos-session-add');

    function money(n) {
        return Number(n || 0).toFixed(2) + currencySuffix;
    }

    // ── Multi-cart "sessions" (New Session / Close Session) ─────────────
    let sessions = new Map();
    let activeSessionId = null;
    let sessionCounter = 0;

    function newSession() {
        sessionCounter += 1;
        const id = 'sess-' + sessionCounter;
        sessions.set(id, { name: 'Session ' + sessionCounter, discountPercent: 0, cart: new Map() });
        return id;
    }

    function activeCart() {
        return sessions.get(activeSessionId).cart;
    }

    function persistSessions() {
        const out = {};
        sessions.forEach((s, id) => {
            out[id] = { name: s.name, discountPercent: s.discountPercent, cart: Array.from(s.cart.entries()) };
        });
        try {
            sessionStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify({ activeSessionId, counter: sessionCounter, sessions: out }));
        } catch (e) {}
    }

    function restoreSessions() {
        try {
            const raw = sessionStorage.getItem(SESSION_STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                sessionCounter = parsed.counter || 0;
                Object.keys(parsed.sessions || {}).forEach((id) => {
                    const s = parsed.sessions[id];
                    sessions.set(id, { name: s.name, discountPercent: s.discountPercent || 0, cart: new Map(s.cart || []) });
                });
                if (parsed.activeSessionId && sessions.has(parsed.activeSessionId)) {
                    activeSessionId = parsed.activeSessionId;
                }
            }
        } catch (e) {}
        if (sessions.size === 0) {
            activeSessionId = newSession();
        }
        if (!activeSessionId) {
            activeSessionId = sessions.keys().next().value;
        }
    }

    function renderSessionTabs() {
        if (!sessionTabsEl) return;
        sessionTabsEl.querySelectorAll('[data-session-id]').forEach((el) => el.remove());
        sessions.forEach((s, id) => {
            const tab = document.createElement('div');
            tab.className = 'pos-session-tab' + (id === activeSessionId ? ' is-active' : '');
            tab.dataset.sessionId = id;

            const label = document.createElement('button');
            label.type = 'button';
            label.className = 'pos-session-tab__label';
            label.textContent = s.name;
            if (s.cart.size > 0) {
                const count = document.createElement('span');
                count.className = 'pos-session-tab__count';
                count.textContent = String(s.cart.size);
                label.appendChild(count);
            }
            label.addEventListener('click', function () { switchSession(id); });
            tab.appendChild(label);

            if (sessions.size > 1) {
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'pos-session-tab__close';
                closeBtn.innerHTML = '&times;';
                closeBtn.setAttribute('aria-label', 'Close session');
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    closeSession(id);
                });
                tab.appendChild(closeBtn);
            }

            sessionTabsEl.insertBefore(tab, sessionAddBtn);
        });
    }

    function syncDiscountFieldFromSession() {
        if (!discountPercentEl) return;
        const s = sessions.get(activeSessionId);
        discountPercentEl.value = String(s.discountPercent || 0);
    }

    function switchSession(id) {
        if (!sessions.has(id) || id === activeSessionId) return;
        activeSessionId = id;
        syncDiscountFieldFromSession();
        renderCart();
        renderSessionTabs();
        persistSessions();
    }

    function closeSession(id) {
        const s = sessions.get(id);
        if (!s || sessions.size <= 1) return;
        if (s.cart.size > 0 && !window.confirm('Close this session? Its cart will be lost.')) return;
        sessions.delete(id);
        if (activeSessionId === id) {
            activeSessionId = sessions.keys().next().value;
            syncDiscountFieldFromSession();
        }
        renderCart();
        renderSessionTabs();
        persistSessions();
    }

    sessionAddBtn?.addEventListener('click', function () {
        activeSessionId = newSession();
        syncDiscountFieldFromSession();
        renderCart();
        renderSessionTabs();
        persistSessions();
    });

    function renderCart() {
        const cart = activeCart();
        cartItemsEl.querySelectorAll('[data-cart-row]').forEach((el) => el.remove());

        if (cart.size === 0) {
            cartEmptyEl.hidden = false;
            if (clearBtn) clearBtn.disabled = true;
            if (openCheckoutBtn) openCheckoutBtn.disabled = true;
            completeBtn.disabled = true;
            if (cartSummaryEl) cartSummaryEl.hidden = true;
            cartTotalEl.textContent = money(0);
            window.posPaymentSyncTotal?.(0, false);
            persistSessions();
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
            let subLine = (row.sku ? row.sku + ' · ' : '') + money(row.unitPrice) + (row.sellingUnitLabel ? ' / ' + row.sellingUnitLabel : ' each');
            if (row.layerLabel) subLine += ' · ' + row.layerLabel;
            if (row.isRental) subLine += ' · Return ' + row.rentalReturnDate + ' (' + row.rentalDays + 'd)';
            if (row.isDynamic) subLine += ' · Dynamic';
            if (row.warrantyType) subLine += ' · Warranty: ' + (row.warrantyType === 'lifetime' ? 'Lifetime' : row.warrantyDate);
            if (row.isSubscription) subLine += ' · ' + (row.subscriptionPeriod ? row.subscriptionPeriod.charAt(0).toUpperCase() + row.subscriptionPeriod.slice(1) : 'Recurring') + ' subscription';
            if (row.itemType !== 'service') subLine += ' · stock ' + row.stock;
            wrap.querySelector('.pos-cart-row__sub').textContent = subLine;

            const qtyInput = wrap.querySelector('[data-qty]');
            qtyInput.value = String(row.quantity);
            wrap.querySelector('[data-line-total]').textContent = money(lineTotal);

            if (row.itemType === 'service') {
                const svcInput = document.createElement('input');
                svcInput.type = 'hidden';
                svcInput.name = 'items[' + index + '][service_item_id]';
                svcInput.value = String(row.id);
                svcInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(svcInput);
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'items[' + index + '][item_type]';
                typeInput.value = 'service';
                typeInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(typeInput);
            } else {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'items[' + index + '][product_id]';
                idInput.value = String(row.id);
                idInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(idInput);
            }

            const qtyHidden = document.createElement('input');
            qtyHidden.type = 'hidden';
            qtyHidden.name = 'items[' + index + '][quantity]';
            qtyHidden.value = String(row.quantity * (row.sellingUnitFactor || 1.0));
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

            if (row.sellingUnitId != null) {
                const suId = document.createElement('input');
                suId.type = 'hidden';
                suId.name = 'items[' + index + '][product_selling_unit_id]';
                suId.value = String(row.sellingUnitId);
                suId.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(suId);
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

            if (row.warrantyType) {
                const wtyType = document.createElement('input');
                wtyType.type = 'hidden';
                wtyType.name = 'items[' + index + '][warranty_type]';
                wtyType.value = row.warrantyType;
                wtyType.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(wtyType);
                if (row.warrantyDate) {
                    const wtyDate = document.createElement('input');
                    wtyDate.type = 'hidden';
                    wtyDate.name = 'items[' + index + '][warranty_date]';
                    wtyDate.value = row.warrantyDate;
                    wtyDate.setAttribute('form', 'pos-checkout-form');
                    wrap.appendChild(wtyDate);
                }
            }

            if (row.isRental && row.rentalReturnDate) {
                const rentalInput = document.createElement('input');
                rentalInput.type = 'hidden';
                rentalInput.name = 'items[' + index + '][rental_return_date]';
                rentalInput.value = row.rentalReturnDate;
                rentalInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(rentalInput);
            }

            if (row.isDynamic && !row.dynamicLinked && row.customUnitPrice != null) {
                const dynInput = document.createElement('input');
                dynInput.type = 'hidden';
                dynInput.name = 'items[' + index + '][custom_unit_price]';
                dynInput.value = String(row.customUnitPrice);
                dynInput.setAttribute('form', 'pos-checkout-form');
                wrap.appendChild(dynInput);
            }

            if (row.itemType === 'service' && Array.isArray(row.customRequirementValues)) {
                row.customRequirementValues.forEach(function (entry, j) {
                    ['key', 'label', 'type', 'value'].forEach(function (field) {
                        const creqInput = document.createElement('input');
                        creqInput.type = 'hidden';
                        creqInput.name = 'items[' + index + '][custom_requirement_values][' + j + '][' + field + ']';
                        creqInput.value = entry[field] != null ? String(entry[field]) : '';
                        creqInput.setAttribute('form', 'pos-checkout-form');
                        wrap.appendChild(creqInput);
                    });
                });
            }

            qtyInput.addEventListener('change', function () {
                let qty = parseFloat(qtyInput.value);
                if (!Number.isFinite(qty) || qty <= 0) {
                    activeCart().delete(row.cartKey);
                    renderCart();
                    renderSessionTabs();
                    return;
                }
                const maxStock = parseFloat(row.stock);
                if (Number.isFinite(maxStock) && qty > maxStock) {
                    qty = maxStock;
                }
                row.quantity = qty;
                qtyInput.value = String(qty);
                qtyHidden.value = String(qty * (row.sellingUnitFactor || 1.0));
                renderCart();
                renderSessionTabs();
            });

            wrap.querySelector('[data-remove]').addEventListener('click', function () {
                activeCart().delete(row.cartKey);
                renderCart();
                renderSessionTabs();
            });

            cartItemsEl.appendChild(wrap);
            index += 1;
        });

        const s = sessions.get(activeSessionId);
        const discountPct = discountEnabled && discountPercentEl
            ? Math.min(100, Math.max(0, parseFloat(discountPercentEl.value) || 0))
            : 0;
        s.discountPercent = discountPct;
        const discountAmt = discountPct > 0 ? Math.round(subtotal * discountPct / 100 * 100) / 100 : 0;
        const total = Math.max(0, Math.round((subtotal - discountAmt) * 100) / 100);
        if (cartSubtotalEl) cartSubtotalEl.textContent = money(subtotal);
        if (discountAmountRow) discountAmountRow.hidden = discountAmt <= 0.001;
        if (cartDiscountEl) cartDiscountEl.textContent = money(discountAmt);
        cartTotalEl.textContent = money(total);
        window.posPaymentSyncTotal?.(total, true);
        persistSessions();
    }

    function getSelectedCustomerId() {
        const el = document.getElementById('pos-customer-id');
        return el && el.value ? el.value : '';
    }

    function requireCustomerForFlow(message) {
        if (getSelectedCustomerId()) return true;
        window.alert(message);
        document.getElementById('pos-customer-search-input')?.focus();
        return false;
    }

    async function addServiceToCart(btn) {
        const svcId = parseInt(btn.dataset.serviceId, 10);
        if (!svcId) return;

        const details = await window.posPickServiceDetails({
            serviceName: btn.dataset.serviceName || '',
            hasWarranty: btn.dataset.hasWarranty === '1',
            customRequirementEnabled: btn.dataset.customRequirementEnabled === '1',
            customRequirementFields: btn.dataset.customRequirementFields || '[]',
        });
        if (!details) return;

        const cart = activeCart();
        const cartKey = 'svc-' + svcId;
        const price = parseFloat(btn.dataset.unitPrice) || 0;
        const existing = cart.get(cartKey);
        if (existing) {
            existing.quantity += 1;
            existing.warrantyType = details.warrantyType;
            existing.warrantyDate = details.warrantyDate;
            existing.customRequirementValues = details.customRequirementValues;
        } else {
            cart.set(cartKey, {
                cartKey: cartKey,
                itemType: 'service',
                id: svcId,
                name: btn.dataset.serviceName || '',
                sku: null,
                unitPrice: price,
                quantity: 1,
                stock: Infinity,
                layerId: null,
                layerLabel: null,
                sellingUnitId: null,
                sellingUnitLabel: null,
                sellingUnitFactor: null,
                warrantyType: details.warrantyType,
                warrantyDate: details.warrantyDate,
                customRequirementValues: details.customRequirementValues,
            });
        }
        renderCart();
        renderSessionTabs();
        if (typeof window.playPosBeep === 'function') window.playPosBeep();
    }

    async function addRentalToCart(btn) {
        if (!requireCustomerForFlow('Select a customer before renting a product.')) return false;

        const id = parseInt(btn.dataset.productId, 10);
        const dailyRate = parseFloat(btn.dataset.rentalDailyRate) || 0;
        const maxDays = parseInt(btn.dataset.rentalMaxDays, 10) || 1;
        const result = await window.posPickRentalDetails({
            name: btn.dataset.productName || '',
            dailyRate: dailyRate,
            maxDays: maxDays,
        });
        if (!result) return false;

        const line = {
            cartKey: id + ':rental:' + result.returnDate,
            id: id,
            layerId: null,
            layerLabel: '',
            sellingUnitId: null,
            sellingUnitLabel: null,
            sellingUnitFactor: null,
            name: btn.dataset.productName || 'Product',
            sku: btn.dataset.productSku || '',
            unitPrice: Math.round(dailyRate * result.days * 100) / 100,
            quantity: 0,
            stock: parseFloat(btn.dataset.stock) || 0,
            isRental: true,
            rentalReturnDate: result.returnDate,
            rentalDays: result.days,
        };
        const added = window.posAddCartLine(activeCart(), line, 1);
        if (!added) {
            window.alert('Not enough stock available for this rental.');
            return false;
        }
        renderCart();
        renderSessionTabs();
        if (typeof window.playPosBeep === 'function') window.playPosBeep();
        return true;
    }

    async function addDynamicToCart(btn) {
        const id = parseInt(btn.dataset.productId, 10);
        const linked = btn.dataset.dynamicQtyLinked === '1';
        const stock = parseFloat(btn.dataset.stock) || 0;
        const result = await window.posPickDynamicPrice({
            name: btn.dataset.productName || '',
            linked: linked,
            stock: stock,
        });
        if (!result) return false;

        const line = {
            cartKey: id + ':dyn:' + Date.now(),
            id: id,
            layerId: null,
            layerLabel: '',
            sellingUnitId: null,
            sellingUnitLabel: null,
            sellingUnitFactor: null,
            name: btn.dataset.productName || 'Product',
            sku: btn.dataset.productSku || '',
            unitPrice: linked ? 1 : result.amount,
            quantity: 0,
            stock: stock,
            isDynamic: true,
            dynamicLinked: linked,
            customUnitPrice: linked ? null : result.amount,
        };
        const initialQty = linked ? result.amount : 1;
        const added = window.posAddCartLine(activeCart(), line, initialQty);
        if (!added) {
            window.alert('Not enough stock available for this amount.');
            return false;
        }
        renderCart();
        renderSessionTabs();
        if (typeof window.playPosBeep === 'function') window.playPosBeep();
        return true;
    }

    async function addProductFromButton(btn) {
        const added = await window.posAddProductWithUnit(btn, activeCart(), posProductCatalog, currencySuffix);
        if (!added) return false;
        renderCart();
        renderSessionTabs();
        if (typeof window.playPosBeep === 'function') {
            window.playPosBeep();
        }
        return true;
    }

    async function routeProductButton(btn) {
        if (btn.dataset.isRental === '1') return addRentalToCart(btn);
        if (btn.dataset.isDynamicPricing === '1') return addDynamicToCart(btn);
        return addProductFromButton(btn);
    }

    async function addBySku(rawSku) {
        const sku = String(rawSku || '').trim();
        if (!sku) return;
        const card = productsBySku[sku];
        if (!card) {
            window.alert('No product found for SKU: ' + sku);
            return;
        }
        const btn = catalogWrapEl?.querySelector('[data-product-id="' + card.id + '"]');
        if (btn) {
            await routeProductButton(btn);
            return;
        }
        const fakeBtn = document.createElement('button');
        fakeBtn.dataset.productId = String(card.id);
        fakeBtn.dataset.productName = card.name || '';
        fakeBtn.dataset.productSku = card.sku || '';
        fakeBtn.dataset.unitPrice = String(card.unit_sell_price || 0);
        fakeBtn.dataset.stock = String(card.stock_quantity || 0);
        fakeBtn.dataset.isRental = card.is_rental ? '1' : '0';
        fakeBtn.dataset.rentalDailyRate = String(card.rental_daily_rate || 0);
        fakeBtn.dataset.rentalMaxDays = String(card.rental_max_days || 1);
        fakeBtn.dataset.isDynamicPricing = card.is_dynamic_pricing ? '1' : '0';
        fakeBtn.dataset.dynamicQtyLinked = card.dynamic_price_qty_linked ? '1' : '0';
        await routeProductButton(fakeBtn);
    }

    catalogWrapEl?.addEventListener('click', function (event) {
        const svcBtn = event.target.closest('[data-pos-service]');
        if (svcBtn) { void addServiceToCart(svcBtn); return; }
        const btn = event.target.closest('[data-pos-product]');
        if (!btn || btn.disabled) return;
        void routeProductButton(btn);
    });

    checkoutForm?.addEventListener('submit', function (event) {
        if (getSelectedCustomerId()) return;
        const cart = activeCart();
        let reason = '';
        cart.forEach(function (row) {
            if (row.isRental) reason = reason || 'rental products';
            if (row.warrantyType) reason = reason || 'warranty products';
            const catalogEntry = posProductCatalog[row.id];
            if ((row.isSubscription || (catalogEntry && catalogEntry.is_subscription))) reason = reason || 'subscription products';
        });
        if (reason) {
            event.preventDefault();
            window.alert('Select a customer before completing a sale with ' + reason + '.');
            document.getElementById('pos-customer-search-input')?.focus();
        }
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

    clearBtn?.addEventListener('click', clearCart);

    discountPercentEl?.addEventListener('input', renderCart);

    function clearCart() {
        activeCart().clear();
        renderCart();
        renderSessionTabs();
        document.getElementById('pos-register-search')?.focus();
    }

    window.initPosPaymentField?.({
        currencySuffix: currencySuffix,
        completeBtn: completeBtn,
        checkoutForm: checkoutForm,
    });
    window.initPosKeyboardShortcuts?.({
        skuInput: skuInput,
        searchInput: document.getElementById('pos-register-search'),
        cartItemsEl: cartItemsEl,
        discountEnabled: discountEnabled,
        clearCart: clearCart,
    });
    window.initPosAddProductModal?.({
        productsEl: productsEl,
        productsBySku: productsBySku,
        currencySuffix: currencySuffix,
        gridVariant: 'register',
        storeUrl: @json(route('pos.products.store')),
        onProductAdded: function (btn) {
            void addProductFromButton(btn);
        },
    });

    restoreSessions();
    renderSessionTabs();
    renderCart();
    document.getElementById('pos-register-search')?.focus();

    // Cash-balance stat tile — driven by the drawer-status fetch the
    // register-session widget already performs (avoids a second fetch).
    window.addEventListener('pos-drawer-status', function (event) {
        const el = document.getElementById('pos-stat-cash');
        if (!el) return;
        const data = event.detail || {};
        el.textContent = data.is_opened ? money(data.balance) : '—';
    });

    // Listen for print completion and reset
    document.addEventListener('pos-clear-cart-and-reset', function () {
        clearCart();
    });
})();
</script>
@endonce

@if($printInvoiceUrl ?? null)
    <script>
    (function () {
        window.open(@json($printInvoiceUrl), '_blank');
        var toast = document.createElement('div');
        toast.textContent = @json(session('status') ?: 'Invoice created.');
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:500;background:#16a34a;color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.25);';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 4000);
        document.dispatchEvent(new CustomEvent('pos-clear-cart-and-reset'));
    })();
    </script>
@elseif($printSale)
    @include('pos::partials.pos-sale-completed-modal', ['completedSale' => $printSale, 'currency' => $currency, 'business' => $business, 'posSettings' => $posSettings])
@endif
@endsection
