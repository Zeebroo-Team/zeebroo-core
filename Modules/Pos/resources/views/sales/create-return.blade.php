@extends('theme::layouts.app', ['title' => 'Create return note', 'heading' => 'Create return note'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
/* ── Mode toggle cards ───────────────────────────────────────────── */
.crn-modes{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;}
@media(max-width:560px){.crn-modes{grid-template-columns:1fr;}}
.crn-mode-card{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-radius:12px;border:2px solid var(--border);background:transparent;text-decoration:none;cursor:pointer;transition:border-color .15s,background .15s;}
.crn-mode-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 5%,transparent);}
.crn-mode-card--active{border-color:color-mix(in srgb,var(--primary) 70%,var(--border));background:color-mix(in srgb,var(--primary) 9%,transparent);}
.crn-mode-card__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);}
.crn-mode-card--active .crn-mode-card__icon{background:color-mix(in srgb,var(--primary) 22%,transparent);}
.crn-mode-card__body{flex:1;}
.crn-mode-card__title{font-size:13px;font-weight:800;color:var(--text);margin:0 0 3px;display:flex;align-items:center;gap:7px;}
.crn-mode-card__check{width:16px;height:16px;border-radius:50%;border:2px solid var(--border);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:9px;color:transparent;transition:all .15s;}
.crn-mode-card--active .crn-mode-card__check{border-color:var(--primary);background:var(--primary);color:#fff;}
.crn-mode-card__desc{font-size:12px;color:var(--muted);margin:0;line-height:1.5;}

/* ── Section heading with step number ───────────────────────────── */
.crn-step{display:flex;align-items:center;gap:9px;margin-bottom:14px;}
.crn-step__num{width:24px;height:24px;border-radius:50%;background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.crn-step__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}

/* ── Sale search card ─────────────────────────────────────────────── */
.crn-search{border:1px solid var(--border);border-radius:12px;padding:16px 18px;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:16px;}
.crn-search__label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:8px;}
.crn-search__row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.crn-search__input{flex:1;min-width:180px;box-sizing:border-box;padding:9px 12px;font-size:14px;font-weight:600;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.crn-search__input:focus{outline:none;border-color:var(--primary);}
.crn-search__btn{padding:9px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text);cursor:pointer;display:inline-flex;align-items:center;gap:6px;}

/* ── Sale info strip ─────────────────────────────────────────────── */
.crn-sale-info{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 94%,transparent);}
.crn-sale-info__chip{font-size:12px;color:var(--muted);}
.crn-sale-info__chip strong{color:var(--text);}
.crn-sale-info__num{font-size:15px;font-weight:800;color:var(--text);}

/* ── Items table ─────────────────────────────────────────────────── */
.crn-table{width:100%;border-collapse:collapse;font-size:13px;}
.crn-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);padding:0 10px 8px;text-align:left;border-bottom:1px solid var(--border);}
.crn-table th.crn-th-right{text-align:right;}
.crn-table td{padding:10px;border-bottom:1px solid color-mix(in srgb,var(--border) 50%,transparent);vertical-align:middle;}
.crn-table tr:last-child td{border-bottom:none;}
.crn-qty-input{width:84px;padding:7px 8px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:center;box-sizing:border-box;}
.crn-qty-input:focus{outline:none;border-color:var(--primary);}
.crn-qty-input:disabled{opacity:.35;cursor:not-allowed;}
.crn-price-input{width:100px;padding:7px 8px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:right;box-sizing:border-box;}
.crn-price-input:focus{outline:none;border-color:var(--primary);}
.crn-fully-returned{display:inline-block;font-size:11px;font-weight:600;padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,#94a3b8 38%,var(--border));color:var(--muted);}

/* ── Open-return product search row ──────────────────────────────── */
.crn-product-input{width:100%;min-width:160px;box-sizing:border-box;padding:7px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.crn-product-input:focus{outline:none;border-color:var(--primary);}
.crn-remove-btn{width:28px;height:28px;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 38%,var(--border));background:transparent;color:color-mix(in srgb,#ef4444 70%,var(--text));cursor:pointer;font-size:13px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;}
.crn-remove-btn:hover{background:color-mix(in srgb,#ef4444 12%,transparent);}
.crn-add-row-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 40%,var(--border));background:transparent;color:var(--text);cursor:pointer;margin-bottom:12px;}
.crn-add-row-btn:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);}

/* ── Running total bar ───────────────────────────────────────────── */
.crn-total-bar{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));border-radius:12px;background:color-mix(in srgb,var(--primary) 8%,transparent);margin-top:16px;}
.crn-total-bar__left{display:flex;align-items:center;gap:10px;}
.crn-total-bar__icon{width:34px;height:34px;border-radius:9px;background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);display:flex;align-items:center;justify-content:center;font-size:15px;}
.crn-total-bar__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 2px;}
.crn-total-bar__value{font-size:24px;font-weight:800;color:var(--text);}
.crn-total-bar__currency{font-size:13px;font-weight:600;color:var(--muted);margin-left:4px;}

/* ── Form fields grid ────────────────────────────────────────────── */
.crn-fields{display:grid;gap:12px;margin-top:16px;}
@media(min-width:640px){.crn-fields{grid-template-columns:1fr 1fr;}}
.crn-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.crn-field select,.crn-field textarea{width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.crn-field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;padding-right:32px;}
.crn-field select:focus,.crn-field textarea:focus{outline:none;border-color:var(--primary);}
.crn-field textarea{min-height:62px;resize:vertical;font-family:inherit;}
.crn-field--full{grid-column:1/-1;}
.crn-credit-field{display:none;}

/* ── Alert banners ───────────────────────────────────────────────── */
.crn-alert{padding:12px 14px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.45;}
.crn-alert--err{border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:color-mix(in srgb,#ef4444 75%,var(--text));}
.crn-alert--warn{border:1px solid color-mix(in srgb,#f59e0b 40%,var(--border));background:color-mix(in srgb,#f59e0b 9%,var(--card));color:color-mix(in srgb,#b45309 80%,var(--text));}
.crn-alert--info{border:1px solid color-mix(in srgb,#3b82f6 35%,var(--border));background:color-mix(in srgb,#3b82f6 8%,var(--card));color:color-mix(in srgb,#2563eb 80%,var(--text));}

/* ── Open-mode badge ─────────────────────────────────────────────── */
.crn-open-badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;padding:5px 12px;border-radius:999px;border:1px solid color-mix(in srgb,#a78bfa 45%,var(--border));background:color-mix(in srgb,#a78bfa 10%,transparent);color:color-mix(in srgb,#7c3aed 75%,var(--text));margin-bottom:16px;}

/* ── Footer buttons ──────────────────────────────────────────────── */
.crn-footer{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--border);}
.crn-btn{padding:10px 18px;font-size:13px;font-weight:700;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px;}
.crn-btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);}
.crn-btn--primary:hover{background:color-mix(in srgb,var(--primary) 26%,transparent);}
.crn-btn:disabled{opacity:.5;cursor:not-allowed;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if($errors->any())
        <div class="crn-alert crn-alert--err">
            <i class="fa fa-circle-exclamation" style="margin-top:2px;flex-shrink:0;"></i>
            <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        </div>
    @endif

    {{-- ── Step 1: Choose mode ──────────────────────────────────────── --}}
    <div class="crn-step" style="margin-bottom:10px;">
        <span class="crn-step__num">1</span>
        <span class="crn-step__label">Choose return type</span>
    </div>

    <div class="crn-modes">
        <a href="{{ route('pos.returns.create') }}"
           class="crn-mode-card {{ $mode === 'ref' ? 'crn-mode-card--active' : '' }}">
            <div class="crn-mode-card__icon"><i class="fa fa-receipt"></i></div>
            <div class="crn-mode-card__body">
                <p class="crn-mode-card__title">
                    <span class="crn-mode-card__check"><i class="fa fa-check" style="font-size:8px;"></i></span>
                    With sale reference
                </p>
                <p class="crn-mode-card__desc">Search for an existing sale to select items eligible for return. Quantities are validated automatically.</p>
            </div>
        </a>
        <a href="{{ route('pos.returns.create', ['mode' => 'open']) }}"
           class="crn-mode-card {{ $mode === 'open' ? 'crn-mode-card--active' : '' }}">
            <div class="crn-mode-card__icon"><i class="fa fa-box-open"></i></div>
            <div class="crn-mode-card__body">
                <p class="crn-mode-card__title">
                    <span class="crn-mode-card__check"><i class="fa fa-check" style="font-size:8px;"></i></span>
                    Without sale reference
                </p>
                <p class="crn-mode-card__desc">Return products directly without linking to a sale record. Stock is restored to the selected products.</p>
            </div>
        </a>
    </div>

    {{-- ════════════════════════════════════════════════════════════════ --}}
    {{--  MODE A — With sale reference                                   --}}
    {{-- ════════════════════════════════════════════════════════════════ --}}
    @if($mode === 'ref')

        {{-- Step 2: Find sale --}}
        <div class="crn-step">
            <span class="crn-step__num">2</span>
            <span class="crn-step__label">Find the sale</span>
        </div>

        <div class="crn-search">
            <span class="crn-search__label"><i class="fa fa-magnifying-glass" style="margin-right:5px;"></i>Enter sale number</span>
            <form method="get" action="{{ route('pos.returns.create') }}" class="crn-search__row">
                <input type="text" name="sale" value="{{ $saleNumber }}"
                    class="crn-search__input"
                    placeholder="e.g. POS-0001"
                    @if(!filled($saleNumber)) autofocus @endif
                    autocomplete="off" spellcheck="false">
                <input type="hidden" name="mode" value="ref">
                <button type="submit" class="crn-search__btn">
                    <i class="fa fa-search"></i> Search
                </button>
                @if(filled($saleNumber))
                    <a href="{{ route('pos.returns.create') }}" class="pcat-link" style="font-size:13px;">Clear</a>
                @endif
            </form>
        </div>

        @if($saleNotFound)
            <div class="crn-alert crn-alert--err">
                <i class="fa fa-circle-exclamation" style="flex-shrink:0;margin-top:2px;"></i>
                <span>Sale <strong>{{ $saleNumber }}</strong> was not found. Check the number and try again.</span>
            </div>
        @endif

        @if($sale)
            <div class="crn-sale-info">
                <span class="crn-sale-info__num"><i class="fa fa-receipt" style="margin-right:6px;opacity:.6;"></i>{{ $sale->sale_number }}</span>
                <span class="crn-sale-info__chip">Date: <strong>{{ $sale->sold_at?->format('M j, Y g:i A') ?? '—' }}</strong></span>
                <span class="crn-sale-info__chip">Payment: <strong>{{ $sale->paymentMethodLabel() }}</strong></span>
                <span class="crn-sale-info__chip">Total: <strong>{{ number_format((float) $sale->total, 2) }}@if(filled($currency)) {{ $currency }}@endif</strong></span>
                <a href="{{ route('pos.sales.show', $sale) }}" class="pcat-link" style="font-size:12px;margin-left:auto;">
                    View full sale <i class="fa fa-arrow-up-right-from-square"></i>
                </a>
            </div>

            @if($sale->isVoid())
                <div class="crn-alert crn-alert--warn">
                    <i class="fa fa-ban" style="flex-shrink:0;margin-top:2px;"></i>
                    <span>This sale has been <strong>voided</strong>. Returns cannot be created for voided sales.</span>
                </div>
            @else
                @php
                    $allFullyReturned = $sale->items->every(function ($item) use ($returnedQtys) {
                        return round((float) $item->quantity - round((float) ($returnedQtys[$item->id] ?? 0), 3), 3) <= 0;
                    });
                @endphp
                @if($allFullyReturned)
                    <div class="crn-alert crn-alert--info">
                        <i class="fa fa-circle-info" style="flex-shrink:0;margin-top:2px;"></i>
                        <span>All items in this sale have already been fully returned.</span>
                    </div>
                @else
                    {{-- Step 3: Select items --}}
                    <div class="crn-step">
                        <span class="crn-step__num">3</span>
                        <span class="crn-step__label">Select items &amp; quantities</span>
                    </div>

                    <form method="post" action="{{ route('pos.sales.returns.store', $sale) }}" id="crnForm">
                        @csrf
                        <div class="pcat-table-wrap" style="margin-bottom:0;">
                            <table class="crn-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold</th>
                                        <th>Returned</th>
                                        <th>Returnable</th>
                                        <th class="crn-th-right">Return qty</th>
                                        <th class="crn-th-right">Unit price @if(filled($currency))({{ $currency }})@endif</th>
                                        <th class="crn-th-right">Line total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sale->items as $index => $item)
                                        @php
                                            $retQty     = round((float) ($returnedQtys[$item->id] ?? 0), 3);
                                            $returnable = round((float) $item->quantity - $retQty, 3);
                                            $fmtNum     = fn($n) => rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                                                <strong style="color:var(--text);">{{ $item->product_name }}</strong>
                                                @if(filled($item->sku))<div class="muted" style="font-size:11px;margin-top:2px;">{{ $item->sku }}</div>@endif
                                            </td>
                                            <td class="muted">{{ $fmtNum($item->quantity) }}</td>
                                            <td class="muted">{{ $retQty > 0 ? $fmtNum($retQty) : '—' }}</td>
                                            <td>
                                                @if($returnable > 0)
                                                    <strong style="color:var(--text);">{{ $fmtNum($returnable) }}</strong>
                                                @else
                                                    <span class="crn-fully-returned"><i class="fa fa-check" style="font-size:9px;margin-right:3px;"></i>Done</span>
                                                @endif
                                            </td>
                                            <td style="text-align:right;">
                                                <input type="number" name="items[{{ $index }}][quantity]"
                                                    class="crn-qty-input" min="0" max="{{ $returnable }}" step="0.001"
                                                    value="{{ old('items.'.$index.'.quantity', 0) }}"
                                                    @if($returnable <= 0) disabled @endif
                                                    data-max="{{ $returnable }}"
                                                    data-unit-price="{{ (float) $item->unit_sell_price }}"
                                                    data-row="{{ $index }}">
                                            </td>
                                            <td class="muted" style="text-align:right;">{{ number_format((float) $item->unit_sell_price, 2) }}</td>
                                            <td style="text-align:right;" id="crn-line-{{ $index }}">
                                                <strong style="color:var(--text);">0.00</strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @include('pos::partials.crn-shared-fields', ['formId' => 'crnForm', 'cancelUrl' => route('pos.returns.index')])
                    </form>
                    @include('pos::partials.crn-totals-script', ['formId' => 'crnForm', 'mode' => 'ref'])
                @endif
            @endif
        @endif

        @if(!filled($saleNumber))
            <div class="crn-alert crn-alert--info" style="margin-top:4px;">
                <i class="fa fa-circle-info" style="flex-shrink:0;margin-top:2px;"></i>
                <span>Enter a sale number above, or switch to
                    <a href="{{ route('pos.returns.create', ['mode' => 'open']) }}" class="pcat-link">without sale reference</a>
                    to return products directly.
                </span>
            </div>
        @endif

    {{-- ════════════════════════════════════════════════════════════════ --}}
    {{--  MODE B — Without sale reference                                --}}
    {{-- ════════════════════════════════════════════════════════════════ --}}
    @else

        {{-- Step 2: Add products --}}
        <div class="crn-step">
            <span class="crn-step__num">2</span>
            <span class="crn-step__label">Add products to return</span>
        </div>

        <span class="crn-open-badge">
            <i class="fa fa-box-open"></i> No sale reference — stock will be restored directly to product
        </span>

        <form method="post" action="{{ route('pos.returns.store-open') }}" id="crnOpenForm">
            @csrf

            <button type="button" class="crn-add-row-btn" id="crnAddRowBtn">
                <i class="fa fa-plus"></i> Add product
            </button>

            <div class="pcat-table-wrap" style="margin-bottom:0;">
                <table class="crn-table" id="crnOpenTable">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">Product</th>
                            <th>SKU</th>
                            <th class="crn-th-right">Qty</th>
                            <th class="crn-th-right">Unit price @if(filled($currency))({{ $currency }})@endif</th>
                            <th class="crn-th-right">Line total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="crnOpenBody"></tbody>
                </table>
            </div>

            <div id="crnOpenEmpty" class="crn-alert crn-alert--info" style="margin-top:10px;display:none;">
                <i class="fa fa-circle-info" style="flex-shrink:0;margin-top:2px;"></i>
                <span>Click <strong>Add product</strong> to add items to this return.</span>
            </div>

            @include('pos::partials.crn-shared-fields', ['formId' => 'crnOpenForm', 'cancelUrl' => route('pos.returns.index')])
        </form>

        @include('pos::partials.crn-totals-script', ['formId' => 'crnOpenForm', 'mode' => 'open'])

        {{-- Product catalogue JSON for autocomplete --}}
        @php
            $crnProductsJson = $products->map(function ($p) {
                return [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'sku'   => $p->sku ?? '',
                    'price' => round((float) $p->unit_price, 2),
                ];
            });
        @endphp
        <script>
        var CRN_PRODUCTS = @json($crnProductsJson);
        var CRN_CURRENCY = @json($currency);

        (function () {
            var body    = document.getElementById('crnOpenBody');
            var addBtn  = document.getElementById('crnAddRowBtn');
            var emptyEl = document.getElementById('crnOpenEmpty');
            var rowIdx  = 0;

            // Build datalist once
            var dl = document.createElement('datalist');
            dl.id  = 'crnProductList';
            CRN_PRODUCTS.forEach(function (p) {
                var opt = document.createElement('option');
                opt.value = p.name + (p.sku ? ' (' + p.sku + ')' : '');
                opt.setAttribute('data-id', p.id);
                dl.appendChild(opt);
            });
            document.body.appendChild(dl);

            function findProduct(val) {
                val = val.trim().toLowerCase();
                return CRN_PRODUCTS.find(function (p) {
                    var label = p.name + (p.sku ? ' (' + p.sku + ')' : '');
                    return label.toLowerCase() === val ||
                           p.name.toLowerCase() === val ||
                           (p.sku && p.sku.toLowerCase() === val);
                }) || null;
            }

            function updateEmpty() {
                var hasRows = body.querySelectorAll('tr').length > 0;
                if (emptyEl) emptyEl.style.display = hasRows ? 'none' : 'block';
            }

            function addRow(product) {
                var idx = rowIdx++;
                var tr  = document.createElement('tr');
                tr.setAttribute('data-row', idx);
                tr.innerHTML = [
                    '<td>',
                        '<input type="text" list="crnProductList" class="crn-product-input" ',
                            'placeholder="Search by name or SKU…" autocomplete="off" ',
                            'data-row="' + idx + '" value="' + (product ? escHtml(product.name + (product.sku ? ' (' + product.sku + ')' : '')) : '') + '">',
                        '<input type="hidden" name="items[' + idx + '][product_id]" class="crn-pid-inp" value="' + (product ? product.id : '') + '">',
                    '</td>',
                    '<td class="muted" id="crn-sku-' + idx + '" style="font-size:12px;">' + (product ? escHtml(product.sku || '—') : '—') + '</td>',
                    '<td style="text-align:right;">',
                        '<input type="number" name="items[' + idx + '][quantity]" class="crn-qty-input" ',
                            'min="0.001" step="0.001" value="1" ',
                            'data-row="' + idx + '" data-unit-price="' + (product ? product.price : 0) + '">',
                    '</td>',
                    '<td style="text-align:right;">',
                        '<input type="number" name="items[' + idx + '][unit_price]" class="crn-price-input crn-uprice-inp" ',
                            'min="0" step="0.01" value="' + (product ? product.price.toFixed(2) : '0.00') + '" ',
                            'data-row="' + idx + '">',
                    '</td>',
                    '<td style="text-align:right;" id="crn-line-' + idx + '">',
                        '<strong style="color:var(--text);">' + (product ? (product.price * 1).toFixed(2) : '0.00') + '</strong>',
                    '</td>',
                    '<td style="text-align:center;">',
                        '<button type="button" class="crn-remove-btn" data-row="' + idx + '" title="Remove"><i class="fa fa-xmark"></i></button>',
                    '</td>',
                ].join('');

                body.appendChild(tr);

                var searchInp = tr.querySelector('.crn-product-input');
                var pidInp    = tr.querySelector('.crn-pid-inp');
                var skuEl     = document.getElementById('crn-sku-' + idx);
                var priceInp  = tr.querySelector('.crn-uprice-inp');
                var qtyInp    = tr.querySelector('.crn-qty-input');

                searchInp.addEventListener('input', function () {
                    var found = findProduct(this.value);
                    if (found) {
                        pidInp.value   = found.id;
                        priceInp.value = found.price.toFixed(2);
                        priceInp.setAttribute('data-unit-price', found.price);
                        qtyInp.setAttribute('data-unit-price', found.price);
                        if (skuEl) skuEl.textContent = found.sku || '—';
                    } else {
                        pidInp.value = '';
                        if (skuEl) skuEl.textContent = '—';
                    }
                    recalcOpen();
                });

                tr.querySelector('.crn-remove-btn').addEventListener('click', function () {
                    tr.remove();
                    recalcOpen();
                    updateEmpty();
                });

                updateEmpty();
            }

            if (addBtn) addBtn.addEventListener('click', function () { addRow(null); });

            body.addEventListener('input', function (e) {
                if (e.target.classList.contains('crn-qty-input') || e.target.classList.contains('crn-uprice-inp')) {
                    recalcOpen();
                }
            });

            addRow(null);

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
        })();

        function recalcOpen() {
            var totalEl = document.getElementById('crnRunningTotal');
            var rows    = document.querySelectorAll('#crnOpenBody tr[data-row]');
            var total   = 0;
            rows.forEach(function (tr) {
                var idx      = tr.getAttribute('data-row');
                var qtyInp   = tr.querySelector('.crn-qty-input');
                var priceInp = tr.querySelector('.crn-uprice-inp');
                var lineEl   = document.getElementById('crn-line-' + idx);
                var qty      = Math.max(0, parseFloat(qtyInp ? qtyInp.value : 0) || 0);
                var price    = Math.max(0, parseFloat(priceInp ? priceInp.value : 0) || 0);
                var line     = qty * price;
                total       += line;
                if (lineEl) lineEl.innerHTML = '<strong style="color:var(--text);">' + line.toFixed(2) + '</strong>';
            });
            if (totalEl) totalEl.textContent = total.toFixed(2);
        }
        </script>
    @endif
</div>
@endsection
