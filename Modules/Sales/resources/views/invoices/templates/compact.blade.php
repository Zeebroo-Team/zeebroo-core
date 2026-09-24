<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:{{ $geomW }}px;position:relative;padding:{{ $mg['top'] }}mm {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm}
.topbar{background:{{ $accent }};border-radius:9px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.tb-biz{font-size:18px;font-weight:900;color:#fff}.tb-addr{font-size:10px;color:rgba(255,255,255,.72);margin-top:3px}
.tb-il{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.65);margin-bottom:3px;text-align:right}
.tb-in{font-size:22px;font-weight:900;color:#fff;text-align:right}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.gc{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:7px;border-left:3px solid {{ $accent }}}
.gcl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#94a3b8;margin-bottom:4px}
.gcv{font-size:12px;font-weight:800;color:#0f172a}
.bt-cell{grid-column:span 2;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:7px;border-left:3px solid {{ $accent }}}
table{width:100%;border-collapse:collapse;margin-bottom:18px}
thead th{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:{{ $accent }};padding:8px 6px;border-bottom:2px solid {{ $accent }};background:{{ $accent }}14}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:8px 6px;border-bottom:1px solid #f1f5f9}
tbody tr:nth-child(even) td{background:{{ $accent }}08}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 255px;gap:16px}
.nb{padding:12px;border:1.5px solid #e2e8f0;border-radius:7px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55;white-space:pre-line}
.tc{border:1.5px solid #e2e8f0;border-radius:7px;overflow:hidden;padding:0 12px}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:8px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{background:{{ $accent }};font-weight:900;font-size:14px;margin:0 -12px;padding:8px 12px!important}.gr span{color:#fff!important}
.ft{margin-top:16px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
.print-bar{display:flex;gap:10px;margin-bottom:14px}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accent }};border-color:{{ $accent }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
@media print{.no-print{display:none!important}}
{!! $hdrCss !!}
</style>
</head>
<body>
@if($letterheadCanvasJson)<img id="lhHeaderImg" style="display:none;width:100%;vertical-align:top;" alt="">@endif
<div class="pg">
@unless($hideActions ?? false)
<div class="print-bar no-print">
    <a href="{{ $backUrl ?? url()->previous() }}" class="print-btn print-btn--back">← Back</a>
    <button type="button" onclick="window.print()" class="print-btn print-btn--primary"><i class="fa fa-print"></i> Print / Save PDF</button>
</div>
@endunless
<div class="topbar">
  <div id="lhBizFallback">
      <div class="tb-biz">{{ $business->name }}</div>
      @if($mainBranch?->address)<div class="tb-addr">{{ $mainBranch->address }}</div>@endif
  </div>
  <div><div class="tb-il">Invoice</div><div class="tb-in">{{ $doc['invNumber'] }}</div></div>
</div>
<div class="grid4">
  <div class="bt-cell">
      <div class="gcl">Billed To</div>
      <div style="font-size:14px;font-weight:800;margin-bottom:3px">{{ $doc['customerName'] }}</div>
      @foreach($doc['customerLines'] as $line)<div class="ds">{{ $line }}</div>@endforeach
  </div>
  <div class="gc"><div class="gcl">Issue Date</div><div class="gcv">{{ $doc['issueDate'] }}</div></div>
  <div class="gc"><div class="gcl">Due Date</div><div class="gcv">{{ $doc['dueDate'] }}</div></div>
  <div class="gc"><div class="gcl">Status</div><div class="gcv" style="color:{{ $doc['statusColor'] }}">{{ $doc['statusLabel'] }}</div></div>
  <div class="gc"><div class="gcl">Amount</div><div class="gcv" style="color:{{ $accent }}">{{ number_format($doc['total'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</div></div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:90px">Unit Price</th><th class="r" style="width:60px">Disc</th><th class="r" style="width:70px">Tax</th><th class="r" style="width:90px">Total</th></tr></thead>
  <tbody>@include('sales::invoices.templates._item-rows', ['doc' => $doc, 'currency' => $currency, 'italic' => false])</tbody>
</table>
<div class="bot">
  <div class="nb"><div class="nl">Notes</div><div class="nt">{{ $doc['notes'] }}</div></div>
  <div class="tc">
    @include('sales::invoices.templates._totals-rows', ['doc' => $doc, 'currency' => $currency])
    <div class="tr gr"><span>Total Due</span><span>{{ number_format($doc['total'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span></div>
  </div>
</div>
<div class="ft"><span>{{ $doc['invNumber'] }} · {{ $business->name }}</span><span>{{ $doc['issueDate'] }}</span></div>
</div>
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
