<?php
$statusColorDark = [
    '#64748b' => '#94a3b8',
    '#3b82f6' => '#60a5fa',
    '#10b981' => '#4ade80',
    '#ef4444' => '#f87171',
    '#94a3b8' => '#94a3b8',
][$doc['statusColor']] ?? '#94a3b8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:{{ $geomW }}px;{{ $geomMinH }}position:relative;display:flex;flex-direction:column}
.hdr{background:#0f172a;padding:30px {{ $mg['right'] }}mm 26px {{ $mg['left'] }}mm}
.hdr-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}
.biz-n{font-size:22px;font-weight:900;color:#fff;letter-spacing:-.01em}
.biz-i{font-size:10px;color:rgba(255,255,255,.45);margin-top:5px;line-height:1.7}
.il{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:{{ $accent }};margin-bottom:5px;text-align:right}
.in{font-size:28px;font-weight:900;color:#fff;text-align:right;letter-spacing:-.01em}
.hdr-rule{height:1px;background:{{ $accent }};opacity:.45;margin-bottom:16px}
.hdr-meta{display:flex;gap:0}
.hm{border-left:2px solid {{ $accent }};padding:0 0 0 12px;margin-right:24px}
.hml{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.4);margin-bottom:3px}
.hmv{font-size:12px;font-weight:700;color:#fff}
.body{flex:1;padding:22px {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm}
.bt{margin-bottom:20px;padding:13px 15px;border:1px solid #e2e8f0;border-radius:6px;border-left:3px solid {{ $accent }}}
.btl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.btn{font-size:14px;font-weight:800;margin-bottom:3px}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{background:#0f172a;color:{{ $accent }};font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 6px}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 6px;border-bottom:1px solid #e2e8f0}
tbody tr:nth-child(even) td{background:#f8fafc}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 248px;gap:20px}
.nb{padding:13px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55;white-space:pre-line}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{font-size:15px;font-weight:900;border-top:2px solid {{ $accent }};margin-top:4px;padding-top:9px}
.gr span:last-child{color:{{ $accent }}}
.ft{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
.print-bar{display:flex;gap:10px;margin-bottom:14px}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accent }};border-color:{{ $accent }};color:#0f172a;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
@media print{.no-print{display:none!important}}
{!! $hdrCss !!}
</style>
</head>
<body>
@if($letterheadCanvasJson)<img id="lhHeaderImg" style="display:none;width:100%;vertical-align:top;" alt="">@endif
<div class="pg">
@unless($hideActions ?? false)
<div class="print-bar no-print" style="padding:10px {{ $mg['right'] }}mm 0 {{ $mg['left'] }}mm">
    <a href="{{ $backUrl ?? url()->previous() }}" class="print-btn print-btn--back">← Back</a>
    <button type="button" onclick="window.print()" class="print-btn print-btn--primary"><i class="fa fa-print"></i> Print / Save PDF</button>
</div>
@endunless
<div class="hdr">
  <div class="hdr-top">
    <div id="lhBizFallback">
        <div class="biz-n">{{ $business->name }}</div>
        @if($mainBranch?->address)<div class="biz-i">{{ $mainBranch->address }}</div>@endif
    </div>
    <div><div class="il">Invoice</div><div class="in">{{ $doc['invNumber'] }}</div></div>
  </div>
  <div class="hdr-rule"></div>
  <div class="hdr-meta">
    <div class="hm"><div class="hml">Issue Date</div><div class="hmv">{{ $doc['issueDate'] }}</div></div>
    <div class="hm"><div class="hml">Due Date</div><div class="hmv">{{ $doc['dueDate'] }}</div></div>
    <div class="hm"><div class="hml">Status</div><div class="hmv" style="color:{{ $statusColorDark }}">{{ $doc['statusLabel'] }}</div></div>
  </div>
</div>
<div class="body">
  <div class="bt">
      <div class="btl">Billed To</div>
      <div class="btn">{{ $doc['customerName'] }}</div>
  </div>
  <table>
    <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:90px">Unit Price</th><th class="r" style="width:60px">Disc</th><th class="r" style="width:70px">Tax</th><th class="r" style="width:90px">Total</th></tr></thead>
    <tbody>@include('sales::invoices.templates._item-rows', ['doc' => $doc, 'currency' => $currency, 'italic' => false])</tbody>
  </table>
  <div class="bot">
    <div class="nb"><div class="nl">Notes</div><div class="nt">{{ $doc['notes'] }}</div></div>
    <div>
      @include('sales::invoices.templates._totals-rows', ['doc' => $doc, 'currency' => $currency])
      <div class="tr gr"><span>Total Due</span><span>{{ number_format($doc['total'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span></div>
    </div>
  </div>
  <div class="ft"><span>{{ $doc['invNumber'] }} · {{ $business->name }}</span><span>{{ $business->name }}</span></div>
</div>
</div>
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
