@extends('theme::layouts.app', ['title' => 'Orders', 'heading' => 'Restaurant'])

@section('content')
<style>
.oi-stat       { border-radius:10px;border:1px solid var(--border);padding:10px 14px;display:flex;align-items:center;
                  gap:10px;text-decoration:none;color:var(--text);transition:box-shadow .15s;background:var(--bg); }
.oi-stat:hover { box-shadow:0 3px 12px rgba(0,0,0,.08); }
.oi-stat-icon  { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.oi-pill       { padding:4px 11px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid var(--border);
                  text-decoration:none;color:var(--muted);background:var(--bg);transition:all .15s;white-space:nowrap; }
.oi-pill:hover { border-color:var(--primary);color:var(--primary); }
.oi-pill.on    { background:var(--primary);border-color:var(--primary);color:#fff; }
.oi-thead      { display:grid;grid-template-columns:130px 1fr 100px 64px 100px 130px 96px;
                  border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--primary) 4%,var(--bg)); }
.oi-row        { display:grid;grid-template-columns:130px 1fr 100px 64px 100px 130px 96px;
                  align-items:center;border-bottom:1px solid var(--border);text-decoration:none;
                  color:var(--text);transition:background .1s; }
.oi-row:hover  { background:color-mix(in srgb,var(--primary) 4%,var(--bg)); }
.oi-th         { padding:8px 12px;font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.4px; }
.oi-td         { padding:10px 12px;font-size:13px; }
@media(max-width:860px){
  .oi-thead,.oi-row { grid-template-columns:120px 1fr 90px 90px; }
  .oi-th:nth-child(3),.oi-th:nth-child(4),.oi-th:nth-child(6),
  .oi-td:nth-child(3),.oi-td:nth-child(4),.oi-td:nth-child(6) { display:none; }
}
</style>

<div>
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
  @endif

  {{-- Top bar --}}
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
    <div>
      <h2 style="margin:0 0 1px;font-size:16px;font-weight:800;">Orders</h2>
      <p style="margin:0;font-size:12px;color:var(--muted);">Track and manage all restaurant orders</p>
    </div>
    <a href="{{ route('restaurant.orders.create') }}" class="linkbtn"
       style="padding:8px 16px;font-size:13px;font-weight:700;border-radius:9px;text-decoration:none;display:flex;align-items:center;gap:6px;">
      <i class="fa fa-plus"></i> New Order
    </a>
  </div>

  {{-- Stat cards --}}
  @php
    $statGroups = [
      'pending'   => ['label'=>'Pending',   'icon'=>'fa-hourglass-start','color'=>'#f59e0b'],
      'preparing' => ['label'=>'Preparing', 'icon'=>'fa-fire-burner',    'color'=>'#3b82f6'],
      'ready'     => ['label'=>'Ready',     'icon'=>'fa-bell',           'color'=>'#8b5cf6'],
      'served'    => ['label'=>'Served',    'icon'=>'fa-utensils',       'color'=>'#22c55e'],
    ];
  @endphp
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px;">
    @foreach($statGroups as $st => $sg)
      <a href="{{ route('restaurant.orders.index', array_merge(request()->query(),['status'=>$st])) }}"
         class="oi-stat{{ $status===$st?' on':'' }}"
         style="{{ $status===$st ? 'border-color:'.($sg['color']).';background:color-mix(in srgb,'.($sg['color']).' 8%,var(--bg));' : '' }}">
        <div class="oi-stat-icon"
             style="background:color-mix(in srgb,{{ $sg['color'] }} 14%,transparent);color:{{ $sg['color'] }};">
          <i class="fa {{ $sg['icon'] }}"></i>
        </div>
        <div>
          <div style="font-size:20px;font-weight:900;line-height:1;color:var(--text);">{{ $statusCounts->get($st,0) }}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:1px;">{{ $sg['label'] }}</div>
        </div>
      </a>
    @endforeach
  </div>

  {{-- Filter bar --}}
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;
              padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:10px;">
    <span style="font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;">Status</span>
    <div style="display:flex;gap:4px;flex-wrap:wrap;">
      @foreach(['all'=>'All','pending'=>'Pending','preparing'=>'Preparing','ready'=>'Ready','served'=>'Served','paid'=>'Paid','cancelled'=>'Cancelled'] as $key=>$lbl)
        <a href="{{ route('restaurant.orders.index', array_merge(request()->query(),['status'=>$key])) }}"
           class="oi-pill {{ $status===$key?'on':'' }}" style="color:inherit;">{{ $lbl }}</a>
      @endforeach
    </div>
    <div style="width:1px;height:18px;background:var(--border);flex-shrink:0;"></div>
    <span style="font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;">Type</span>
    <div style="display:flex;gap:4px;flex-wrap:wrap;">
      @foreach(['all'=>'All','dine_in'=>'Dine In','takeaway'=>'Takeaway','delivery'=>'Delivery'] as $key=>$lbl)
        <a href="{{ route('restaurant.orders.index', array_merge(request()->query(),['type'=>$key])) }}"
           class="oi-pill {{ $type===$key?'on':'' }}" style="color:inherit;">{{ $lbl }}</a>
      @endforeach
    </div>
  </div>

  {{-- Table --}}
  <div style="border-radius:10px;border:1px solid var(--border);overflow:hidden;">
    <div class="oi-thead">
      <div class="oi-th">Order #</div>
      <div class="oi-th">Customer / Table</div>
      <div class="oi-th">Type</div>
      <div class="oi-th">Items</div>
      <div class="oi-th">Total{{ $currency ? ' ('.$currency.')' : '' }}</div>
      <div class="oi-th">Time</div>
      <div class="oi-th">Status</div>
    </div>

    @forelse($orders as $order)
      <a href="{{ route('restaurant.orders.show', $order) }}" class="oi-row">
        <div class="oi-td" style="font-weight:800;font-size:12px;letter-spacing:-.2px;font-family:monospace;">{{ $order->order_number }}</div>
        <div class="oi-td">
          @if($order->table)
            <span style="font-weight:700;font-size:13px;">{{ $order->table->name }}</span>
          @endif
          @if($order->customer_name)
            <span style="font-size:12px;color:var(--muted);{{ $order->table?' margin-left:6px;':'' }}">{{ $order->customer_name }}</span>
          @endif
          @if(!$order->table && !$order->customer_name)
            <span style="color:var(--muted);font-size:12px;">—</span>
          @endif
        </div>
        <div class="oi-td">
          @php $typeIcons=['dine_in'=>'fa-chair','takeaway'=>'fa-bag-shopping','delivery'=>'fa-motorcycle']; @endphp
          <span style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px;">
            <i class="fa {{ $typeIcons[$order->order_type] ?? 'fa-receipt' }}" style="font-size:11px;"></i>
            {{ $order->typeLabel() }}
          </span>
        </div>
        <div class="oi-td" style="font-weight:700;text-align:center;">{{ $order->items->count() }}</div>
        <div class="oi-td" style="font-weight:800;">{{ $currency }}{{ number_format((float)$order->total,2) }}</div>
        <div class="oi-td">
          <div style="font-size:12px;color:var(--text);">{{ $order->created_at->format('d M, H:i') }}</div>
          <div style="font-size:11px;color:var(--muted);">{{ $order->created_at->diffForHumans() }}</div>
        </div>
        <div class="oi-td">
          <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;
                background:color-mix(in srgb,{{ $order->statusColor() }} 14%,transparent);
                color:{{ $order->statusColor() }};">
            {{ ucfirst($order->status) }}
          </span>
        </div>
      </a>
    @empty
      <div style="text-align:center;padding:36px 20px;color:var(--muted);">
        <i class="fa fa-receipt" style="font-size:28px;opacity:.25;display:block;margin-bottom:8px;"></i>
        <p style="margin:0;font-size:13px;">No orders found for the selected filters.</p>
      </div>
    @endforelse
  </div>

  <div style="margin-top:12px;">{{ $orders->withQueryString()->links() }}</div>
</div>
@endsection
