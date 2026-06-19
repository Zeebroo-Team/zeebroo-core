@extends('theme::layouts.app', [
    'title'           => 'Kitchen Monitor',
    'heading'         => 'Restaurant',
    'minimalAppShell' => true,
    'hideNavbar'      => true,
])

@section('content')
@php
  $statusMeta = [
    'pending'   => ['label'=>'Pending',   'color'=>'#eab308','icon'=>'fa-clock'],
    'preparing' => ['label'=>'Preparing', 'color'=>'#8b5cf6','icon'=>'fa-fire-burner'],
    'ready'     => ['label'=>'Ready',     'color'=>'#22c55e','icon'=>'fa-bell'],
    'served'    => ['label'=>'Served',    'color'=>'#06b6d4','icon'=>'fa-check-circle'],
  ];
  $statusOrder = ['pending','preparing','ready','served'];

  $counts = ['pending'=>0,'preparing'=>0,'ready'=>0,'served'=>0];
  foreach ($orders as $order) {
    foreach ($order->items as $item) {
      $s = $item->status ?? 'pending';
      if (isset($counts[$s])) $counts[$s]++;
    }
  }
@endphp
<style>
/* ── Layout ── */
.km-wrap { display:flex;flex-direction:column;height:calc(100vh - 56px);overflow:hidden;gap:0; }

/* ── Top bar ── */
.km-top { display:flex;align-items:center;justify-content:space-between;gap:12px;
          padding:14px 0 0;flex-shrink:0; }
.km-title { font-size:18px;font-weight:900;display:flex;align-items:center;gap:10px;letter-spacing:-.3px; }
.km-title-icon { width:36px;height:36px;border-radius:10px;display:flex;align-items:center;
                  justify-content:center;font-size:16px;
                  background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 20%,transparent),color-mix(in srgb,var(--primary) 8%,transparent));
                  color:var(--primary);border:1px solid color-mix(in srgb,var(--primary) 18%,var(--border)); }
.km-actions { display:flex;align-items:center;gap:6px; }
.km-btn { padding:7px 13px;border-radius:9px;border:1px solid var(--border);background:var(--bg);
          color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;
          display:inline-flex;align-items:center;gap:5px;text-decoration:none;
          transition:all .15s;white-space:nowrap; }
.km-btn:hover { border-color:var(--primary);color:var(--primary); }
.km-btn--danger { border-color:color-mix(in srgb,#ef4444 30%,var(--border));color:#ef4444; }
.km-btn--danger:hover { background:color-mix(in srgb,#ef4444 6%,var(--bg));border-color:#ef4444; }

/* ── Filter bar ── */
.km-filters { display:flex;align-items:center;gap:8px;padding:12px 0 0;
              overflow-x:auto;scrollbar-width:none;flex-shrink:0; }
.km-filters::-webkit-scrollbar { display:none; }
.km-filter { display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:12px;
             border:1.5px solid var(--border);background:var(--bg);cursor:pointer;
             font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap;transition:all .18s; }
.km-filter:hover { color:var(--text);border-color:color-mix(in srgb,var(--tab-color,var(--primary)) 40%,var(--border)); }
.km-filter.active { border-color:var(--tab-color,var(--primary));color:var(--tab-color,var(--primary));
                    background:color-mix(in srgb,var(--tab-color,var(--primary)) 7%,var(--bg));
                    box-shadow:0 2px 12px color-mix(in srgb,var(--tab-color,var(--primary)) 15%,transparent); }
.km-filter__icon { width:22px;height:22px;border-radius:6px;display:flex;align-items:center;
                    justify-content:center;font-size:10px;
                    background:color-mix(in srgb,var(--border) 40%,transparent);transition:all .18s; }
.km-filter.active .km-filter__icon { background:var(--tab-color,var(--primary));color:#fff; }
.km-filter__count { display:inline-flex;align-items:center;justify-content:center;
                    min-width:22px;height:20px;padding:0 5px;border-radius:999px;font-size:10px;font-weight:900;
                    background:color-mix(in srgb,var(--border) 50%,transparent);color:var(--muted);transition:all .18s; }
.km-filter.active .km-filter__count { background:var(--tab-color,var(--primary));color:#fff; }

/* ── Divider ── */
.km-divider { height:1px;background:var(--border);flex-shrink:0;margin:10px 0 0; }

/* ── Order grid ── */
.km-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;
           overflow-y:auto;flex:1;min-height:0;padding:14px 0 6px;align-content:start; }

/* ── Order card ── */
.km-card { border:1px solid var(--border);border-radius:16px;background:var(--bg);
           display:flex;flex-direction:column;overflow:hidden;
           box-shadow:0 1px 4px rgba(0,0,0,.04);
           transition:box-shadow .2s,opacity .25s,transform .25s; }
.km-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); }
.km-card.km-hidden { display:none; }
.km-card.km-clearing { opacity:0;transform:scale(.95);pointer-events:none; }

/* Coloured top strip */
.km-card__strip { height:4px;width:100%;flex-shrink:0; }

/* Card header — single compact row */
.km-card__head { padding:11px 12px 9px;display:flex;align-items:center;gap:8px;flex-wrap:nowrap; }
.km-card__order-num { display:inline-flex;align-items:center;gap:4px;flex-shrink:0;
                       padding:4px 9px;border-radius:8px;font-size:10px;font-weight:900;
                       background:color-mix(in srgb,var(--primary) 9%,transparent);color:var(--primary);
                       white-space:nowrap; }
.km-card__title { flex:1;min-width:0;font-size:13px;font-weight:900;color:var(--text);
                   white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.km-card__type { display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:6px;
                  font-size:10px;font-weight:800;flex-shrink:0;white-space:nowrap; }
.km-card__age { font-size:10px;font-weight:700;padding:3px 8px;border-radius:6px;
                white-space:nowrap;flex-shrink:0; }
.km-card__clear { width:24px;height:24px;border-radius:6px;border:1px solid var(--border);
                   background:var(--bg);cursor:pointer;flex-shrink:0;
                   display:flex;align-items:center;justify-content:center;
                   color:var(--muted);font-size:9px;transition:all .15s; }
.km-card__clear:hover { border-color:#ef4444;color:#ef4444;background:color-mix(in srgb,#ef4444 7%,var(--bg)); }

/* Sub-row: type + customer + items count */
.km-card__sub { display:flex;align-items:center;gap:6px;padding:0 12px 9px;flex-wrap:wrap;border-bottom:1px solid var(--border); }
.km-card__sub-item { font-size:10px;color:var(--muted);display:flex;align-items:center;gap:3px; }

/* Bulk bar */
.km-bulk { display:flex;align-items:center;gap:5px;padding:7px 14px;flex-wrap:wrap;
           background:color-mix(in srgb,var(--border) 14%,var(--bg));
           border-top:1px solid var(--border);border-bottom:1px solid var(--border);flex-shrink:0; }
.km-bulk__label { font-size:9px;font-weight:800;color:var(--muted);text-transform:uppercase;
                   letter-spacing:.6px;margin-right:3px; }
.km-bulk__btn { display:inline-flex;align-items:center;gap:4px;padding:4px 11px;
                 border-radius:8px;border:none;font-size:10px;font-weight:800;cursor:pointer;
                 transition:opacity .15s,transform .1s; }
.km-bulk__btn:hover { opacity:.82;transform:translateY(-1px); }

/* ── Item card ── */
.km-items { display:flex;flex-direction:column; }
.km-item  { display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;
            padding:11px 14px;border-bottom:1px solid var(--border);transition:background .15s; }
.km-item:last-child { border-bottom:none; }
.km-item:hover { background:color-mix(in srgb,var(--border) 8%,var(--bg)); }
.km-item.km-item--dimmed { opacity:.3; }

/* Qty bubble */
.km-item__qty { width:34px;height:34px;border-radius:10px;display:flex;align-items:center;
                 justify-content:center;font-size:15px;font-weight:900;flex-shrink:0;color:#fff; }

/* Item info */
.km-item__body { min-width:0; }
.km-item__name { font-size:12px;font-weight:800;line-height:1.3;color:var(--text);
                  word-break:break-word; }
.km-item__note { display:inline-flex;align-items:center;gap:4px;margin-top:3px;
                  font-size:10px;color:var(--muted);font-style:italic; }

/* Status + actions */
.km-item__actions { display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0; }
.km-item__status { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;
                    border-radius:999px;font-size:9px;font-weight:800;text-transform:uppercase;
                    letter-spacing:.4px;color:#fff;white-space:nowrap; }
.km-item__btns { display:flex;align-items:center;gap:3px;flex-wrap:nowrap; }
.km-item__btn { display:inline-flex;align-items:center;gap:3px;padding:4px 9px;border-radius:8px;
                 font-size:9px;font-weight:800;cursor:pointer;white-space:nowrap;
                 border:1.5px solid currentColor;background:transparent;
                 transition:background .15s,transform .1s; }
.km-item__btn:hover { transform:translateY(-1px); }
.km-item__btn:hover i { opacity:1; }
.km-item__btn:disabled { opacity:.45;cursor:not-allowed;transform:none; }
.km-done { display:inline-flex;align-items:center;gap:4px;padding:4px 9px;
            font-size:9px;font-weight:800;border-radius:8px;
            background:color-mix(in srgb,#06b6d4 10%,transparent);color:#06b6d4; }

/* Empty state */
.km-empty { grid-column:1/-1;display:flex;flex-direction:column;align-items:center;
             justify-content:center;padding:70px 20px;color:var(--muted); }
.km-empty__icon { width:72px;height:72px;border-radius:20px;display:flex;align-items:center;
                   justify-content:center;font-size:28px;margin-bottom:16px;
                   background:color-mix(in srgb,var(--border) 30%,var(--bg));opacity:.5; }
.km-empty h3 { margin:0 0 6px;font-size:17px;font-weight:900;color:var(--text); }
.km-empty p  { margin:0;font-size:13px; }

/* Auto-refresh bar */
.km-progress-wrap { height:3px;background:color-mix(in srgb,var(--border) 60%,transparent);flex-shrink:0; }
.km-progress-bar  { height:100%;background:var(--primary);transition:width 1s linear; }
</style>

<div class="km-wrap">

  {{-- Top bar --}}
  <div class="km-top">
    <div class="km-title">
      <div class="km-title-icon"><i class="fa fa-fire-burner"></i></div>
      Kitchen Monitor
    </div>
    <div class="km-actions">
      <span style="font-size:11px;color:var(--muted);font-weight:600;margin-right:2px;" id="lastRefresh">
        <i class="fa fa-rotate-right" style="font-size:10px;opacity:.5;"></i> 15s
      </span>
      <button class="km-btn km-btn--danger" id="clearAllServedBtn"
              onclick="clearAllServed(this)" style="display:none;">
        <i class="fa fa-broom"></i> Clear Served
      </button>
      <button class="km-btn km-btn--danger" onclick="clearAllOrders(this)">
        <i class="fa fa-trash-can"></i> Clear All
      </button>
      <button class="km-btn" onclick="location.reload()">
        <i class="fa fa-rotate-right"></i> Refresh
      </button>
      <a href="{{ route('restaurant.orders.create') }}" class="km-btn">
        <i class="fa fa-cash-register"></i> POS
      </a>
    </div>
  </div>

  {{-- Filter tabs --}}
  <div class="km-filters">
    @foreach(['pending'=>'#eab308','preparing'=>'#8b5cf6','ready'=>'#22c55e','served'=>'#06b6d4'] as $fk => $fc)
      @php $fm = $statusMeta[$fk]; @endphp
      <button class="km-filter {{ $fk === 'pending' ? 'active' : '' }}"
              style="--tab-color:{{ $fc }};"
              onclick="setFilter('{{ $fk }}',this)" data-filter="{{ $fk }}">
        <span class="km-filter__icon"><i class="fa {{ $fm['icon'] }}"></i></span>
        {{ $fm['label'] }}
        <span class="km-filter__count">{{ $counts[$fk] }}</span>
      </button>
    @endforeach
  </div>

  <div class="km-divider"></div>

  {{-- Order grid --}}
  <div class="km-grid" id="kmGrid">
    @forelse($orders as $order)
      @php
        $typeColors  = ['dine_in'=>'#3b82f6','takeaway'=>'#f97316','delivery'=>'#8b5cf6'];
        $typeIcons   = ['dine_in'=>'fa-chair','takeaway'=>'fa-bag-shopping','delivery'=>'fa-motorcycle'];
        $typeColor   = $typeColors[$order->order_type] ?? '#9ca3af';
        $typeIcon    = $typeIcons[$order->order_type]  ?? 'fa-receipt';
        $ageMin      = (int) $order->created_at->diffInMinutes(now());
        $ageLabel    = $ageMin < 1 ? 'Just now' : ($ageMin < 60 ? $ageMin.'m ago' : floor($ageMin/60).'h '.($ageMin%60).'m');
        $ageUrgent   = $ageMin >= 20;
        $ageMedium   = $ageMin >= 10 && !$ageUrgent;
        $ageColor    = $ageUrgent ? '#ef4444' : ($ageMedium ? '#f97316' : 'var(--muted)');
        $ageBackground = $ageUrgent ? 'color-mix(in srgb,#ef4444 10%,transparent)'
                       : ($ageMedium ? 'color-mix(in srgb,#f97316 10%,transparent)' : 'color-mix(in srgb,var(--border) 30%,transparent)');
        $itemStatuses = $order->items->pluck('status')->map(fn($s) => $s ?? 'pending')->toArray();

        /* Strip colour = most urgent status present */
        $urgencyOrder = ['pending','preparing','ready','served'];
        $stripStatus  = 'served';
        foreach ($urgencyOrder as $us) {
          if (in_array($us, $itemStatuses)) { $stripStatus = $us; break; }
        }
        $stripColor = $statusMeta[$stripStatus]['color'];
      @endphp

      <div class="km-card"
           id="order_{{ $order->id }}"
           data-statuses="{{ implode(',', $itemStatuses) }}">

        {{-- Coloured top strip --}}
        <div class="km-card__strip" style="background:{{ $stripColor }};"></div>

        {{-- Card header — single compact row --}}
        <div class="km-card__head">
          <span class="km-card__order-num">
            <i class="fa fa-receipt" style="font-size:9px;opacity:.7;"></i>
            #{{ $order->order_number ?? $order->id }}
          </span>
          <span class="km-card__title">
            @if($order->table)
              <i class="fa fa-chair" style="font-size:10px;color:var(--muted);margin-right:3px;"></i>{{ $order->table->name }}
            @else
              {{ $order->typeLabel() }}
            @endif
          </span>
          <span class="km-card__type"
                style="background:color-mix(in srgb,{{ $typeColor }} 11%,transparent);color:{{ $typeColor }};">
            <i class="fa {{ $typeIcon }}" style="font-size:9px;"></i> {{ $order->typeLabel() }}
          </span>
          <span class="km-card__age"
                style="background:{{ $ageBackground }};color:{{ $ageColor }};">
            <i class="fa fa-clock" style="font-size:9px;"></i> {{ $ageLabel }}
          </span>
          <button class="km-card__clear" title="Clear order"
                  onclick="clearOrder({{ $order->id }},this)">
            <i class="fa fa-xmark"></i>
          </button>
        </div>

        {{-- Sub-row: badges --}}
        <div class="km-card__sub">
          @if($order->customer_name)
            <span class="km-card__sub-item">
              <i class="fa fa-user" style="opacity:.5;"></i> {{ $order->customer_name }}
            </span>
            <span class="km-card__sub-item" style="opacity:.3;">·</span>
          @endif
          <span class="km-card__sub-item">
            <i class="fa fa-utensils" style="opacity:.5;"></i> {{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}
          </span>
        </div>

        {{-- Bulk action bar --}}
        <div class="km-bulk">
          <span class="km-bulk__label"><i class="fa fa-bolt" style="font-size:8px;margin-right:2px;"></i>All</span>
          @if(in_array('pending', $itemStatuses))
            <button class="km-bulk__btn"
                    style="background:color-mix(in srgb,#8b5cf6 11%,transparent);color:#8b5cf6;"
                    onclick="bulkUpdate({{ $order->id }},'preparing',this)">
              <i class="fa fa-fire-burner" style="font-size:9px;"></i> Preparing
            </button>
          @endif
          @if(in_array('pending', $itemStatuses) || in_array('preparing', $itemStatuses))
            <button class="km-bulk__btn"
                    style="background:color-mix(in srgb,#22c55e 11%,transparent);color:#22c55e;"
                    onclick="bulkUpdate({{ $order->id }},'ready',this)">
              <i class="fa fa-bell" style="font-size:9px;"></i> Ready
            </button>
          @endif
          @php $hasUnserved = count(array_filter($itemStatuses, fn($s) => $s !== 'served')) > 0; @endphp
          @if($hasUnserved)
            <button class="km-bulk__btn"
                    style="background:color-mix(in srgb,#06b6d4 11%,transparent);color:#06b6d4;"
                    onclick="bulkUpdate({{ $order->id }},'served',this)">
              <i class="fa fa-check-circle" style="font-size:9px;"></i> Served
            </button>
          @endif
        </div>

        {{-- Items --}}
        <div class="km-items">
          @foreach($order->items as $item)
            @php
              $st     = $item->status ?? 'pending';
              $sm     = $statusMeta[$st] ?? $statusMeta['pending'];
              $nextMap= ['pending'=>'preparing','preparing'=>'ready','ready'=>'served'];
              $nextSt = $nextMap[$st] ?? null;
              $nextSm = $nextSt ? $statusMeta[$nextSt] : null;
            @endphp
            <div class="km-item" id="item_{{ $item->id }}" data-status="{{ $st }}">

              {{-- Qty bubble coloured by status --}}
              <div class="km-item__qty" style="background:{{ $sm['color'] }};">
                {{ $item->quantity }}
              </div>

              {{-- Name + note --}}
              <div class="km-item__body">
                <div class="km-item__name" title="{{ $item->name }}">{{ $item->name }}</div>
                @if($item->notes)
                  <div class="km-item__note">
                    <i class="fa fa-note-sticky" style="font-size:9px;"></i> {{ $item->notes }}
                  </div>
                @endif
              </div>

              {{-- Status badge + single next-step button --}}
              <div class="km-item__actions">
                <span class="km-item__status" style="background:{{ $sm['color'] }};">
                  <i class="fa {{ $sm['icon'] }}" style="font-size:8px;"></i>
                  {{ $sm['label'] }}
                </span>
                @if($nextSt && $nextSm)
                  <button class="km-item__btn"
                          style="color:{{ $nextSm['color'] }};border-color:{{ $nextSm['color'] }};background:color-mix(in srgb,{{ $nextSm['color'] }} 8%,transparent);"
                          onclick="updateStatus({{ $order->id }},{{ $item->id }},'{{ $nextSt }}',this)">
                    <i class="fa {{ $nextSm['icon'] }}" style="font-size:8px;"></i>
                    {{ $nextSm['label'] }}
                  </button>
                @else
                  <span class="km-done">
                    <i class="fa fa-circle-check" style="font-size:10px;"></i> Done
                  </span>
                @endif
              </div>

            </div>
          @endforeach
        </div>

      </div>
    @empty
      <div class="km-empty">
        <div class="km-empty__icon"><i class="fa fa-fire-burner"></i></div>
        <h3>All quiet in the kitchen!</h3>
        <p>No active orders for today.</p>
      </div>
    @endforelse
  </div>

  {{-- Progress bar --}}
  <div class="km-progress-wrap">
    <div class="km-progress-bar" id="progressBar" style="width:100%;"></div>
  </div>

</div>

<script>
(function(){
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var INTERVAL = 15;
  var countdown = INTERVAL;
  var activeFilter = sessionStorage.getItem('km_filter') || 'pending';

  /* ── Filter ── */
  window.setFilter = function(filter, btn) {
    activeFilter = filter;
    sessionStorage.setItem('km_filter', filter);
    document.querySelectorAll('.km-filter').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    applyFilter();
  };

  function applyFilter() {
    document.querySelectorAll('.km-card[data-statuses]').forEach(function(card){
      var statuses = (card.dataset.statuses || '').split(',');
      var hasMatch = statuses.indexOf(activeFilter) !== -1;
      card.classList.toggle('km-hidden', !hasMatch);
      if (hasMatch) {
        card.querySelectorAll('.km-item').forEach(function(it){
          it.classList.toggle('km-item--dimmed', it.dataset.status !== activeFilter);
        });
      }
    });
    var visible = document.querySelectorAll('.km-card:not(.km-hidden)').length;
    var emptyEl = document.querySelector('.km-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? '' : 'none';
    checkClearAllBtn();
  }

  /* ── Single item status ── */
  window.updateStatus = function(orderId, itemId, newStatus, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:8px;"></i>';
    patch(orderId, itemId, newStatus, function(ok){
      if (ok) location.reload(); else { btn.disabled = false; btn.textContent = 'Retry'; }
    });
  };

  /* ── Bulk update ── */
  window.bulkUpdate = function(orderId, newStatus, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:9px;"></i>';
    var card  = document.getElementById('order_' + orderId);
    var items = card ? Array.from(card.querySelectorAll('.km-item[id]')) : [];
    if (!items.length) { location.reload(); return; }
    var done = 0;
    items.forEach(function(el){
      patch(orderId, el.id.replace('item_',''), newStatus, function(){
        if (++done === items.length) location.reload();
      });
    });
  };

  /* ── Clear single order ── */
  window.clearOrder = function(orderId, btn) {
    if (!confirm('Clear this order from the kitchen monitor?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:9px;"></i>';
    clearFetch(orderId, function(ok){
      if (ok) {
        var card = document.getElementById('order_' + orderId);
        if (card) { card.classList.add('km-clearing'); setTimeout(function(){ card.remove(); checkClearAllBtn(); }, 260); }
      } else { btn.disabled = false; btn.innerHTML = '<i class="fa fa-xmark"></i>'; }
    });
  };

  /* ── Clear all served ── */
  window.clearAllServed = function(btn) {
    var cards = Array.from(document.querySelectorAll('.km-card:not(.km-hidden)')).filter(function(c){
      return (c.dataset.statuses||'').split(',').every(function(s){ return s==='served'; });
    });
    if (!cards.length || !confirm('Clear '+cards.length+' served order(s)?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clearing…';
    var done = 0;
    cards.forEach(function(card){
      clearFetch(card.id.replace('order_',''), function(){
        card.classList.add('km-clearing');
        setTimeout(function(){ card.remove(); }, 260);
        if (++done===cards.length) { setTimeout(checkClearAllBtn,300); btn.disabled=false; btn.innerHTML='<i class="fa fa-broom"></i> Clear Served'; }
      });
    });
  };

  /* ── Clear all orders ── */
  window.clearAllOrders = function(btn) {
    var cards = Array.from(document.querySelectorAll('.km-card:not(.km-hidden):not(.km-clearing)'));
    if (!cards.length || !confirm('Clear all '+cards.length+' order(s)?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clearing…';
    var done = 0;
    cards.forEach(function(card){
      clearFetch(card.id.replace('order_',''), function(){
        card.classList.add('km-clearing');
        setTimeout(function(){ card.remove(); }, 260);
        if (++done===cards.length) { btn.disabled=false; btn.innerHTML='<i class="fa fa-trash-can"></i> Clear All'; setTimeout(checkClearAllBtn,300); }
      });
    });
  };

  function clearFetch(orderId, cb) {
    fetch('/restaurant/orders/'+orderId+'/clear',{
      method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
    }).then(function(r){return r.json();}).then(function(d){cb(d.success);}).catch(function(){cb(false);});
  }

  function patch(orderId, itemId, status, cb) {
    fetch('/restaurant/orders/'+orderId+'/items/'+itemId+'/status',{
      method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
      body:JSON.stringify({status:status}),
    }).then(function(r){return r.json();}).then(function(d){cb(d.success);}).catch(function(){cb(false);});
  }

  function checkClearAllBtn() {
    var btn = document.getElementById('clearAllServedBtn');
    if (!btn) return;
    var has = Array.from(document.querySelectorAll('.km-card:not(.km-hidden)')).some(function(c){
      return (c.dataset.statuses||'').split(',').every(function(s){ return s==='served'; });
    });
    btn.style.display = has ? '' : 'none';
  }
  checkClearAllBtn();

  /* ── Countdown ── */
  var bar = document.getElementById('progressBar');
  var refreshEl = document.getElementById('lastRefresh');
  setInterval(function(){
    countdown--;
    if (bar) bar.style.width = ((countdown/INTERVAL)*100)+'%';
    if (refreshEl) refreshEl.innerHTML = '<i class="fa fa-rotate-right" style="font-size:10px;opacity:.5;"></i> '+countdown+'s';
    if (countdown<=0) location.reload();
  }, 1000);

  /* Restore active filter button to match sessionStorage value, then apply */
  document.querySelectorAll('.km-filter').forEach(function(b){
    b.classList.toggle('active', b.dataset.filter === activeFilter);
  });
  applyFilter();

})();
</script>
@endsection
