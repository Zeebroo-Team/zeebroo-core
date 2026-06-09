@extends('theme::layouts.app', ['title' => 'Print – ' . $sheet->name, 'heading' => 'Barcode sheet'])

@php
    $encodeTypeLabels = [
        'CODE128' => 'Code 128',
        'CODE39'  => 'Code 39',
        'EAN13'   => 'EAN-13',
        'EAN8'    => 'EAN-8',
        'UPC'     => 'UPC-A',
        'QR'      => 'QR Code',
    ];
    $labelTypeLabels = [
        'barcode_only'    => 'Barcode only',
        'with_name'       => 'Barcode + Name',
        'with_name_price' => 'Barcode + Name + Price',
        'with_sku'        => 'Barcode + SKU',
    ];
    $totalPages   = $sheet->totalPages();
    $barcodeValue = $sheet->barcodeValue();
    $isQr         = $sheet->encode_type === 'QR';
    $productName  = $sheet->product?->name ?? '';
    $productSku   = $sheet->product?->sku  ?? '';
    $productPrice = $sheet->product?->unit_price ?? null;

    // CSS @page size mapping
    $cssPageSize = match($sheet->page_size) {
        'A5'     => 'A5',
        'Letter' => 'letter',
        'Legal'  => 'legal',
        default  => 'A4',
    };
    $cssOrientation = $sheet->page_orientation; // portrait | landscape

    // Grid columns based on labels_per_page
    $lpp = max(1, $sheet->labels_per_page);
    $cols = match(true) {
        $lpp <= 2  => 1,
        $lpp <= 6  => 2,
        $lpp <= 12 => 3,
        $lpp <= 20 => 4,
        default    => 5,
    };

    // Build label list: repeat the barcode $total_quantity times
    $labels = range(1, $sheet->total_quantity);
@endphp

@section('content')
@include('product::partials.catalog-hub-styles')

{{-- Screen toolbar (hidden when printing) --}}
<div class="bc-screen-toolbar" id="bc-screen-toolbar">
    <div class="bc-screen-toolbar__info">
        <h2 style="margin:0;font-size:15px;font-weight:800;color:var(--text);">{{ $sheet->name }}</h2>
        <p style="margin:4px 0 0;font-size:12px;color:var(--muted);">
            {{ $sheet->product?->name }} &middot;
            {{ $encodeTypeLabels[$sheet->encode_type] ?? $sheet->encode_type }} &middot;
            {{ $sheet->page_size }} {{ ucfirst($sheet->page_orientation) }} &middot;
            {{ $sheet->labels_per_page }} labels/page &middot;
            {{ $sheet->total_quantity }} total &middot;
            {{ $totalPages }} {{ $totalPages === 1 ? 'page' : 'pages' }}
        </p>
    </div>
    <div class="bc-screen-toolbar__actions">
        <button type="button" id="bc-export-pdf-btn" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-file-pdf"></i> <span id="bc-export-pdf-label">Export PDF</span>
        </button>
        <button type="button" onclick="window.print()" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-print"></i> Print
        </button>
        <a href="{{ route('product.barcodes.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>
</div>

{{-- Printable area --}}
<div id="bc-print-root">
    @foreach(array_chunk($labels, $lpp) as $pageIndex => $pageLabels)
        <div class="bc-page" data-page="{{ $pageIndex + 1 }}">
            <div class="bc-label-grid" style="--bc-cols:{{ $cols }};">
                @foreach($pageLabels as $labelIndex => $labelNum)
                    <div class="bc-label" data-label="{{ $labelNum }}">
                        @if($isQr)
                            <div class="bc-qr" id="bc-qr-{{ $labelNum }}"></div>
                        @else
                            <svg class="bc-svg" id="bc-svg-{{ $labelNum }}" data-value="{{ $barcodeValue }}" data-format="{{ $sheet->encode_type }}"></svg>
                        @endif

                        @if(in_array($sheet->label_type, ['with_name', 'with_name_price', 'with_sku']))
                            <div class="bc-label__text">
                                @if($sheet->label_type === 'with_sku')
                                    <span class="bc-label__sku">{{ $productSku ?: $barcodeValue }}</span>
                                @else
                                    <span class="bc-label__name">{{ $productName }}</span>
                                    @if($sheet->label_type === 'with_name_price' && $productPrice !== null)
                                        <span class="bc-label__price">{{ number_format((float)$productPrice, 2) }}</span>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<style>
/* Screen-only toolbar */
.bc-screen-toolbar{
    display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;
    margin-bottom:16px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;
    background:color-mix(in srgb,var(--card) 97%,transparent);
}
.bc-screen-toolbar__info{flex:1;min-width:0;}
.bc-screen-toolbar__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}

/* Print page */
#bc-print-root{
    display:flex;flex-direction:column;gap:0;
}
.bc-page{
    width:100%;
    page-break-after:always;
    break-after:page;
    box-sizing:border-box;
    padding:8mm;
}
.bc-page:last-child{
    page-break-after:auto;
    break-after:auto;
}
.bc-label-grid{
    display:grid;
    grid-template-columns:repeat(var(--bc-cols, 3), 1fr);
    gap:4mm;
    width:100%;
}
.bc-label{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    border:0.5pt solid #ccc;
    border-radius:3mm;
    padding:3mm 2mm 2mm;
    text-align:center;
    box-sizing:border-box;
    min-height:20mm;
    background:#fff;
    page-break-inside:avoid;
    break-inside:avoid;
}
.bc-svg{
    max-width:100%;
    height:auto;
    display:block;
}
.bc-qr{
    display:flex;align-items:center;justify-content:center;
    width:100%;
}
.bc-qr canvas,.bc-qr img{
    max-width:100%;
    height:auto;
}
.bc-label__text{
    margin-top:2mm;
    font-family:Arial,Helvetica,sans-serif;
    font-size:7pt;
    color:#111;
    line-height:1.3;
    display:flex;flex-direction:column;align-items:center;gap:0.5mm;
}
.bc-label__name{font-weight:700;font-size:7pt;}
.bc-label__price{font-size:8pt;font-weight:700;color:#111;}
.bc-label__sku{font-size:6.5pt;font-family:monospace;color:#333;}

/* Print CSS */
@media print {
    /* Hide all app chrome */
    .navbar,
    #appSidebar,
    .sidebar,
    .sidebar-mobile-backdrop,
    #app-toast-container,
    #bc-screen-toolbar,
    .bc-screen-toolbar,
    .pcat-nav,
    .pcat-page-card > *:not(#bc-print-root) { display:none !important; }

    /* Remove all layout margins/padding so content fills the page */
    html, body {
        margin:0 !important;
        padding:0 !important;
        background:#fff !important;
    }
    .content, .content-inner {
        margin:0 !important;
        padding:0 !important;
        border:none !important;
        min-height:unset !important;
    }

    @page {
        size: {{ $cssPageSize }} {{ $cssOrientation }};
        margin:5mm;
    }

    #bc-print-root {
        display:block;
    }

    .bc-page {
        padding:0;
    }

    .bc-label {
        border-color:#bbb;
    }
}

/* Screen preview styling */
@media screen {
    #bc-print-root{
        background:color-mix(in srgb,var(--bg) 60%,#e2e8f0);
        padding:16px;
        border-radius:12px;
        border:1px solid var(--border);
    }
    .bc-page{
        background:#fff;
        border:1px solid #cbd5e1;
        border-radius:8px;
        margin-bottom:16px;
        box-shadow:0 2px 12px -4px rgba(0,0,0,.15);
    }
    .bc-page:last-child{
        margin-bottom:0;
    }
    .bc-page::before{
        content:'Page ' attr(data-page);
        display:block;
        font-size:10px;
        color:#94a3b8;
        text-align:right;
        padding:4px 6px 0;
        font-family:inherit;
    }
}
</style>

{{-- JsBarcode (standard barcodes) --}}
@if(!$isQr)
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
(function () {
    var svgs = document.querySelectorAll('.bc-svg');
    svgs.forEach(function (el) {
        var val    = el.getAttribute('data-value') || '';
        var fmt    = el.getAttribute('data-format') || 'CODE128';
        if (!val) return;
        try {
            JsBarcode(el, val, {
                format:      fmt,
                lineColor:   '#000',
                width:       1.4,
                height:      38,
                displayValue: false,
                margin:      2,
            });
        } catch (e) {
            el.parentNode.insertAdjacentHTML('afterbegin',
                '<div style="font-size:9px;color:#f87171;padding:2px;">Invalid: ' + (e.message || fmt) + '</div>');
        }
    });
})();
</script>
@else
{{-- QR Code --}}
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var containers = document.querySelectorAll('.bc-qr');
    var val = {{ Js::from($barcodeValue) }};
    containers.forEach(function (el) {
        new QRCode(el, {
            text:          val,
            width:         80,
            height:        80,
            colorDark:     '#000000',
            colorLight:    '#ffffff',
            correctLevel:  QRCode.CorrectLevel.M,
        });
    });
})();
</script>
@endif
{{-- PDF Export --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
(function () {
    var btn       = document.getElementById('bc-export-pdf-btn');
    var btnLabel  = document.getElementById('bc-export-pdf-label');
    if (!btn) return;

    // Page dimensions in mm (portrait; swap for landscape)
    var pageDims = {
        'A4':     { w: 210,   h: 297   },
        'A5':     { w: 148,   h: 210   },
        'Letter': { w: 215.9, h: 279.4 },
        'Legal':  { w: 215.9, h: 355.6 },
    };

    var pageSize        = {{ Js::from($sheet->page_size) }};
    var pageOrientation = {{ Js::from($sheet->page_orientation) }};
    var sheetName       = {{ Js::from($sheet->name) }};
    var dims            = pageDims[pageSize] || pageDims['A4'];

    // Swap for landscape
    var pdfW = pageOrientation === 'landscape' ? dims.h : dims.w;
    var pdfH = pageOrientation === 'landscape' ? dims.w : dims.h;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btnLabel.textContent = 'Generating…';

        var pages = Array.from(document.querySelectorAll('#bc-print-root .bc-page'));
        if (!pages.length) {
            btn.disabled = false;
            btnLabel.textContent = 'Export PDF';
            return;
        }

        // Apply clean white background during capture
        document.body.classList.add('bc-pdf-capturing');

        var { jsPDF } = window.jspdf;
        var pdf = new jsPDF({
            orientation: pageOrientation,
            unit:        'mm',
            format:      [pdfW, pdfH],
        });

        var margin   = 5; // mm
        var imgW     = pdfW - margin * 2;
        var imgH     = pdfH - margin * 2;
        var scale    = 2;  // 2× for sharper output

        function capturePage(index) {
            if (index >= pages.length) {
                document.body.classList.remove('bc-pdf-capturing');
                var filename = sheetName.replace(/[^a-z0-9_\-\s]/gi, '_').replace(/\s+/g, '-').toLowerCase();
                pdf.save(filename + '.pdf');
                btn.disabled = false;
                btnLabel.textContent = 'Export PDF';
                return;
            }

            html2canvas(pages[index], {
                scale:           scale,
                backgroundColor: '#ffffff',
                useCORS:         false,
                logging:         false,
                removeContainer: true,
            }).then(function (canvas) {
                var canvasW = canvas.width;
                var canvasH = canvas.height;

                // Fit canvas proportionally within the PDF content area
                var ratio   = Math.min(imgW / (canvasW / scale), imgH / (canvasH / scale));
                var drawW   = (canvasW / scale) * ratio;
                var drawH   = (canvasH / scale) * ratio;
                var offsetX = margin + (imgW - drawW) / 2;
                var offsetY = margin + (imgH - drawH) / 2;

                if (index > 0) {
                    pdf.addPage([pdfW, pdfH], pageOrientation);
                }

                pdf.addImage(
                    canvas.toDataURL('image/png'),
                    'PNG',
                    offsetX, offsetY,
                    drawW, drawH,
                    '', 'FAST'
                );

                btnLabel.textContent = 'Generating… ' + (index + 1) + '/' + pages.length;
                capturePage(index + 1);
            }).catch(function () {
                document.body.classList.remove('bc-pdf-capturing');
                btn.disabled = false;
                btnLabel.textContent = 'Export PDF';
            });
        }

        capturePage(0);
    });
})();
</script>

<style>
/* Strip screen decorations during PDF capture so pages are clean white */
body.bc-pdf-capturing .bc-page {
    background: #fff !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    margin: 0 !important;
}
body.bc-pdf-capturing .bc-page::before {
    display: none !important;
}
body.bc-pdf-capturing #bc-print-root {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
}
</style>
@endsection
