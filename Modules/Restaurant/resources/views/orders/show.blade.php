@extends('theme::layouts.app', ['title' => $order->order_number, 'heading' => 'Restaurant'])

@section('content')
<style>
/* ── Layout ─────────────────────────────── */
.os-wrap   { max-width:100%; }
.os-grid   { display:grid; grid-template-columns:1fr 320px; gap:16px; align-items:start; }
@media (max-width:780px) { .os-grid { grid-template-columns:1fr; } }

/* ── Card ────────────────────────────────── */
.os-card   { background:var(--bg); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.os-card-head { padding:13px 18px; border-bottom:1px solid var(--border);
                display:flex; align-items:center; justify-content:space-between; gap:10px;
                background:color-mix(in srgb,var(--primary) 4%,var(--bg)); }
.os-card-head-title { font-size:12px; font-weight:800; text-transform:uppercase;
                       letter-spacing:.05em; color:var(--muted);
                       display:flex; align-items:center; gap:7px; }
.os-card-head-title i { color:var(--primary); font-size:13px; }
.os-card-body  { padding:16px 18px; }

/* ── Status badge ────────────────────────── */
.os-badge  { display:inline-flex; align-items:center; gap:5px;
             padding:4px 12px; border-radius:999px; font-size:12px; font-weight:800; }

/* ── Item status pill ────────────────────── */
.os-pill   { display:inline-flex; align-items:center; gap:4px;
             padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; }

/* ── Timeline ────────────────────────────── */
.os-tl     { display:flex; align-items:flex-start; gap:0; padding:4px 0; }
.os-tl-step { display:flex; flex-direction:column; align-items:center; flex:1; min-width:56px; position:relative; }
.os-tl-step::after { content:''; position:absolute; top:13px; left:calc(50% + 13px);
                      right:calc(-50% + 13px); height:2px; background:var(--border); z-index:0; }
.os-tl-step:last-child::after { display:none; }
.os-tl-step.line-done::after  { background:var(--primary); }
.os-tl-dot { width:28px; height:28px; border-radius:50%; border:2px solid var(--border);
              background:var(--bg); display:flex; align-items:center; justify-content:center;
              font-size:11px; z-index:1; color:var(--muted); flex-shrink:0; transition:all .2s; }
.os-tl-dot.done    { background:var(--primary); border-color:var(--primary); color:#fff; }
.os-tl-dot.active  { background:var(--bg); border-color:var(--primary); color:var(--primary);
                       box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 15%,transparent); }
.os-tl-dot.is-cancelled { background:#fee2e2; border-color:#ef4444; color:#ef4444; }
.os-tl-label { font-size:9px; font-weight:700; color:var(--muted); text-align:center;
                white-space:nowrap; margin-top:5px; }
.os-tl-label.done   { color:var(--text); }
.os-tl-label.active { color:var(--primary); }

/* ── Items table ─────────────────────────── */
.os-table  { width:100%; border-collapse:collapse; font-size:13px; }
.os-table thead th { padding:9px 14px; text-align:left; font-size:10px; font-weight:800;
                      text-transform:uppercase; letter-spacing:.05em; color:var(--muted);
                      background:color-mix(in srgb,var(--border) 25%,var(--bg));
                      border-bottom:1px solid var(--border); }
.os-table thead th:last-child { text-align:right; }
.os-table tbody td { padding:12px 14px; border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);
                      vertical-align:middle; }
.os-table tbody tr:last-child td { border-bottom:none; }
.os-table tfoot td { padding:9px 14px; font-size:13px; color:var(--muted); }
.os-table tfoot tr:last-child td { font-size:15px; font-weight:900; color:var(--text);
                                    border-top:2px solid var(--border); padding-top:11px; }

/* ── Meta rows ───────────────────────────── */
.os-meta-row  { display:flex; align-items:center; justify-content:space-between;
                 padding:10px 0; border-bottom:1px solid color-mix(in srgb,var(--border) 50%,transparent);
                 gap:12px; font-size:13px; }
.os-meta-row:last-child { border-bottom:none; padding-bottom:0; }
.os-meta-row:first-child { padding-top:0; }
.os-meta-label { color:var(--muted); font-weight:600; font-size:12px; display:flex; align-items:center; gap:6px; flex-shrink:0; }
.os-meta-label i { width:14px; text-align:center; color:var(--primary); }
.os-meta-val { font-weight:700; text-align:right; }

/* ── Action buttons ──────────────────────── */
.os-action { width:100%; padding:12px 16px; border-radius:11px; border:none; font-size:13px;
              font-weight:800; cursor:pointer; display:flex; align-items:center;
              justify-content:center; gap:7px; color:#fff; transition:opacity .15s,transform .1s; }
.os-action:hover { opacity:.88; transform:translateY(-1px); }
.os-action:active { transform:none; }
.os-ghost  { width:100%; padding:11px 16px; border-radius:11px; border:1.5px solid; font-size:13px;
              font-weight:800; cursor:pointer; background:transparent;
              display:flex; align-items:center; justify-content:center; gap:7px;
              transition:all .15s; }
.os-ghost:hover { filter:brightness(1.05); }

/* ── Sale info ───────────────────────────── */
.os-sale   { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:10px;
              border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));
              background:color-mix(in srgb,#22c55e 6%,var(--bg)); font-size:13px; }
</style>

@php
  $steps      = ['pending','preparing','ready','served','paid'];
  $stepLabels = ['Pending','Preparing','Ready','Served','Paid'];
  $stepIcons  = ['fa-hourglass-start','fa-fire-burner','fa-bell','fa-utensils','fa-circle-check'];
  $statusIdx  = array_search($order->status, $steps);
  $cancelled  = $order->status === 'cancelled';
  $typeIcons  = ['dine_in'=>'fa-chair','takeaway'=>'fa-bag-shopping','delivery'=>'fa-motorcycle'];

  $nextActions = [
    'pending'   => ['label'=>'Start Preparing', 'target'=>'preparing', 'bg'=>'#3b82f6', 'icon'=>'fa-fire-burner'],
    'preparing' => ['label'=>'Mark Ready',      'target'=>'ready',     'bg'=>'#8b5cf6', 'icon'=>'fa-bell'],
    'ready'     => ['label'=>'Mark Served',     'target'=>'served',    'bg'=>'#22c55e', 'icon'=>'fa-utensils'],
    'served'    => ['label'=>'Mark as Paid',    'target'=>'paid',      'bg'=>'#f59e0b', 'icon'=>'fa-circle-check'],
  ];
  $cancelable = in_array($order->status, ['pending','preparing','ready']);
  $deletable  = in_array($order->status, ['pending','cancelled']);

  $itemStatusMeta = [
    'pending'   => ['color'=>'#f59e0b','icon'=>'fa-clock'],
    'preparing' => ['color'=>'#3b82f6','icon'=>'fa-fire-burner'],
    'ready'     => ['color'=>'#8b5cf6','icon'=>'fa-bell'],
    'served'    => ['color'=>'#22c55e','icon'=>'fa-check'],
  ];
@endphp

<div class="os-wrap">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:14px;">{{ session('status') }}</div>
  @endif

  {{-- ── Top header bar ── --}}
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <a href="{{ route('restaurant.orders.index') }}"
         style="width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--bg);
                color:var(--muted);display:flex;align-items:center;justify-content:center;text-decoration:none;
                font-size:13px;flex-shrink:0;transition:border-color .15s,color .15s;"
         title="All orders">
        <i class="fa fa-arrow-left"></i>
      </a>
      <div>
        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;">
          <h1 style="margin:0;font-size:20px;font-weight:900;letter-spacing:-.4px;line-height:1;">
            {{ $order->order_number }}
          </h1>
          <span class="os-badge"
                style="background:color-mix(in srgb,{{ $order->statusColor() }} 14%,transparent);
                       color:{{ $order->statusColor() }};">
            <span style="width:6px;height:6px;border-radius:50%;background:{{ $order->statusColor() }};flex-shrink:0;"></span>
            {{ ucfirst($order->status) }}
          </span>
        </div>
        <div style="margin-top:4px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <span style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;">
            <i class="fa {{ $typeIcons[$order->order_type] ?? 'fa-receipt' }}"></i>
            {{ $order->typeLabel() }}
          </span>
          @if($order->table)
            <span style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;">
              <i class="fa fa-chair"></i> {{ $order->table->name }}
            </span>
          @endif
          <span style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;">
            <i class="fa fa-clock"></i>
            {{ $order->created_at->format('d M Y, H:i') }}
            <span style="opacity:.6;">({{ $order->created_at->diffForHumans() }})</span>
          </span>
        </div>
      </div>
    </div>

    {{-- Total amount callout --}}
    <div style="text-align:right;flex-shrink:0;padding:10px 18px;border-radius:12px;
                border:1px solid color-mix(in srgb,var(--primary) 25%,var(--border));
                background:color-mix(in srgb,var(--primary) 6%,var(--bg));">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
                  color:var(--primary);margin-bottom:2px;">Order Total</div>
      <div style="font-size:24px;font-weight:900;color:var(--text);letter-spacing:-.5px;line-height:1;">
        {{ $currency }}{{ number_format((float)$order->total, 2) }}
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px;">
        {{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}
      </div>
    </div>
  </div>

  {{-- ── Two-column grid ── --}}
  <div class="os-grid">

    {{-- LEFT — Items + Notes ── --}}
    <div style="display:flex;flex-direction:column;gap:14px;">

      {{-- Order Items table --}}
      <div class="os-card">
        <div class="os-card-head">
          <span class="os-card-head-title">
            <i class="fa fa-list-ul"></i> Order Items
          </span>
          <span style="font-size:12px;color:var(--muted);font-weight:600;">
            {{ $order->items->count() }} line{{ $order->items->count() !== 1 ? 's' : '' }}
          </span>
        </div>
        <table class="os-table">
          <thead>
            <tr>
              <th>Item</th>
              <th style="text-align:center;white-space:nowrap;">Qty</th>
              <th style="text-align:right;white-space:nowrap;">Unit Price</th>
              <th style="text-align:right;white-space:nowrap;">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->items as $item)
              <tr>
                <td>
                  <div style="font-weight:700;color:var(--text);">{{ $item->name }}</div>
                  @if($item->notes)
                    <div style="font-size:11px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:4px;">
                      <i class="fa fa-note-sticky" style="opacity:.5;font-size:10px;"></i>
                      {{ $item->notes }}
                    </div>
                  @endif
                  @if($item->status && $item->status !== 'pending')
                    @php $sm = $itemStatusMeta[$item->status] ?? null; @endphp
                    @if($sm)
                      <div style="margin-top:4px;">
                        <span class="os-pill"
                              style="background:color-mix(in srgb,{{ $sm['color'] }} 12%,transparent);
                                     color:{{ $sm['color'] }};">
                          <i class="fa {{ $sm['icon'] }}" style="font-size:9px;"></i>
                          {{ ucfirst($item->status) }}
                        </span>
                      </div>
                    @endif
                  @endif
                </td>
                <td style="text-align:center;">
                  <span style="display:inline-flex;align-items:center;justify-content:center;
                               width:28px;height:28px;border-radius:8px;font-size:13px;font-weight:900;
                               background:color-mix(in srgb,var(--primary) 10%,transparent);
                               color:var(--primary);">
                    {{ $item->quantity }}
                  </span>
                </td>
                <td style="text-align:right;color:var(--muted);white-space:nowrap;">
                  {{ $currency }}{{ number_format((float)$item->unit_price, 2) }}
                </td>
                <td style="text-align:right;font-weight:800;white-space:nowrap;">
                  {{ $currency }}{{ number_format($item->lineTotal(), 2) }}
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="text-align:right;color:var(--muted);">Subtotal</td>
              <td style="text-align:right;font-weight:700;">{{ $currency }}{{ number_format((float)$order->subtotal, 2) }}</td>
            </tr>
            @if((float)$order->discount_amount > 0)
              <tr>
                <td colspan="3" style="text-align:right;color:#ef4444;">Discount</td>
                <td style="text-align:right;font-weight:700;color:#ef4444;">
                  – {{ $currency }}{{ number_format((float)$order->discount_amount, 2) }}
                </td>
              </tr>
            @endif
            <tr>
              <td colspan="3" style="text-align:right;color:var(--text);">Total</td>
              <td style="text-align:right;color:var(--primary);">
                {{ $currency }}{{ number_format((float)$order->total, 2) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      {{-- Notes --}}
      @if($order->notes)
        <div class="os-card">
          <div class="os-card-head">
            <span class="os-card-head-title"><i class="fa fa-note-sticky"></i> Notes</span>
          </div>
          <div class="os-card-body" style="font-size:13px;line-height:1.6;color:var(--text);">
            {{ $order->notes }}
          </div>
        </div>
      @endif

      {{-- Linked sale --}}
      @if($order->sale)
        <div class="os-sale">
          <div style="width:34px;height:34px;border-radius:9px;background:color-mix(in srgb,#22c55e 14%,transparent);
                      color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">
            <i class="fa fa-cash-register"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#16a34a;margin-bottom:1px;">
              Payment Recorded
            </div>
            <div style="font-size:13px;font-weight:800;color:var(--text);">
              POS Sale — {{ $order->sale->sale_number }}
            </div>
            @if($order->sale->payment_method)
              <div style="font-size:11px;color:var(--muted);margin-top:1px;">
                {{ ucfirst($order->sale->payment_method) }}
                @if($order->sale->sold_at)
                  &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($order->sale->sold_at)->format('d M Y, H:i') }}
                @endif
              </div>
            @endif
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:16px;font-weight:900;color:#16a34a;">
              {{ $currency }}{{ number_format((float)$order->sale->total, 2) }}
            </div>
            <div style="font-size:10px;color:var(--muted);margin-top:1px;font-weight:600;">Settled</div>
          </div>
        </div>
      @endif

    </div>{{-- /left --}}

    {{-- RIGHT — Status + Info + Actions ── --}}
    <div style="display:flex;flex-direction:column;gap:14px;">

      {{-- Status timeline --}}
      <div class="os-card">
        <div class="os-card-head">
          <span class="os-card-head-title"><i class="fa fa-signal"></i> Status</span>
        </div>
        <div class="os-card-body">
          @if($cancelled)
            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;
                        background:#fee2e2;border:1px solid color-mix(in srgb,#ef4444 25%,transparent);">
              <span style="width:30px;height:30px;border-radius:50%;background:#fecaca;
                           border:2px solid #ef4444;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa fa-ban" style="color:#ef4444;font-size:12px;"></i>
              </span>
              <div>
                <div style="font-size:13px;font-weight:800;color:#b91c1c;">Order Cancelled</div>
                <div style="font-size:11px;color:#ef4444;margin-top:1px;">This order was cancelled</div>
              </div>
            </div>
          @else
            <div class="os-tl">
              @foreach($steps as $i => $step)
                @php
                  $isDone   = $statusIdx !== false && $i < $statusIdx;
                  $isActive = $statusIdx !== false && $i === $statusIdx;
                @endphp
                <div class="os-tl-step {{ $isDone ? 'line-done' : '' }}">
                  <div class="os-tl-dot {{ $isDone ? 'done' : ($isActive ? 'active' : '') }}">
                    @if($isDone)
                      <i class="fa fa-check" style="font-size:10px;"></i>
                    @else
                      <i class="fa {{ $stepIcons[$i] }}" style="font-size:10px;"></i>
                    @endif
                  </div>
                  <span class="os-tl-label {{ $isDone ? 'done' : ($isActive ? 'active' : '') }}">
                    {{ $stepLabels[$i] }}
                  </span>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      {{-- Order info --}}
      <div class="os-card">
        <div class="os-card-head">
          <span class="os-card-head-title"><i class="fa fa-circle-info"></i> Details</span>
        </div>
        <div class="os-card-body" style="padding-top:12px;padding-bottom:12px;">

          <div class="os-meta-row">
            <span class="os-meta-label"><i class="fa fa-hashtag"></i> Order #</span>
            <span class="os-meta-val" style="font-family:monospace;font-size:13px;">{{ $order->order_number }}</span>
          </div>

          <div class="os-meta-row">
            <span class="os-meta-label"><i class="fa fa-{{ $typeIcons[$order->order_type] ?? 'receipt' }}"></i> Type</span>
            <span class="os-meta-val">{{ $order->typeLabel() }}</span>
          </div>

          @if($order->table)
            <div class="os-meta-row">
              <span class="os-meta-label"><i class="fa fa-chair"></i> Table</span>
              <span class="os-meta-val">{{ $order->table->name }}</span>
            </div>
          @endif

          @if($order->customer_name)
            <div class="os-meta-row">
              <span class="os-meta-label"><i class="fa fa-user"></i> Customer</span>
              <span class="os-meta-val">{{ $order->customer_name }}</span>
            </div>
          @endif

          @if($order->customer_phone)
            <div class="os-meta-row">
              <span class="os-meta-label"><i class="fa fa-phone"></i> Phone</span>
              <span class="os-meta-val" style="font-family:monospace;">{{ $order->customer_phone }}</span>
            </div>
          @endif

          <div class="os-meta-row">
            <span class="os-meta-label"><i class="fa fa-calendar"></i> Placed</span>
            <span class="os-meta-val" style="font-size:12px;text-align:right;">
              {{ $order->created_at->format('d M Y') }}<br>
              <span style="color:var(--muted);font-weight:600;">{{ $order->created_at->format('H:i') }}</span>
            </span>
          </div>

          @if($order->updated_at && $order->updated_at->ne($order->created_at))
            <div class="os-meta-row">
              <span class="os-meta-label"><i class="fa fa-pen"></i> Updated</span>
              <span class="os-meta-val" style="font-size:12px;color:var(--muted);">
                {{ $order->updated_at->diffForHumans() }}
              </span>
            </div>
          @endif

        </div>
      </div>

      {{-- Action buttons --}}
      @if(isset($nextActions[$order->status]) || $cancelable || $deletable)
        <div class="os-card">
          <div class="os-card-head">
            <span class="os-card-head-title"><i class="fa fa-bolt"></i> Actions</span>
          </div>
          <div class="os-card-body" style="display:flex;flex-direction:column;gap:8px;">

            @if(isset($nextActions[$order->status]))
              @php $act = $nextActions[$order->status]; @endphp
              <form method="POST" action="{{ route('restaurant.orders.transition', $order) }}">
                @csrf
                <input type="hidden" name="status" value="{{ $act['target'] }}">
                <button type="submit" class="os-action" style="background:{{ $act['bg'] }};">
                  <i class="fa {{ $act['icon'] }}"></i> {{ $act['label'] }}
                </button>
              </form>
            @endif

            @if($cancelable)
              <form method="POST" action="{{ route('restaurant.orders.transition', $order) }}"
                    onsubmit="return confirm('Cancel this order?')">
                @csrf
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="os-ghost" style="border-color:#ef4444;color:#ef4444;">
                  <i class="fa fa-ban"></i> Cancel Order
                </button>
              </form>
            @endif

            @if($deletable)
              <form method="POST" action="{{ route('restaurant.orders.destroy', $order) }}"
                    onsubmit="return confirm('Permanently delete this order?')">
                @csrf @method('DELETE')
                <button type="submit" class="os-ghost" style="border-color:var(--border);color:var(--muted);">
                  <i class="fa fa-trash"></i> Delete Order
                </button>
              </form>
            @endif

          </div>
        </div>
      @endif

    </div>{{-- /right --}}
  </div>{{-- /os-grid --}}
</div>
@endsection
