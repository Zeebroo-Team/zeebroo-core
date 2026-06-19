@extends('theme::layouts.app', ['title' => $order->order_number, 'heading' => 'Restaurant'])

@section('content')
<style>
.os-timeline       { display:flex; align-items:center; gap:0; margin-bottom:28px; overflow-x:auto; padding-bottom:4px; }
.os-step           { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; min-width:80px; position:relative; }
.os-step::after    { content:''; position:absolute; top:14px; left:calc(50% + 16px); right:calc(-50% + 16px);
                     height:2px; background:var(--border); z-index:0; }
.os-step:last-child::after { display:none; }
.os-dot            { width:30px; height:30px; border-radius:50%; border:2px solid var(--border); background:var(--bg);
                     display:flex; align-items:center; justify-content:center; font-size:12px; z-index:1; color:var(--muted); }
.os-dot.done       { background:var(--primary); border-color:var(--primary); color:#fff; }
.os-dot.active     { background:var(--bg); border-color:var(--primary); color:var(--primary); box-shadow:0 0 0 4px color-mix(in srgb,var(--primary) 15%,transparent); }
.os-dot.cancelled  { background:#fee2e2; border-color:#ef4444; color:#ef4444; }
.os-step.line-done::after { background:var(--primary); }
.os-step-label     { font-size:10px; font-weight:700; color:var(--muted); text-align:center; white-space:nowrap; }
.os-step-label.active { color:var(--primary); }
.os-step-label.done   { color:var(--text); }
.os-receipt        { border-radius:14px; border:1px solid var(--border); overflow:hidden; margin-bottom:20px; }
.os-receipt-item   { display:grid; grid-template-columns:1fr auto auto auto; gap:12px; align-items:center;
                     padding:13px 20px; border-bottom:1px solid var(--border); }
.os-action-btn     { flex:1; padding:13px 20px; border-radius:12px; border:none; font-size:14px; font-weight:700;
                     cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity .15s; }
.os-action-btn:hover { opacity:.88; }
.os-ghost-btn      { flex:1; padding:13px 20px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer;
                     display:flex; align-items:center; justify-content:center; gap:8px; border:2px solid var(--border);
                     background:transparent; color:var(--text); transition:all .15s; }
.os-ghost-btn:hover { border-color:var(--text); }
</style>

<div style="max-width:760px;">

  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:16px;">{{ session('status') }}</div>
  @endif

  {{-- ── Header card ── --}}
  <div style="background:var(--bg);border:1px solid var(--border);border-radius:16px;padding:20px 24px;margin-bottom:16px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
          <h1 style="margin:0;font-size:20px;font-weight:900;letter-spacing:-.3px;">{{ $order->order_number }}</h1>
          <span style="padding:5px 13px;border-radius:999px;font-size:12px;font-weight:800;
                background:color-mix(in srgb,{{ $order->statusColor() }} 18%,transparent);
                color:{{ $order->statusColor() }};">
            {{ ucfirst($order->status) }}
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
          @php
            $typeIcons = ['dine_in' => 'fa-chair', 'takeaway' => 'fa-bag-shopping', 'delivery' => 'fa-motorcycle'];
          @endphp
          <span style="font-size:13px;color:var(--muted);">
            <i class="fa {{ $typeIcons[$order->order_type] ?? 'fa-receipt' }}" style="margin-right:4px;"></i>
            {{ $order->typeLabel() }}
          </span>
          @if($order->table)
            <span style="font-size:13px;color:var(--muted);">
              <i class="fa fa-chair" style="margin-right:4px;"></i>{{ $order->table->name }}
            </span>
          @endif
          @if($order->customer_name)
            <span style="font-size:13px;color:var(--muted);">
              <i class="fa fa-user" style="margin-right:4px;"></i>{{ $order->customer_name }}
              @if($order->customer_phone)
                <span style="margin-left:4px;opacity:.7;">{{ $order->customer_phone }}</span>
              @endif
            </span>
          @endif
          <span style="font-size:12px;color:var(--muted);">
            <i class="fa fa-clock" style="margin-right:4px;"></i>{{ $order->created_at->format('d M Y, H:i') }}
            <span style="opacity:.7;">({{ $order->created_at->diffForHumans() }})</span>
          </span>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:28px;font-weight:900;color:var(--primary);line-height:1;">
          {{ $currency }}{{ number_format((float)$order->total, 2) }}
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ $order->items->count() }} item{{ $order->items->count() !== 1 ? 's' : '' }}</div>
      </div>
    </div>
  </div>

  {{-- ── Status timeline ── --}}
  @php
    $steps = ['pending','preparing','ready','served','paid'];
    $statusIndex = array_search($order->status, $steps);
    $cancelled   = $order->status === 'cancelled';
    $stepLabels  = ['Pending','Preparing','Ready','Served','Paid'];
    $stepIcons   = ['fa-hourglass-start','fa-fire-burner','fa-bell','fa-utensils','fa-circle-check'];
  @endphp

  <div style="background:var(--bg);border:1px solid var(--border);border-radius:16px;padding:20px 24px;margin-bottom:16px;">
    @if($cancelled)
      <div style="display:flex;align-items:center;gap:10px;color:#ef4444;font-size:13px;font-weight:700;">
        <span style="width:30px;height:30px;border-radius:50%;background:#fee2e2;border:2px solid #ef4444;display:flex;align-items:center;justify-content:center;">
          <i class="fa fa-ban" style="font-size:12px;"></i>
        </span>
        This order was cancelled.
      </div>
    @else
      <div class="os-timeline">
        @foreach($steps as $i => $step)
          @php
            $isDone   = $statusIndex !== false && $i < $statusIndex;
            $isActive = $statusIndex !== false && $i === $statusIndex;
          @endphp
          <div class="os-step {{ $isDone ? 'line-done' : '' }}">
            <div class="os-dot {{ $isDone ? 'done' : ($isActive ? 'active' : '') }}">
              @if($isDone)
                <i class="fa fa-check" style="font-size:11px;"></i>
              @else
                <i class="fa {{ $stepIcons[$i] }}" style="font-size:11px;"></i>
              @endif
            </div>
            <span class="os-step-label {{ $isDone ? 'done' : ($isActive ? 'active' : '') }}">
              {{ $stepLabels[$i] }}
            </span>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- ── Order items receipt ── --}}
  <div class="os-receipt">
    <div style="padding:13px 20px;background:color-mix(in srgb,var(--primary) 5%,var(--bg));border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:13px;font-weight:800;"><i class="fa fa-list-ul" style="margin-right:6px;color:var(--primary);"></i>Order Items</span>
      <span style="font-size:12px;color:var(--muted);">{{ $order->items->count() }} line{{ $order->items->count() !== 1 ? 's' : '' }}</span>
    </div>
    @foreach($order->items as $item)
      <div class="os-receipt-item">
        <div>
          <div style="font-size:14px;font-weight:700;">{{ $item->name }}</div>
          @if($item->notes)
            <div style="font-size:11px;color:var(--muted);margin-top:2px;"><i class="fa fa-note-sticky" style="margin-right:3px;opacity:.6;"></i>{{ $item->notes }}</div>
          @endif
        </div>
        <div style="font-size:12px;color:var(--muted);text-align:center;">
          <span style="display:block;font-weight:800;font-size:13px;color:var(--text);">×{{ $item->quantity }}</span>
          <span>ea.</span>
        </div>
        <div style="font-size:13px;color:var(--muted);min-width:70px;text-align:right;">
          {{ $currency }}{{ number_format((float)$item->unit_price, 2) }}
        </div>
        <div style="font-size:14px;font-weight:800;min-width:80px;text-align:right;">
          {{ $currency }}{{ number_format($item->lineTotal(), 2) }}
        </div>
      </div>
    @endforeach

    {{-- Subtotal / discount / total --}}
    <div style="padding:12px 20px;display:flex;flex-direction:column;gap:4px;background:color-mix(in srgb,var(--bg) 50%,transparent);">
      <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted);">
        <span>Subtotal</span>
        <span>{{ $currency }}{{ number_format((float)$order->subtotal, 2) }}</span>
      </div>
      @if((float)$order->discount_amount > 0)
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#ef4444;">
          <span>Discount</span>
          <span>– {{ $currency }}{{ number_format((float)$order->discount_amount, 2) }}</span>
        </div>
      @endif
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:900;color:var(--text);padding-top:8px;border-top:1px solid var(--border);margin-top:4px;">
        <span>Total</span>
        <span style="color:var(--primary);">{{ $currency }}{{ number_format((float)$order->total, 2) }}</span>
      </div>
    </div>
  </div>

  @if($order->notes)
    <div style="background:color-mix(in srgb,var(--primary) 5%,var(--bg));border:1px solid color-mix(in srgb,var(--primary) 20%,var(--border));border-radius:12px;padding:13px 18px;margin-bottom:16px;font-size:13px;">
      <i class="fa fa-note-sticky" style="margin-right:6px;color:var(--primary);"></i>
      <strong>Notes:</strong> {{ $order->notes }}
    </div>
  @endif

  @if($order->sale)
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:13px 18px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;">
      <i class="fa fa-cash-register" style="color:var(--primary);font-size:16px;"></i>
      <span>Linked to POS Sale <strong>{{ $order->sale->sale_number }}</strong></span>
    </div>
  @endif

  {{-- ── Action buttons ── --}}
  @php
    $nextActions = [
      'pending'   => ['label' => 'Start Preparing', 'target' => 'preparing', 'bg' => '#3b82f6', 'icon' => 'fa-fire-burner'],
      'preparing' => ['label' => 'Mark Ready',      'target' => 'ready',     'bg' => '#8b5cf6', 'icon' => 'fa-bell'],
      'ready'     => ['label' => 'Mark Served',     'target' => 'served',    'bg' => '#22c55e', 'icon' => 'fa-utensils'],
      'served'    => ['label' => 'Mark as Paid',    'target' => 'paid',      'bg' => '#f59e0b', 'icon' => 'fa-circle-check'],
    ];
    $cancelable  = in_array($order->status, ['pending','preparing','ready']);
    $deletable   = in_array($order->status, ['pending','cancelled']);
  @endphp

  @if(isset($nextActions[$order->status]) || $cancelable)
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
      @if(isset($nextActions[$order->status]))
        @php $act = $nextActions[$order->status]; @endphp
        <form method="POST" action="{{ route('restaurant.orders.transition', $order) }}" style="flex:1;min-width:180px;">
          @csrf
          <input type="hidden" name="status" value="{{ $act['target'] }}">
          <button type="submit" class="os-action-btn" style="background:{{ $act['bg'] }};color:#fff;width:100%;">
            <i class="fa {{ $act['icon'] }}"></i> {{ $act['label'] }}
          </button>
        </form>
      @endif

      @if($cancelable)
        <form method="POST" action="{{ route('restaurant.orders.transition', $order) }}" onsubmit="return confirm('Cancel this order?')" style="min-width:140px;">
          @csrf
          <input type="hidden" name="status" value="cancelled">
          <button type="submit" class="os-ghost-btn" style="width:100%;border-color:#ef4444;color:#ef4444;">
            <i class="fa fa-ban"></i> Cancel Order
          </button>
        </form>
      @endif
    </div>
  @endif

  {{-- Back / delete row --}}
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <a href="{{ route('restaurant.orders.index') }}"
       style="padding:9px 18px;border-radius:10px;border:1px solid var(--border);font-size:13px;text-decoration:none;color:var(--muted);display:inline-flex;align-items:center;gap:6px;">
      <i class="fa fa-arrow-left"></i> All orders
    </a>
    @if($deletable)
      <form method="POST" action="{{ route('restaurant.orders.destroy', $order) }}"
            onsubmit="return confirm('Permanently delete this order?')">
        @csrf @method('DELETE')
        <button type="submit"
                style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:12px;display:flex;align-items:center;gap:5px;padding:8px 10px;">
          <i class="fa fa-trash"></i> Delete order
        </button>
      </form>
    @endif
  </div>

</div>
@endsection
