<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:{{ $geomW }}px;{{ $geomMinH }}position:relative;display:flex;flex-direction:column}
.banner{background:{{ $accent }};padding:28px {{ $mg['right'] }}mm 26px {{ $mg['left'] }}mm;display:flex;justify-content:space-between;align-items:flex-end}
.b-biz{font-size:20px;font-weight:900;color:#fff}.b-addr{font-size:10px;color:rgba(255,255,255,.7);margin-top:4px;line-height:1.5}
.b-num{font-size:38px;font-weight:900;color:#fff;letter-spacing:-.02em;line-height:1;text-align:right}
.b-lbl{font-size:10px;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.15em;font-weight:700;margin-bottom:4px;text-align:right}
.body{padding:22px {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm;flex:1}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
.card{padding:13px 15px;border:1.5px solid #e2e8f0;border-radius:7px}
.cl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.cn{font-size:14px;font-weight:800;margin-bottom:3px}.ci{font-size:11px;color:#64748b;line-height:1.5}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:8px 6px;border-bottom:2px solid {{ $accent }}}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 6px;border-bottom:1px solid #f1f5f9}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 255px;gap:18px}
.nb{padding:13px;border:1.5px solid #e2e8f0;border-radius:7px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55;white-space:pre-line}
.tc{border:1.5px solid #e2e8f0;border-radius:7px;overflow:hidden;padding:0 13px}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:8px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{background:{{ $accent }};color:#fff!important;font-weight:900;font-size:14px;margin:0 -13px;padding:8px 13px!important}.gr span{color:#fff!important}
.print-bar{display:flex;gap:10px;padding:10px {{ $mg['right'] }}mm 0 {{ $mg['left'] }}mm}
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
<div class="banner">
  <div id="lhBizFallback">
      <div class="b-biz">{{ $business->name }}</div>
      @if($mainBranch?->address)<div class="b-addr">{{ $mainBranch->address }}</div>@endif
  </div>
  <div><div class="b-lbl">Invoice</div><div class="b-num">{{ $doc['invNumber'] }}</div></div>
</div>
<div class="body">
  <div class="cards">
    <div class="card">
        <div class="cl">Billed To</div>
        <div class="cn">{{ $doc['customerName'] }}</div>
    </div>
    <div class="card"><div class="cl">Invoice Details</div><div class="ci" style="line-height:1.9"><b>Issue Date</b> &nbsp; {{ $doc['issueDate'] }}<br><b>Due Date</b> &nbsp;&nbsp; {{ $doc['dueDate'] }}<br><b>Status</b> &nbsp;&nbsp;&nbsp;&nbsp; <span style="color:{{ $doc['statusColor'] }};font-weight:700">{{ $doc['statusLabel'] }}</span></div></div>
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
</div>
</div>
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
