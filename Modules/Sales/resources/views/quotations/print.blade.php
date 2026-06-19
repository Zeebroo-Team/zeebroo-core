<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotation {{ $quotation->quote_number }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#1e293b;background:#fff;padding:24px 32px;}
.print-page{max-width:780px;margin:0 auto;}

/* Letterhead zones */
.lh-print-zone{display:none;}
@media print{
    .lh-print-zone{display:block;}
    .no-print{display:none!important;}
    body{padding:0;}
    @page{margin:12mm 14mm;}
}

.qt-header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding:20px 0 16px;border-bottom:2px solid #e2e8f0;margin-bottom:20px;}
.qt-header__business{font-size:20px;font-weight:800;color:#1e293b;}
.qt-header__meta p{margin:2px 0;font-size:12px;color:#64748b;}
.qt-header__meta strong{color:#1e293b;}

.qt-doc-title{display:inline-block;font-size:13px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;
    padding:5px 16px;border-radius:999px;background:color-mix(in srgb,{{ $accentColor }} 14%,#fff);
    border:1.5px solid {{ $accentColor }};color:{{ $accentColor }};margin-bottom:12px;}

.qt-parties{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.qt-party__title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:4px;}
.qt-party p{margin:2px 0;font-size:12.5px;color:#1e293b;}
.qt-party .muted{color:#64748b;}

table{width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:16px;}
thead th{background:{{ $accentColor }};color:#fff;font-weight:700;padding:8px 10px;text-align:left;}
thead th.right{text-align:right;}
tbody tr:nth-child(even){background:#f8fafc;}
tbody td{padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#1e293b;}
tbody td.right{text-align:right;}

.qt-totals{display:flex;justify-content:flex-end;margin-bottom:20px;}
.qt-totals-inner{width:260px;}
.qt-totals-inner .row{display:flex;justify-content:space-between;padding:5px 0;font-size:12.5px;border-bottom:1px solid #e2e8f0;}
.qt-totals-inner .row.total{font-size:15px;font-weight:800;border-bottom:none;padding-top:8px;}

.qt-notes{background:#f8fafc;border-left:3px solid {{ $accentColor }};padding:10px 14px;border-radius:0 8px 8px 0;margin-bottom:20px;}
.qt-notes__title{font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:4px;}
.qt-notes p{font-size:12px;color:#475569;white-space:pre-line;}

.qt-footer{border-top:1px solid #e2e8f0;padding-top:10px;font-size:11px;color:#94a3b8;text-align:center;}

.print-btn-bar{display:flex;gap:10px;margin-bottom:20px;}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accentColor }};border-color:{{ $accentColor }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
</style>
</head>
<body>
<div class="print-page">

    {{-- Letterhead header (print only) --}}
    <div id="lhHeaderZone" class="lh-print-zone">
        <img id="lhHeaderImg" src="" alt="" style="width:100%;display:block;" onerror="this.style.display='none'">
    </div>

    {{-- Action bar (screen only) --}}
    <div class="print-btn-bar no-print">
        <a href="{{ route('sales.quotations.show', $quotation) }}" class="print-btn print-btn--back">← Back</a>
        <button id="lhPrintBtn" type="button" onclick="window.print()" class="print-btn print-btn--primary">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
    </div>

    {{-- Document header --}}
    <div class="qt-header">
        <div>
            <div class="qt-header__business">{{ $business->name }}</div>
            @if($mainBranch)
                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                    @if($mainBranch->address) {{ $mainBranch->address }}<br> @endif
                    @if($mainBranch->phone) {{ $mainBranch->phone }} @endif
                </div>
            @endif
        </div>
        <div class="qt-header__meta" style="text-align:right;">
            <span class="qt-doc-title">Quotation</span>
            <p><strong>{{ $quotation->quote_number }}</strong></p>
            <p>Date: <strong>{{ $quotation->quote_date->format('M j, Y') }}</strong></p>
            @if($quotation->expiry_date)
                <p>Expires: <strong>{{ $quotation->expiry_date->format('M j, Y') }}</strong></p>
            @endif
            @if($quotation->reference)
                <p>Ref: <strong>{{ $quotation->reference }}</strong></p>
            @endif
            <p style="margin-top:6px;">
                <span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;
                    background:color-mix(in srgb,{{ $quotation->statusColor() }} 15%,#fff);
                    border:1px solid {{ $quotation->statusColor() }};color:{{ $quotation->statusColor() }};">
                    {{ $quotation->statusLabel() }}
                </span>
            </p>
        </div>
    </div>

    {{-- Bill To --}}
    <div class="qt-parties">
        <div class="qt-party">
            <div class="qt-party__title">Bill To</div>
            @if($quotation->customer)
                <p style="font-weight:700;">{{ $quotation->customer->name }}</p>
                @if($quotation->customer->contact_name)<p class="muted">{{ $quotation->customer->contact_name }}</p>@endif
                @if($quotation->customer->email)<p class="muted">{{ $quotation->customer->email }}</p>@endif
                @if($quotation->customer->phone)<p class="muted">{{ $quotation->customer->phone }}</p>@endif
                @if($quotation->customer->address)<p class="muted" style="white-space:pre-line;">{{ $quotation->customer->address }}</p>@endif
            @else
                <p class="muted">—</p>
            @endif
        </div>
        <div class="qt-party">
            <div class="qt-party__title">From</div>
            <p style="font-weight:700;">{{ $business->name }}</p>
            @if($mainBranch?->address)<p class="muted" style="white-space:pre-line;">{{ $mainBranch->address }}</p>@endif
        </div>
    </div>

    {{-- Items table --}}
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
            @foreach($quotation->items as $item)
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

    {{-- Totals --}}
    <div class="qt-totals">
        <div class="qt-totals-inner">
            <div class="row"><span>Subtotal</span><span>{{ $currency }} {{ number_format($quotation->subtotal, 2) }}</span></div>
            @if($quotation->discount_amount > 0)
                <div class="row"><span>Discount</span><span style="color:#ef4444;">− {{ $currency }} {{ number_format($quotation->discount_amount, 2) }}</span></div>
            @endif
            @if($quotation->tax_amount > 0)
                <div class="row"><span>Tax</span><span>+ {{ $currency }} {{ number_format($quotation->tax_amount, 2) }}</span></div>
            @endif
            <div class="row total">
                <span>Total</span>
                <span style="color:{{ $accentColor }};">{{ $currency }} {{ number_format($quotation->total, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    @if($quotation->notes)
    <div class="qt-notes">
        <div class="qt-notes__title">Notes &amp; Terms</div>
        <p>{{ $quotation->notes }}</p>
    </div>
    @endif

    {{-- Footer --}}
    <div class="qt-footer">
        This quotation was generated by {{ $business->name }}. Valid until
        {{ $quotation->expiry_date ? $quotation->expiry_date->format('M j, Y') : 'further notice' }}.
    </div>

    {{-- Letterhead footer (print only) --}}
    <div id="lhFooterZone" class="lh-print-zone">
        <img id="lhFooterImg" src="" alt="" style="width:100%;display:block;position:fixed;bottom:0;left:0;" onerror="this.style.display='none'">
    </div>
</div>

@if($letterheadCanvasJson)
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    const CANVAS_JSON = @json($letterheadCanvasJson);
    const btn = document.getElementById('lhPrintBtn');
    if (btn) btn.disabled = true, btn.textContent = 'Preparing…';

    const canvasEl = document.createElement('canvas');
    canvasEl.style.cssText = 'position:fixed;left:-9999px;top:-9999px;visibility:hidden;';
    document.body.appendChild(canvasEl);

    const fc = new fabric.Canvas(canvasEl, { enableRetinaScaling: false, renderOnAddRemove: false });

    fc.loadFromJSON(CANVAS_JSON, () => {
        fc.renderAll();
        try {
            const cw = fc.getWidth(), ch = fc.getHeight();
            const headerH = Math.round(ch * 0.115);
            const headerUrl = fc.toDataURL({ left:0, top:0, width:cw, height:headerH, enableRetinaScaling:false });
            const headerImg = document.getElementById('lhHeaderImg');
            if (headerImg) headerImg.src = headerUrl;

            const footerY = Math.round(ch * 0.893);
            const footerUrl = fc.toDataURL({ left:0, top:footerY, width:cw, height:ch - footerY, enableRetinaScaling:false });
            const footerImg = document.getElementById('lhFooterImg');
            if (footerImg) footerImg.src = footerUrl;
        } catch (e) {}

        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-print"></i> Print / Save PDF'; }
        fc.dispose();
        canvasEl.remove();
    });
})();
</script>
@else
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
@endif
</body>
</html>
