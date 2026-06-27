@extends('theme::layouts.app', ['title' => $grn->grn_number, 'heading' => 'Restaurant'])

@section('content')
<style>
.igrn-wrap { max-width:860px; }
.igrn-head { display:flex;align-items:flex-start;gap:14px;margin-bottom:24px;flex-wrap:wrap; }
.igrn-head__main { flex:1;min-width:0; }
.igrn-head__title { font-size:20px;font-weight:900;margin-bottom:4px; }
.igrn-badge { display:inline-flex;align-items:center;padding:4px 11px;border-radius:999px;
               font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.3px;
               background:#dcfce7;color:#15803d; }
.igrn-card  { background:var(--bg);border:1px solid var(--border);border-radius:14px;margin-bottom:20px; }
.igrn-card__head { padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800; }
.igrn-card__body { padding:18px; }
.igrn-meta-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px; }
.igrn-meta-item__label { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:3px; }
.igrn-meta-item__val   { font-size:13px;font-weight:700; }
.igrn-table { width:100%;border-collapse:collapse; }
.igrn-table th { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);
                  padding:8px 12px;border-bottom:1.5px solid var(--border);text-align:left; }
.igrn-table td { padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px; }
.igrn-table tr:last-child td { border-bottom:none; }
.igrn-table .num { text-align:right;font-variant-numeric:tabular-nums; }
.igrn-btn { padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid;cursor:pointer;
             display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s; }
.igrn-btn--outline { background:transparent;color:var(--text);border-color:var(--border); }
.igrn-btn--outline:hover { border-color:var(--primary);color:var(--primary); }
</style>

<div class="igrn-wrap">

  @if(session('status'))
    <div style="background:#dcfce7;color:#15803d;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:700;">
      <i class="fa fa-check-circle"></i> {{ session('status') }}
    </div>
  @endif

  {{-- Header --}}
  <div class="igrn-head">
    <div class="igrn-head__main">
      <div style="margin-bottom:6px;">
        @if($grn->purchaseOrder)
          <a href="{{ route('restaurant.ingredients.purchases.show', $grn->purchaseOrder) }}"
             style="color:var(--muted);font-size:12px;text-decoration:none;">
            <i class="fa fa-arrow-left"></i> {{ $grn->purchaseOrder->po_number }}
          </a>
        @else
          <a href="{{ route('restaurant.ingredients.purchases.index') }}"
             style="color:var(--muted);font-size:12px;text-decoration:none;">
            <i class="fa fa-arrow-left"></i> Purchase Orders
          </a>
        @endif
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="igrn-head__title">{{ $grn->grn_number }}</div>
        <div class="igrn-badge"><i class="fa fa-check" style="margin-right:4px;"></i> Received</div>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px;">
        {{ $grn->received_date->format('d M Y') }}
        @if($grn->purchaseOrder?->supplier) &bull; {{ $grn->purchaseOrder->supplier->name }} @endif
        &bull; {{ $grn->paymentMethodLabel() }}
        @if($grn->reference) &bull; Ref: {{ $grn->reference }} @endif
      </div>
    </div>
    <div>
      <a href="{{ route('restaurant.ingredients.purchases.index') }}" class="igrn-btn igrn-btn--outline">
        <i class="fa fa-list"></i> All Orders
      </a>
    </div>
  </div>

  {{-- Meta card --}}
  <div class="igrn-card">
    <div class="igrn-card__body">
      <div class="igrn-meta-grid">
        <div>
          <div class="igrn-meta-item__label">GRN Number</div>
          <div class="igrn-meta-item__val">{{ $grn->grn_number }}</div>
        </div>
        <div>
          <div class="igrn-meta-item__label">Purchase Order</div>
          <div class="igrn-meta-item__val">
            @if($grn->purchaseOrder)
              <a href="{{ route('restaurant.ingredients.purchases.show', $grn->purchaseOrder) }}"
                 style="color:var(--primary);text-decoration:none;">{{ $grn->purchaseOrder->po_number }}</a>
            @else
              —
            @endif
          </div>
        </div>
        <div>
          <div class="igrn-meta-item__label">Supplier</div>
          <div class="igrn-meta-item__val">{{ $grn->purchaseOrder?->supplier?->name ?? '—' }}</div>
        </div>
        <div>
          <div class="igrn-meta-item__label">Received Date</div>
          <div class="igrn-meta-item__val">{{ $grn->received_date->format('d M Y') }}</div>
        </div>
        <div>
          <div class="igrn-meta-item__label">Payment Method</div>
          <div class="igrn-meta-item__val">{{ $grn->paymentMethodLabel() }}</div>
        </div>
        <div>
          <div class="igrn-meta-item__label">Total Value</div>
          <div class="igrn-meta-item__val" style="font-size:16px;color:var(--primary);">
            {{ $currency }}{{ number_format((float)$grn->total, 2) }}
          </div>
        </div>
      </div>
      @if($grn->notes)
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);">
          {{ $grn->notes }}
        </div>
      @endif
    </div>
  </div>

  {{-- Items --}}
  <div class="igrn-card">
    <div class="igrn-card__head">
      <i class="fa fa-box" style="margin-right:6px;color:var(--primary);"></i> Received Items
    </div>
    <table class="igrn-table">
      <thead>
        <tr>
          <th>Ingredient</th>
          <th class="num">Qty Received</th>
          <th class="num">Unit Cost</th>
          <th class="num">Line Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($grn->items as $item)
          <tr>
            <td>
              <div style="font-weight:700;">{{ $item->ingredient?->name }}</div>
              <div style="font-size:11px;color:var(--muted);">{{ $item->ingredient?->unit }}</div>
            </td>
            <td class="num" style="font-weight:700;">{{ (float)$item->quantity_received }}</td>
            <td class="num">{{ $currency }}{{ number_format((float)$item->unit_cost, 4) }}</td>
            <td class="num" style="font-weight:700;">{{ $currency }}{{ number_format((float)$item->line_total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="text-align:right;font-size:12px;font-weight:800;padding:10px 12px;border-top:1.5px solid var(--border);color:var(--muted);">TOTAL</td>
          <td class="num" style="font-size:15px;font-weight:900;border-top:1.5px solid var(--border);">
            {{ $currency }}{{ number_format((float)$grn->total, 2) }}
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  {{-- Stock impact note --}}
  <div style="background:color-mix(in srgb,#16a34a 6%,var(--bg));border:1px solid #86efac;border-radius:12px;padding:14px 18px;font-size:13px;color:#15803d;">
    <i class="fa fa-check-circle" style="margin-right:6px;"></i>
    <strong>Stock applied.</strong> Ingredient quantities were increased when this GRN was created.
    Cost per unit has been updated to the latest purchase price.
  </div>

</div>
@endsection
