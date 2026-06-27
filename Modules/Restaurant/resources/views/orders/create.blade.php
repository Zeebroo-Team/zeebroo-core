@extends('theme::layouts.app', [
    'title'           => 'New Order',
    'heading'         => 'Restaurant',
    'minimalAppShell' => true,
    'hideNavbar'      => true,
])

@section('content')
@include('pos::partials.pos-three-panel-styles')
<style>
/* ── Sticky top header ── */
.rpos-sticky   { position:sticky;top:0;z-index:50;background:var(--bg);
                 margin:-28px -28px 0;padding:14px 28px 0;
                 box-shadow:0 2px 12px rgba(0,0,0,.06);
                 border-bottom:1px solid var(--border); }

/* ── Top bar ── */
.rpos-top      { display:flex;align-items:center;gap:8px;margin-bottom:12px; }
.rpos-search   { flex:1 1 180px;min-width:0;display:flex;gap:6px; }
.rpos-search input { flex:1;min-width:0;padding:8px 12px;font-size:13px;border-radius:8px;
                     border:1px solid var(--border);background:var(--bg);color:var(--text);box-sizing:border-box; }
.rpos-search input:focus { outline:none;border-color:var(--primary); }
.rpos-back     { padding:8px 12px;border-radius:8px;border:1px solid var(--border);color:var(--muted);
                 text-decoration:none;font-size:13px;display:flex;align-items:center;gap:5px;flex-shrink:0; }
.rpos-back:hover { border-color:var(--text);color:var(--text); }
.rpos-action-btn { width:34px;height:34px;display:flex;align-items:center;justify-content:center;
                   border-radius:8px;border:1px solid var(--border);background:var(--bg);
                   color:var(--muted);font-size:13px;cursor:pointer;text-decoration:none;
                   transition:border-color .15s,color .15s,background .15s;flex-shrink:0; }
.rpos-action-btn:hover { border-color:var(--primary);color:var(--primary);
                          background:color-mix(in srgb,var(--primary) 6%,var(--bg)); }
.rpos-settings-menu { position:absolute;top:calc(100% + 8px);right:0;z-index:200;
                       min-width:180px;background:var(--bg);border:1px solid var(--border);
                       border-radius:11px;padding:6px;
                       box-shadow:0 8px 28px rgba(0,0,0,.14);
                       animation:rpos-fade-in .12s ease; }
@keyframes rpos-fade-in { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }
.rpos-settings-item { display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;
                       font-size:12px;font-weight:600;color:var(--text);text-decoration:none;
                       transition:background .12s,color .12s; }
.rpos-settings-item:hover { background:color-mix(in srgb,var(--primary) 8%,var(--bg));color:var(--primary); }
.rpos-settings-item i { width:14px;text-align:center;color:var(--muted);font-size:12px; }
.rpos-settings-item:hover i { color:var(--primary); }
.rpos-settings-divider { height:1px;background:var(--border);margin:4px 6px; }

/* ── Table / session tabs ── */
.rpos-tabs-wrap { display:flex;align-items:center;gap:6px;padding:8px 0 10px;margin-bottom:0;
                  overflow-x:auto;scrollbar-width:none; }
.rpos-tabs-wrap::-webkit-scrollbar { display:none; }

.rpos-tab       { display:inline-flex;flex-direction:column;align-items:flex-start;
                  gap:2px;padding:7px 13px 8px;flex-shrink:0;
                  border:1.5px solid var(--border);border-radius:9px;
                  background:var(--bg);cursor:pointer;white-space:nowrap;
                  transition:border-color .18s,background .18s,box-shadow .18s; }
.rpos-tab:hover { border-color:color-mix(in srgb,var(--tab-color,var(--primary)) 45%,var(--border));
                  background:color-mix(in srgb,var(--tab-color,var(--primary)) 4%,var(--bg)); }
.rpos-tab.active { border-color:var(--tab-color,var(--primary));
                   background:color-mix(in srgb,var(--tab-color,var(--primary)) 8%,var(--bg));
                   box-shadow:0 2px 10px color-mix(in srgb,var(--tab-color,var(--primary)) 18%,transparent); }
.rpos-tab:disabled { opacity:.4;cursor:not-allowed;pointer-events:none; }

/* Top row: dot + name + badge */
.rpos-tab__top  { display:flex;align-items:center;gap:6px; }
.rpos-tab__dot  { width:7px;height:7px;border-radius:50%;flex-shrink:0;transition:transform .2s; }
.rpos-tab.active .rpos-tab__dot { transform:scale(1.3); }
.rpos-tab__name { font-size:12px;font-weight:800;color:var(--muted);line-height:1;transition:color .15s; }
.rpos-tab.active .rpos-tab__name { color:var(--tab-color,var(--primary)); }
.rpos-tab:hover:not(.active) .rpos-tab__name { color:var(--text); }
.rpos-tab__badge { display:inline-flex;align-items:center;justify-content:center;
                   min-width:16px;height:16px;padding:0 4px;border-radius:999px;
                   font-size:9px;font-weight:800;
                   background:var(--tab-color,var(--primary));color:#fff;line-height:1; }

/* Sub-label */
.rpos-tab__sub  { font-size:10px;color:var(--muted);padding-left:13px;opacity:.7;line-height:1; }
.rpos-tab.active .rpos-tab__sub { opacity:1;color:var(--tab-color,var(--primary)); }

.rpos-tab-sep   { width:1px;background:var(--border);margin:0 2px;flex-shrink:0;align-self:stretch; }

@keyframes rposTabPulse {
  0%   { box-shadow:0 0 0 0   color-mix(in srgb,var(--tab-color,var(--primary)) 55%,transparent); }
  55%  { box-shadow:0 0 0 8px color-mix(in srgb,var(--tab-color,var(--primary))  0%,transparent); }
  100% { box-shadow:0 0 0 0   color-mix(in srgb,var(--tab-color,var(--primary))  0%,transparent); }
}
.rpos-tab--pulse {
  animation:rposTabPulse .65s ease-out 3;
  border-color:var(--tab-color,var(--primary)) !important;
  background:color-mix(in srgb,var(--tab-color,var(--primary)) 12%,var(--bg)) !important;
}

/* ── Category tabs ── */
.rpos-cat-bar  { display:flex;align-items:center;gap:6px;overflow-x:auto;flex-shrink:0;
                 margin-bottom:12px;padding:2px 1px 4px;scrollbar-width:none; }
.rpos-cat-bar::-webkit-scrollbar { display:none; }
.rpos-cat-btn  { display:inline-flex;align-items:center;gap:6px;
                 padding:6px 13px;border-radius:999px;flex-shrink:0;
                 border:1.5px solid var(--border);background:transparent;
                 color:var(--muted);font-size:11px;font-weight:700;cursor:pointer;
                 white-space:nowrap;transition:all .18s; }
.rpos-cat-btn:hover { border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
                      color:var(--text);
                      background:color-mix(in srgb,var(--primary) 4%,transparent); }
.rpos-cat-btn.active { background:var(--primary);border-color:var(--primary);color:#fff;
                        box-shadow:0 3px 10px color-mix(in srgb,var(--primary) 28%,transparent); }
.rpos-cnt      { display:inline-flex;align-items:center;justify-content:center;
                 min-width:17px;height:17px;padding:0 4px;border-radius:999px;
                 font-size:9px;font-weight:800;line-height:1;
                 background:color-mix(in srgb,var(--border) 60%,transparent);color:var(--muted);
                 transition:background .18s,color .18s; }
.rpos-cat-btn.active .rpos-cnt { background:rgba(255,255,255,.25);color:#fff; }
.rpos-cat-btn:hover:not(.active) .rpos-cnt { background:color-mix(in srgb,var(--primary) 12%,transparent);
                                              color:var(--primary); }

/* ── Menu item cards ── */
.pos-products  { display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;
                 align-content:start; }

.pos-product   { border:1px solid var(--border);border-radius:12px;background:var(--bg);
                 cursor:pointer;text-align:left;display:flex;flex-direction:column;
                 align-self:start;overflow:hidden;padding:0;
                 transition:border-color .18s,transform .15s,box-shadow .18s; }
.pos-product:hover { border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
                     transform:translateY(-2px);
                     box-shadow:0 6px 18px color-mix(in srgb,var(--primary) 10%,transparent); }
.pos-product:active { transform:translateY(0); }
.pos-product.is-disabled { opacity:.45;cursor:not-allowed;transform:none !important;
                            box-shadow:none !important;pointer-events:none; }

/* Thumbnail */
.pos-product__thumb { width:100%;aspect-ratio:16/10;position:relative;overflow:hidden;flex-shrink:0;
                      display:flex;align-items:center;justify-content:center;
                      background:linear-gradient(145deg,
                        color-mix(in srgb,var(--primary) 9%,var(--border)),
                        color-mix(in srgb,var(--primary) 3%,var(--bg))); }
.pos-product__thumb-icon { font-size:24px;
                            color:color-mix(in srgb,var(--primary) 30%,var(--muted));
                            transition:transform .2s; }
.pos-product:hover .pos-product__thumb-icon { transform:scale(1.12); }
.pos-product__prep { position:absolute;bottom:6px;right:6px;display:flex;align-items:center;gap:3px;
                     padding:2px 7px;border-radius:999px;font-size:9px;font-weight:700;
                     background:rgba(0,0,0,.42);color:#fff;backdrop-filter:blur(4px); }
.pos-product__unavail { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                         background:rgba(0,0,0,.35);font-size:10px;font-weight:800;color:#fff;
                         letter-spacing:.5px;text-transform:uppercase; }

/* Body */
.pos-product__body  { padding:9px 10px 10px;display:flex;flex-direction:column;gap:3px;flex:1; }
.pos-product__name  { font-size:12px;font-weight:800;color:var(--text);line-height:1.35;
                      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
.pos-product__tags  { font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.3px; }
.pos-product__footer { display:flex;align-items:center;justify-content:space-between;margin-top:5px; }
.pos-product__price { font-size:13px;font-weight:900;color:var(--primary);line-height:1; }
.pos-product__add   { width:24px;height:24px;border-radius:7px;display:flex;align-items:center;
                      justify-content:center;font-size:11px;flex-shrink:0;
                      background:color-mix(in srgb,var(--primary) 12%,transparent);
                      color:var(--primary);transition:background .15s,color .15s; }
.pos-product:hover .pos-product__add { background:var(--primary);color:#fff; }

/* ── Cart ── */
.pos-cart-empty { padding:28px 16px;text-align:center;color:var(--muted);font-size:12px;
                  border:1.5px dashed var(--border);border-radius:12px; }

/* Card-style row */
.pos-cart-row  { display:flex;flex-direction:column;gap:0;flex-shrink:0;
                 border:1px solid var(--border);border-radius:12px;
                 overflow:hidden;background:var(--bg);
                 transition:box-shadow .25s,background .35s,border-color .35s; }
.pos-cart-row:hover { box-shadow:0 2px 10px rgba(0,0,0,.07); }

/* Coloured accent strip top */
.pos-cart-row__accent { height:3px;width:100%;flex-shrink:0; }

/* Main content area */
.pos-cart-row__body { display:flex;align-items:flex-start;gap:10px;padding:10px 12px 8px; }
.pos-cart-row__info { flex:1;min-width:0; }
.pos-cart-row__head { display:flex;align-items:center;gap:6px;margin-bottom:2px;flex-wrap:wrap; }
.pos-cart-row__badge { display:inline-flex;align-items:center;gap:3px;padding:2px 7px;
                        border-radius:999px;font-size:8px;font-weight:800;
                        text-transform:uppercase;letter-spacing:.4px;color:#fff;flex-shrink:0; }
.pos-cart-row__name { font-size:13px;font-weight:800;line-height:1.25;color:var(--text); }
.pos-cart-row__price { font-size:11px;color:var(--muted);margin-top:1px; }
.pos-cart-row__price strong { color:var(--text);font-weight:800; }

/* Qty controls column */
.pos-cart-row__ctrl { display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0; }

/* Note + action footer */
.pos-cart-row__foot { display:flex;align-items:center;gap:6px;padding:0 12px 10px;flex-wrap:wrap; }
.pos-cart-row__note { flex:1;min-width:90px;padding:5px 9px;font-size:11px;
                       border-radius:8px;border:1px solid var(--border);
                       background:color-mix(in srgb,var(--border) 18%,var(--bg));
                       color:var(--text);outline:none;transition:border-color .15s; }
.pos-cart-row__note:focus { border-color:var(--primary);background:var(--bg); }
.pos-cart-row__note::placeholder { color:var(--muted); }
.pos-cart-row__action { display:inline-flex;align-items:center;gap:5px;padding:5px 11px;
                         border-radius:8px;border:none;font-size:10px;font-weight:800;
                         cursor:pointer;white-space:nowrap;transition:opacity .15s,transform .1s;
                         color:#fff;flex-shrink:0; }
.pos-cart-row__action:hover { opacity:.85;transform:translateY(-1px); }
.pos-cart-row__action:active { transform:translateY(0); }
.pos-cart-row__done { display:inline-flex;align-items:center;gap:4px;padding:5px 10px;
                       font-size:10px;font-weight:800;border-radius:8px;
                       background:color-mix(in srgb,#06b6d4 10%,transparent);color:#06b6d4; }

/* Cart section headers */
.pos-cart-section-head { display:flex;align-items:center;gap:7px;padding:4px 2px 2px;
                          font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;
                          color:var(--muted);flex-shrink:0; }
.pos-cart-section-dot  { width:6px;height:6px;border-radius:50%;flex-shrink:0; }

/* Qty controls */
.qty-btn  { width:26px;height:26px;border:1px solid var(--border);border-radius:8px;
            background:var(--bg);color:var(--text);cursor:pointer;
            display:flex;align-items:center;justify-content:center;font-size:11px; }
.qty-btn:hover { background:var(--primary);border-color:var(--primary);color:#fff; }
.qty-num  { min-width:22px;text-align:center;font-size:13px;font-weight:900; }
.del-btn  { width:26px;height:26px;border:1px solid color-mix(in srgb,#ef4444 30%,var(--border));
            border-radius:8px;background:transparent;color:#f87171;cursor:pointer;
            display:flex;align-items:center;justify-content:center;font-size:11px; }
.del-btn:hover { background:color-mix(in srgb,#ef4444 10%,transparent);border-color:#ef4444; }

/* ── Totals ── */
.pos-totals     { padding:10px 14px;border-top:2px solid var(--border); }
.pos-totals__row { display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-bottom:4px; }
.pos-totals__row--grand { font-size:15px;font-weight:900;color:var(--text);margin-bottom:0; }

/* ── Checkout fields ── */
.pos-field     { margin-bottom:10px; }
.pos-field label { display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px; }
.pos-field input,.pos-field textarea { width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;
  border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text); }
.pos-field input:focus,.pos-field textarea:focus { outline:none;border-color:var(--primary); }

/* ── Session info card ── */
.rpos-sess-info { border-radius:10px;border:1px solid var(--border);padding:10px 12px;margin-bottom:12px;
                  display:flex;align-items:center;gap:10px;background:color-mix(in srgb,var(--primary) 4%,var(--bg)); }
.rpos-sess-info-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;
                        background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);font-size:14px;flex-shrink:0; }

/* Override left column width to be narrower */
@media (min-width:960px) {
  .pos-three-panel { grid-template-columns: minmax(260px,300px) minmax(0,1fr) minmax(260px,310px); }
}

/* Panel helpers — all three panels fill the fixed-height grid row */
.pos-three-panel__left,.pos-three-panel__center,.pos-three-panel__right { display:flex;flex-direction:column;min-height:0;overflow:hidden;height:100%; }

/* Cart scroll container */
.pos-cart-list { display:flex;flex-direction:column;flex:1;min-height:0;overflow-y:auto;padding:8px 10px;gap:6px; }

.rpos-panel-head { padding:11px 14px;border-bottom:1px solid var(--border);
                   background:color-mix(in srgb,var(--primary) 4%,var(--bg));flex-shrink:0;
                   display:flex;align-items:center;justify-content:space-between; }
.rpos-panel-head h2 { margin:0;font-size:13px;font-weight:800; }
.rpos-catalog-body { flex:1;min-height:0;display:flex;flex-direction:column;padding:10px 12px;overflow:hidden; }
.rpos-checkout-scroll { flex:1;min-height:0;overflow-y:auto;padding:12px 14px; }
.rpos-sec-head  { font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;
                  margin:0 0 8px;display:flex;align-items:center;gap:8px; }
.rpos-sec-head::before,.rpos-sec-head::after { content:'';flex:1;border-top:1px solid var(--border); }
</style>

@php
  $activeTables   = $tables->where('status', '!=', 'inactive');
  $tblStatusColor = ['available' => '#22c55e', 'occupied' => '#ef4444', 'reserved' => '#f59e0b'];
  $totalMenuItems = $categories->sum(fn($c) => $c->menuItems->count());
  $totalItems     = $totalMenuItems + $products->count();
  $productGroups  = $products->groupBy(fn($p) => $p->categories->first()?->name ?? 'Products');

  /* Build JS session data for each tab */
  $sessionsPhp = [];
  foreach ($activeTables as $tbl) {
      $sessionsPhp['tbl_' . $tbl->id] = [
          'tableId'   => (string) $tbl->id,
          'tableName' => $tbl->name,
          'tableCap'  => $tbl->capacity,
          'tblStatus' => $tbl->status,
          'orderType' => 'dine_in',
          'icon'      => 'fa-chair',
          'label'     => $tbl->name,
      ];
  }
  $sessionsPhp['takeaway'] = ['tableId'=>'','tableName'=>'','tableCap'=>0,'tblStatus'=>'','orderType'=>'takeaway','icon'=>'fa-bag-shopping','label'=>'Takeaway'];
  $sessionsPhp['delivery'] = ['tableId'=>'','tableName'=>'','tableCap'=>0,'tblStatus'=>'','orderType'=>'delivery','icon'=>'fa-motorcycle','label'=>'Delivery'];
@endphp

{{-- Toast notification --}}
<div id="rposToast" style="
  position:fixed;bottom:28px;right:28px;z-index:9999;
  display:none;align-items:center;gap:10px;
  padding:13px 18px;border-radius:12px;
  background:var(--bg);border:1px solid var(--border);
  box-shadow:0 8px 28px rgba(0,0,0,.14);
  font-size:13px;font-weight:600;color:var(--text);
  animation:rpos-fade-in .2s ease;
  max-width:320px;">
  <span id="rposToastIcon" style="font-size:16px;"></span>
  <span id="rposToastMsg"></span>
</div>

<form method="POST" action="{{ route('restaurant.orders.store') }}"
      id="orderForm" onsubmit="return submitOrder()">
@csrf
<input type="hidden" name="order_type" id="orderTypeInput" value="dine_in">
<input type="hidden" name="table_id"   id="tableIdInput"   value="">

{{-- ── Sticky header (top bar + session tabs) ── --}}
<div class="rpos-sticky">

{{-- Top bar --}}
<div class="rpos-top">
  <a href="{{ route('restaurant.orders.index') }}" class="rpos-back">
    <i class="fa fa-arrow-left"></i>
  </a>
  <div style="flex-shrink:0;">
    <div style="font-size:14px;font-weight:800;line-height:1;">New Order</div>
    <div style="font-size:11px;color:var(--muted);">Restaurant</div>
  </div>
  <div class="rpos-search">
    <input type="search" id="menuSearch"
           placeholder="Search by name or SKU…"
           oninput="filterMenu(this.value)" autocomplete="off">
  </div>

  {{-- Action buttons --}}
  <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;position:relative;">

    {{-- Settings dropdown --}}
    <div style="position:relative;">
      <button type="button" class="rpos-action-btn" id="settingsBtn"
              onclick="toggleSettingsMenu()" title="Settings">
        <i class="fa fa-gear"></i>
      </button>
      <div id="settingsMenu" class="rpos-settings-menu" style="display:none;">
        <a href="{{ route('restaurant.tables.index') }}" class="rpos-settings-item">
          <i class="fa fa-chair"></i> Tables
        </a>
        <a href="{{ route('restaurant.menu.items.index') }}" class="rpos-settings-item">
          <i class="fa fa-utensils"></i> Menu Items
        </a>
        <a href="{{ route('restaurant.menu.categories.index') }}" class="rpos-settings-item">
          <i class="fa fa-list"></i> Categories
        </a>
        <a href="{{ route('restaurant.reservations.index') }}" class="rpos-settings-item">
          <i class="fa fa-calendar-check"></i> Reservations
        </a>
        <div class="rpos-settings-divider"></div>
        <a href="{{ route('restaurant.orders.index') }}" class="rpos-settings-item">
          <i class="fa fa-receipt"></i> All Orders
        </a>
      </div>
    </div>

    {{-- Kitchen monitor --}}
    <a href="{{ route('restaurant.kitchen') }}" target="_blank"
       class="rpos-action-btn" title="Kitchen Monitor"
       style="display:flex;align-items:center;gap:5px;padding:0 10px;width:auto;font-size:12px;font-weight:700;">
      <i class="fa fa-fire-burner"></i>
      <span>Kitchen</span>
    </a>

    {{-- Fullscreen --}}
    <button type="button" class="rpos-action-btn" id="fullscreenBtn"
            onclick="toggleFullscreen()" title="Toggle fullscreen">
      <i class="fa fa-expand" id="fullscreenIcon"></i>
    </button>

  </div>
</div>

{{-- ── Table / session tabs ── --}}
<div class="rpos-tabs-wrap">

  {{-- Table tabs --}}
  @forelse($activeTables as $tbl)
    @php
      $dotColor    = $tblStatusColor[$tbl->status] ?? '#9ca3af';
      $statusLabel = ['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved'][$tbl->status] ?? ucfirst($tbl->status);
    @endphp
    <button type="button"
            class="rpos-tab{{ $loop->first ? ' active' : '' }}"
            id="tab_tbl_{{ $tbl->id }}"
            style="--tab-color:{{ $dotColor }};"
            onclick="switchSession('tbl_{{ $tbl->id }}')">
      <div class="rpos-tab__top">
        <span class="rpos-tab__dot" style="background:{{ $dotColor }};"></span>
        <span class="rpos-tab__name">{{ $tbl->name }}</span>
        <span class="rpos-tab__badge" id="badge_tbl_{{ $tbl->id }}" style="display:none;">0</span>
      </div>
      <div class="rpos-tab__sub">{{ $tbl->capacity }}p &middot; {{ $statusLabel }}</div>
    </button>
  @empty
    <span style="padding:8px 14px;font-size:12px;color:var(--muted);font-style:italic;align-self:center;">No tables</span>
  @endforelse

  {{-- Separator --}}
  @if($activeTables->isNotEmpty())
    <div class="rpos-tab-sep"></div>
  @endif

  {{-- Takeaway --}}
  <button type="button"
          class="rpos-tab{{ $activeTables->isEmpty() ? ' active' : '' }}"
          id="tab_takeaway" style="--tab-color:#f97316;"
          onclick="switchSession('takeaway')">
    <div class="rpos-tab__top">
      <span class="rpos-tab__dot" style="background:#f97316;"></span>
      <span class="rpos-tab__name">Takeaway</span>
      <span class="rpos-tab__badge" id="badge_takeaway" style="display:none;">0</span>
    </div>
    <div class="rpos-tab__sub">Walk-in order</div>
  </button>

  {{-- Delivery --}}
  <button type="button" class="rpos-tab" id="tab_delivery"
          style="--tab-color:#8b5cf6;"
          onclick="switchSession('delivery')">
    <div class="rpos-tab__top">
      <span class="rpos-tab__dot" style="background:#8b5cf6;"></span>
      <span class="rpos-tab__name">Delivery</span>
      <span class="rpos-tab__badge" id="badge_delivery" style="display:none;">0</span>
    </div>
    <div class="rpos-tab__sub">To address</div>
  </button>

</div>{{-- /rpos-tabs-wrap --}}
</div>{{-- /rpos-sticky --}}

@if($totalItems === 0)
  <div style="text-align:center;padding:60px 20px;border-radius:14px;border:2px dashed var(--border);">
    <i class="fa fa-utensils" style="font-size:38px;color:var(--muted);opacity:.3;display:block;margin-bottom:14px;"></i>
    <h3 style="margin:0 0 6px;font-size:15px;font-weight:700;">No menu items or products yet</h3>
    <p style="margin:0 0 14px;font-size:13px;color:var(--muted);">Add items to your menu first.</p>
    <a href="{{ route('restaurant.menu.items.index') }}" class="linkbtn"
       style="padding:8px 20px;font-size:13px;text-decoration:none;">
      <i class="fa fa-plus"></i> Add menu items
    </a>
  </div>
@else

{{-- ── Three-panel layout ── --}}
<div class="pos-layout pos-three-panel" id="posThreePanel"
     style="height:calc(100vh - var(--rpos-hdr-h,130px) - 42px);margin-top:14px;overflow:hidden;">

  {{-- LEFT — Current Order / Cart ── --}}
  <aside class="pos-three-panel__left"
         style="border:1px solid var(--border);border-radius:12px;background:var(--bg);">
    <div class="rpos-panel-head">
      <h2 id="cartPanelTitle">Current Order</h2>
      <span id="cartItemCount" style="font-size:11px;font-weight:700;color:var(--muted);">0 items</span>
    </div>
    <div class="pos-cart-list" id="cartItems">
      <p class="pos-cart-empty" id="cartEmpty">
        <i class="fa fa-utensils" style="display:block;font-size:22px;opacity:.25;margin-bottom:8px;"></i>
        Tap menu items to add them
      </p>
    </div>

  </aside>

  {{-- CENTER — Menu Catalog ── --}}
  <section class="pos-three-panel__center"
           style="border:1px solid var(--border);border-radius:12px;background:var(--bg);">
    <div class="rpos-catalog-body">

      {{-- Category tabs --}}
      <div class="rpos-cat-bar">
        <button type="button" class="rpos-cat-btn active" id="cat_all"
                onclick="filterCat('all')">
          All <span class="rpos-cnt">{{ $totalItems }}</span>
        </button>
        @if($totalMenuItems > 0)
          <button type="button" class="rpos-cat-btn" id="cat_menu"
                  onclick="filterSource('menu')"
                  style="border-color:color-mix(in srgb,var(--primary) 35%,var(--border));">
            <i class="fa fa-utensils" style="font-size:9px;"></i> Menu<span class="rpos-cnt">{{ $totalMenuItems }}</span>
          </button>
          @foreach($categories as $cat)
            @if($cat->menuItems->isNotEmpty())
              <button type="button" class="rpos-cat-btn" id="cat_mc_{{ $cat->id }}"
                      onclick="filterCat('mc_{{ $cat->id }}')">
                {{ $cat->name }}<span class="rpos-cnt">{{ $cat->menuItems->count() }}</span>
              </button>
            @endif
          @endforeach
        @endif
        @if($products->isNotEmpty())
          @if($totalMenuItems > 0)
            <span style="width:1px;background:var(--border);margin:0 2px;align-self:stretch;"></span>
          @endif
          <button type="button" class="rpos-cat-btn" id="cat_products"
                  onclick="filterSource('product')"
                  style="border-color:color-mix(in srgb,#8b5cf6 35%,var(--border));color:#8b5cf6;">
            <i class="fa fa-box" style="font-size:9px;"></i> Products<span class="rpos-cnt">{{ $products->count() }}</span>
          </button>
          @foreach($productGroups as $grpName => $grpProducts)
            <button type="button" class="rpos-cat-btn" id="cat_pg_{{ \Illuminate\Support\Str::slug($grpName) }}"
                    onclick="filterCat('pg_{{ \Illuminate\Support\Str::slug($grpName) }}')">
              {{ $grpName }}<span class="rpos-cnt">{{ $grpProducts->count() }}</span>
            </button>
          @endforeach
        @endif
      </div>

      <div id="noResults"
           style="display:none;padding:24px 0;text-align:center;color:var(--muted);font-size:13px;">
        <i class="fa fa-magnifying-glass"
           style="display:block;font-size:20px;opacity:.3;margin-bottom:8px;"></i>
        No items match your search.
      </div>

      {{-- Each category gets its own inner grid — isolates row heights between sections --}}
      <div id="menuGrid" style="overflow-y:auto;flex:1;min-height:0;display:flex;flex-direction:column;gap:16px;padding-right:3px;padding-bottom:8px;">

        {{-- ── Menu item sections ── --}}
        @foreach($categories as $cat)
          @if($cat->menuItems->isNotEmpty())
            <div class="cat-section" data-cat="mc_{{ $cat->id }}" data-source="menu">
              <p class="rpos-sec-head">{{ $cat->name }}</p>
              <div class="pos-products" style="overflow:visible;">
                @foreach($cat->menuItems as $mi)
                  <button type="button"
                          class="pos-product cat-item{{ !$mi->is_available ? ' is-disabled' : '' }}"
                          data-cat="mc_{{ $cat->id }}"
                          data-source="menu"
                          data-name="{{ strtolower($mi->name) }}"
                          {{ !$mi->is_available ? 'disabled' : '' }}
                          onclick="addItem({{ $mi->id }},'{{ addslashes($mi->name) }}',{{ $mi->price }},'mi')">

                    <div class="pos-product__thumb">
                      @if($mi->imageFile)
                        <img src="{{ $mi->imageFile->publicUrl() }}" alt="{{ $mi->name }}"
                             style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                      @else
                        <i class="fa fa-utensils pos-product__thumb-icon"></i>
                      @endif
                      @if($mi->prep_time_minutes)
                        <div class="pos-product__prep">
                          <i class="fa fa-clock"></i> {{ $mi->prepLabel() }}
                        </div>
                      @endif
                      @if(!$mi->is_available)
                        <div class="pos-product__unavail">Unavailable</div>
                      @endif
                    </div>

                    <div class="pos-product__body">
                      <div class="pos-product__name">{{ $mi->name }}</div>
                      @if($mi->dietary_tags)
                        <div class="pos-product__tags">
                          {{ implode(' · ', array_map(fn($t) => str_replace('_',' ',$t), array_slice((array)$mi->dietary_tags,0,3))) }}
                        </div>
                      @endif
                      <div class="pos-product__footer">
                        <span class="pos-product__price">{{ $currency }}{{ number_format((float)$mi->price,2) }}</span>
                        <span class="pos-product__add"><i class="fa fa-plus"></i></span>
                      </div>
                    </div>

                  </button>
                @endforeach
              </div>
            </div>
          @endif
        @endforeach

        {{-- ── Product sections ── --}}
        @foreach($productGroups as $grpName => $grpProducts)
          @php $grpSlug = \Illuminate\Support\Str::slug($grpName); @endphp
          <div class="cat-section" data-cat="pg_{{ $grpSlug }}" data-source="product">
            <p class="rpos-sec-head" style="color:#8b5cf6;">
              <i class="fa fa-box" style="font-size:9px;opacity:.7;"></i> {{ $grpName }}
            </p>
            <div class="pos-products" style="overflow:visible;">
              @foreach($grpProducts as $prod)
                <button type="button"
                        class="pos-product cat-item"
                        data-cat="pg_{{ $grpSlug }}"
                        data-source="product"
                        data-name="{{ strtolower($prod->name) }}"
                        data-sku="{{ strtolower($prod->sku ?? '') }}"
                        onclick="addItem({{ $prod->id }},'{{ addslashes($prod->name) }}',{{ $prod->unit_price ?? 0 }},'pr')">

                  <div class="pos-product__thumb"
                       style="background:linear-gradient(145deg,color-mix(in srgb,#8b5cf6 9%,var(--border)),color-mix(in srgb,#8b5cf6 3%,var(--bg)));">
                    @if($prod->imageFile)
                      <img src="{{ $prod->imageFile->publicUrl() }}" alt="{{ $prod->name }}"
                           style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                    @else
                      <i class="fa fa-box pos-product__thumb-icon" style="color:color-mix(in srgb,#8b5cf6 35%,var(--muted));"></i>
                    @endif
                  </div>

                  <div class="pos-product__body">
                    <div class="pos-product__name">{{ $prod->name }}</div>
                    @if($prod->sku)
                      <div class="pos-product__tags">{{ $prod->sku }}</div>
                    @endif
                    <div class="pos-product__footer">
                      <span class="pos-product__price">{{ $currency }}{{ number_format((float)($prod->unit_price ?? 0),2) }}</span>
                      <span class="pos-product__add" style="background:color-mix(in srgb,#8b5cf6 12%,transparent);color:#8b5cf6;">
                        <i class="fa fa-plus"></i>
                      </span>
                    </div>
                  </div>

                </button>
              @endforeach
            </div>
          </div>
        @endforeach

      </div>

    </div>
  </section>

  {{-- RIGHT — Order Details ── --}}
  <aside class="pos-three-panel__right"
         style="border:1px solid var(--border);border-radius:12px;background:var(--bg);">
    <div class="rpos-panel-head">
      <h2>Order Details</h2>
    </div>
    <div class="rpos-checkout-scroll">

      {{-- Active session info --}}
      <div class="rpos-sess-info" id="sessInfoCard">
        <div class="rpos-sess-info-icon" id="sessInfoIcon">
          <i class="fa fa-chair"></i>
        </div>
        <div>
          <div id="sessInfoTitle"
               style="font-size:14px;font-weight:800;line-height:1.2;">—</div>
          <div id="sessInfoMeta"
               style="font-size:11px;color:var(--muted);margin-top:2px;">—</div>
        </div>
      </div>

      {{-- Customer --}}
      <div class="pos-field">
        <label><i class="fa fa-user" style="margin-right:3px;"></i>Customer Name</label>
        <input type="text" name="customer_name" id="customerNameInput"
               placeholder="Optional" maxlength="255" oninput="saveCurrent()">
      </div>
      <div class="pos-field">
        <label><i class="fa fa-phone" style="margin-right:3px;"></i>Phone</label>
        <input type="text" name="customer_phone" id="customerPhoneInput"
               placeholder="Optional" maxlength="30" oninput="saveCurrent()">
      </div>
      <div class="pos-field">
        <label><i class="fa fa-note-sticky" style="margin-right:3px;"></i>Notes</label>
        <textarea name="notes" id="notesInput" rows="2" maxlength="1000"
                  style="resize:vertical;"
                  placeholder="Special instructions…"
                  oninput="saveCurrent()"></textarea>
      </div>

    </div>

    {{-- Totals + Place Order --}}
    <div class="pos-totals">
      <div class="pos-totals__row">
        <span>Subtotal</span>
        <span id="cartSubtotal">{{ $currency }}0.00</span>
      </div>
      <div class="pos-totals__row pos-totals__row--grand">
        <span>Total</span>
        <span id="cartTotal" style="color:var(--primary);">{{ $currency }}0.00</span>
      </div>
    </div>
    <div style="padding:0 14px 14px;display:flex;flex-direction:column;gap:8px;">
      <button type="submit" id="placeOrderBtn" class="linkbtn"
              style="width:100%;padding:12px;font-size:14px;font-weight:700;border-radius:11px;
                     opacity:.5;cursor:not-allowed;display:flex;align-items:center;
                     justify-content:center;gap:8px;" disabled>
        <i class="fa fa-check-circle"></i> Place Order
      </button>
      <div id="orderValidationMsg"
           style="text-align:center;font-size:11px;color:#ef4444;margin-top:-4px;display:none;"></div>
      <button type="button" id="completeOrderBtn"
              onclick="completeOrder()"
              style="display:none;width:100%;padding:12px;border-radius:11px;border:none;
                     cursor:pointer;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;
                     font-size:14px;font-weight:800;align-items:center;justify-content:center;
                     gap:8px;box-shadow:0 3px 14px color-mix(in srgb,#22c55e 28%,transparent);
                     transition:opacity .15s,transform .1s;">
        <i class="fa fa-circle-check" style="font-size:15px;"></i> Complete Order
      </button>
    </div>
  </aside>

</div>{{-- /pos-three-panel --}}
@endif
</form>

{{-- ══════════════════════════════════════
     PAYMENT MODAL
══════════════════════════════════════ --}}
<style>
/* ── Modal overlay ── */
.pm-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;
              display:flex;align-items:center;justify-content:center;padding:16px;
              opacity:0;transition:opacity .2s; }
.pm-overlay.pm-open { opacity:1; }

/* ── Modal container ── */
.pm-modal { background:var(--bg);border-radius:18px;overflow:hidden;
            width:100%;max-width:820px;max-height:90vh;
            display:flex;flex-direction:column;
            box-shadow:0 24px 80px rgba(0,0,0,.22);
            transform:translateY(18px) scale(.97);transition:transform .22s,opacity .22s;opacity:0; }
.pm-overlay.pm-open .pm-modal { transform:none;opacity:1; }

/* ── Modal header ── */
.pm-header { display:flex;align-items:center;gap:10px;padding:16px 20px;
             border-bottom:1px solid var(--border);flex-shrink:0; }
.pm-header-icon { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#16a34a);
                   color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px; }
.pm-header h2 { margin:0;font-size:16px;font-weight:900;flex:1; }
.pm-header-close { width:32px;height:32px;border-radius:8px;border:1px solid var(--border);
                    background:var(--bg);cursor:pointer;display:flex;align-items:center;
                    justify-content:center;color:var(--muted);font-size:13px;transition:all .15s; }
.pm-header-close:hover { border-color:#ef4444;color:#ef4444; }

/* ── Body: two columns ── */
.pm-body { display:grid;grid-template-columns:1fr 1fr;flex:1;overflow:hidden; }
@media (max-width:620px) { .pm-body { grid-template-columns:1fr; } }

/* ── Left: order summary ── */
.pm-summary { border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden; }
.pm-summary-head { padding:12px 18px;font-size:10px;font-weight:800;text-transform:uppercase;
                    letter-spacing:.6px;color:var(--muted);border-bottom:1px solid var(--border);flex-shrink:0; }
.pm-summary-items { flex:1;overflow-y:auto;padding:8px 0; }
.pm-summary-row { display:flex;align-items:center;gap:10px;padding:8px 18px; }
.pm-summary-row:hover { background:color-mix(in srgb,var(--border) 10%,var(--bg)); }
.pm-summary-qty { width:26px;height:26px;border-radius:8px;display:flex;align-items:center;
                   justify-content:center;font-size:11px;font-weight:900;
                   background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);flex-shrink:0; }
.pm-summary-name { flex:1;font-size:12px;font-weight:700;min-width:0;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.pm-summary-price { font-size:12px;font-weight:800;color:var(--text);flex-shrink:0; }
.pm-summary-foot { padding:12px 18px;border-top:2px solid var(--border);flex-shrink:0; }
.pm-summary-total { display:flex;align-items:baseline;justify-content:space-between; }
.pm-summary-total-label { font-size:12px;font-weight:700;color:var(--muted); }
.pm-summary-total-amt { font-size:22px;font-weight:900;color:var(--primary); }

/* ── Right: payment panel ── */
.pm-payment { display:flex;flex-direction:column;padding:14px 18px;gap:12px;overflow-y:auto; }

/* Payment method tabs */
.pm-methods { display:flex;gap:6px; }
.pm-method { flex:1;padding:8px 4px;border-radius:10px;border:1.5px solid var(--border);
             background:var(--bg);color:var(--muted);font-size:11px;font-weight:800;
             cursor:pointer;transition:all .15s;display:flex;flex-direction:column;
             align-items:center;gap:4px; }
.pm-method:hover { border-color:var(--primary);color:var(--primary); }
.pm-method.active { border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,var(--bg));
                    color:var(--primary);box-shadow:0 2px 10px color-mix(in srgb,var(--primary) 18%,transparent); }
.pm-method i { font-size:16px; }

/* Amount display */
.pm-amount-wrap { background:color-mix(in srgb,var(--border) 18%,var(--bg));border-radius:12px;
                   padding:10px 16px;text-align:right; }
.pm-amount-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
                    color:var(--muted);text-align:left;margin-bottom:4px; }
.pm-amount-value { font-size:28px;font-weight:900;color:var(--text);letter-spacing:-.5px;
                    min-height:38px;word-break:break-all; }

/* Quick amounts */
.pm-quick { display:flex;gap:5px;flex-wrap:wrap; }
.pm-quick-btn { flex:1;min-width:calc(25% - 4px);padding:6px 4px;border-radius:8px;
                 border:1px solid var(--border);background:var(--bg);
                 font-size:10px;font-weight:800;cursor:pointer;text-align:center;
                 color:var(--muted);transition:all .15s;white-space:nowrap; }
.pm-quick-btn:hover { border-color:var(--primary);color:var(--primary);
                        background:color-mix(in srgb,var(--primary) 6%,var(--bg)); }

/* Numpad */
.pm-numpad { display:grid;grid-template-columns:repeat(3,1fr);gap:6px; }
.pm-key { padding:13px 4px;border-radius:10px;border:1px solid var(--border);
           background:var(--bg);font-size:16px;font-weight:800;cursor:pointer;
           text-align:center;color:var(--text);transition:all .12s; }
.pm-key:hover { background:color-mix(in srgb,var(--primary) 8%,var(--bg));
                border-color:var(--primary);color:var(--primary); }
.pm-key:active { transform:scale(.95); }
.pm-key--del { color:#ef4444;border-color:color-mix(in srgb,#ef4444 25%,var(--border)); }
.pm-key--del:hover { background:color-mix(in srgb,#ef4444 8%,var(--bg));color:#ef4444; }
.pm-key--exact { grid-column:span 3;font-size:12px;
                  background:color-mix(in srgb,var(--primary) 6%,var(--bg));
                  color:var(--primary);border-color:color-mix(in srgb,var(--primary) 25%,var(--border)); }

/* Change display */
.pm-change-row { display:flex;align-items:center;justify-content:space-between;
                  padding:10px 14px;border-radius:10px;
                  background:color-mix(in srgb,#22c55e 8%,var(--bg));
                  border:1px solid color-mix(in srgb,#22c55e 20%,var(--border)); }
.pm-change-label { font-size:11px;font-weight:800;color:#16a34a; }
.pm-change-val { font-size:18px;font-weight:900;color:#16a34a; }

/* Confirm button */
.pm-confirm { width:100%;padding:14px;border-radius:12px;border:none;cursor:pointer;
               font-size:14px;font-weight:900;color:#fff;
               background:linear-gradient(135deg,#22c55e,#16a34a);
               box-shadow:0 4px 16px color-mix(in srgb,#22c55e 30%,transparent);
               display:flex;align-items:center;justify-content:center;gap:8px;
               transition:opacity .15s,transform .1s;flex-shrink:0; }
.pm-confirm:hover { opacity:.9;transform:translateY(-1px); }
.pm-confirm:disabled { opacity:.5;cursor:not-allowed;transform:none; }

/* ══════════════════════════════════════
   RECEIPT MODAL
══════════════════════════════════════ */
.receipt-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;
                    display:flex;align-items:center;justify-content:center;padding:16px; }
.receipt-modal { background:#fff;border-radius:14px;overflow:hidden;
                  width:100%;max-width:400px;max-height:90vh;
                  display:flex;flex-direction:column;
                  box-shadow:0 24px 80px rgba(0,0,0,.25); }
.receipt-actions { display:flex;gap:8px;padding:12px 16px;border-top:1px solid #e5e7eb;flex-shrink:0; }
.receipt-act-btn { flex:1;padding:10px;border-radius:9px;border:none;font-size:13px;font-weight:800;
                    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px; }
.receipt-act-btn--print { background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff; }
.receipt-act-btn--close { background:#f3f4f6;color:#374151;border:1px solid #e5e7eb; }

/* Thermal receipt content */
.thermal { padding:20px 16px;overflow-y:auto;flex:1;
            font-family:'Courier New',Courier,monospace;font-size:12px;color:#111;line-height:1.6; }
.thermal-center { text-align:center; }
.thermal-biz { font-size:16px;font-weight:900;letter-spacing:.5px;text-transform:uppercase;margin-bottom:2px; }
.thermal-sub  { font-size:11px;color:#555;margin-bottom:2px; }
.thermal-divider { border:none;border-top:1px dashed #aaa;margin:10px 0; }
.thermal-divider-solid { border:none;border-top:1px solid #333;margin:10px 0; }
.thermal-row { display:flex;align-items:flex-start;gap:6px;margin-bottom:2px; }
.thermal-row-qty { width:28px;flex-shrink:0;color:#555; }
.thermal-row-name { flex:1;min-width:0; }
.thermal-row-price { text-align:right;flex-shrink:0;font-weight:700; }
.thermal-total-row { display:flex;justify-content:space-between;margin-bottom:3px; }
.thermal-total-row--grand { font-size:14px;font-weight:900;border-top:1px solid #333;
                              padding-top:5px;margin-top:3px; }
.thermal-pay-row { display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px; }
.thermal-thanks { text-align:center;margin-top:14px;font-size:11px;color:#555; }
.thermal-thanks strong { display:block;font-size:13px;color:#111;margin-bottom:2px; }

/* Print: hide everything except the receipt panel */
@media print {
  body > * { display:none !important; }
  #receiptPrintArea { display:flex !important;flex-direction:column;position:fixed;inset:0;
                      border-radius:0;max-height:none;background:#fff;z-index:9999;
                      box-shadow:none;border:none; }
  .receipt-actions { display:none !important; }
  .thermal { overflow:visible;max-height:none;flex:1; }
}
</style>

{{-- Payment Modal --}}
<div class="pm-overlay" id="paymentOverlay" style="display:none;" onclick="pmOverlayClick(event)">
  <div class="pm-modal" id="paymentModal">
    <div class="pm-header">
      <div class="pm-header-icon"><i class="fa fa-credit-card"></i></div>
      <h2>Checkout</h2>
      <button class="pm-header-close" onclick="closePaymentModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="pm-body">

      {{-- Order summary --}}
      <div class="pm-summary">
        <div class="pm-summary-head"><i class="fa fa-receipt" style="margin-right:5px;"></i>Order Summary</div>
        <div class="pm-summary-items" id="pmItemsList"></div>
        <div class="pm-summary-foot">
          <div class="pm-summary-total">
            <span class="pm-summary-total-label">Total Amount</span>
            <span class="pm-summary-total-amt" id="pmTotalDisplay">0.00</span>
          </div>
        </div>
      </div>

      {{-- Payment panel --}}
      <div class="pm-payment">

        {{-- Method --}}
        <div class="pm-methods">
          <button class="pm-method active" data-method="cash" onclick="setPmMethod('cash',this)">
            <i class="fa fa-money-bills"></i> Cash
          </button>
          <button class="pm-method" data-method="card" onclick="setPmMethod('card',this)">
            <i class="fa fa-credit-card"></i> Card
          </button>
          <button class="pm-method" data-method="transfer" onclick="setPmMethod('transfer',this)">
            <i class="fa fa-building-columns"></i> Transfer
          </button>
        </div>

        {{-- Account selector (hidden, value still submitted) --}}
        @if($accounts->isNotEmpty())
        <select id="pmAccountSelect" style="display:none;">
          @foreach($accounts as $acc)
            <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
          @endforeach
        </select>
        @endif

        {{-- Amount received display --}}
        <div class="pm-amount-wrap">
          <div class="pm-amount-label">Amount Received</div>
          <div class="pm-amount-value" id="pmAmountDisplay">0.00</div>
        </div>

        {{-- Quick amounts --}}
        <div class="pm-quick" id="pmQuickAmounts"></div>

        {{-- Numpad --}}
        <div class="pm-numpad">
          @foreach([7,8,9,4,5,6,1,2,3] as $n)
            <button class="pm-key" onclick="pmKey('{{ $n }}')">{{ $n }}</button>
          @endforeach
          <button class="pm-key" onclick="pmKey('.')">.</button>
          <button class="pm-key" onclick="pmKey('0')">0</button>
          <button class="pm-key pm-key--del" onclick="pmKey('back')"><i class="fa fa-delete-left"></i></button>
          <button class="pm-key pm-key--exact" onclick="pmSetExact()">
            <i class="fa fa-equals" style="margin-right:4px;"></i> Exact Amount
          </button>
        </div>

        {{-- Change --}}
        <div class="pm-change-row" id="pmChangeRow">
          <span class="pm-change-label"><i class="fa fa-coins" style="margin-right:4px;"></i>Change</span>
          <span class="pm-change-val" id="pmChangeDisplay">0.00</span>
        </div>

        {{-- Confirm --}}
        <button class="pm-confirm" id="pmConfirmBtn" onclick="confirmPayment()">
          <i class="fa fa-circle-check" style="font-size:16px;"></i> Confirm Payment
        </button>

      </div>
    </div>
  </div>
</div>

{{-- Receipt Modal (overlay + modal are siblings so @media print can target #receiptPrintArea directly) --}}
<div id="receiptBg" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;"></div>
<div id="receiptPrintArea" class="receipt-modal"
     style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1200;">
  <div class="thermal" id="receiptContent"></div>
  <div class="receipt-actions">
    <button class="receipt-act-btn receipt-act-btn--print" onclick="printReceipt()">
      <i class="fa fa-print"></i> Print Bill
    </button>
    <button class="receipt-act-btn receipt-act-btn--close" onclick="closeReceipt()">
      <i class="fa fa-xmark"></i> Close
    </button>
  </div>
</div>

<script>
(function () {
  var currency = @json($currency ?: '');

  /* ─────────────────────────────────────────
     SESSION DATA  (pre-populated from PHP)
  ───────────────────────────────────────── */
  var sessionMeta = @json($sessionsPhp);
  var LS_KEY = 'rpos_sessions_{{ $business->id }}';

  /* ── Persist sessions to localStorage ── */
  function persistSessions() {
    try {
      var data = {};
      Object.keys(sessions).forEach(function (id) {
        data[id] = {
          customerName:  sessions[id].customerName,
          customerPhone: sessions[id].customerPhone,
          notes:         sessions[id].notes,
          cart:          sessions[id].cart,
        };
      });
      localStorage.setItem(LS_KEY, JSON.stringify(data));
    } catch(e) {}
  }

  /* ── Restore sessions from localStorage ── */
  function restoreSessions() {
    try {
      var raw = localStorage.getItem(LS_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      Object.keys(data).forEach(function (id) {
        if (sessions[id] && data[id]) {
          sessions[id].customerName  = data[id].customerName  || '';
          sessions[id].customerPhone = data[id].customerPhone || '';
          sessions[id].notes         = data[id].notes         || '';
          sessions[id].cart          = data[id].cart          || {};
        }
      });
    } catch(e) {}
  }

  // Runtime state (cart + form values), keyed by same id
  var sessions = {};
  Object.keys(sessionMeta).forEach(function (id) {
    sessions[id] = {
      customerName:  '',
      customerPhone: '',
      notes:         '',
      cart:          {},
    };
  });
  restoreSessions();

  /* refresh all tab badges from restored cart data */
  Object.keys(sessions).forEach(function (id) { updateBadge(id); });

  var activeId     = null;
  var currentCatId = 'all';

  /* ─────────────────────────────────────────
     SWITCH SESSION
  ───────────────────────────────────────── */

  function switchSession(id) {
    if (!sessions[id]) return;
    saveCurrent();
    activeId = id;
    try { sessionStorage.setItem('rpos_tab_{{ $business->id }}', id); } catch(e) {}

    // Update tab active state
    document.querySelectorAll('.rpos-tab').forEach(function (t) {
      t.classList.remove('active');
    });
    var activeTab = document.getElementById('tab_' + id);
    if (activeTab) activeTab.classList.add('active');

    // Update hidden order fields
    var meta = sessionMeta[id];
    document.getElementById('orderTypeInput').value = meta.orderType;
    document.getElementById('tableIdInput').value   = meta.tableId;

    // Update session info card
    var icons  = { 'dine_in': 'fa-chair', 'takeaway': 'fa-bag-shopping', 'delivery': 'fa-motorcycle' };
    var colors = { 'dine_in': null, 'takeaway': '#f97316', 'delivery': '#8b5cf6' };
    var statusColors = { available: '#22c55e', occupied: '#ef4444', reserved: '#f59e0b' };
    var statusLabels = { available: 'Available',  occupied: 'Occupied',  reserved: 'Reserved' };

    var accentColor = meta.orderType === 'dine_in'
      ? (statusColors[meta.tblStatus] || '#9ca3af')
      : (colors[meta.orderType] || 'var(--primary)');

    var card = document.getElementById('sessInfoCard');
    card.style.borderLeftColor = accentColor;
    card.style.borderLeftWidth = '3px';

    var iconEl = document.getElementById('sessInfoIcon');
    iconEl.innerHTML  = '<i class="fa ' + (icons[meta.orderType] || 'fa-receipt') + '"></i>';
    iconEl.style.background = 'color-mix(in srgb,' + accentColor + ' 14%,transparent)';
    iconEl.style.color      = accentColor;

    document.getElementById('sessInfoTitle').textContent = meta.label;

    if (meta.orderType === 'dine_in' && meta.tableCap) {
      var sc = statusColors[meta.tblStatus] || '#9ca3af';
      document.getElementById('sessInfoMeta').innerHTML =
        meta.tableCap + ' seats &nbsp;·&nbsp; ' +
        '<span style="display:inline-flex;align-items:center;gap:4px;">' +
          '<span style="width:6px;height:6px;border-radius:50%;background:' + sc + ';display:inline-block;"></span>' +
          (statusLabels[meta.tblStatus] || meta.tblStatus) +
        '</span>';
    } else {
      document.getElementById('sessInfoMeta').textContent =
        meta.orderType === 'takeaway' ? 'Walk-in / Takeaway' : 'Delivery to address';
    }

    // Update cart panel title
    document.getElementById('cartPanelTitle').textContent = meta.label;

    // Restore form fields
    var s = sessions[id];
    document.getElementById('customerNameInput').value  = s.customerName;
    document.getElementById('customerPhoneInput').value = s.customerPhone;
    document.getElementById('notesInput').value         = s.notes;

    renderCart();
  }

  window.switchSession = switchSession;

  /* ─────────────────────────────────────────
     SAVE CURRENT FORM FIELDS
  ───────────────────────────────────────── */

  window.saveCurrent = function () {
    if (!activeId || !sessions[activeId]) return;
    var s = sessions[activeId];
    s.customerName  = document.getElementById('customerNameInput').value;
    s.customerPhone = document.getElementById('customerPhoneInput').value;
    s.notes         = document.getElementById('notesInput').value;
    persistSessions();
  };

  /* ─────────────────────────────────────────
     BADGE UPDATE
  ───────────────────────────────────────── */

  function updateBadge(id) {
    var badge = document.getElementById('badge_' + id);
    if (!badge || !sessions[id]) return;
    var count = Object.keys(sessions[id].cart)
      .reduce(function (s, k) { return s + sessions[id].cart[k].qty; }, 0);
    badge.textContent   = count;
    badge.style.display = count > 0 ? '' : 'none';
  }

  /* ─────────────────────────────────────────
     CATEGORY TABS + SEARCH
  ───────────────────────────────────────── */

  window.filterCat = function (catId) {
    currentCatId = catId;
    document.querySelectorAll('.rpos-cat-btn').forEach(function (b) { b.classList.remove('active'); });
    var activeBtn = document.getElementById('cat_' + catId);
    if (activeBtn) activeBtn.classList.add('active');

    var isAll = catId === 'all';
    document.querySelectorAll('#menuGrid .cat-section').forEach(function (sec) {
      var match = isAll || sec.getAttribute('data-cat') === catId;
      sec.style.display = match ? '' : 'none';
      if (match) {
        sec.querySelectorAll('.cat-item').forEach(function (el) { el.style.display = ''; });
      }
    });
    var el = document.getElementById('menuSearch');
    if (el) el.value = '';
    document.getElementById('noResults').style.display = 'none';
  };

  /* Filter by source type: 'menu' or 'product' */
  window.filterSource = function (source) {
    currentCatId = source;
    document.querySelectorAll('.rpos-cat-btn').forEach(function (b) { b.classList.remove('active'); });
    var activeBtn = document.getElementById('cat_' + (source === 'menu' ? 'menu' : 'products'));
    if (activeBtn) activeBtn.classList.add('active');

    document.querySelectorAll('#menuGrid .cat-section').forEach(function (sec) {
      var match = sec.getAttribute('data-source') === source;
      sec.style.display = match ? '' : 'none';
      if (match) {
        sec.querySelectorAll('.cat-item').forEach(function (el) { el.style.display = ''; });
      }
    });
    var el = document.getElementById('menuSearch');
    if (el) el.value = '';
    document.getElementById('noResults').style.display = 'none';
  };

  window.filterMenu = function (q) {
    var term = q.trim().toLowerCase();
    if (!term) {
      if (currentCatId === 'menu' || currentCatId === 'product') {
        filterSource(currentCatId);
      } else {
        filterCat(currentCatId);
      }
      return;
    }

    document.querySelectorAll('.rpos-cat-btn').forEach(function (b) { b.classList.remove('active'); });
    document.getElementById('cat_all').classList.add('active');

    var any = false;
    document.querySelectorAll('#menuGrid .cat-section').forEach(function (sec) {
      var hasMatch = false;
      sec.querySelectorAll('.cat-item').forEach(function (el) {
        var match = el.dataset.name.indexOf(term) !== -1
                 || (el.dataset.sku && el.dataset.sku.indexOf(term) !== -1);
        el.style.display = match ? '' : 'none';
        if (match) { hasMatch = true; any = true; }
      });
      sec.style.display = hasMatch ? '' : 'none';
    });
    document.getElementById('noResults').style.display = any ? 'none' : 'block';
  };

  /* ─────────────────────────────────────────
     CART
  ───────────────────────────────────────── */

  /* ── Beep on item add ── */
  var _beepAudio = new Audio('/sounds/beep.wav');
  function beep() {
    try { _beepAudio.currentTime = 0; _beepAudio.play(); } catch(e) {}
  }

  /* ── Kitchen status update sound ── */
  var _bellAudio = new Audio('/sounds/bell_kitchen.mp3');
  function bellKitchen() {
    try { _bellAudio.currentTime = 0; _bellAudio.play(); } catch(e) {}
  }

  /* type: 'mi' = menu item, 'pr' = product */
  window.addItem = function (id, name, price, type) {
    if (!activeId) return;
    var t    = type || 'mi';
    var cart = sessions[activeId].cart;

    /* Find an existing NEW (not yet ordered) entry of the same type + id */
    var newKey = Object.keys(cart).find(function (k) {
      return cart[k].id == id && cart[k].type === t && cart[k].status !== 'ordered';
    });

    if (newKey) {
      cart[newKey].qty++;
    } else {
      var baseKey = t + '_' + id;
      var key = cart[baseKey] ? baseKey + '_' + Date.now() : baseKey;
      cart[key] = { id: id, name: name, price: parseFloat(price), qty: 1, notes: '', status: 'new', type: t };
    }

    beep();
    renderCart();
    updateBadge(activeId);
    persistSessions();
  };

  /* Backward-compat alias */
  window.addMenuItem = function (id, name, price) { window.addItem(id, name, price, 'mi'); };

  window.changeQty = function (key, delta) {
    if (!activeId) return;
    var cart = sessions[activeId].cart;
    if (!cart[key]) return;
    cart[key].qty = Math.max(0, cart[key].qty + delta);
    if (cart[key].qty === 0) delete cart[key];
    renderCart();
    updateBadge(activeId);
    persistSessions();
  };

  window.removeItem = function (key) {
    if (!activeId) return;
    var item = sessions[activeId].cart[key];

    /* If the item was sent to the kitchen, delete it server-side too */
    if (item && item.dbId && item.orderId) {
      fetch('/restaurant/orders/' + item.orderId + '/items/' + item.dbId, {
        method:  'DELETE',
        headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':csrf,
                   'X-Requested-With':'XMLHttpRequest' },
      }).catch(function(){}); /* fire-and-forget; cart removed locally regardless */
    }

    delete sessions[activeId].cart[key];
    renderCart();
    updateBadge(activeId);
    persistSessions();
  };

  window.setItemNote = function (key, val) {
    if (!activeId || !sessions[activeId].cart[key]) return;
    sessions[activeId].cart[key].notes = val;
    syncHiddenInputs();
    persistSessions();
  };

  var kitchenMeta = {
    pending:   { label:'Pending',   color:'#eab308', icon:'fa-clock',        next:'preparing', btn:'Start Cooking' },
    preparing: { label:'Preparing', color:'#8b5cf6', icon:'fa-fire-burner',  next:'ready',     btn:'Mark Ready' },
    ready:     { label:'Ready',     color:'#22c55e', icon:'fa-bell',         next:'served',    btn:'Mark Served' },
    served:    { label:'Served',    color:'#06b6d4', icon:'fa-check-circle', next:null,        btn:'' },
  };
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  window.updateKitchenStatus = function (key, orderId, itemId, newStatus) {
    var cart = sessions[activeId] ? sessions[activeId].cart : {};
    var item = cart[key]; if (!item) return;

    fetch('/restaurant/orders/' + orderId + '/items/' + itemId + '/status', {
      method:  'PATCH',
      headers: { 'Content-Type':'application/json','Accept':'application/json',
                 'X-CSRF-TOKEN': csrf, 'X-Requested-With':'XMLHttpRequest' },
      body: JSON.stringify({ status: newStatus }),
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.success) {
        item.kitchenStatus = data.status;
        renderCart();
        showToast('Item marked as ' + data.status, 'success');
      }
    });
  };

  function buildCartRow(key, item) {
    var isOrdered  = item.status === 'ordered';
    var isProduct  = item.type === 'pr';

    /* Products skip the kitchen chain — treat as already-served colour once ordered */
    var ks = isOrdered
      ? (isProduct ? item.kitchenStatus || 'served' : (item.kitchenStatus || 'pending'))
      : null;
    var km = (ks && !isProduct) ? (kitchenMeta[ks] || kitchenMeta.pending) : null;
    var servedColor = '#06b6d4';
    var accentColor = isOrdered && isProduct
      ? (item.kitchenStatus === 'served' ? servedColor : '#8b5cf6')
      : (km ? km.color : (isProduct ? '#8b5cf6' : 'var(--primary)'));

    /* Action button */
    var actionHtml = '';
    if (isOrdered && item.dbId && item.orderId) {
      if (isProduct) {
        /* Products: single direct "Serve" button, or done state */
        if (item.kitchenStatus === 'served') {
          actionHtml = '<span class="pos-cart-row__done">' +
            '<i class="fa fa-circle-check" style="font-size:10px;"></i> Served</span>';
        } else {
          actionHtml = '<button type="button" class="pos-cart-row__action" ' +
            'style="background:' + servedColor + ';" ' +
            'onclick="updateKitchenStatus(\'' + key + '\',' + item.orderId + ',' + item.dbId + ',\'served\')">' +
            '<i class="fa fa-circle-check" style="font-size:9px;"></i> Serve</button>';
        }
      } else if (km && km.next) {
        var nm = kitchenMeta[km.next];
        actionHtml = '<button type="button" class="pos-cart-row__action" ' +
          'style="background:' + nm.color + ';" ' +
          'onclick="updateKitchenStatus(\'' + key + '\',' + item.orderId + ',' + item.dbId + ',\'' + km.next + '\')">' +
          '<i class="fa ' + nm.icon + '" style="font-size:9px;"></i> ' + nm.label +
          '</button>';
      } else {
        actionHtml = '<span class="pos-cart-row__done">' +
          '<i class="fa fa-circle-check" style="font-size:10px;"></i> Served</span>';
      }
    }

    /* Badge */
    var badgeHtml = '';
    if (isOrdered && isProduct) {
      var prodBadgeColor = item.kitchenStatus === 'served' ? servedColor : '#8b5cf6';
      var prodBadgeIcon  = item.kitchenStatus === 'served' ? 'fa-circle-check' : 'fa-box';
      var prodBadgeLabel = item.kitchenStatus === 'served' ? 'Served' : 'Product';
      badgeHtml = '<span class="pos-cart-row__badge" style="background:' + prodBadgeColor + ';">' +
        '<i class="fa ' + prodBadgeIcon + '" style="font-size:7px;"></i> ' + prodBadgeLabel + '</span>';
    } else if (isOrdered && km) {
      badgeHtml = '<span class="pos-cart-row__badge" style="background:' + km.color + ';">' +
        '<i class="fa ' + km.icon + '" style="font-size:7px;"></i> ' + km.label + '</span>';
    } else if (isProduct) {
      badgeHtml = '<span class="pos-cart-row__badge" style="background:#8b5cf6;">' +
        '<i class="fa fa-box" style="font-size:7px;"></i> Product</span>';
    } else {
      badgeHtml = '<span class="pos-cart-row__badge" style="background:var(--primary);">' +
        '<i class="fa fa-circle-plus" style="font-size:7px;"></i> New</span>';
    }

    var row = document.createElement('div');
    row.className = 'pos-cart-row';
    if (isOrdered) {
      row.style.background  = 'color-mix(in srgb,' + accentColor + ' 7%,var(--bg))';
      row.style.borderColor = 'color-mix(in srgb,' + accentColor + ' 30%,var(--border))';
    }
    row.innerHTML =
      /* Accent strip */
      '<div class="pos-cart-row__accent" style="background:' + accentColor + ';"></div>' +

      /* Body: info + qty controls */
      '<div class="pos-cart-row__body">' +
        '<div class="pos-cart-row__info">' +
          '<div class="pos-cart-row__head">' + badgeHtml + '</div>' +
          '<div class="pos-cart-row__name">' + esc(item.name) + '</div>' +
          '<div class="pos-cart-row__price">' +
            currency + item.price.toFixed(2) + ' &times; ' + item.qty +
            ' = <strong>' + currency + (item.price * item.qty).toFixed(2) + '</strong>' +
          '</div>' +
        '</div>' +
        '<div class="pos-cart-row__ctrl">' +
          '<button type="button" class="del-btn" onclick="removeItem(\'' + key + '\')" title="Remove">' +
            '<i class="fa fa-times" style="font-size:10px;"></i></button>' +
          '<div style="display:flex;align-items:center;gap:3px;">' +
            '<button type="button" class="qty-btn" onclick="changeQty(\'' + key + '\',-1)">' +
              '<i class="fa fa-minus" style="font-size:9px;"></i></button>' +
            '<span class="qty-num">' + item.qty + '</span>' +
            '<button type="button" class="qty-btn" onclick="changeQty(\'' + key + '\',1)">' +
              '<i class="fa fa-plus" style="font-size:9px;"></i></button>' +
          '</div>' +
        '</div>' +
      '</div>' +

      /* Footer: note input + action button */
      '<div class="pos-cart-row__foot">' +
        '<input type="text" class="pos-cart-row__note" placeholder="Add note…" value="' + esc(item.notes) + '"' +
          ' oninput="setItemNote(\'' + key + '\',this.value)">' +
        actionHtml +
      '</div>';

    return row;
  }

  function makeSectionHead(label, cls, dotColor, count) {
    var div = document.createElement('div');
    div.className = 'pos-cart-section-head';
    div.innerHTML =
      '<span class="pos-cart-section-dot" style="background:' + dotColor + ';"></span>' +
      '<span style="color:' + dotColor + ';font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;">' + label + '</span>' +
      '<span style="margin-left:auto;font-size:10px;font-weight:700;color:var(--muted);">' + count + ' item' + (count !== 1 ? 's' : '') + '</span>';
    return div;
  }

  function renderCart() {
    var cart    = activeId ? sessions[activeId].cart : {};
    var keys    = Object.keys(cart);
    var ordered = keys.filter(function (k) { return cart[k].status === 'ordered'; });
    var newKeys = keys.filter(function (k) { return cart[k].status !== 'ordered'; });
    var total   = keys.reduce(function (s, k) { return s + cart[k].qty; }, 0);

    document.getElementById('cartItemCount').textContent =
      total + (total === 1 ? ' item' : ' items');

    var list  = document.getElementById('cartItems');
    var empty = document.getElementById('cartEmpty');
    /* Clear all dynamic rows and section headers */
    list.querySelectorAll('.pos-cart-row,.pos-cart-section-head').forEach(function (el) { el.remove(); });

    if (keys.length === 0) {
      empty.style.display = '';
      return;
    }

    empty.style.display = 'none';

    /* ── Ordered section ── */
    if (ordered.length > 0) {
      list.insertBefore(makeSectionHead('Sent to Kitchen', '', '#eab308', ordered.length), empty);
      ordered.forEach(function (key) {
        list.insertBefore(buildCartRow(key, cart[key]), empty);
      });
    }

    /* ── New / pending section ── */
    if (newKeys.length > 0) {
      list.insertBefore(makeSectionHead('New Items', '', 'var(--primary)', newKeys.length), empty);
      newKeys.forEach(function (key) {
        list.insertBefore(buildCartRow(key, cart[key]), empty);
      });
    }

    syncHiddenInputs();
    updateTotals();
    updatePlaceBtn();
  }

  function syncHiddenInputs() {
    document.querySelectorAll('.cart-hidden').forEach(function (el) { el.remove(); });
    if (!activeId) return;
    var cart = sessions[activeId].cart;
    var form = document.getElementById('orderForm');
    var idx  = 0;
    /* Only submit items that haven't been ordered yet */
    Object.keys(cart).forEach(function (key) {
      var item = cart[key];
      if (item.status === 'ordered') return;
      var isProduct = item.type === 'pr';
      addH(form, 'items[' + idx + '][' + (isProduct ? 'product_id' : 'menu_item_id') + ']', item.id || '');
      addH(form, 'items[' + idx + '][name]',         item.name);
      addH(form, 'items[' + idx + '][quantity]',     item.qty);
      addH(form, 'items[' + idx + '][unit_price]',   item.price.toFixed(2));
      addH(form, 'items[' + idx + '][notes]',        item.notes);
      idx++;
    });
  }

  function addH(form, name, value) {
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = name; i.value = value; i.className = 'cart-hidden';
    form.appendChild(i);
  }

  function updateTotals() {
    var cart = activeId ? sessions[activeId].cart : {};
    var sub  = Object.keys(cart).reduce(function (s, k) {
      return s + cart[k].price * cart[k].qty;
    }, 0);
    document.getElementById('cartSubtotal').textContent = currency + sub.toFixed(2);
    document.getElementById('cartTotal').textContent    = currency + sub.toFixed(2);
  }

  function updatePlaceBtn() {
    var cart     = activeId ? sessions[activeId].cart : {};
    var hasNew     = Object.keys(cart).some(function (k) { return cart[k].status !== 'ordered'; });
    var hasOrdered = Object.keys(cart).some(function (k) { return cart[k].status === 'ordered'; });
    var allServed  = hasOrdered && !hasNew && Object.keys(cart).every(function (k) {
      var it = cart[k];
      return it.status !== 'ordered' || it.kitchenStatus === 'served';
    });
    var btn = document.getElementById('placeOrderBtn');
    btn.disabled      = !hasNew;
    btn.style.opacity = hasNew ? '1' : '.5';
    btn.style.cursor  = hasNew ? 'pointer' : 'not-allowed';
    document.getElementById('orderValidationMsg').style.display = 'none';
    /* Show Complete Order only when every item has been served by kitchen */
    var completeBtn = document.getElementById('completeOrderBtn');
    if (completeBtn) completeBtn.style.display = allServed ? 'flex' : 'none';
  }

  /* ── Complete Order → open payment modal ── */
  window.completeOrder = function () {
    openPaymentModal();
  };

  /* ═══════════════════════════════════════════
     PAYMENT MODAL
  ═══════════════════════════════════════════ */
  var _pmMethod  = 'cash';
  var _pmAmount  = '';   // raw string entered on numpad
  var _pmTotal   = 0;
  var _pmOrderIds = [];

  window.openPaymentModal = function () {
    if (!activeId) return;
    var sess = sessions[activeId];
    var cart = sess ? sess.cart : {};
    var keys = Object.keys(cart);

    /* collect order IDs */
    _pmOrderIds = [];
    keys.forEach(function (k) {
      var it = cart[k];
      if (it.status === 'ordered' && it.orderId && _pmOrderIds.indexOf(it.orderId) === -1)
        _pmOrderIds.push(it.orderId);
    });
    if (!_pmOrderIds.length) return;

    /* calculate total */
    _pmTotal = keys.reduce(function (s, k) {
      var it = cart[k];
      return it.status === 'ordered' ? s + (it.price * it.qty) : s;
    }, 0);

    /* build summary list */
    var listEl = document.getElementById('pmItemsList');
    listEl.innerHTML = '';
    keys.forEach(function (k) {
      var it = cart[k];
      if (it.status !== 'ordered') return;
      var row = document.createElement('div');
      row.className = 'pm-summary-row';
      row.innerHTML =
        '<div class="pm-summary-qty">' + it.qty + '</div>' +
        '<div class="pm-summary-name">' + escHtml(it.name) + '</div>' +
        '<div class="pm-summary-price">' + currency + (it.price * it.qty).toFixed(2) + '</div>';
      listEl.appendChild(row);
    });

    document.getElementById('pmTotalDisplay').textContent = currency + _pmTotal.toFixed(2);

    /* reset state */
    _pmAmount = '';
    _pmMethod = 'cash';
    document.querySelectorAll('.pm-method').forEach(function (b) {
      b.classList.toggle('active', b.dataset.method === 'cash');
    });
    pmRefreshDisplay();
    pmBuildQuickAmounts();

    /* show overlay */
    var ov = document.getElementById('paymentOverlay');
    ov.style.display = 'flex';
    requestAnimationFrame(function () { ov.classList.add('pm-open'); });
  };

  window.closePaymentModal = function () {
    var ov = document.getElementById('paymentOverlay');
    ov.classList.remove('pm-open');
    setTimeout(function () { ov.style.display = 'none'; }, 220);
  };

  window.pmOverlayClick = function (e) {
    if (e.target === document.getElementById('paymentOverlay')) closePaymentModal();
  };

  window.setPmMethod = function (method, btn) {
    _pmMethod = method;
    document.querySelectorAll('.pm-method').forEach(function (b) {
      b.classList.toggle('active', b === btn);
    });
    pmRefreshDisplay();
  };

  window.pmKey = function (val) {
    if (val === 'back') {
      _pmAmount = _pmAmount.slice(0, -1);
    } else if (val === '.') {
      if (_pmAmount.indexOf('.') === -1) _pmAmount += (_pmAmount === '' ? '0.' : '.');
    } else {
      /* prevent more than 2 decimals */
      var dotIdx = _pmAmount.indexOf('.');
      if (dotIdx !== -1 && _pmAmount.length - dotIdx > 2) return;
      _pmAmount += val;
    }
    pmRefreshDisplay();
  };

  window.pmSetExact = function () {
    _pmAmount = _pmTotal.toFixed(2);
    pmRefreshDisplay();
  };

  function pmBuildQuickAmounts() {
    var el = document.getElementById('pmQuickAmounts');
    el.innerHTML = '';
    var suggestions = pmQuickSuggestions(_pmTotal);
    suggestions.forEach(function (amt) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pm-quick-btn';
      b.textContent = currency + amt.toFixed(2);
      b.onclick = function () { _pmAmount = amt.toFixed(2); pmRefreshDisplay(); };
      el.appendChild(b);
    });
  }

  function pmQuickSuggestions(total) {
    var base = Math.ceil(total);
    var results = [base];
    var steps = [5, 10, 20, 50, 100];
    steps.forEach(function (s) {
      var r = Math.ceil(total / s) * s;
      if (r !== base && results.indexOf(r) === -1) results.push(r);
    });
    results.sort(function (a, b) { return a - b; });
    return results.slice(0, 4);
  }

  function pmRefreshDisplay() {
    var received = parseFloat(_pmAmount) || 0;
    var change   = received - _pmTotal;

    document.getElementById('pmAmountDisplay').textContent =
      _pmAmount === '' ? '0.00' : _pmAmount;

    var changeEl  = document.getElementById('pmChangeDisplay');
    var changeRow = document.getElementById('pmChangeRow');
    if (change >= 0) {
      changeEl.textContent = currency + change.toFixed(2);
      changeRow.style.background = 'color-mix(in srgb,#22c55e 8%,var(--bg))';
      changeRow.style.borderColor = 'color-mix(in srgb,#22c55e 20%,var(--border))';
      changeEl.style.color = '#16a34a';
      document.querySelector('.pm-change-label').style.color = '#16a34a';
    } else {
      changeEl.textContent = '–' + currency + Math.abs(change).toFixed(2);
      changeRow.style.background = 'color-mix(in srgb,#ef4444 8%,var(--bg))';
      changeRow.style.borderColor = 'color-mix(in srgb,#ef4444 20%,var(--border))';
      changeEl.style.color = '#dc2626';
      document.querySelector('.pm-change-label').style.color = '#dc2626';
    }

    /* Disable confirm if cash and underpaid */
    var confirmBtn = document.getElementById('pmConfirmBtn');
    var underpaid = (_pmMethod === 'cash') && (received < _pmTotal) && (_pmAmount !== '');
    confirmBtn.disabled = underpaid;
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  window.confirmPayment = function () {
    var received = parseFloat(_pmAmount) || (_pmMethod !== 'cash' ? _pmTotal : 0);
    if (_pmMethod === 'cash' && received < _pmTotal) {
      showToast('Amount received is less than total.', 'error');
      return;
    }

    var confirmBtn = document.getElementById('pmConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing…';

    var accountSelect = document.getElementById('pmAccountSelect');
    var creditAccountId = accountSelect ? parseInt(accountSelect.value) || null : null;
    var amountTendered = _pmMethod === 'cash' ? received : _pmTotal;

    var done = 0;
    var failed = false;
    _pmOrderIds.forEach(function (orderId) {
      fetch('/restaurant/orders/' + orderId + '/complete', {
        method:  'POST',
        headers: { 'Content-Type':'application/json','Accept':'application/json',
                   'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest' },
        body: JSON.stringify({
          payment_method:    _pmMethod,
          credit_account_id: creditAccountId,
          amount_tendered:   amountTendered,
        }),
      })
      .then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok || !data.success) {
            var msg = (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                   || data.message
                   || 'Payment failed. Please check your settings and try again.';
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function () {
        if (++done === _pmOrderIds.length && !failed) {
          closePaymentModal();
          showReceipt(received);
          /* clear session cart */
          if (activeId && sessions[activeId]) {
            sessions[activeId].cart          = {};
            sessions[activeId].customerName  = '';
            sessions[activeId].customerPhone = '';
            sessions[activeId].notes         = '';
          }
          persistSessions();
          renderCart();
          updateBadge(activeId);
          updatePlaceBtn();
          /* immediately reflect available status on the tab */
          if (activeId && sessionMeta[activeId] && sessionMeta[activeId].tableId) {
            updateTabStatus(parseInt(sessionMeta[activeId].tableId), 'available');
          }
        }
      })
      .catch(function (err) {
        failed = true;
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fa fa-circle-check" style="font-size:16px;"></i> Confirm Payment';
        showToast(err.message || 'Network error — please try again.', 'error');
      });
    });
  };

  /* ═══════════════════════════════════════════
     THERMAL RECEIPT
  ═══════════════════════════════════════════ */
  function showReceipt(received) {
    if (!activeId) return;
    var sess = sessions[activeId] || {};
    var cart = sess.cart || {};
    var change = (parseFloat(received) || 0) - _pmTotal;
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
    var timeStr = now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
    var orderIds = _pmOrderIds.join(', #');
    var methodLabel = { cash:'Cash', card:'Card', transfer:'Bank Transfer' }[_pmMethod] || _pmMethod;
    var tableName = sess.tableName || '';
    var custName  = sess.customerName || '';
    var orderType = (sess.orderType || 'dine_in').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});

    var rows = '';
    Object.keys(cart).forEach(function (k) {
      var it = cart[k];
      if (it.status !== 'ordered') return;
      rows += '<div class="thermal-row">' +
        '<div class="thermal-row-qty">' + it.qty + 'x</div>' +
        '<div class="thermal-row-name">' + escHtml(it.name) + '</div>' +
        '<div class="thermal-row-price">' + currency + (it.price * it.qty).toFixed(2) + '</div>' +
        '</div>';
    });

    var receiptHtml =
      '<div class="thermal-center">' +
        '<div class="thermal-biz">{{ $business->name }}</div>' +
        '<div class="thermal-sub">Order #' + orderIds + '</div>' +
        '<div class="thermal-sub">' + dateStr + ' &middot; ' + timeStr + '</div>' +
      '</div>' +
      '<hr class="thermal-divider">' +
      (tableName ? '<div style="font-size:11px;margin-bottom:2px;"><strong>Table:</strong> ' + escHtml(tableName) + '</div>' : '') +
      (custName  ? '<div style="font-size:11px;margin-bottom:2px;"><strong>Customer:</strong> ' + escHtml(custName) + '</div>' : '') +
      '<div style="font-size:11px;margin-bottom:6px;"><strong>Type:</strong> ' + escHtml(orderType) + '</div>' +
      '<hr class="thermal-divider">' +
      '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;color:#555;">Items</div>' +
      rows +
      '<hr class="thermal-divider-solid">' +
      '<div class="thermal-total-row thermal-total-row--grand">' +
        '<span>TOTAL</span><span>' + currency + _pmTotal.toFixed(2) + '</span>' +
      '</div>' +
      '<hr class="thermal-divider">' +
      '<div class="thermal-pay-row"><span>Payment Method</span><span>' + escHtml(methodLabel) + '</span></div>' +
      '<div class="thermal-pay-row"><span>Amount Received</span><span>' + currency + parseFloat(received).toFixed(2) + '</span></div>' +
      (change > 0 ? '<div class="thermal-pay-row"><span>Change</span><span>' + currency + change.toFixed(2) + '</span></div>' : '') +
      '<hr class="thermal-divider">' +
      '<div class="thermal-thanks"><strong>Thank you!</strong>Visit us again soon.</div>';

    document.getElementById('receiptContent').innerHTML = receiptHtml;
    document.getElementById('receiptBg').style.display = 'block';
    document.getElementById('receiptPrintArea').style.display = 'flex';
  }

  window.closeReceipt = function () {
    document.getElementById('receiptBg').style.display = 'none';
    document.getElementById('receiptPrintArea').style.display = 'none';
  };

  window.printReceipt = function () {
    window.print();
  };

  /* ─────────────────────────────────────────
     TOAST
  ───────────────────────────────────────── */

  function showToast(message, type) {
    var toast   = document.getElementById('rposToast');
    var icon    = document.getElementById('rposToastIcon');
    var msg     = document.getElementById('rposToastMsg');
    var colors  = { success:'#22c55e', error:'#ef4444', info:'#3b82f6' };
    var icons   = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
    var t       = colors[type] ? type : 'success';
    icon.innerHTML  = '<i class="fa ' + icons[t] + '"></i>';
    icon.style.color = colors[t];
    msg.innerHTML    = message;
    toast.style.display = 'flex';
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toast.style.display = 'none'; }, 4000);
  }

  /* ─────────────────────────────────────────
     FORM SUBMISSION — AJAX, stay on page
  ───────────────────────────────────────── */

  window.submitOrder = function () {
    saveCurrent();
    if (!activeId) return false;

    var cart   = sessions[activeId].cart;
    var hasNew = Object.keys(cart).some(function (k) { return cart[k].status !== 'ordered'; });
    if (!hasNew) {
      var msg = document.getElementById('orderValidationMsg');
      msg.textContent   = 'Add new items before placing another order.';
      msg.style.display = 'block';
      return false;
    }

    var btn  = document.getElementById('placeOrderBtn');
    var form = document.getElementById('orderForm');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Placing…';

    var formData = new FormData(form);

    fetch(form.action, {
      method:  'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    formData,
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.success) {
        var cart    = sessions[activeId].cart;
        /* Grab new items in submission order (same as syncHiddenInputs) */
        var newKeys = Object.keys(cart).filter(function(k){ return cart[k].status !== 'ordered'; });
        newKeys.forEach(function(key, idx){
          cart[key].status        = 'ordered';
          cart[key].kitchenStatus = 'pending';
          if (data.items && data.items[idx]) {
            cart[key].dbId    = data.items[idx].id;
            cart[key].orderId = data.order_id;
          }
        });
        renderCart();
        persistSessions();
        showToast(data.message || 'Order placed!', 'success');
        /* immediately reflect occupied status on the tab */
        if (activeId && sessionMeta[activeId] && sessionMeta[activeId].tableId) {
          updateTabStatus(parseInt(sessionMeta[activeId].tableId), 'occupied');
        }
      } else {
        showToast(data.message || 'Something went wrong.', 'error');
      }
    })
    .catch(function () {
      showToast('Network error — please try again.', 'error');
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> Place Order';
      updatePlaceBtn();
    });

    return false; /* always prevent native submit */
  };

  /* ─────────────────────────────────────────
     HELPERS
  ───────────────────────────────────────── */

  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ── Init: restore last active tab, fall back to first ── */
  var _savedTab = null;
  try { _savedTab = sessionStorage.getItem('rpos_tab_{{ $business->id }}'); } catch(e) {}
  var _initId = (_savedTab && sessions[_savedTab]) ? _savedTab : Object.keys(sessionMeta)[0];
  if (_initId) switchSession(_initId);

  /* ── Settings dropdown ── */
  window.toggleSettingsMenu = function () {
    var menu = document.getElementById('settingsMenu');
    menu.style.display = menu.style.display === 'none' ? '' : 'none';
  };
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#settingsBtn') && !e.target.closest('#settingsMenu')) {
      var menu = document.getElementById('settingsMenu');
      if (menu) menu.style.display = 'none';
    }
  });

  /* ── Fullscreen toggle ── */
  window.toggleFullscreen = function () {
    var icon = document.getElementById('fullscreenIcon');
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(function () {});
    } else {
      document.exitFullscreen().catch(function () {});
    }
  };
  document.addEventListener('fullscreenchange', function () {
    var icon = document.getElementById('fullscreenIcon');
    if (!icon) return;
    icon.className = document.fullscreenElement ? 'fa fa-compress' : 'fa fa-expand';
  });

  /* ── Measure sticky header → CSS var drives three-panel height ── */
  function syncPanelHeight() {
    var hdr = document.querySelector('.rpos-sticky');
    if (!hdr) return;
    document.documentElement.style.setProperty('--rpos-hdr-h', hdr.offsetHeight + 'px');
  }
  /* Run after layout paint so offsetHeight is accurate */
  requestAnimationFrame(function () { syncPanelHeight(); });
  window.addEventListener('resize', syncPanelHeight);

  /* ── Real-time table status polling ── */
  var TABLE_STATUS_COLORS  = { available:'#22c55e', occupied:'#ef4444', reserved:'#f59e0b' };
  var TABLE_STATUS_LABELS  = { available:'Available', occupied:'Occupied', reserved:'Reserved' };

  function updateTabStatus(tableId, status) {
    var sessId = 'tbl_' + tableId;
    if (!sessionMeta[sessId]) return;
    var old = sessionMeta[sessId].tblStatus;
    if (old === status) return; /* no change */
    sessionMeta[sessId].tblStatus = status;
    var color = TABLE_STATUS_COLORS[status] || '#9ca3af';
    var label = TABLE_STATUS_LABELS[status] || status.charAt(0).toUpperCase() + status.slice(1);
    /* update tab dot + CSS var */
    var tab = document.getElementById('tab_' + sessId);
    if (tab) {
      tab.style.setProperty('--tab-color', color);
      var dot = tab.querySelector('.rpos-tab__dot');
      if (dot) dot.style.background = color;
      var sub = tab.querySelector('.rpos-tab__sub');
      if (sub) {
        var cap = sessionMeta[sessId].tableCap;
        sub.innerHTML = (cap ? cap + 'p &middot; ' : '') + label;
      }
    }
    /* re-render active session info card if this is the active tab */
    if (activeId === sessId) switchSession(sessId);
  }

  function pollTableStatuses() {
    fetch('/restaurant/tables/statuses', {
      headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      Object.keys(data).forEach(function(id) {
        updateTabStatus(parseInt(id), data[id]);
      });
    })
    .catch(function() {});
  }

  setInterval(pollTableStatuses, 10000);

  /* ── Real-time kitchen status polling ── */
  var POLL_INTERVAL = 5000;
  var statusLabels  = { pending:'Pending', preparing:'Preparing', ready:'Ready', served:'Served' };

  function highlightTab(sessId) {
    var tab = document.getElementById('tab_' + sessId);
    if (!tab) return;
    tab.classList.remove('rpos-tab--pulse');
    void tab.offsetWidth; /* reflow to restart animation */
    tab.classList.add('rpos-tab--pulse');
    /* remove class after animation completes so it can retrigger */
    setTimeout(function() { tab.classList.remove('rpos-tab--pulse'); }, 2000);
  }

  function pollKitchenStatuses() {
    /* collect tracked items across ALL sessions (not just active) */
    var tracked = []; /* {dbId, sessId, key} */
    Object.keys(sessions).forEach(function(sessId) {
      var cart = sessions[sessId] ? sessions[sessId].cart : {};
      Object.keys(cart).forEach(function(k) {
        var item = cart[k];
        if (item.status === 'ordered' && item.dbId) {
          tracked.push({ dbId: item.dbId, sessId: sessId, key: k });
        }
      });
    });
    if (!tracked.length) return;

    var qs = tracked.map(function(t){ return 'ids[]=' + t.dbId; }).join('&');
    fetch('/restaurant/orders/item-statuses?' + qs, {
      headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var changedSessions = {};
      tracked.forEach(function(t) {
        var cartNow = sessions[t.sessId] ? sessions[t.sessId].cart : null;
        if (!cartNow || !cartNow[t.key]) return;
        var newStatus = data[t.dbId];
        if (newStatus && cartNow[t.key].kitchenStatus !== newStatus) {
          cartNow[t.key].kitchenStatus = newStatus;
          changedSessions[t.sessId] = true;
          /* toast + sound only for active session */
          if (t.sessId === activeId) {
            bellKitchen();
            var icon = kitchenMeta[newStatus] ? kitchenMeta[newStatus].icon : 'fa-circle';
            showToast(
              '<i class="fa ' + icon + '" style="margin-right:5px;"></i>' +
              esc(cartNow[t.key].name) + ' &rarr; ' + (statusLabels[newStatus] || newStatus),
              newStatus === 'ready' ? 'success' : 'info'
            );
          }
        }
      });
      /* update UI per changed session */
      Object.keys(changedSessions).forEach(function(sessId) {
        highlightTab(sessId);
        if (sessId === activeId) {
          saveCurrent(); renderCart(); updatePlaceBtn();
        }
      });
      if (Object.keys(changedSessions).length) persistSessions();
    })
    .catch(function() {}); /* silent — next poll retries */
  }

  setInterval(pollKitchenStatuses, POLL_INTERVAL);

})();
</script>
@endsection
