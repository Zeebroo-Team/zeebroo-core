<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {{ $doc['invNumber'] }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#1e293b;background:#fff;}
.print-page{width:{{ $geomW }}px;{{ $geomMinH }}margin:0 auto;position:relative;padding:{{ $mg['top'] }}mm {{ $mg['right'] }}mm {{ $mg['bottom'] }}mm {{ $mg['left'] }}mm;}

@media print{
    .no-print{display:none!important;}
    body{padding:0;}
}
{!! $hdrCss !!}

.inv-header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding:20px 0 16px;border-bottom:2px solid {{ $accent }};margin-bottom:20px;}
.inv-header__business{font-size:20px;font-weight:800;color:{{ $accent }};}
.inv-header__meta p{margin:2px 0;font-size:12px;color:#64748b;}
.inv-header__meta strong{color:#1e293b;}

.inv-doc-title{display:inline-block;font-size:13px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
    padding:5px 16px;border-radius:999px;background:color-mix(in srgb,{{ $accent }} 14%,#fff);
    border:1.5px solid {{ $accent }};color:{{ $accent }};margin-bottom:12px;}

.inv-parties{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.inv-party__title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:4px;}
.inv-party p{margin:2px 0;font-size:12.5px;color:#1e293b;}
.inv-party .muted{color:#64748b;}

table{width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:16px;}
thead th{background:{{ $accent }};color:#fff;font-weight:700;padding:8px 10px;text-align:left;}
thead th.right,thead th.r{text-align:right;}
tbody tr:nth-child(even){background:#f8fafc;}
tbody td{padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#1e293b;}
tbody td.right,td.r{text-align:right;}
td.n{color:#94a3b8;text-align:center;width:26px;}
td.b{font-weight:700;}
.ds{font-size:10px;color:#94a3b8;display:block;margin-top:1px;}

.inv-totals{display:flex;justify-content:flex-end;margin-bottom:20px;}
.inv-totals-inner{width:260px;}
.tr{display:flex;justify-content:space-between;padding:5px 0;font-size:12.5px;border-bottom:1px solid #e2e8f0;}
.tr:last-child{border-bottom:none;}
.tr span:first-child{color:#64748b;}
.gr{font-size:15px;font-weight:800;border-bottom:none;padding-top:8px;border-top:2px solid {{ $accent }};margin-top:4px;}
.gr span:last-child{color:{{ $accent }};}

.inv-notes{background:#f8fafc;border-left:3px solid {{ $accent }};padding:10px 14px;border-radius:0 8px 8px 0;margin-bottom:20px;}
.inv-notes__title{font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px;}
.inv-notes p{font-size:12px;color:#475569;white-space:pre-line;}

.inv-footer{border-top:1px solid #e2e8f0;padding-top:10px;font-size:11px;color:#94a3b8;text-align:center;}

.print-btn-bar{display:flex;gap:10px;margin-bottom:20px;}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accent }};border-color:{{ $accent }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
</style>
</head>
<body>
@if($letterheadCanvasJson)<img id="lhHeaderImg" style="display:none;width:100%;vertical-align:top;" alt="">@endif
<div class="print-page">

    @unless($hideActions ?? false)
    <div class="print-btn-bar no-print">
        <a href="{{ $backUrl ?? url()->previous() }}" class="print-btn print-btn--back">← Back</a>
        <button type="button" onclick="window.print()" class="print-btn print-btn--primary">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
    </div>
    @endunless

    <div class="inv-header">
        <div id="lhBizFallback">
            <div class="inv-header__business">{{ $business->name }}</div>
            @if($mainBranch)
                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                    @if($mainBranch->address) {{ $mainBranch->address }}<br> @endif
                    @if($mainBranch->phone) {{ $mainBranch->phone }} @endif
                </div>
            @endif
        </div>
        <div class="inv-header__meta" style="text-align:right;">
            <span class="inv-doc-title">Invoice</span>
            <p><strong>{{ $doc['invNumber'] }}</strong></p>
            <p>Issue date: <strong>{{ $doc['issueDate'] }}</strong></p>
            @if($doc['dueDate'] !== '—')
                <p>Payment due: <strong>{{ $doc['dueDate'] }}</strong></p>
            @endif
            <p style="margin-top:6px;">
                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;
                    background:color-mix(in srgb,{{ $doc['statusColor'] }} 15%,#fff);
                    border:1px solid {{ $doc['statusColor'] }};color:{{ $doc['statusColor'] }};">
                    {{ $doc['statusLabel'] }}
                </span>
            </p>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <div class="inv-party__title">Bill To</div>
            <p style="font-weight:700;">{{ $doc['customerName'] }}</p>
            @foreach($doc['customerLines'] as $line)
                <p class="muted" style="white-space:pre-line;">{{ $line }}</p>
            @endforeach
        </div>
        <div class="inv-party">
            <div class="inv-party__title">From</div>
            <p style="font-weight:700;">{{ $business->name }}</p>
            @if($mainBranch?->address)<p class="muted" style="white-space:pre-line;">{{ $mainBranch->address }}</p>@endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th>Item / Description</th>
                <th class="r" style="width:8%;">Qty</th>
                <th class="r" style="width:13%;">Unit price</th>
                <th class="r" style="width:9%;">Disc</th>
                <th class="r" style="width:9%;">Tax</th>
                <th class="r" style="width:13%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @include('sales::invoices.templates._item-rows', ['doc' => $doc, 'currency' => $currency, 'italic' => false])
        </tbody>
    </table>

    <div class="inv-totals">
        <div class="inv-totals-inner">
            @include('sales::invoices.templates._totals-rows', ['doc' => $doc, 'currency' => $currency])
            <div class="tr gr">
                <span>Total Due</span>
                <span>{{ number_format($doc['total'], 2) }}{{ filled($currency) ? ' '.$currency : '' }}</span>
            </div>
        </div>
    </div>

    <div class="inv-notes">
        <div class="inv-notes__title">Notes &amp; Payment Terms</div>
        <p>{{ $doc['notes'] }}</p>
    </div>

    <div class="inv-footer">
        {{ $business->name }} &mdash; {{ $doc['invNumber'] }}
    </div>
</div>
@include('sales::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson])
</body>
</html>
