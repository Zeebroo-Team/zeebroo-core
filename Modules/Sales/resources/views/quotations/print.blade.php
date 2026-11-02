<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotation {{ $quotation->quote_number }}</title>
{{-- Same "Classic" layout as the desktop invoice/quotation print. Laid out with tables only,
     so the browser print page and the dompdf PDF (no flex / grid) render the same. --}}
<style>
{{-- dompdf treats an html / "*" margin as the page margin, which would wipe the @page margins below --}}
{{ !empty($pdf) ? 'body,div,span,p,b,strong,img,table,tr,th,td' : '*' }}{box-sizing:border-box;margin:0;padding:0;}
body{font-family:{!! !empty($pdf) ? "'DejaVu Sans'," : '' !!}'Segoe UI',Arial,sans-serif;font-size:11px;color:#0f172a;background:#fff;padding:{{ !empty($pdf) ? '0' : '24px 32px' }};}
.print-page{max-width:{{ !empty($pdf) ? 'none' : '780px' }};margin:0 auto;}
table{width:100%;border-collapse:collapse;}
@if(!empty($pdf))
/* dompdf: page margins, and only normal / bold weights exist (heavier ones fall back to a serif) */
@page{margin:16mm 15mm;}
body,b,strong,th{font-family:'DejaVu Sans',sans-serif;}
@endif

/* Letterhead zones */
.lh-print-zone{display:none;}
@media print{
    .lh-print-zone{display:block;}
    .no-print{display:none!important;}
    body{padding:0;}
    @page{margin:12mm 14mm;}
}

/* Header: document title left, business right, accent rule underneath */
.qt-top{border-bottom:2.5px solid {{ $accentColor }};margin-bottom:22px;}
.qt-top td{vertical-align:top;padding-bottom:18px;}
.qt-title{font-size:30px;font-weight:700;text-transform:uppercase;color:{{ $accentColor }};letter-spacing:.01em;}
.qt-num{font-size:12px;color:#64748b;margin-top:4px;}
.qt-biz{font-size:20px;font-weight:700;color:{{ $accentColor }};}
.qt-addr{font-size:10px;color:#64748b;margin-top:5px;line-height:1.6;white-space:pre-line;}
.qt-logo{max-height:56px;max-width:180px;margin-bottom:6px;}

/* Billed to + dates */
.qt-meta{margin-bottom:20px;}
.qt-meta > tbody > tr > td{vertical-align:top;}
.qt-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:6px;}
.qt-bill-name{font-size:14px;font-weight:700;}
.qt-bill-info{font-size:11px;color:#64748b;margin-top:3px;line-height:1.5;}
.qt-dates td{font-size:11px;padding:6px 0;border-bottom:1px dashed #e2e8f0;}
.qt-dates tr:last-child td{border-bottom:none;}
.qt-dates .k{color:#94a3b8;}
.qt-dates .v{font-weight:700;text-align:right;}

/* Items */
.qt-items{margin-bottom:20px;}
.qt-items thead th{background:{{ $accentColor }};color:#fff;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px;text-align:left;}
.qt-items tbody td{font-size:11px;padding:8px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;}
.qt-items tbody tr.alt td{background:#f8fafc;}
.qt-items .n{color:#94a3b8;text-align:center;}
.qt-items .r{text-align:right;}
.qt-items .b{font-weight:700;}
.qt-items .muted{color:#94a3b8;}
.qt-items .sub td{font-size:10px;color:#64748b;padding-top:4px;padding-bottom:4px;}

/* Notes + totals */
.qt-bottom > tbody > tr > td{vertical-align:top;}
.qt-notes{padding:13px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;}
.qt-notes-text{font-size:11px;color:#64748b;line-height:1.55;white-space:pre-line;}
.qt-totals td{font-size:11px;padding:5px 0;border-bottom:1px solid #f1f5f9;color:#64748b;}
.qt-totals td.v{text-align:right;color:#475569;}
.qt-totals tr.grand td{font-size:15px;font-weight:700;color:#0f172a;border-top:2.5px solid {{ $accentColor }};border-bottom:none;padding-top:9px;}
.qt-totals tr.grand td.v{color:{{ $accentColor }};}

.qt-footer{margin-top:22px;border-top:1px solid #e2e8f0;}
.qt-footer td{padding-top:12px;font-size:10px;color:#94a3b8;}

.print-btn-bar{display:flex;gap:10px;margin-bottom:20px;}
.print-btn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.print-btn--primary{background:{{ $accentColor }};border-color:{{ $accentColor }};color:#fff;}
.print-btn--back{background:transparent;border-color:#e2e8f0;color:#64748b;}
</style>
</head>
<body>
@php
    $fmtQty     = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
    $money      = fn ($n) => trim(number_format((float) $n, 2) . ' ' . $currency);
    $validUntil = $quotation->expiry_date ? $quotation->expiry_date->format('Y-m-d') : '—';
    $address    = $mainBranch?->address;
    $phone      = $mainBranch?->phone;
    // Discount / tax columns only when a line has one (lead quotations)
    $lineDiscounts = $quotation->items->contains(fn ($i) => (float) $i->discount_amount > 0);
    $lineTaxes     = $quotation->items->contains(fn ($i) => (float) $i->tax_amount > 0);
@endphp
<div class="print-page">

    @if(empty($pdf))
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
    @endif

    {{-- Document header --}}
    <table class="qt-top">
        <tr>
            <td>
                <div class="qt-title">Quotation</div>
                <div class="qt-num">
                    {{ $quotation->quote_number }}@if(($quotation->version ?? 1) > 1) (v{{ $quotation->version }})@endif
                    · {{ $quotation->quote_date->format('Y-m-d') }}
                </div>
            </td>
            <td style="text-align:right;">
                @if(!empty($logoSrc))<img src="{{ $logoSrc }}" alt="" class="qt-logo"><br>@endif
                <div class="qt-biz">{{ $business->name }}</div>
                @if($address || $phone)
                    <div class="qt-addr">{{ $address }}@if($address && $phone) · @endif @if($phone)Tel: {{ $phone }}@endif</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Billed to + dates --}}
    <table class="qt-meta">
        <tr>
            <td>
                <div class="qt-label">Billed To</div>
                @if($quotation->customer)
                    <div class="qt-bill-name">{{ $quotation->customer->name }}</div>
                    <div class="qt-bill-info">
                        @if($quotation->customer->contact_name){{ $quotation->customer->contact_name }}<br>@endif
                        @if($quotation->customer->email){{ $quotation->customer->email }}<br>@endif
                        @if($quotation->customer->phone){{ $quotation->customer->phone }}<br>@endif
                        @if($quotation->customer->address)<span style="white-space:pre-line;">{{ $quotation->customer->address }}</span>@endif
                    </div>
                @elseif($quotation->lead_id && $quotation->lead)
                    {{-- A lead not converted to a customer yet --}}
                    <div class="qt-bill-name">{{ $quotation->lead->company ?: $quotation->lead->name }}</div>
                    <div class="qt-bill-info">
                        @if($quotation->lead->company){{ $quotation->lead->name }}<br>@endif
                        @if($quotation->lead->email){{ $quotation->lead->email }}<br>@endif
                        @if($quotation->lead->phone){{ $quotation->lead->phone }}@endif
                    </div>
                @else
                    <div class="qt-bill-name">Walk-in Customer</div>
                @endif
            </td>
            <td style="width:220px;">
                <table class="qt-dates">
                    <tr><td class="k">Issue Date</td><td class="v">{{ $quotation->quote_date->format('Y-m-d') }}</td></tr>
                    <tr><td class="k">Valid Until</td><td class="v">{{ $validUntil }}</td></tr>
                    @if($quotation->reference)
                        <tr><td class="k">Reference</td><td class="v">{{ $quotation->reference }}</td></tr>
                    @endif
                    <tr><td class="k">Status</td><td class="v" style="color:{{ $quotation->statusColor() }};">{{ $quotation->statusLabel() }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="qt-items">
        <thead>
            <tr>
                <th style="width:26px;text-align:center;">#</th>
                <th>Description</th>
                <th class="r" style="width:50px;text-align:right;">Qty</th>
                <th class="r" style="width:100px;text-align:right;">Unit Price</th>
                @if($lineDiscounts)<th style="width:80px;text-align:right;">Disc</th>@endif
                @if($lineTaxes)<th style="width:80px;text-align:right;">Tax</th>@endif
                <th style="width:110px;text-align:right;">Total</th>
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
                    $rowClass      = $loop->even ? 'alt' : '';
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="n">{{ $loop->iteration }}</td>
                    <td>
                        <b>{{ $label }}</b>
                        @if($isService)
                            <span style="font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;background:#ede9fe;color:#7c3aed;margin-left:4px;">Service</span>
                        @endif
                        @if($sublabel)
                            <br><span style="color:#64748b;font-size:10px;">{{ $sublabel }}</span>
                        @endif
                    </td>
                    <td class="r">{{ $fmtQty($item->quantity) }}</td>
                    <td class="r">{{ $money($item->unit_price) }}</td>
                    @if($lineDiscounts)
                        @if((float) $item->discount_amount > 0)
                            <td class="r" style="color:#ef4444;">−{{ number_format($item->discount_amount, 2) }}</td>
                        @else
                            <td class="r muted">—</td>
                        @endif
                    @endif
                    @if($lineTaxes)
                        @if((float) $item->tax_amount > 0)
                            <td class="r" style="color:#10b981;">{{ number_format($item->tax_amount, 2) }} ({{ $fmtQty($item->tax_rate) }}%)</td>
                        @else
                            <td class="r muted">—</td>
                        @endif
                    @endif
                    <td class="r b">{{ $money($item->line_total) }}</td>
                </tr>
                @foreach($boundProducts as $bp)
                    <tr class="sub {{ $rowClass }}">
                        <td></td>
                        <td style="padding-left:22px;">&#8627; {{ $bp->name }}@if($bp->sku) ({{ $bp->sku }})@endif</td>
                        <td class="r">{{ $fmtQty($bp->pivot->qty) }}</td>
                        <td></td>
                        @if($lineDiscounts)<td></td>@endif
                        @if($lineTaxes)<td></td>@endif
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- Notes + totals --}}
    <table class="qt-bottom">
        <tr>
            <td style="padding-right:20px;">
                <div class="qt-notes">
                    <div class="qt-label">Notes</div>
                    <div class="qt-notes-text">{{ $quotation->notes ?: 'Thank you for your business!' }}</div>
                </div>
            </td>
            <td style="width:250px;">
                <table class="qt-totals">
                    <tr><td>Subtotal</td><td class="v">{{ $money($quotation->subtotal) }}</td></tr>
                    @if($quotation->discount_amount > 0)
                        <tr><td>Discount</td><td class="v" style="color:#ef4444;">−{{ $money($quotation->discount_amount) }}</td></tr>
                    @endif
                    @if($quotation->tax_amount > 0)
                        <tr><td>Tax</td><td class="v" style="color:#10b981;">+{{ $money($quotation->tax_amount) }}</td></tr>
                    @endif
                    <tr class="grand"><td>Quote Total</td><td class="v">{{ $money($quotation->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table class="qt-footer">
        <tr>
            <td>{{ $quotation->quote_number }} · {{ $business->name }}</td>
            <td style="text-align:right;">Valid until {{ $quotation->expiry_date ? $quotation->expiry_date->format('M j, Y') : 'further notice' }}</td>
        </tr>
    </table>

    @if(empty($pdf))
    {{-- Letterhead footer (print only) --}}
    <div id="lhFooterZone" class="lh-print-zone">
        <img id="lhFooterImg" src="" alt="" style="width:100%;display:block;position:fixed;bottom:0;left:0;" onerror="this.style.display='none'">
    </div>
    @endif
</div>

@if(!empty($pdf))
@elseif($letterheadCanvasJson)
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
