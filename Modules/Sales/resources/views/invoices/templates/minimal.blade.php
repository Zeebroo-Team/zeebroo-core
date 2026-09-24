<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,'Times New Roman',serif;font-size:12px;color:#1a1a1a;background:#fff}
.pg{width:{{ $geomW }}px;position:relative;padding:{{ $mg['top'] }}mm {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm}
.top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px}
.bn{font-size:18px;font-weight:700;font-family:Georgia,serif}
.bi{font-size:10px;color:#6b7280;margin-top:5px;line-height:1.7;font-family:Arial,sans-serif}
.inv-word{font-size:46px;font-weight:700;font-family:Georgia,serif;color:#e5e7eb;line-height:1;text-align:right;letter-spacing:-.02em}
.inv-ref{font-size:11px;color:#6b7280;text-align:right;margin-top:5px;font-family:Arial,sans-serif}
.rule{height:1.5px;background:#1a1a1a;margin-bottom:20px}
.meta{display:flex;justify-content:space-between;margin-bottom:28px}
.btl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.16em;color:#9ca3af;margin-bottom:5px;font-family:Arial,sans-serif}
.btn{font-size:15px;font-weight:700;font-family:Georgia,serif;margin-bottom:3px}
.bti{font-size:11px;color:#6b7280;font-family:Arial,sans-serif;line-height:1.6}
.dts{text-align:right}
.dr{display:flex;gap:18px;justify-content:flex-end;font-size:11px;padding:3px 0;font-family:Arial,sans-serif}
.dk{color:#9ca3af}.dv{font-weight:700;color:#1a1a1a}
table{width:100%;border-collapse:collapse;margin-bottom:24px;font-family:Arial,sans-serif}
thead th{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:#9ca3af;padding:0 6px 8px;border-bottom:1.5px solid #1a1a1a}
thead th.r{text-align:right}
tbody td{font-size:11px;padding:9px 6px;border-bottom:1px solid #e5e7eb;vertical-align:top}
td.n{color:#d1d5db;text-align:center;width:26px;font-style:italic}td.r{text-align:right}td.b{font-weight:700}
.ds{font-size:10px;color:#9ca3af;display:block;margin-top:1px;font-family:Arial,sans-serif}
.bot{display:grid;grid-template-columns:1fr 215px;gap:24px}
.nt{font-size:11px;color:#6b7280;line-height:1.65;font-family:Arial,sans-serif;border-top:1px solid #e5e7eb;padding-top:12px;white-space:pre-line}
.tr{display:flex;justify-content:space-between;font-size:11px;padding:5px 0;color:#6b7280;font-family:Arial,sans-serif}
.rule2{height:1px;background:#e5e7eb;margin:4px 0}
.gr{display:flex;justify-content:space-between;font-size:16px;font-weight:700;padding-top:8px;font-family:Georgia,serif;color:#1a1a1a;border-top:1.5px solid #1a1a1a;margin-top:4px}
.ft{margin-top:28px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:9px;color:#9ca3af;text-align:center;letter-spacing:.06em;font-family:Arial,sans-serif;text-transform:uppercase}
.print-bar{display:flex;gap:10px;margin-bottom:20px}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-family:Arial,sans-serif}
.print-btn--primary{background:{{ $accent }};border-color:{{ $accent }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e5e7eb;color:#6b7280;}
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
  <div><div class="inv-word">INVOICE</div><div class="inv-ref">{{ $doc['invNumber'] }} / {{ $doc['issueDate'] }}</div></div>
</div>
<div class="rule"></div>
<div class="meta">
  <div>
      <div class="btl">Billed To</div>
      <div class="btn">{{ $doc['customerName'] }}</div>
      @foreach($doc['customerLines'] as $line)<div class="bti">{{ $line }}</div>@endforeach
  </div>
  <div class="dts">
    <div class="dr"><span class="dk">Issued</span><span class="dv">{{ $doc['issueDate'] }}</span></div>
    <div class="dr"><span class="dk">Due</span><span class="dv">{{ $doc['dueDate'] }}</span></div>
    <div class="dr"><span class="dk">Status</span><span class="dv">{{ $doc['statusLabel'] }}</span></div>
  </div>
</div>
<table>
  <thead><tr><th style="width:26px;text-align:center">#</th><th>Description</th><th class="r" style="width:50px">Qty</th><th class="r" style="width:90px">Rate</th><th class="r" style="width:60px">Disc</th><th class="r" style="width:70px">Tax</th><th class="r" style="width:90px">Amount</th></tr></thead>
  <tbody>@include('sales::invoices.templates._item-rows', ['doc' => $doc, 'currency' => $currency, 'italic' => true])</tbody>
</table>
<div class="bot">
  <div class="nt">{{ $doc['notes'] }}</div>
  <div>
    @include('sales::invoices.templates._totals-rows', ['doc' => $doc, 'currency' => $currency])
    <div class="rule2"></div>
    <div class="gr"><span>Total</span><span>{{ number_format($doc['total'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span></div>
  </div>
</div>
<div class="ft">Invoice {{ $doc['invNumber'] }} &nbsp;·&nbsp; {{ $business->name }} &nbsp;·&nbsp; {{ $doc['issueDate'] }}</div>
</div>
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
