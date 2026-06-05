@php
    $supplierReturnedItems = $supplierReturnedItems ?? collect();
    $qualityReasonLabels = [
        'expired'     => 'Date expired',
        'damaged'     => 'Damaged / defective',
        'low_quality' => 'Low quality products',
        'wrong_item'  => 'Wrong item delivered',
    ];
@endphp
<style>
.sup-ret-reason{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;}
.sup-ret-reason--expired{border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 11%,transparent);color:color-mix(in srgb,#92400e 85%,var(--text));}
.sup-ret-reason--damaged{border:1px solid color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 10%,transparent);color:color-mix(in srgb,#991b1b 85%,var(--text));}
.sup-ret-reason--low_quality{border:1px solid color-mix(in srgb,#f97316 40%,var(--border));background:color-mix(in srgb,#f97316 10%,transparent);color:color-mix(in srgb,#9a3412 85%,var(--text));}
.sup-ret-reason--wrong_item{border:1px solid color-mix(in srgb,#8b5cf6 40%,var(--border));background:color-mix(in srgb,#8b5cf6 10%,transparent);color:color-mix(in srgb,#5b21b6 85%,var(--text));}
</style>

@if($supplierReturnedItems->isEmpty())
    <div style="padding:24px 0;text-align:center;">
        <p style="margin:0 0 6px;font-size:22px;">✓</p>
        <p style="margin:0;font-size:14px;font-weight:700;color:var(--text);">No quality-related returns</p>
        <p class="muted" style="margin:4px 0 0;font-size:13px;">Products from this supplier have not been returned for quality reasons (expired, damaged, low quality, or wrong item).</p>
    </div>
@else
    @php
        $totalQty = $supplierReturnedItems->sum('quantity');
        $totalValue = $supplierReturnedItems->sum('line_total');
    @endphp
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <div class="ret-summary-card" style="border:1px solid var(--border);border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--card) 96%,transparent);min-width:130px;">
            <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;">Return lines</p>
            <p style="font-size:18px;font-weight:800;color:var(--text);margin:0;">{{ $supplierReturnedItems->count() }}</p>
        </div>
        <div class="ret-summary-card" style="border:1px solid var(--border);border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--card) 96%,transparent);min-width:130px;">
            <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;">Total qty returned</p>
            <p style="font-size:18px;font-weight:800;color:var(--text);margin:0;">{{ rtrim(rtrim(number_format((float) $totalQty, 3, '.', ''), '0'), '.') }}</p>
        </div>
        <div class="ret-summary-card" style="border:1px solid var(--border);border-radius:10px;padding:10px 14px;background:color-mix(in srgb,var(--card) 96%,transparent);min-width:130px;">
            <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 3px;">Total value @if(filled($currency ?? ''))({{ $currency }})@endif</p>
            <p style="font-size:18px;font-weight:800;color:var(--text);margin:0;">{{ number_format((float) $totalValue, 2) }}</p>
        </div>
    </div>

    <p class="muted" style="margin:0 0 12px;font-size:12px;">
        Showing returns where the reason is <strong>date expired</strong>, <strong>damaged / defective</strong>, <strong>low quality</strong>, or <strong>wrong item delivered</strong> — products sourced from this supplier.
    </p>

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Qty</th>
                    <th>Unit price @if(filled($currency ?? ''))({{ $currency }})@endif</th>
                    <th>Line total @if(filled($currency ?? ''))({{ $currency }})@endif</th>
                    <th>Return reason</th>
                    <th>Return #</th>
                    <th>Sale #</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($supplierReturnedItems as $item)
                    @php
                        $ret = $item->saleReturn;
                        $reason = $ret?->refund_reason ?? '';
                        $reasonLabel = $qualityReasonLabels[$reason] ?? ucfirst((string) $reason);
                        $reasonClass = 'sup-ret-reason sup-ret-reason--' . ($reason ?: 'other');
                    @endphp
                    <tr>
                        <td><strong style="color:var(--text);">{{ $item->product_name }}</strong></td>
                        <td class="muted" style="font-size:12px;">{{ filled($item->sku) ? $item->sku : '—' }}</td>
                        <td class="muted">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                        <td class="muted">{{ number_format((float) $item->unit_sell_price, 2) }}</td>
                        <td><strong style="color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</strong></td>
                        <td>
                            @if(filled($reason))
                                <span class="{{ $reasonClass }}">{{ $reasonLabel }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($ret)
                                <span style="font-size:12px;font-weight:700;color:var(--muted);">{{ $ret->return_number }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($ret?->sale)
                                <a href="{{ route('pos.sales.show', $ret->sale) }}" class="pcat-link" style="font-weight:700;">
                                    {{ $ret->sale->sale_number }}
                                </a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted" style="white-space:nowrap;font-size:12px;">
                            {{ $ret?->returned_at?->format('M j, Y') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
