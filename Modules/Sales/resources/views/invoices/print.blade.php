<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#1e293b;background:#fff;padding:24px 32px;}
.print-page{max-width:780px;margin:0 auto;}

.lh-print-zone{display:none;}
@media print{
    .lh-print-zone{display:block;}
    .no-print{display:none!important;}
    body{padding:0;}
    @page{margin:12mm 14mm;}
}

.inv-header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding:20px 0 16px;border-bottom:2px solid #e2e8f0;margin-bottom:20px;}
.inv-header__business{font-size:20px;font-weight:800;color:#1e293b;}
.inv-header__meta p{margin:2px 0;font-size:12px;color:#64748b;}
.inv-header__meta strong{color:#1e293b;}

.inv-doc-title{display:inline-block;font-size:13px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
    padding:5px 16px;border-radius:999px;background:color-mix(in srgb,{{ $accentColor }} 14%,#fff);
    border:1.5px solid {{ $accentColor }};color:{{ $accentColor }};margin-bottom:12px;}

.inv-parties{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.inv-party__title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:4px;}
.inv-party p{margin:2px 0;font-size:12.5px;color:#1e293b;}
.inv-party .muted{color:#64748b;}

table{width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:16px;}
thead th{background:{{ $accentColor }};color:#fff;font-weight:700;padding:8px 10px;text-align:left;}
thead th.right{text-align:right;}
tbody tr:nth-child(even){background:#f8fafc;}
tbody td{padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#1e293b;}
tbody td.right{text-align:right;}

.inv-totals{display:flex;justify-content:flex-end;margin-bottom:20px;}
.inv-totals-inner{width:260px;}
.inv-totals-inner .row{display:flex;justify-content:space-between;padding:5px 0;font-size:12.5px;border-bottom:1px solid #e2e8f0;}
.inv-totals-inner .row.total{font-size:15px;font-weight:800;border-bottom:none;padding-top:8px;}

.inv-notes{background:#f8fafc;border-left:3px solid {{ $accentColor }};padding:10px 14px;border-radius:0 8px 8px 0;margin-bottom:20px;}
.inv-notes__title{font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px;}
.inv-notes p{font-size:12px;color:#475569;white-space:pre-line;}

.inv-footer{border-top:1px solid #e2e8f0;padding-top:10px;font-size:11px;color:#94a3b8;text-align:center;}

.print-btn-bar{display:flex;gap:10px;margin-bottom:20px;}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accentColor }};border-color:{{ $accentColor }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
</style>
</head>
<body>
<div class="print-page">

    <div id="lhHeaderZone" class="lh-print-zone">
        <img id="lhHeaderImg" src="" alt="" style="width:100%;display:block;" onerror="this.style.display='none'">
    </div>

    <div class="print-btn-bar no-print">
        <a href="{{ route('sales.invoices.show', $invoice) }}" class="print-btn print-btn--back">← Back</a>
        <button type="button" onclick="window.print()" class="print-btn print-btn--primary">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
    </div>

    <div class="inv-header">
        <div>
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
            <p><strong>{{ $invoice->invoice_number }}</strong></p>
            <p>Issue date: <strong>{{ $invoice->issue_date->format('M j, Y') }}</strong></p>
            @if($invoice->due_date)
                <p>Payment due: <strong>{{ $invoice->due_date->format('M j, Y') }}</strong></p>
            @endif
            @if($invoice->reference)
                <p>Ref: <strong>{{ $invoice->reference }}</strong></p>
            @endif
            <p style="margin-top:6px;">
                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;
                    background:color-mix(in srgb,{{ $invoice->statusColor() }} 15%,#fff);
                    border:1px solid {{ $invoice->statusColor() }};color:{{ $invoice->statusColor() }};">
                    {{ $invoice->statusLabel() }}
                </span>
            </p>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <div class="inv-party__title">Bill To</div>
            @if($invoice->customer)
                <p style="font-weight:700;">{{ $invoice->customer->name }}</p>
                @if($invoice->customer->contact_name)<p class="muted">{{ $invoice->customer->contact_name }}</p>@endif
                @if($invoice->customer->email)<p class="muted">{{ $invoice->customer->email }}</p>@endif
                @if($invoice->customer->phone)<p class="muted">{{ $invoice->customer->phone }}</p>@endif
                @if($invoice->customer->address)<p class="muted" style="white-space:pre-line;">{{ $invoice->customer->address }}</p>@endif
            @else
                <p class="muted">—</p>
            @endif
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
                <th class="right" style="width:10%;">Qty</th>
                <th class="right" style="width:16%;">Unit price</th>
                <th class="right" style="width:16%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                @php
                    $isService     = $item->service_item_id && $item->serviceItem;
                    $boundProducts = $isService ? $item->serviceItem->products : collect();
                    $label         = $item->serviceItem?->name ?? $item->product?->name ?? ($item->description ?: '—');
                    $sublabel      = (!$item->serviceItem && $item->product && $item->description && $item->description !== $item->product->name)
                                   ? $item->description : null;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <strong>{{ $label }}</strong>
                        @if($isService)
                            <span style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:#ede9fe;color:#7c3aed;margin-left:5px;">Service</span>
                        @endif
                        @if($sublabel)
                            <br><span style="color:#64748b;font-size:11px;">{{ $sublabel }}</span>
                        @endif
                    </td>
                    <td class="right">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                    <td class="right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">{{ $currency }} {{ number_format($item->line_total, 2) }}</td>
                </tr>
                @foreach($boundProducts as $bp)
                    <tr style="background:#f8fafc;">
                        <td></td>
                        <td style="padding-left:24px;color:#64748b;font-size:11px;">
                            &#8627; {{ $bp->name }}@if($bp->sku) ({{ $bp->sku }})@endif
                        </td>
                        <td class="right" style="color:#64748b;font-size:11px;">
                            {{ rtrim(rtrim(number_format((float)$bp->pivot->qty, 3), '0'), '.') }}
                        </td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="inv-totals">
        <div class="inv-totals-inner">
            <div class="row"><span>Subtotal</span><span>{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</span></div>
            @if($invoice->discount_amount > 0)
                <div class="row"><span>Discount</span><span style="color:#ef4444;">− {{ $currency }} {{ number_format($invoice->discount_amount, 2) }}</span></div>
            @endif
            @if($invoice->tax_amount > 0)
                <div class="row"><span>Tax</span><span>+ {{ $currency }} {{ number_format($invoice->tax_amount, 2) }}</span></div>
            @endif
            <div class="row total">
                <span>Total</span>
                <span style="color:{{ $accentColor }};">{{ $currency }} {{ number_format($invoice->total, 2) }}</span>
            </div>
        </div>
    </div>

    @if($invoice->notes)
    <div class="inv-notes">
        <div class="inv-notes__title">Notes &amp; Payment Terms</div>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="inv-footer">
        {{ $business->name }} &mdash; Generated {{ now()->format('M j, Y') }}
    </div>
</div>

@if($letterheadCanvasJson)
<script>
(function () {
    try {
        var json = @json($letterheadCanvasJson);
        var objs = typeof json === 'string' ? JSON.parse(json).objects : json.objects;
        if (!Array.isArray(objs)) return;
        objs.forEach(function (obj) {
            if ((obj.type === 'image' || obj.type === 'Image') && obj.src) {
                var img = document.getElementById('lhHeaderImg');
                if (img) { img.src = obj.src; }
            }
        });
    } catch (e) {}
})();
</script>
@endif
</body>
</html>
