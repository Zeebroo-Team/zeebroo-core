@extends('theme::layouts.app', ['title' => 'Printer Settings', 'heading' => 'Printer Settings'])

@section('content')
@php
    $receiptMode = $posSettings['receipt_mode'] ?? 'bill';
    $paperWidth  = (string) ($posSettings['receipt_paper_width'] ?? '80');
@endphp
<style>
.prs-wrap{max-width:820px;}
.prs-section{margin-bottom:20px;}
.prs-section__label{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin:0 0 10px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.prs-field{margin-bottom:16px;}
.prs-field__label{display:block;font-size:13px;font-weight:700;color:var(--text);margin-bottom:6px;}
.prs-field__hint{font-size:11.5px;color:var(--muted);margin:6px 0 0;line-height:1.45;}
.prs-select{width:100%;box-sizing:border-box;padding:10px 12px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.prs-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.prs-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--border);}
.prs-row__name{font-size:13px;font-weight:700;color:var(--text);}
.prs-row__desc{font-size:11.5px;color:var(--muted);line-height:1.45;margin-top:2px;}
.prs-switch{position:relative;flex-shrink:0;display:inline-flex;cursor:pointer;}
.prs-switch input{position:absolute;opacity:0;width:0;height:0;}
.prs-switch__track{display:block;width:42px;height:24px;border-radius:100px;background:color-mix(in srgb,var(--border) 80%,transparent);border:1px solid var(--border);position:relative;transition:background .2s;}
.prs-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.2);transition:transform .2s;}
.prs-switch input:checked~.prs-switch__track{background:var(--primary);border-color:var(--primary);}
.prs-switch input:checked~.prs-switch__track .prs-switch__thumb{transform:translateX(18px);}
.prs-switch input:focus-visible~.prs-switch__track{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 25%,transparent);}
.prs-note{display:flex;gap:9px;font-size:12px;color:var(--muted);line-height:1.5;padding:10px 12px;border:1px dashed var(--border);border-radius:10px;}
.prs-note i{color:var(--primary);margin-top:2px;}
.prs-actions{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:18px;}
.prs-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;}
.prs-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
</style>

<div class="card" style="max-width:100%;padding:16px;">
    @include('pos::settings.partials.print-settings-nav')

    @if(session('status'))
        <div class="prs-ok"><i class="fa fa-check" aria-hidden="true"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-bottom:12px;color:#ef4444;font-size:13px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('settings.printer.update') }}" class="prs-wrap" id="prs-form">
        @csrf

        <div class="prs-section">
            <p class="prs-section__label"><i class="fa fa-file-invoice" aria-hidden="true"></i> Receipts</p>
            <div class="prs-field">
                <label class="prs-field__label" for="prs-receipt-mode">POS Receipt / Invoice Mode</label>
                <select name="receipt_mode" id="prs-receipt-mode" class="prs-select">
                    <option value="bill" @selected($receiptMode === 'bill')>Bill Printing — thermal receipt after each sale</option>
                    <option value="invoice" @selected($receiptMode === 'invoice')>Invoice — create &amp; print a formal invoice after each sale</option>
                </select>
                <p class="prs-field__hint">
                    Applies to every POS — web, POS Lite and the desktop app.
                    Bill mode uses the <a href="{{ route('settings.receipt') }}" style="color:var(--primary);">Receipt Setting</a> layout;
                    Invoice mode uses @if(Route::has('sales.invoice-setup.edit'))<a href="{{ route('sales.invoice-setup.edit') }}" style="color:var(--primary);">Invoice Setup</a>@else Invoice Setup @endif.
                </p>
            </div>
        </div>

        <div class="prs-section">
            <p class="prs-section__label"><i class="fa fa-print" aria-hidden="true"></i> Thermal printer</p>
            <div class="prs-field">
                <label class="prs-field__label" for="prs-paper-width">Paper Width</label>
                <select name="receipt_paper_width" id="prs-paper-width" class="prs-select">
                    <option value="80" @selected($paperWidth === '80')>80 mm — standard thermal roll (XP-80T)</option>
                    <option value="58" @selected($paperWidth === '58')>58 mm — compact thermal roll</option>
                </select>
                <p class="prs-field__hint">Must match the paper roll loaded in your printer.</p>
            </div>

            <div class="prs-row">
                <div>
                    <div class="prs-row__name">Show receipt preview after sale</div>
                    <div class="prs-row__desc">Turn off to open the print dialog straight away when a sale is completed (no preview popup). Saved for this browser / device only.</div>
                </div>
                <label class="prs-switch" title="Show receipt preview after sale">
                    <input type="checkbox" id="prs-show-preview" checked>
                    <span class="prs-switch__track" aria-hidden="true"><span class="prs-switch__thumb"></span></span>
                </label>
            </div>

            <div class="prs-note" style="margin-top:14px;">
                <i class="fa fa-circle-info" aria-hidden="true"></i>
                <span>In the browser, the printer is chosen in the print dialog and the paper size comes from the printer driver — set your thermal printer as the default and its paper to the roll width above. Silent printing and ESC/POS mode are available in the POS Lite app (Settings → Printer).</span>
            </div>
        </div>

        <div class="prs-actions">
            <button type="button" class="prs-btn" id="prs-test-print"><i class="fa fa-print" aria-hidden="true"></i> Test Print</button>
            <button type="submit" class="prs-btn prs-btn--primary"><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save settings</button>
        </div>
    </form>
</div>

<script>
(function () {
    // Per-device printer prefs — read by pos::partials.pos-sale-completed-modal
    var KEY = 'zeebroo.pos.printer';
    function readPrefs() {
        try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
    }
    function writePrefs(prefs) {
        try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) {}
    }

    var previewToggle = document.getElementById('prs-show-preview');
    previewToggle.checked = readPrefs().showPreview !== false;

    document.getElementById('prs-form').addEventListener('submit', function () {
        var prefs = readPrefs();
        prefs.showPreview = previewToggle.checked;
        writePrefs(prefs);
    });

    var biz = @json($business->name);
    document.getElementById('prs-test-print').addEventListener('click', function () {
        var width = document.getElementById('prs-paper-width').value === '58' ? 58 : 80;
        var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
        var w = window.open('', '_blank', 'width=400,height=600');
        if (!w) { alert('Please allow pop-ups to print.'); return; }
        w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test Print</title><style>'
            + '@page{size:' + width + 'mm auto;margin:0;}'
            + 'body{font-family:"Courier New",monospace;margin:0;padding:3mm;width:' + width + 'mm;box-sizing:border-box;font-size:12px;color:#000;}'
            + 'h1{font-size:14px;text-align:center;margin:0 0 4px;}hr{border:none;border-top:1px dashed #000;margin:6px 0;}'
            + '.r{display:flex;justify-content:space-between;}</style></head><body>'
            + '<h1>' + esc(biz) + '</h1><div style="text-align:center;">PRINTER TEST</div><hr>'
            + '<div class="r"><span>Paper width</span><span>' + width + ' mm</span></div>'
            + '<div class="r"><span>Printed</span><span>' + esc(new Date().toLocaleString()) + '</span></div><hr>'
            + '<div style="text-align:center;">If this text is centred and not cut off,<br>your printer is set up correctly.</div>'
            + '</body></html>');
        w.document.close();
        w.focus();
        w.addEventListener('afterprint', function () { try { w.close(); } catch (e) {} });
        setTimeout(function () { w.print(); }, 150);
    });
})();
</script>
@endsection
