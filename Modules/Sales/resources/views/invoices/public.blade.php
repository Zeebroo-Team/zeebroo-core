<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {{ $invoice->invoice_number }} — {{ $business->name }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#1e293b;background:#f1f5f9;min-height:100vh;padding:32px 16px 60px;}
.pub-wrap{max-width:780px;margin:0 auto;}

/* ── Topbar ─────────────────────────────────────────────────────────── */
.pub-topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.pub-brand{font-size:13px;font-weight:700;color:#475569;}
.pub-print-btn{padding:7px 16px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
.pub-print-btn:hover{background:#f8fafc;border-color:#cbd5e1;}

/* ── Letterhead zone ─────────────────────────────────────────────────── */
.pub-lh-zone{
    width:100%;border-radius:14px 14px 0 0;overflow:hidden;
    background:#f8fafc;line-height:0;
    /* hidden until the image loads */
    display:none;
}
.pub-lh-zone.loaded{display:block;}
.pub-lh-img{
    width:100%;
    /* Show the top portion of the A4 letterhead — the branding/header area */
    max-height:220px;
    object-fit:cover;
    object-position:top center;
    display:block;
}
/* When letterhead is present, merge it with the card below */
.pub-lh-zone + .pub-card{border-radius:0 0 16px 16px;border-top:none;}

/* ── Invoice card ────────────────────────────────────────────────────── */
.pub-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06);}

/* ── Header ─────────────────────────────────────────────────────────── */
.pub-header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding:24px 28px 20px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;}
.pub-biz-name{font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.02em;}
.pub-biz-sub{font-size:12px;color:#64748b;margin-top:4px;line-height:1.5;}
.pub-doc-label{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;padding:4px 14px;border-radius:999px;background:#f1f5f9;border:1.5px solid #e2e8f0;color:#64748b;margin-bottom:8px;}
.pub-inv-num{font-size:18px;font-weight:800;color:#0f172a;}
.pub-inv-meta{font-size:12px;color:#64748b;margin-top:4px;line-height:1.6;}

/* When letterhead is expected: hide biz name from FIRST PAINT (set server-side)
   so it never flashes before the letterhead image loads.
   On image error, JS removes pub-has-lh to restore visibility. */
.pub-has-lh .pub-biz-name,
.pub-has-lh .pub-biz-sub { display:none; }

.pub-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;margin-top:8px;}
.pub-status--draft     {background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;}
.pub-status--sent      {background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;}
.pub-status--paid      {background:#f0fdf4;color:#10b981;border:1px solid #a7f3d0;}
.pub-status--overdue   {background:#fef2f2;color:#ef4444;border:1px solid #fecaca;}
.pub-status--cancelled {background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;}

/* ── Amount due pill ─────────────────────────────────────────────────── */
.pub-amount-due{
    background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);
    border-radius:12px;padding:14px 20px;margin:0 28px 20px;
    display:flex;justify-content:space-between;align-items:center;
}
.pub-amount-due-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.7);}
.pub-amount-due-val{font-size:26px;font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1;}

/* ── Body ────────────────────────────────────────────────────────────── */
.pub-body{padding:4px 28px 24px;}

.pub-parties{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;padding-top:20px;border-top:1px solid #f1f5f9;}
@media(max-width:480px){.pub-parties{grid-template-columns:1fr;}}
.pub-party-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:6px;}
.pub-party-name{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px;}
.pub-party-detail{font-size:12px;color:#64748b;line-height:1.55;}

table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;}
thead th{background:#f8fafc;color:#64748b;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:10px 12px;text-align:left;border-bottom:1px solid #e2e8f0;}
thead th.right{text-align:right;}
tbody tr{border-bottom:1px solid #f1f5f9;}
tbody tr:last-child{border-bottom:none;}
tbody td{padding:10px 12px;color:#1e293b;}
tbody td.right{text-align:right;}
tbody td.muted{color:#64748b;font-size:12px;}

.pub-totals{display:flex;justify-content:flex-end;margin-bottom:20px;}
.pub-totals-inner{width:280px;font-size:13px;}
.pub-totals-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;color:#475569;}
.pub-totals-row:last-child{border-bottom:none;}
.pub-totals-row.total-row{font-size:16px;font-weight:800;color:#0f172a;padding-top:10px;border-top:2px solid #e2e8f0;border-bottom:none;margin-top:4px;}

.pub-notes{background:#f8fafc;border-left:3px solid #e2e8f0;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:20px;}
.pub-notes-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:4px;}
.pub-notes-text{font-size:13px;color:#475569;white-space:pre-line;line-height:1.55;}

.pub-footer{padding:16px 28px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8;text-align:center;}

@media print{
    body{background:#fff;padding:0;}
    .pub-topbar{display:none!important;}
    .pub-card{border:none;box-shadow:none;border-radius:0;}
    .pub-lh-zone{border-radius:0;}
    .pub-amount-due{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    @page{margin:0;}
}
</style>
</head>
<body>
<div class="pub-wrap{{ $letterheadCanvasJson ? ' pub-has-lh' : '' }}" id="pubWrap">

    <div class="pub-topbar">
        <span class="pub-brand">{{ $business->name }}</span>
        <button type="button" class="pub-print-btn" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save PDF
        </button>
    </div>

    {{-- ── Letterhead zone (hidden; JS shows it if a letterhead image is found) ── --}}
    <div class="pub-lh-zone" id="pubLhZone">
        <img class="pub-lh-img" id="pubLhImg" src="" alt="">
    </div>

    <div class="pub-card">

        {{-- ── Invoice header ── --}}
        <div class="pub-header">
            <div>
                <div class="pub-biz-name">{{ $business->name }}</div>
                @if($mainBranch)
                    <div class="pub-biz-sub">
                        @if($mainBranch->address){{ $mainBranch->address }}<br>@endif
                        @if($mainBranch->phone){{ $mainBranch->phone }}@endif
                    </div>
                @endif
            </div>
            <div style="text-align:right;">
                <div class="pub-doc-label">Invoice</div>
                <div class="pub-inv-num">{{ $invoice->invoice_number }}</div>
                <div class="pub-inv-meta">
                    Issued: {{ $invoice->issue_date->format('M j, Y') }}<br>
                    @if($invoice->due_date)Due: {{ $invoice->due_date->format('M j, Y') }}<br>@endif
                    @if($invoice->reference)Ref: {{ $invoice->reference }}@endif
                </div>
                <div class="pub-status pub-status--{{ $invoice->status }}">{{ $invoice->statusLabel() }}</div>
            </div>
        </div>

        {{-- ── Amount due pill ── --}}
        <div class="pub-amount-due">
            <div class="pub-amount-due-lbl">Amount Due</div>
            <div class="pub-amount-due-val">
                {{ number_format($invoice->total, 2) }}{{ $currency ? ' '.$currency : '' }}
            </div>
        </div>

        <div class="pub-body">

            {{-- ── Bill to / From ── --}}
            <div class="pub-parties">
                <div>
                    <div class="pub-party-label">Bill To</div>
                    @if($invoice->customer)
                        <div class="pub-party-name">{{ $invoice->customer->name }}</div>
                        <div class="pub-party-detail">
                            @if($invoice->customer->contact_name){{ $invoice->customer->contact_name }}<br>@endif
                            @if($invoice->customer->email){{ $invoice->customer->email }}<br>@endif
                            @if($invoice->customer->phone){{ $invoice->customer->phone }}<br>@endif
                            @if($invoice->customer->address){{ $invoice->customer->address }}@endif
                        </div>
                    @else
                        <div class="pub-party-detail" style="color:#94a3b8;">Walk-in Customer</div>
                    @endif
                </div>
                <div>
                    <div class="pub-party-label">From</div>
                    <div class="pub-party-name">{{ $business->name }}</div>
                    @if($mainBranch?->address)
                        <div class="pub-party-detail" style="white-space:pre-line;">{{ $mainBranch->address }}</div>
                    @endif
                    @if($mainBranch?->phone)
                        <div class="pub-party-detail">{{ $mainBranch->phone }}</div>
                    @endif
                </div>
            </div>

            {{-- ── Line items ── --}}
            <table>
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th>Item / Description</th>
                        <th class="right" style="width:10%;">Qty</th>
                        <th class="right" style="width:18%;">Unit Price{{ $currency ? ' ('.$currency.')' : '' }}</th>
                        <th class="right" style="width:18%;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
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
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td>
                                <strong style="color:#0f172a;">{{ $label }}</strong>
                                @if($isService)
                                    <span style="font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;background:#ede9fe;color:#7c3aed;margin-left:5px;">Service</span>
                                @endif
                                @if($sublabel)
                                    <div class="muted">{{ $sublabel }}</div>
                                @endif
                            </td>
                            <td class="right">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                            <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="right" style="font-weight:700;color:#0f172a;">{{ number_format($item->line_total, 2) }}</td>
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

            {{-- ── Totals ── --}}
            <div class="pub-totals">
                <div class="pub-totals-inner">
                    <div class="pub-totals-row"><span>Subtotal</span><span>{{ number_format($invoice->subtotal, 2) }}</span></div>
                    @if($invoice->discount_amount > 0)
                        <div class="pub-totals-row"><span>Discount</span><span style="color:#ef4444;">− {{ number_format($invoice->discount_amount, 2) }}</span></div>
                    @endif
                    @if($invoice->tax_amount > 0)
                        <div class="pub-totals-row"><span>Tax</span><span>+ {{ number_format($invoice->tax_amount, 2) }}</span></div>
                    @endif
                    <div class="pub-totals-row total-row">
                        <span>Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                        <span>{{ number_format($invoice->total, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- ── Notes ── --}}
            @if($invoice->notes)
            <div class="pub-notes">
                <div class="pub-notes-label">Notes &amp; Payment Terms</div>
                <div class="pub-notes-text">{{ $invoice->notes }}</div>
            </div>
            @endif

        </div>

        <div class="pub-footer">
            {{ $business->name }} &mdash; Invoice {{ $invoice->invoice_number }} &mdash; Generated {{ now()->format('M j, Y') }}
        </div>

    </div>
</div>

@if($letterheadCanvasJson)
<script>
(function () {
    try {
        var raw  = @json($letterheadCanvasJson);
        var data = typeof raw === 'string' ? JSON.parse(raw) : raw;
        var objs = Array.isArray(data) ? [] : (data.objects || []);

        // Find the first image object in the canvas and use its src
        var src = null;
        for (var i = 0; i < objs.length; i++) {
            var t = (objs[i].type || '').toLowerCase();
            if ((t === 'image') && objs[i].src) {
                src = objs[i].src;
                break;
            }
        }
        if (!src) return;

        var img  = document.getElementById('pubLhImg');
        var zone = document.getElementById('pubLhZone');
        var wrap = document.getElementById('pubWrap');
        if (!img || !zone) return;

        img.onload  = function () { zone.classList.add('loaded'); };
        // If the image fails (data URL corrupt, network issue, etc.),
        // restore the business name that was hidden server-side.
        img.onerror = function () {
            if (wrap) wrap.classList.remove('pub-has-lh');
        };
        img.src = src;
    } catch (e) {}
})();
</script>
@endif
</body>
</html>
