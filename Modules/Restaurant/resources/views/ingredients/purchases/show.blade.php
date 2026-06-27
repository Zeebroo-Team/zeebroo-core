@extends('theme::layouts.app', ['title' => $po->po_number, 'heading' => 'Restaurant'])

@section('content')
@php
  $sc = match($po->status) {
    'draft'              => ['bg'=>'#f1f5f9','color'=>'#64748b'],
    'ordered'            => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
    'partially_received' => ['bg'=>'#fef3c7','color'=>'#92400e'],
    'received'           => ['bg'=>'#dcfce7','color'=>'#15803d'],
    'cancelled'          => ['bg'=>'#fee2e2','color'=>'#991b1b'],
    default              => ['bg'=>'#f1f5f9','color'=>'#64748b'],
  };
  $totalReceived = $po->items->sum(fn($i) => $i->quantityReceived() * (float)$i->unit_cost);
@endphp
<style>
.ipo-show-wrap { max-width:960px; }
.ipo-show-head { display:flex;align-items:flex-start;gap:14px;margin-bottom:24px;flex-wrap:wrap; }
.ipo-show-head__main { flex:1;min-width:0; }
.ipo-show-head__title { font-size:20px;font-weight:900;margin-bottom:4px; }
.ipo-show-head__meta  { font-size:12px;color:var(--muted); }
.ipo-show-head__actions { display:flex;gap:8px;flex-wrap:wrap; }
.ipo-badge { display:inline-flex;align-items:center;padding:4px 11px;border-radius:999px;
              font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.3px; }
.ipo-card  { background:var(--bg);border:1px solid var(--border);border-radius:14px;margin-bottom:20px; }
.ipo-card__head { padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800; }
.ipo-card__body { padding:18px; }
.ipo-meta-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px; }
.ipo-meta-item__label { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:3px; }
.ipo-meta-item__val   { font-size:13px;font-weight:700; }
.ipo-items-table { width:100%;border-collapse:collapse; }
.ipo-items-table th { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);
                       padding:8px 12px;border-bottom:1.5px solid var(--border);text-align:left; }
.ipo-items-table td { padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px; }
.ipo-items-table tr:last-child td { border-bottom:none; }
.ipo-items-table .num { text-align:right;font-variant-numeric:tabular-nums; }
.ipo-btn { padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid;cursor:pointer;
            display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s; }
.ipo-btn--primary { background:var(--primary);color:#fff;border-color:var(--primary); }
.ipo-btn--primary:hover { opacity:.88; }
.ipo-btn--outline { background:transparent;color:var(--text);border-color:var(--border); }
.ipo-btn--outline:hover { border-color:var(--primary);color:var(--primary); }
.ipo-btn--danger  { background:transparent;color:#ef4444;border-color:#fca5a5; }
.ipo-btn--danger:hover  { background:#fef2f2; }
.ipo-btn--green   { background:#16a34a;color:#fff;border-color:#16a34a; }
.ipo-btn--green:hover   { opacity:.88; }
.ipo-progress { height:6px;border-radius:3px;background:var(--border);overflow:hidden;margin-top:4px; }
.ipo-progress__bar { height:100%;border-radius:3px;background:#16a34a;transition:width .3s; }
.ipo-field label { display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px; }
.ipo-field input,.ipo-field select,.ipo-field textarea {
  width:100%;padding:8px 11px;border-radius:8px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box; }
.ipo-field input:focus,.ipo-field select:focus,.ipo-field textarea:focus { outline:none;border-color:var(--primary); }
.ipo-row2 { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.ipo-row3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px; }
.ipo-grn-row { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border); }
.ipo-grn-row:last-child { border-bottom:none; }
.ipo-grn-row__num { font-size:13px;font-weight:800; }
.ipo-grn-row__meta { font-size:11px;color:var(--muted);margin-top:1px; }
</style>

<div class="ipo-show-wrap">

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

  {{-- Header --}}
  <div class="ipo-show-head">
    <div class="ipo-show-head__main">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
        <a href="{{ route('restaurant.ingredients.purchases.index') }}" style="color:var(--muted);font-size:12px;text-decoration:none;">
          <i class="fa fa-arrow-left"></i> Purchase Orders
        </a>
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="ipo-show-head__title">{{ $po->po_number }}</div>
        <div class="ipo-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
          {{ $po->statusLabel() }}
        </div>
      </div>
      <div class="ipo-show-head__meta">
        {{ $po->purchase_date->format('d M Y') }}
        @if($po->supplier) &bull; {{ $po->supplier->name }} @endif
        @if($po->expected_delivery_date) &bull; Expected {{ $po->expected_delivery_date->format('d M Y') }} @endif
      </div>
    </div>
    <div class="ipo-show-head__actions">
      @if($po->isDraft())
        <form method="POST" action="{{ route('restaurant.ingredients.purchases.place-order', $po) }}" style="display:inline;">
          @csrf
          <button class="ipo-btn ipo-btn--primary" type="submit">
            <i class="fa fa-paper-plane"></i> Place Order
          </button>
        </form>
      @endif
      @if($po->isEditable())
        <button class="ipo-btn ipo-btn--outline" onclick="document.getElementById('editModal').classList.add('open')">
          <i class="fa fa-edit"></i> Edit
        </button>
        <form method="POST" action="{{ route('restaurant.ingredients.purchases.cancel', $po) }}"
              onsubmit="return confirm('Cancel this purchase order?')" style="display:inline;">
          @csrf
          <button class="ipo-btn ipo-btn--danger" type="submit"><i class="fa fa-ban"></i> Cancel</button>
        </form>
      @endif
      @if($po->canReceiveGoods() && !$po->isDraft())
        <button class="ipo-btn ipo-btn--green" onclick="document.getElementById('receiveModal').classList.add('open')">
          <i class="fa fa-truck"></i> Receive Goods
        </button>
      @endif
      @if($po->isDraft() && $po->grns->isEmpty())
        <form method="POST" action="{{ route('restaurant.ingredients.purchases.destroy', $po) }}"
              onsubmit="return confirm('Delete this draft?')" style="display:inline;">
          @csrf @method('DELETE')
          <button class="ipo-btn ipo-btn--danger" type="submit"><i class="fa fa-trash"></i></button>
        </form>
      @endif
    </div>
  </div>

  {{-- Meta --}}
  <div class="ipo-card" style="margin-bottom:20px;">
    <div class="ipo-card__body">
      <div class="ipo-meta-grid">
        <div>
          <div class="ipo-meta-item__label">PO Number</div>
          <div class="ipo-meta-item__val">{{ $po->po_number }}</div>
        </div>
        <div>
          <div class="ipo-meta-item__label">Supplier</div>
          <div class="ipo-meta-item__val">{{ $po->supplier?->name ?? '—' }}</div>
        </div>
        <div>
          <div class="ipo-meta-item__label">Order Date</div>
          <div class="ipo-meta-item__val">{{ $po->purchase_date->format('d M Y') }}</div>
        </div>
        <div>
          <div class="ipo-meta-item__label">Expected Delivery</div>
          <div class="ipo-meta-item__val">{{ $po->expected_delivery_date?->format('d M Y') ?? '—' }}</div>
        </div>
        <div>
          <div class="ipo-meta-item__label">Order Total</div>
          <div class="ipo-meta-item__val">{{ $currency }}{{ number_format((float)$po->total, 2) }}</div>
        </div>
        <div>
          <div class="ipo-meta-item__label">Total Received</div>
          <div class="ipo-meta-item__val">{{ $currency }}{{ number_format($totalReceived, 2) }}</div>
        </div>
      </div>
      @if($po->notes)
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);">
          {{ $po->notes }}
        </div>
      @endif
    </div>
  </div>

  {{-- Items --}}
  <div class="ipo-card" style="margin-bottom:20px;">
    <div class="ipo-card__head"><i class="fa fa-list" style="margin-right:6px;color:var(--primary);"></i> Ordered Ingredients</div>
    <table class="ipo-items-table">
      <thead>
        <tr>
          <th>Ingredient</th>
          <th class="num">Ordered</th>
          <th class="num">Received</th>
          <th class="num">Remaining</th>
          <th class="num">Unit Cost</th>
          <th class="num">Line Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($po->items as $item)
          @php
            $received  = $item->quantityReceived();
            $remaining = $item->quantityRemaining();
            $pct = (float)$item->quantity > 0 ? min(100, round($received / (float)$item->quantity * 100)) : 0;
          @endphp
          <tr>
            <td>
              <div style="font-weight:700;">{{ $item->ingredient?->name }}</div>
              <div style="font-size:11px;color:var(--muted);">{{ $item->ingredient?->unit }}</div>
            </td>
            <td class="num">{{ (float)$item->quantity }}</td>
            <td class="num">
              <div>{{ $received }}</div>
              <div class="ipo-progress"><div class="ipo-progress__bar" style="width:{{ $pct }}%;"></div></div>
            </td>
            <td class="num" style="{{ $remaining > 0 ? 'color:#d97706;font-weight:700;' : 'color:#16a34a;' }}">
              {{ $remaining > 0 ? $remaining : '—' }}
            </td>
            <td class="num">{{ $currency }}{{ number_format((float)$item->unit_cost, 4) }}</td>
            <td class="num">{{ $currency }}{{ number_format((float)$item->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" style="text-align:right;font-size:12px;font-weight:800;padding:10px 12px;border-top:1.5px solid var(--border);color:var(--muted);">TOTAL</td>
          <td class="num" style="font-size:14px;font-weight:900;border-top:1.5px solid var(--border);">{{ $currency }}{{ number_format((float)$po->total, 2) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- GRNs --}}
  @if($po->grns->isNotEmpty())
  <div class="ipo-card" style="margin-bottom:20px;">
    <div class="ipo-card__head"><i class="fa fa-clipboard-check" style="margin-right:6px;color:#16a34a;"></i> Goods Received Notes</div>
    <div class="ipo-card__body">
      @foreach($po->grns as $grn)
        <div class="ipo-grn-row">
          <div style="flex:1;">
            <a href="{{ route('restaurant.ingredients.grn.show', $grn) }}" style="font-size:13px;font-weight:800;color:var(--primary);">
              {{ $grn->grn_number }}
            </a>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;">
              {{ $grn->received_date->format('d M Y') }}
              &bull; {{ $grn->items->count() }} {{ Str::plural('item', $grn->items->count()) }}
              &bull; {{ $grn->paymentMethodLabel() }}
            </div>
          </div>
          <div style="font-size:14px;font-weight:800;">{{ $currency }}{{ number_format((float)$grn->total, 2) }}</div>
          <a href="{{ route('restaurant.ingredients.grn.show', $grn) }}" class="ipo-btn ipo-btn--outline" style="padding:5px 10px;font-size:11px;">
            <i class="fa fa-eye"></i> View
          </a>
        </div>
      @endforeach
    </div>
  </div>
  @endif

</div>

{{-- Edit Modal --}}
@if($po->isEditable())
<div class="ipo-modal-overlay" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="ipo-modal" style="background:var(--bg);border-radius:16px;width:min(700px,96vw);max-height:90vh;overflow-y:auto;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="margin:0;font-size:15px;font-weight:900;"><i class="fa fa-edit" style="color:var(--primary);margin-right:6px;"></i> Edit {{ $po->po_number }}</h3>
      <button type="button" onclick="document.getElementById('editModal').classList.remove('open')"
              style="background:none;border:none;font-size:18px;color:var(--muted);cursor:pointer;">&times;</button>
    </div>
    @include('restaurant::ingredients.purchases._form', ['po' => $po])
  </div>
</div>
@endif

{{-- Receive Goods Modal --}}
@if($po->canReceiveGoods() && !$po->isDraft())
<div class="ipo-modal-overlay" id="receiveModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="ipo-modal" style="background:var(--bg);border-radius:16px;width:min(680px,96vw);max-height:90vh;overflow-y:auto;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="margin:0;font-size:15px;font-weight:900;"><i class="fa fa-truck" style="color:#16a34a;margin-right:6px;"></i> Receive Goods</h3>
      <button type="button" onclick="document.getElementById('receiveModal').classList.remove('open')"
              style="background:none;border:none;font-size:18px;color:var(--muted);cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="{{ route('restaurant.ingredients.grn.store', $po) }}">
      @csrf

      <div class="ipo-row3" style="margin-bottom:14px;">
        <div class="ipo-field">
          <label>Received Date</label>
          <input type="date" name="received_date" value="{{ date('Y-m-d') }}" required>
        </div>
        <div class="ipo-field">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="credit">Credit / On Account</option>
            <option value="cash">Cash</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>
        <div class="ipo-field">
          <label>Reference</label>
          <input type="text" name="reference" placeholder="Invoice / ref number">
        </div>
      </div>

      <div class="ipo-field" style="margin-bottom:18px;">
        <label>Notes</label>
        <textarea name="notes" rows="2" style="resize:vertical;" placeholder="Optional notes…"></textarea>
      </div>

      <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:8px;">
        Quantities to Receive
      </div>
      <table class="ipo-items-table" style="margin-bottom:16px;">
        <thead>
          <tr>
            <th>Ingredient</th>
            <th class="num">Ordered</th>
            <th class="num">Already Received</th>
            <th class="num">Remaining</th>
            <th class="num">Receive Now</th>
          </tr>
        </thead>
        <tbody>
          @foreach($po->items as $item)
            @php $remaining = $item->quantityRemaining(); @endphp
            <tr>
              <input type="hidden" name="items[{{ $loop->index }}][purchase_order_item_id]" value="{{ $item->id }}">
              <td>
                <div style="font-weight:700;font-size:13px;">{{ $item->ingredient?->name }}</div>
                <div style="font-size:11px;color:var(--muted);">{{ $item->ingredient?->unit }}</div>
              </td>
              <td class="num" style="color:var(--muted);">{{ (float)$item->quantity }}</td>
              <td class="num" style="color:var(--muted);">{{ $item->quantityReceived() }}</td>
              <td class="num" style="{{ $remaining > 0 ? 'color:#d97706;font-weight:700;' : 'color:#16a34a;' }}">
                {{ $remaining }}
              </td>
              <td class="num">
                <input type="number" name="items[{{ $loop->index }}][quantity_received]"
                       step="0.001" min="0" max="{{ $remaining }}"
                       value="{{ $remaining > 0 ? $remaining : 0 }}"
                       style="width:90px;text-align:right;"
                       {{ $remaining <= 0 ? 'disabled' : '' }}>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="ipo-btn ipo-btn--outline" onclick="document.getElementById('receiveModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="ipo-btn ipo-btn--green"><i class="fa fa-check"></i> Confirm Receipt</button>
      </div>
    </form>
  </div>
</div>
@endif

<style>
.ipo-modal-overlay { display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.4);align-items:center;justify-content:center; }
.ipo-modal-overlay.open { display:flex; }
.ipo-items-table th.num,.ipo-items-table td.num { text-align:right; }
</style>
<script>
window.ipoAddLine = function(tableId) {
  var tbody = document.getElementById(tableId);
  if (!tbody) return;
  var idx = tbody.querySelectorAll('tr').length;
  var row = document.createElement('tr');
  row.innerHTML = ipoLineHtml(idx);
  tbody.appendChild(row);
};
window.ipoLineHtml = function(idx) {
  var opts = '<option value="">Select ingredient…</option>';
  @foreach($ingredients as $ing)
  opts += '<option value="{{ $ing->id }}" data-unit="{{ $ing->unit }}" data-cost="{{ (float)($ing->cost_per_unit ?? 0) }}">{{ $ing->name }} ({{ $ing->unit }})</option>';
  @endforeach
  return '<td><select name="items['+idx+'][ingredient_id]" onchange="ipoIngredientChange(this)" required>'+ opts +'</select></td>'
       + '<td><input type="number" name="items['+idx+'][quantity]" step="0.001" min="0.001" placeholder="0" oninput="ipoCalcLine(this)" required style="width:90px;"></td>'
       + '<td><span class="ipo-unit-label" style="font-size:11px;color:var(--muted);"></span></td>'
       + '<td><input type="number" name="items['+idx+'][unit_cost]" step="0.0001" min="0" placeholder="0.00" oninput="ipoCalcLine(this)" required style="width:90px;"></td>'
       + '<td class="ipo-line-total" style="font-size:12px;font-weight:700;white-space:nowrap;">—</td>'
       + '<td><button type="button" class="ipo-btn ipo-btn--ghost" onclick="this.closest(\'tr\').remove();ipoReindex()"><i class="fa fa-times"></i></button></td>';
};
window.ipoIngredientChange = function(sel) {
  var opt = sel.options[sel.selectedIndex];
  var row = sel.closest('tr');
  row.querySelector('.ipo-unit-label').textContent = opt.dataset.unit || '';
  var costInput = row.querySelector('input[name*="[unit_cost]"]');
  if (costInput && opt.dataset.cost && !costInput.value) costInput.value = opt.dataset.cost;
  ipoCalcLine(sel);
};
window.ipoCalcLine = function(el) {
  var row  = el.closest('tr');
  var qty  = parseFloat(row.querySelector('input[name*="[quantity]"]')?.value) || 0;
  var cost = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value) || 0;
  var cell = row.querySelector('.ipo-line-total');
  if (cell) cell.textContent = (qty * cost).toFixed(2);
};
window.ipoReindex = function() {
  document.querySelectorAll('.ipo-items-tbody tr').forEach(function(row, idx) {
    row.querySelectorAll('[name]').forEach(function(el) {
      el.name = el.name.replace(/items\[\d+\]/, 'items['+idx+']');
    });
  });
};
</script>
@endsection
