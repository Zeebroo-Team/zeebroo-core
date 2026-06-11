<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Purchase Order – {{ $purchase->po_number ?? 'PO' }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Inter,system-ui,sans-serif;font-size:13px;color:#1e293b;background:#fff;}
.po-wrap{padding:22px 40px;}
.po-title-row{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;}
.po-title{font-size:22px;font-weight:800;color:#1e293b;letter-spacing:-.02em;}
.po-subtitle{font-size:12px;color:#64748b;margin-top:3px;}
.po-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1.5px solid {{ $accentColor }};color:{{ $accentColor }};background:color-mix(in srgb,{{ $accentColor }} 8%,white);margin-top:6px;}
.po-meta-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:20px;}
.po-meta-item__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:3px;}
.po-meta-item__value{font-size:13px;font-weight:600;color:#1e293b;}
table{width:100%;border-collapse:collapse;margin-bottom:16px;}
thead tr{background:{{ $accentColor }};color:#fff;}
thead th{padding:9px 12px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
thead th:last-child{text-align:right;}
tbody tr{border-bottom:1px solid #f1f5f9;}
tbody tr:nth-child(even){background:#f8fafc;}
tbody td{padding:9px 12px;font-size:12.5px;color:#334155;}
tbody td:last-child{text-align:right;font-weight:700;color:#1e293b;}
tfoot td{padding:10px 12px;font-size:13px;font-weight:700;border-top:2px solid {{ $accentColor }};color:#1e293b;}
tfoot td:last-child{text-align:right;font-size:16px;font-weight:800;color:{{ $accentColor }};}
.po-notes{background:#f8fafc;border-left:3px solid {{ $accentColor }};border-radius:0 8px 8px 0;padding:10px 14px;margin-bottom:18px;font-size:12px;color:#475569;line-height:1.55;}
@media print{
    body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .no-print{display:none!important;}
    @page{margin:0;size:A4 portrait;}
}
.print-bar{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:10px 40px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
.print-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:{{ $accentColor }};color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;}
.print-btn:disabled{opacity:.55;cursor:not-allowed;}
.back-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;background:#fff;color:#475569;border:1px solid #cbd5e1;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;}
</style>
</head>
<body>

<div class="print-bar no-print">
    <a href="{{ route('purchase.show', $purchase) }}" class="back-btn">&#8592; Back</a>
    <button class="print-btn" id="lhPrintBtn" onclick="window.print()">&#128438;&nbsp; Print / Save PDF</button>
</div>

{{-- ── LETTERHEAD HEADER (dynamic canvas rendering) ── --}}
@include('purchase::partials.print-letterhead')

{{-- ── PURCHASE ORDER BODY ── --}}
<div class="po-wrap">
    <div class="po-title-row">
        <div>
            <div class="po-title">{{ $purchase->po_number ?? 'Purchase Order' }}</div>
            @if($purchase->supplier)
                <div class="po-subtitle">Supplier: <strong>{{ $purchase->supplier->name }}</strong></div>
            @endif
            <div class="po-status">{{ $purchase->statusLabel() }}</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Date issued</div>
            <div style="font-size:14px;font-weight:700;color:#1e293b;">{{ $purchase->purchase_date->format('M j, Y') }}</div>
            @if($purchase->expected_delivery_date)
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Expected: {{ $purchase->expected_delivery_date->format('M j, Y') }}</div>
            @endif
        </div>
    </div>

    @php
        $metaItems = array_filter([
            ['label' => 'PO Number',    'value' => $purchase->po_number ?? '—'],
            $purchase->reference ? ['label' => 'Supplier Ref', 'value' => $purchase->reference] : null,
            $purchase->supplier  ? ['label' => 'Supplier',     'value' => $purchase->supplier->name] : null,
            ['label' => 'Currency',     'value' => $currency ?: 'Default'],
        ]);
    @endphp
    @if(count($metaItems))
    <div class="po-meta-grid">
        @foreach($metaItems as $meta)
        <div class="po-meta-item">
            <div class="po-meta-item__label">{{ $meta['label'] }}</div>
            <div class="po-meta-item__value">{{ $meta['value'] }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($purchase->notes)
        <div class="po-notes">{{ $purchase->notes }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th><th>Product</th><th>SKU</th>
                <th>Qty Ordered</th>
                <th>Unit Cost @if($currency)({{ $currency }})@endif</th>
                <th>Line Total @if($currency)({{ $currency }})@endif</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $i => $item)
            @php $ordered = (float) $item->quantity; @endphp
            <tr>
                <td style="color:#94a3b8;font-size:11px;">{{ $i + 1 }}</td>
                <td><strong style="color:#1e293b;">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</strong></td>
                <td style="color:#94a3b8;font-size:11px;font-family:monospace;">{{ $item->product?->sku ?? '—' }}</td>
                <td>{{ rtrim(rtrim(number_format($ordered,3,'.','' ),'0'),'.') }} {{ $item->product?->productUnit?->name ?? '' }}</td>
                <td>{{ number_format((float)$item->unit_cost, 2) }}</td>
                <td>{{ number_format((float)$item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;">Total Amount</td>
                <td>{{ number_format((float)$purchase->total, 2) }}@if($currency) {{ $currency }}@endif</td>
            </tr>
        </tfoot>
    </table>

    <div style="display:flex;justify-content:flex-end;margin-top:30px;gap:60px;">
        <div style="text-align:center;">
            <div style="width:160px;border-top:1.5px solid #cbd5e1;padding-top:6px;font-size:11px;color:#64748b;">Authorised Signature</div>
        </div>
        <div style="text-align:center;">
            <div style="width:160px;border-top:1.5px solid #cbd5e1;padding-top:6px;font-size:11px;color:#64748b;">Received By</div>
        </div>
    </div>
</div>

{{-- ── LETTERHEAD FOOTER ── --}}
<div id="lhFooterZone">
    <img id="lhFooterImg" style="display:none;width:100%;vertical-align:top;max-width:100%;" alt="">
    <div id="lhFooterFallback">
        <div style="height:1px;background:rgba(0,0,0,.08);margin:0 40px;"></div>
        <div style="height:4px;background:{{ $accentColor }};opacity:.35;margin:0 40px;"></div>
        @php
            $footerLines = array_values(array_filter([
                $mainBranch?->address ?? '',
                $mainBranch?->phone   ?? '',
                $mainBranch?->email   ?? '',
            ]));
        @endphp
        @if(count($footerLines))
            <div style="text-align:center;font-size:10px;color:#475569;padding:8px 40px 14px;line-height:1.5;">
                {{ implode('   ·   ', $footerLines) }}
            </div>
        @endif
        <div style="height:10px;background:{{ $accentColor }};opacity:.12;"></div>
    </div>
</div>

@include('purchase::partials.print-letterhead-script')
</body>
</html>
