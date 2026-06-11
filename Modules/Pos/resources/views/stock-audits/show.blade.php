@extends('theme::layouts.app', ['title' => 'Stock Audit', 'heading' => 'Stock Audit'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── Status badges ────────────────────────────────────────── */
.sa-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;}
.sa-badge--open     {border:1px solid color-mix(in srgb,#3b82f6 42%,var(--border));background:color-mix(in srgb,#3b82f6 11%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
.sa-badge--finalized{border:1px solid color-mix(in srgb,#10b981 42%,var(--border));background:color-mix(in srgb,#10b981 11%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));}

/* ── Progress header ──────────────────────────────────────── */
.sa-progress-bar{height:8px;border-radius:999px;background:color-mix(in srgb,var(--border) 55%,transparent);overflow:hidden;margin-top:6px;}
.sa-progress-bar__fill{height:100%;border-radius:999px;background:var(--primary);transition:width .4s ease;}

/* ── Variance cells ───────────────────────────────────────── */
.sa-var{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:2px 8px;border-radius:6px;}
.sa-var--surplus{background:color-mix(in srgb,#10b981 12%,transparent);color:color-mix(in srgb,#059669 85%,var(--text));}
.sa-var--deficit{background:color-mix(in srgb,#ef4444 12%,transparent);color:color-mix(in srgb,#dc2626 85%,var(--text));}
.sa-var--ok     {background:transparent;color:var(--muted);}
.sa-var--empty  {color:var(--muted);font-weight:400;font-style:italic;font-size:12px;}

/* ── Count input ──────────────────────────────────────────── */
.sa-count-input{width:96px;padding:7px 8px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;box-sizing:border-box;}
.sa-count-input:focus{outline:none;border-color:var(--primary);}
.sa-count-input:disabled{opacity:.38;cursor:not-allowed;}
.sa-count-input.sa-has-variance{border-color:color-mix(in srgb,#f59e0b 60%,var(--border));}

/* ── Notes input in table ─────────────────────────────────── */
.sa-note-input{width:100%;min-width:120px;box-sizing:border-box;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.sa-note-input:focus{outline:none;border-color:var(--primary);}
.sa-note-input:disabled{opacity:.38;cursor:not-allowed;}

/* ── Summary row ──────────────────────────────────────────── */
.sa-summary{display:flex;flex-wrap:wrap;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 94%,transparent);margin-bottom:16px;}
.sa-summary__chip{font-size:12px;color:var(--muted);}
.sa-summary__chip strong{color:var(--text);}

/* ── Action buttons in finalize bar ──────────────────────── */
.sa-finalize-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px 16px;border:1px solid color-mix(in srgb,#10b981 40%,var(--border));border-radius:12px;background:color-mix(in srgb,#10b981 7%,transparent);margin-top:14px;}
.sa-finalize-bar p{margin:0;font-size:13px;color:var(--muted);line-height:1.5;flex:1;min-width:200px;}
.sa-finalize-bar strong{color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    @php
        $totalLines   = $audit->totalLinesCount();
        $countedLines = $audit->countedLinesCount();
        $pct          = $totalLines > 0 ? round($countedLines / $totalLines * 100) : 0;
    @endphp
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div style="flex:1;min-width:220px;">
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                {{ $audit->audit_date->format('M j, Y') }}
                @if($audit->createdBy)&middot; Created by {{ $audit->createdBy->name }}@endif
            </p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">
                {{ $audit->audit_number }}
            </h2>
            @if($audit->notes)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">{{ $audit->notes }}</p>
            @endif
            <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="sa-badge sa-badge--{{ $audit->status }}">{{ $audit->statusLabel() }}</span>
                @if($audit->isFinalized() && $audit->finalizedBy)
                    <span class="muted" style="font-size:12px;">Finalized by {{ $audit->finalizedBy->name }} on {{ $audit->finalized_at->format('M j, Y g:i A') }}</span>
                @endif
            </div>

            {{-- Progress bar --}}
            <div style="margin-top:12px;max-width:320px;">
                <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:700;margin-bottom:4px;">
                    <span class="muted">Progress</span>
                    <span style="color:var(--text);">{{ $countedLines }} / {{ $totalLines }} counted</span>
                </div>
                <div class="sa-progress-bar">
                    <div class="sa-progress-bar__fill" style="width:{{ $pct }}%;"></div>
                </div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            <a href="{{ route('pos.stock-audits.index') }}"
               class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-arrow-left"></i> All audits
            </a>

            @if(!$audit->isFinalized())
                <form method="POST" action="{{ route('pos.stock-audits.destroy', $audit) }}" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this audit? This cannot be undone.')"
                            class="pcat-btn-del" style="padding:8px 12px;" title="Delete audit">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Audit metadata strip ─────────────────────────────────────── --}}
    <div class="sa-summary">
        <span class="sa-summary__chip"><i class="fa fa-boxes-stacked" style="margin-right:4px;opacity:.6;"></i>Products: <strong>{{ $totalLines }}</strong></span>
        <span class="sa-summary__chip"><i class="fa fa-check" style="margin-right:4px;opacity:.6;"></i>Counted: <strong>{{ $countedLines }}</strong></span>
        @php $varCount = $audit->varianceLinesCount(); @endphp
        @if($varCount > 0)
            <span class="sa-summary__chip" style="color:color-mix(in srgb,#f59e0b 80%,var(--text));"><i class="fa fa-triangle-exclamation" style="margin-right:4px;"></i>Variances: <strong>{{ $varCount }}</strong></span>
        @else
            <span class="sa-summary__chip"><i class="fa fa-circle-check" style="margin-right:4px;opacity:.6;"></i>Variances: <strong>0</strong></span>
        @endif
        @if($audit->isFinalized())
            <span class="sa-summary__chip" style="color:color-mix(in srgb,#10b981 80%,var(--text));"><i class="fa fa-check-circle" style="margin-right:4px;"></i>Stock updated on finalize</span>
        @endif
    </div>

    {{-- ── Search ───────────────────────────────────────────────────── --}}
    <div class="pcat-toolbar" style="margin-bottom:14px;">
        <form method="GET" action="{{ route('pos.stock-audits.show', $audit) }}"
              style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search product name or SKU…"
                   style="min-width:220px;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);">
            <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Search</button>
            @if(filled($search))
                <a href="{{ route('pos.stock-audits.show', $audit) }}" class="pcat-link" style="font-size:13px;">Clear</a>
            @endif
        </form>
        @if(!$audit->isFinalized())
            <button type="button" class="linkbtn"
                    style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;"
                    onclick="document.getElementById('sa-save-form').requestSubmit()">
                <i class="fa fa-floppy-disk"></i> Save counts
            </button>
        @endif
    </div>

    {{-- ── Lines table ──────────────────────────────────────────────── --}}
    @if($audit->isFinalized())
        {{-- Read-only table --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Unit</th>
                        <th style="text-align:right;">Expected</th>
                        <th style="text-align:right;">Counted</th>
                        <th style="text-align:right;">Variance</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        <tr>
                            <td class="muted" style="font-size:12px;">{{ $loop->iteration }}</td>
                            <td><strong style="color:var(--text);">{{ $line->product_name }}</strong></td>
                            <td class="muted" style="font-size:12px;">{{ $line->sku ?: '—' }}</td>
                            <td class="muted" style="font-size:12px;">{{ $line->unit ?: '—' }}</td>
                            <td style="text-align:right;font-size:13px;">
                                {{ rtrim(rtrim(number_format((float)$line->expected_qty, 3, '.', ''), '0'), '.') }}
                            </td>
                            <td style="text-align:right;font-size:13px;font-weight:700;color:var(--text);">
                                @if($line->counted_qty !== null)
                                    {{ rtrim(rtrim(number_format((float)$line->counted_qty, 3, '.', ''), '0'), '.') }}
                                @else
                                    <span class="muted" style="font-weight:400;font-style:italic;">not counted</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if($line->counted_qty !== null)
                                    @php $vc = $line->varianceClass(); @endphp
                                    <span class="sa-var sa-var--{{ $vc }}">
                                        @if($vc === 'surplus')<i class="fa fa-arrow-up" style="font-size:9px;"></i>@endif
                                        @if($vc === 'deficit')<i class="fa fa-arrow-down" style="font-size:9px;"></i>@endif
                                        {{ $line->varianceLabel() }}
                                    </span>
                                @else
                                    <span class="muted" style="font-size:12px;">—</span>
                                @endif
                            </td>
                            <td class="muted" style="font-size:12px;">{{ $line->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted" style="padding:16px;font-size:13px;">No products match your search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        {{-- Editable table --}}
        <form method="POST" action="{{ route('pos.stock-audits.save-lines', $audit) }}" id="sa-save-form">
            @csrf @method('PUT')

            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Unit</th>
                            <th style="text-align:right;">Expected @if(filled($currency))({{ $currency }})@endif</th>
                            <th style="text-align:right;min-width:110px;">Physical count</th>
                            <th style="text-align:right;">Variance</th>
                            <th style="min-width:120px;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr data-sa-row data-expected="{{ (float) $line->expected_qty }}">
                                <td class="muted" style="font-size:12px;">{{ $loop->iteration }}</td>
                                <td>
                                    <strong style="color:var(--text);">{{ $line->product_name }}</strong>
                                </td>
                                <td class="muted" style="font-size:12px;">{{ $line->sku ?: '—' }}</td>
                                <td class="muted" style="font-size:12px;">{{ $line->unit ?: '—' }}</td>
                                <td style="text-align:right;font-size:13px;font-weight:600;">
                                    {{ rtrim(rtrim(number_format((float)$line->expected_qty, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td style="text-align:right;">
                                    <input type="number"
                                           name="lines[{{ $line->id }}][counted_qty]"
                                           class="sa-count-input"
                                           min="0" step="0.001" inputmode="decimal"
                                           placeholder="—"
                                           value="{{ old('lines.'.$line->id.'.counted_qty', $line->counted_qty !== null ? rtrim(rtrim(number_format((float)$line->counted_qty, 3, '.', ''), '0'), '.') : '') }}"
                                           data-sa-count>
                                </td>
                                <td style="text-align:right;" data-sa-variance>
                                    @if($line->counted_qty !== null)
                                        @php $vc = $line->varianceClass(); @endphp
                                        <span class="sa-var sa-var--{{ $vc }}">
                                            @if($vc === 'surplus')<i class="fa fa-arrow-up" style="font-size:9px;"></i>@endif
                                            @if($vc === 'deficit')<i class="fa fa-arrow-down" style="font-size:9px;"></i>@endif
                                            {{ $line->varianceLabel() }}
                                        </span>
                                    @else
                                        <span class="sa-var sa-var--empty">not counted</span>
                                    @endif
                                </td>
                                <td>
                                    <input type="text"
                                           name="lines[{{ $line->id }}][notes]"
                                           class="sa-note-input"
                                           maxlength="500"
                                           placeholder="Optional note…"
                                           value="{{ old('lines.'.$line->id.'.notes', $line->notes ?? '') }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="muted" style="padding:16px;font-size:13px;">No products match your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Save button --}}
            @if($lines->isNotEmpty())
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                    <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                        <i class="fa fa-floppy-disk"></i> Save counts
                    </button>
                </div>
            @endif
        </form>

        {{-- ── Finalize bar ─────────────────────────────────────────── --}}
        @if(!filled($search) && $totalLines > 0)
            <div class="sa-finalize-bar">
                <div style="width:34px;height:34px;border-radius:9px;background:color-mix(in srgb,#10b981 18%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">
                    <i class="fa fa-check-double"></i>
                </div>
                <p>
                    <strong>Ready to finalize?</strong> Finalizing updates each product's stock quantity to the counted value.
                    Products not counted will keep their current system stock.
                    <strong>This action cannot be undone.</strong>
                </p>
                <form method="POST" action="{{ route('pos.stock-audits.finalize', $audit) }}">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Finalize audit {{ $audit->audit_number }}? Product stock quantities will be updated to match your counts. This cannot be undone.')"
                            class="linkbtn"
                            style="padding:9px 18px;font-size:13px;background:color-mix(in srgb,#10b981 14%,transparent);border:1px solid color-mix(in srgb,#10b981 42%,var(--border));color:var(--text);display:inline-flex;align-items:center;gap:7px;white-space:nowrap;">
                        <i class="fa fa-check-double"></i> Finalize audit
                    </button>
                </form>
            </div>
        @endif
    @endif
</div>

{{-- Live variance calculator --}}
<script>
(function () {
    function fmtQty(n) {
        return n.toFixed(3).replace(/\.?0+$/, '') || '0';
    }

    function varHtml(variance) {
        if (isNaN(variance)) return '<span class="sa-var sa-var--empty">not counted</span>';
        var v = Math.round(variance * 1000) / 1000;
        if (v === 0) return '<span class="sa-var sa-var--ok">0</span>';
        var cls  = v > 0 ? 'surplus' : 'deficit';
        var icon = v > 0 ? '<i class="fa fa-arrow-up" style="font-size:9px;"></i>' : '<i class="fa fa-arrow-down" style="font-size:9px;"></i>';
        var prefix = v > 0 ? '+' : '';
        return '<span class="sa-var sa-var--' + cls + '">' + icon + ' ' + prefix + fmtQty(Math.abs(v)) + '</span>';
    }

    document.querySelectorAll('[data-sa-row]').forEach(function (row) {
        var expected = parseFloat(row.getAttribute('data-expected')) || 0;
        var countInp = row.querySelector('[data-sa-count]');
        var varCell  = row.querySelector('[data-sa-variance]');
        if (!countInp || !varCell) return;

        countInp.addEventListener('input', function () {
            var val      = this.value.trim();
            var counted  = val === '' ? NaN : parseFloat(val);
            var variance = isNaN(counted) ? NaN : counted - expected;
            varCell.innerHTML = varHtml(variance);

            // Highlight input if variance
            if (!isNaN(variance) && Math.round(variance * 1000) !== 0) {
                countInp.classList.add('sa-has-variance');
            } else {
                countInp.classList.remove('sa-has-variance');
            }
        });
    });
})();
</script>
@endsection
