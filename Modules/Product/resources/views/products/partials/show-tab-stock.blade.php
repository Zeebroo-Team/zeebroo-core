@php
    $stockView = $stockView ?? 'layers';
    $summary = $summary ?? [];
    $purchaseItems = $purchaseItems ?? collect();
    $grnItems = $grnItems ?? collect();
    $stockLayers = $stockLayers ?? collect();
    $stockSellingMarkupPercent = $stockSellingMarkupPercent ?? 25;
    $productStockTabUrl = fn (string $view) => route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => $view]);
@endphp
<div class="product-stock-summary" role="region" aria-label="Stock summary">
    <div class="product-stock-summary__card">
        <p class="product-stock-summary__label">On hand</p>
        <p class="product-stock-summary__value">{{ number_format((float) ($summary['current_stock'] ?? 0), 3) }}</p>
    </div>
    <div class="product-stock-summary__card">
        <p class="product-stock-summary__label">Stock batches</p>
        <p class="product-stock-summary__value">{{ (int) ($summary['stock_layers_count'] ?? 0) }}</p>
    </div>
    <div class="product-stock-summary__card">
        <p class="product-stock-summary__label">Batch qty</p>
        <p class="product-stock-summary__value">{{ number_format((float) ($summary['stock_layers_remaining'] ?? 0), 3) }}</p>
    </div>
    <div class="product-stock-summary__card">
        <p class="product-stock-summary__label">Sell value @if(filled($currency))({{ $currency }})@endif</p>
        <p class="product-stock-summary__value">{{ number_format((float) ($summary['stock_layers_value_sell'] ?? 0), 2) }}</p>
    </div>
    <div class="product-stock-summary__card">
        <p class="product-stock-summary__label">Cost value @if(filled($currency))({{ $currency }})@endif</p>
        <p class="product-stock-summary__value">{{ number_format((float) ($summary['stock_layers_value_cost'] ?? 0), 2) }}</p>
    </div>
</div>

<nav class="product-stock-subtabs" aria-label="Stock by document">
    <a href="{{ $productStockTabUrl('layers') }}" class="product-stock-subtabs__tab @if($stockView === 'layers') is-active @endif">
        <i class="fa fa-layer-group" aria-hidden="true"></i> Stock batches
        <span class="product-show-tabs__count">{{ (int) ($summary['stock_layers_count'] ?? 0) }}</span>
    </a>
    <a href="{{ $productStockTabUrl('po') }}" class="product-stock-subtabs__tab @if($stockView === 'po') is-active @endif">
        <i class="fa fa-file-invoice" aria-hidden="true"></i> By purchase order
        <span class="product-show-tabs__count">{{ (int) ($summary['purchase_lines_count'] ?? 0) }}</span>
    </a>
    <a href="{{ $productStockTabUrl('grn') }}" class="product-stock-subtabs__tab @if($stockView === 'grn') is-active @endif">
        <i class="fa fa-truck-ramp-box" aria-hidden="true"></i> By goods receipt
        <span class="product-show-tabs__count">{{ (int) ($summary['grn_lines_count'] ?? 0) }}</span>
    </a>
</nav>

@if($stockView === 'layers')
    <p class="muted" style="margin:0 0 12px;font-size:12px;line-height:1.45;">
        Each goods receipt creates a stock batch with its own <strong style="color:var(--text);">unit cost</strong> and <strong style="color:var(--text);">selling price</strong>.
        New batches default to cost + {{ rtrim(rtrim(number_format((float) $stockSellingMarkupPercent, 2, '.', ''), '0'), '.') }}% markup.
    </p>
    @if($stockLayers->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            No stock batches yet. Receive goods on a purchase order to create batches with cost and sell price.
            @if(Route::has('purchase.grn.index'))
                <a href="{{ route('purchase.grn.index') }}" class="pcat-link">Goods receive notes</a>
            @endif
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table product-stock-layers-table">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>GRN / source</th>
                        <th>Qty left</th>
                        <th>Unit cost @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Sell price @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Margin</th>
                        <th style="text-align:right;">Update</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockLayers as $layer)
                        @php
                            $grn = $layer->goodsReceiveNoteItem?->goodsReceiveNote;
                            $margin = $layer->marginAmount();
                        @endphp
                        <tr>
                            <td class="muted">{{ $layer->received_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if($grn && Route::has('purchase.grn.show'))
                                    <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link" style="font-weight:700;">{{ $grn->grn_number }}</a>
                                @else
                                    <span class="muted">Batch #{{ $layer->id }}</span>
                                @endif
                                @if($grn?->purchase?->po_number)
                                    <div class="muted" style="font-size:11px;margin-top:2px;">PO {{ $grn->purchase->po_number }}</div>
                                @endif
                            </td>
                            <td>
                                <strong style="color:var(--text);">{{ number_format((float) $layer->quantity_remaining, 3) }}</strong>
                                <span class="muted" style="font-size:11px;"> / {{ number_format((float) $layer->quantity_received, 3) }}</span>
                            </td>
                            <td class="muted">{{ number_format((float) $layer->unit_cost, 2) }}</td>
                            <td>
                                <form method="post" action="{{ route('product.stock-layers.update', [$product, $layer]) }}" class="product-stock-layer-sell-form">
                                    @csrf
                                    @method('PUT')
                                    <input
                                        type="number"
                                        name="selling_unit_price"
                                        value="{{ $layer->selling_unit_price !== null ? number_format((float) $layer->selling_unit_price, 2, '.', '') : '' }}"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        required
                                        style="width:100%;max-width:110px;box-sizing:border-box;padding:6px 8px;font-size:12px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);"
                                    >
                            </td>
                            <td class="muted">
                                @if($margin !== null)
                                    <span style="color:{{ $margin >= 0 ? 'color-mix(in srgb,#22c55e 80%,var(--text))' : 'color-mix(in srgb,#f87171 80%,var(--text))' }};font-weight:700;">
                                        {{ $margin >= 0 ? '+' : '' }}{{ number_format($margin, 2) }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align:right;">
                                    <button type="submit" class="linkbtn" style="padding:5px 10px;font-size:11px;">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@elseif($stockView === 'po')
    @if($purchaseItems->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            No purchase order lines for this product yet.
            @if(Route::has('purchase.index'))
                <a href="{{ route('purchase.index') }}" class="pcat-link">Purchase orders</a>
            @endif
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Ordered</th>
                        <th>Received</th>
                        <th>Remaining</th>
                        <th>Unit cost</th>
                        <th>Status</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseItems as $line)
                        @php
                            $po = $line->purchase;
                            $received = round((float) $line->goodsReceiveNoteItems->sum('quantity_received'), 3);
                            $remaining = max(0.0, round((float) $line->quantity - $received, 3));
                        @endphp
                        <tr>
                            <td><strong style="color:var(--text);">{{ $po?->po_number ?? '—' }}</strong></td>
                            <td class="muted">{{ $po?->purchase_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="muted">{{ $po?->supplier?->name ?? '—' }}</td>
                            <td>{{ number_format((float) $line->quantity, 3) }}</td>
                            <td>{{ number_format($received, 3) }}</td>
                            <td>
                                @if($remaining > 0.0005)
                                    <strong style="color:color-mix(in srgb,#f59e0b 85%,var(--text));">{{ number_format($remaining, 3) }}</strong>
                                @else
                                    <span class="muted">0</span>
                                @endif
                            </td>
                            <td class="muted">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td>
                                @if($po)
                                    <span class="product-po-status product-po-status--{{ $po->status }}">{{ $po->statusLabel() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if($po && Route::has('purchase.show'))
                                    <a href="{{ route('purchase.show', $po) }}" class="pcat-link"><i class="fa fa-eye"></i></a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@else
    @if($grnItems->isEmpty())
        <p class="muted" style="margin:0;font-size:13px;line-height:1.45;">
            No goods receive lines for this product yet.
            @if(Route::has('purchase.grn.index'))
                <a href="{{ route('purchase.grn.index') }}" class="pcat-link">Goods receive notes</a>
            @endif
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>Received</th>
                        <th>PO #</th>
                        <th>Qty received</th>
                        <th>Unit cost @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Sell price @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Stock</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grnItems as $line)
                        @php $grn = $line->goodsReceiveNote; @endphp
                        <tr>
                            <td><strong style="color:var(--text);">{{ $grn?->grn_number ?? '—' }}</strong></td>
                            <td class="muted">{{ $grn?->received_date?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                @if($grn?->purchase && Route::has('purchase.show'))
                                    <a href="{{ route('purchase.show', $grn->purchase) }}" class="pcat-link">{{ $grn->purchase->po_number }}</a>
                                @else
                                    <span class="muted">{{ $grn?->purchase?->po_number ?? '—' }}</span>
                                @endif
                            </td>
                            <td><strong style="color:var(--text);">{{ number_format((float) $line->quantity_received, 3) }}</strong></td>
                            <td class="muted">{{ number_format((float) $line->unit_cost, 2) }}</td>
                            <td>
                                @if($line->selling_unit_price !== null)
                                    <strong style="color:var(--text);">{{ number_format((float) $line->selling_unit_price, 2) }}</strong>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($grn?->stock_applied)
                                    <span class="product-stock-applied product-stock-applied--yes">Updated</span>
                                @else
                                    <span class="product-stock-applied product-stock-applied--no">Pending</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if($grn && Route::has('purchase.grn.show'))
                                    <a href="{{ route('purchase.grn.show', $grn) }}" class="pcat-link"><i class="fa fa-eye"></i></a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
