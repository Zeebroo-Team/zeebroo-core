@extends('theme::layouts.app', ['title' => 'Ingredient Purchases', 'heading' => 'Restaurant'])

@section('content')
@php
  $statusColors = [
    'draft'              => ['bg'=>'#f1f5f9','color'=>'#64748b'],
    'ordered'            => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
    'partially_received' => ['bg'=>'#fef3c7','color'=>'#92400e'],
    'received'           => ['bg'=>'#dcfce7','color'=>'#15803d'],
    'cancelled'          => ['bg'=>'#fee2e2','color'=>'#991b1b'],
  ];
  $statusTabs = [
    'all'                => 'All',
    'draft'              => 'Draft',
    'ordered'            => 'Ordered',
    'partially_received' => 'Partially Received',
    'received'           => 'Received',
    'cancelled'          => 'Cancelled',
  ];
@endphp
<style>
.ipo-wrap { max-width:100%; }
.ipo-toolbar { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap; }
.ipo-toolbar__title { font-size:15px;font-weight:900; }
.ipo-tabs { display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px; }
.ipo-tab { padding:6px 14px;border-radius:8px;border:1.5px solid var(--border);font-size:12px;font-weight:700;
           color:var(--muted);text-decoration:none;transition:all .15s; }
.ipo-tab.active,.ipo-tab:hover { border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--bg)); }
.ipo-tab.active { background:color-mix(in srgb,var(--primary) 10%,var(--bg)); }
.ipo-list { background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.ipo-row  { display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit; }
.ipo-row:last-child { border-bottom:none; }
.ipo-row:hover { background:color-mix(in srgb,var(--primary) 3%,var(--bg)); }
.ipo-row__main { flex:1;min-width:0; }
.ipo-row__number { font-size:13px;font-weight:800; }
.ipo-row__meta   { font-size:11px;color:var(--muted);margin-top:2px; }
.ipo-row__total  { font-size:14px;font-weight:800;white-space:nowrap; }
.ipo-badge { display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;
              font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.3px; }
.ipo-empty { text-align:center;padding:48px 24px;color:var(--muted); }
.ipo-empty i { font-size:36px;opacity:.3;display:block;margin-bottom:12px; }
.ipo-empty__text { font-size:14px;font-weight:700;margin-bottom:6px;color:var(--text); }
.ipo-empty__sub  { font-size:12px; }

/* Create form */
.ipo-form-card { background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:22px 24px;margin-bottom:24px; }
.ipo-form-card h3 { font-size:14px;font-weight:900;margin:0 0 18px; }
.ipo-field { margin-bottom:14px; }
.ipo-field label { display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px; }
.ipo-field input,.ipo-field select,.ipo-field textarea {
  width:100%;padding:8px 11px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box; }
.ipo-field input:focus,.ipo-field select:focus,.ipo-field textarea:focus { outline:none;border-color:var(--primary); }
.ipo-row2 { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.ipo-row3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px; }

/* Items table */
.ipo-items-table { width:100%;border-collapse:collapse;margin-top:8px; }
.ipo-items-table th { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);
                       padding:6px 8px;border-bottom:1.5px solid var(--border);text-align:left; }
.ipo-items-table td { padding:6px 8px;border-bottom:1px solid var(--border);vertical-align:middle; }
.ipo-items-table tr:last-child td { border-bottom:none; }
.ipo-items-table input,.ipo-items-table select { width:100%;padding:6px 8px;border-radius:7px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:12px;box-sizing:border-box; }
.ipo-items-table input:focus,.ipo-items-table select:focus { outline:none;border-color:var(--primary); }
.ipo-btn { padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid;cursor:pointer;
            display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s; }
.ipo-btn--primary { background:var(--primary);color:#fff;border-color:var(--primary); }
.ipo-btn--primary:hover { opacity:.88; }
.ipo-btn--outline { background:transparent;color:var(--text);border-color:var(--border); }
.ipo-btn--outline:hover { border-color:var(--primary);color:var(--primary); }
.ipo-btn--ghost { background:transparent;color:#ef4444;border-color:transparent;padding:4px 6px;font-size:11px; }
.ipo-btn--ghost:hover { background:#fef2f2; }
.ipo-add-btn { background:none;border:1.5px dashed var(--border);border-radius:8px;padding:8px;
               width:100%;color:var(--muted);font-size:12px;font-weight:700;cursor:pointer;margin-top:6px;transition:all .15s; }
.ipo-add-btn:hover { border-color:var(--primary);color:var(--primary); }

/* Modal trigger for non-empty list */
.ipo-modal-overlay { display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.4);align-items:center;justify-content:center; }
.ipo-modal-overlay.open { display:flex; }
.ipo-modal { background:var(--bg);border-radius:16px;width:min(700px,96vw);max-height:90vh;overflow-y:auto;
              padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2); }
.ipo-modal h3 { font-size:15px;font-weight:900;margin:0 0 20px; }
</style>

<div class="ipo-wrap">

  @if(session('status'))
    <div style="background:#dcfce7;color:#15803d;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:700;">
      <i class="fa fa-check-circle"></i> {{ session('status') }}
    </div>
  @endif
  @if($errors->any())
    <div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;">
      @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
  @endif

  <div class="ipo-toolbar">
    <div class="ipo-toolbar__title">
      <i class="fa fa-shopping-cart" style="color:var(--primary);margin-right:6px;"></i>
      Ingredient Purchase Orders
    </div>
    @if($orders->isNotEmpty())
      <button class="ipo-btn ipo-btn--primary" onclick="document.getElementById('createModal').classList.add('open')">
        <i class="fa fa-plus"></i> New Purchase Order
      </button>
    @endif
  </div>

  {{-- Status tabs --}}
  <div class="ipo-tabs">
    @foreach($statusTabs as $key => $label)
      <a href="{{ route('restaurant.ingredients.purchases.index', ['status' => $key]) }}"
         class="ipo-tab {{ $status === $key ? 'active' : '' }}">
        {{ $label }}
      </a>
    @endforeach
  </div>

  @if($orders->isEmpty() && $status === 'all')
    {{-- Inline create form when no orders exist --}}
    <div class="ipo-form-card">
      <h3><i class="fa fa-plus" style="color:var(--primary);margin-right:6px;"></i> Create First Purchase Order</h3>
      @include('restaurant::ingredients.purchases._form', ['po' => null])
    </div>
  @elseif($orders->isEmpty())
    <div class="ipo-empty">
      <i class="fa fa-shopping-cart"></i>
      <div class="ipo-empty__text">No orders with status "{{ $statusTabs[$status] ?? $status }}"</div>
      <div class="ipo-empty__sub">Change the filter above or create a new order.</div>
    </div>
  @else
    <div class="ipo-list">
      @foreach($orders as $order)
        @php $sc = $statusColors[$order->status] ?? ['bg'=>'#f1f5f9','color'=>'#64748b']; @endphp
        <a href="{{ route('restaurant.ingredients.purchases.show', $order) }}" class="ipo-row">
          <div class="ipo-row__main">
            <div class="ipo-row__number">
              {{ $order->po_number }}
              @if($order->supplier)
                <span style="font-weight:500;color:var(--muted);font-size:12px;"> &mdash; {{ $order->supplier->name }}</span>
              @endif
            </div>
            <div class="ipo-row__meta">
              {{ $order->purchase_date->format('d M Y') }}
              &bull; {{ $order->items_count }} {{ Str::plural('ingredient', $order->items_count) }}
              @if($order->expected_delivery_date)
                &bull; Expected {{ $order->expected_delivery_date->format('d M Y') }}
              @endif
            </div>
          </div>
          <div class="ipo-row__total">{{ $currency }}{{ number_format((float)$order->total, 2) }}</div>
          <div class="ipo-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
            {{ $order->statusLabel() }}
          </div>
          <i class="fa fa-chevron-right" style="color:var(--muted);font-size:11px;"></i>
        </a>
      @endforeach
    </div>
  @endif

</div>

{{-- Create modal (shown when list has records) --}}
@if($orders->isNotEmpty())
<div class="ipo-modal-overlay" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="ipo-modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="margin:0;"><i class="fa fa-plus" style="color:var(--primary);margin-right:6px;"></i> New Purchase Order</h3>
      <button type="button" onclick="document.getElementById('createModal').classList.remove('open')"
              style="background:none;border:none;font-size:18px;color:var(--muted);cursor:pointer;">&times;</button>
    </div>
    @include('restaurant::ingredients.purchases._form', ['po' => null])
  </div>
</div>
@endif

<script>
// Line item management for create form
window.ipoAddLine = function(tableId) {
  var tbody = document.getElementById(tableId);
  var idx = tbody.querySelectorAll('tr').length;
  var row = document.createElement('tr');
  row.innerHTML = ipoLineHtml(idx);
  tbody.appendChild(row);
};

@php
  $ingredientOptionsJson = $ingredients->map(function($i) {
      return ['id'=>$i->id,'name'=>$i->name,'unit'=>$i->unit,'cost'=>(float)($i->cost_per_unit??0)];
  })->values();
@endphp
window.ipoLineHtml = function(idx) {
  var ingredientOptions = @json($ingredientOptionsJson);
  var opts = '<option value="">Select ingredient…</option>';
  ingredientOptions.forEach(function(i) {
    opts += '<option value="'+i.id+'" data-unit="'+i.unit+'" data-cost="'+i.cost+'">' + i.name + ' ('+i.unit+')</option>';
  });
  return '<td><select name="items['+idx+'][ingredient_id]" onchange="ipoIngredientChange(this)" required>'+opts+'</select></td>'
       + '<td><input type="number" name="items['+idx+'][quantity]" step="0.001" min="0.001" placeholder="0" oninput="ipoCalcLine(this)" required style="width:90px;"></td>'
       + '<td><span class="ipo-unit-label" style="font-size:11px;color:var(--muted);"></span></td>'
       + '<td><input type="number" name="items['+idx+'][unit_cost]" step="0.0001" min="0" placeholder="0.00" oninput="ipoCalcLine(this)" required style="width:90px;"></td>'
       + '<td class="ipo-line-total" style="font-size:12px;font-weight:700;white-space:nowrap;">—</td>'
       + '<td><button type="button" class="ipo-btn ipo-btn--ghost" onclick="this.closest(\'tr\').remove();ipoReindex()"><i class="fa fa-times"></i></button></td>';
};

window.ipoIngredientChange = function(sel) {
  var opt = sel.options[sel.selectedIndex];
  var unit = opt.dataset.unit || '';
  var cost = opt.dataset.cost || '';
  var row = sel.closest('tr');
  row.querySelector('.ipo-unit-label').textContent = unit;
  var costInput = row.querySelector('input[name*="[unit_cost]"]');
  if (costInput && cost && !costInput.value) costInput.value = cost;
  ipoCalcLine(sel);
};

window.ipoCalcLine = function(el) {
  var row = el.closest('tr');
  var qty  = parseFloat(row.querySelector('input[name*="[quantity]"]')?.value) || 0;
  var cost = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value) || 0;
  var cell = row.querySelector('.ipo-line-total');
  if (cell) cell.textContent = (qty * cost).toFixed(2);
};

window.ipoReindex = function() {
  var allRows = document.querySelectorAll('.ipo-items-tbody tr');
  allRows.forEach(function(row, idx) {
    row.querySelectorAll('[name]').forEach(function(el) {
      el.name = el.name.replace(/items\[\d+\]/, 'items['+idx+']');
    });
  });
};
</script>
@endsection
