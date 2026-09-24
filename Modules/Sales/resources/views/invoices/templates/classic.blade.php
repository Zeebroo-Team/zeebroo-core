<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;font-size:12px;color:#0f172a;background:#fff}
.pg{width:{{ $geomW }}px;position:relative;padding:{{ $mg['top'] }}mm {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm}
.top{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:2.5px solid {{ $accent }};margin-bottom:24px}
.bn{font-size:21px;font-weight:900;color:{{ $accent }}}.bi{font-size:10px;color:#64748b;margin-top:5px;line-height:1.6}
.it{font-size:30px;font-weight:900;text-transform:uppercase;color:{{ $accent }};text-align:right}
.in{font-size:12px;color:#64748b;text-align:right;margin-top:4px}
.meta{display:grid;grid-template-columns:1fr auto;gap:24px;margin-bottom:22px}
.btl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:6px}
.btn{font-size:14px;font-weight:800}
.dts{min-width:205px}
.dr{display:flex;justify-content:space-between;font-size:11px;padding:6px 0;border-bottom:1px dashed #e2e8f0}
.dr:last-child{border-bottom:none}.dk{color:#94a3b8}.dv{font-weight:700}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
thead th{background:{{ $accent }};color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:8px 10px;border-bottom:1px solid #e2e8f0}
tbody tr:nth-child(even) td{background:#f8fafc}
td.n{color:#94a3b8;text-align:center;width:26px}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px}
.bot{display:grid;grid-template-columns:1fr 248px;gap:20px}
.nb{padding:13px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc}
.nl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:5px}
.nt{font-size:11px;color:#64748b;line-height:1.55;white-space:pre-line}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;border-bottom:1px solid #f1f5f9;color:#475569}
.tr:last-child{border-bottom:none}.tr span:first-child{color:#64748b}
.gr{font-size:15px;font-weight:900;border-top:2.5px solid {{ $accent }};margin-top:4px;padding-top:9px}
.gr span:last-child{color:{{ $accent }}}
.ft{margin-top:22px;padding-top:12px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
.print-bar{display:flex;gap:10px;margin-bottom:20px}
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
<div class="top">
  <div id="lhBizFallback">
      <div class="bn">{{ $business->name }}</div>
      @if($mainBranch?->address)<div class="bi">{{ $mainBranch->address }}</div>@endif
  </div>
  <div><div class="it">Invoice</div><div class="in">{{ $doc['invNumber'] }} · {{ $doc['issueDate'] }}</div></div>
</div>
<div class="meta">
  <div><div class="btl">Billed To</div><div class="btn">{{ $doc['customerName'] }}</div></div>
  <div class="dts">
    <div class="dr"><span class="dk">Issue Date</span><span class="dv">{{ $doc['issueDate'] }}</span></div>
    <div class="dr"><span class="dk">Due Date</span><span class="dv">{{ $doc['dueDate'] }}</span></div>
    <div class="dr"><span class="dk">Status</span><span class="dv" style="color:{{ $doc['statusColor'] }}">{{ $doc['statusLabel'] }}</span></div>
  </div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:100px">Unit Price</th><th class="r" style="width:60px">Disc</th><th class="r" style="width:70px">Tax</th><th class="r" style="width:100px">Total</th></tr></thead>
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
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
