@php
    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';
    $stockSellingMarkupPercent = (float) ($stockSellingMarkupPercent ?? 25);
    $branchStockSeparate = $branchStockSeparate ?? false;
    $branchOptions = $branchOptions ?? collect();
    $purchaseBranchId = $purchaseBranchId ?? null;
    $suggestSellingPrice = static function (float $unitCost, $product) use ($stockSellingMarkupPercent): ?float {
        if ($unitCost <= 0) {
            return $product?->unit_price !== null ? round((float) $product->unit_price, 2) : null;
        }
        if ($stockSellingMarkupPercent > 0) {
            return round($unitCost * (1 + ($stockSellingMarkupPercent / 100)), 2);
        }
        if ($product?->unit_price !== null && (float) $product->unit_price > $unitCost) {
            return round((float) $product->unit_price, 2);
        }

        return round($unitCost, 2);
    };
@endphp
@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ route('purchase.grn.store', $purchase) }}" class="pcat-form-grid pcat-form-grid--2 grn-receive-form" data-grn-receive-form data-stock-markup-percent="{{ $stockSellingMarkupPercent }}">
    @csrf
    <div class="pcat-field">
        <label for="grn-received-date">Received date</label>
        <input id="grn-received-date" type="date" name="received_date" value="{{ old('received_date', now()->toDateString()) }}" required>
        @error('received_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="pcat-field">
        <label for="grn-reference">Delivery reference</label>
        <input id="grn-reference" type="text" name="reference" value="{{ old('reference') }}" maxlength="120" placeholder="Delivery note #, carrier ref…">
        @error('reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    @if($branchStockSeparate && $branchOptions->isNotEmpty())
    <div class="pcat-field">
        <label for="grn-branch">Receiving branch</label>
        <select id="grn-branch" name="branch_id">
            <option value="">— Not assigned —</option>
            @foreach($branchOptions as $branchRow)
                <option value="{{ $branchRow->id }}" @selected((string) old('branch_id', $purchaseBranchId) === (string) $branchRow->id)>{{ $branchRow->name }}</option>
            @endforeach
        </select>
        @error('branch_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    @endif
    @include('purchase::goods-receive.partials.payment-fields', [
        'canPayByCheque' => $canPayByCheque ?? false,
        'accounts' => $accounts ?? collect(),
        'hasPaymentAccounts' => $hasPaymentAccounts ?? false,
        'currency' => $currency ?? null,
    ])
    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="grn-notes">Notes</label>
        <textarea id="grn-notes" name="notes" maxlength="5000" placeholder="Condition of goods, shortages…">{{ old('notes') }}</textarea>
    </div>
    <div class="pcat-field" style="grid-column:1/-1;">
        <label style="margin:0 0 8px;">Lines to receive</label>
        @error('items')<div style="color:#f87171;font-size:12px;margin-bottom:8px;">{{ $message }}</div>@enderror
        <div class="pcat-table-wrap">
            <table class="pcat-table grn-lines-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width:80px;">Ordered</th>
                        <th style="width:80px;">Received</th>
                        <th style="width:80px;">Remaining</th>
                        <th style="width:100px;">Receive now</th>
                        <th style="width:100px;">Unit cost</th>
                        <th style="width:110px;">Sell price @if($currencyLabel)({{ $currencyLabel }})@endif</th>
                        <th style="width:100px;text-align:right;">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $oldItemsByPoLine = collect(old('items', []))->keyBy(function ($row, $key) {
                            if (is_array($row) && isset($row['purchase_item_id'])) {
                                return (int) $row['purchase_item_id'];
                            }

                            return (int) $key;
                        });
                        $formatQty = static fn (float $qty): string => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
                    @endphp
                    @foreach($purchase->items as $poItem)
                        @php
                            $poItemId = (int) $poItem->id;
                            $ordered = (float) $poItem->quantity;
                            $already = $poItem->quantityReceived();
                            $remaining = $poItem->quantityRemaining();
                            $canReceiveLine = $remaining > 0.0001;
                            $oldRow = $oldItemsByPoLine->get($poItemId);
                            $receiveNowDefault = $canReceiveLine ? $remaining : 0;
                            $receiveNow = (float) ($oldRow['quantity_received'] ?? $receiveNowDefault);
                            if ($canReceiveLine) {
                                $receiveNow = min(max(0, $receiveNow), $remaining);
                            }
                            $lineTotal = $canReceiveLine ? round($receiveNow * (float) $poItem->unit_cost, 2) : 0;
                            $suggestedSell = $canReceiveLine ? $suggestSellingPrice((float) $poItem->unit_cost, $poItem->product) : null;
                            $sellPriceValue = $oldRow['selling_unit_price'] ?? ($suggestedSell !== null ? number_format($suggestedSell, 2, '.', '') : '');
                        @endphp
                        <tr data-grn-line @if(! $canReceiveLine) data-grn-line-complete @endif @if(! $canReceiveLine) style="opacity:.65;" @endif>
                            <td>
                                <strong style="color:var(--text);font-size:13px;">{{ $poItem->product?->name ?? 'Product #'.$poItem->product_id }}</strong>
                                @if($poItem->product?->sku)
                                    <div class="muted" style="font-size:11px;margin-top:2px;">{{ $poItem->product->sku }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $formatQty($ordered) }}</td>
                            <td class="muted">{{ $formatQty($already) }}</td>
                            <td class="muted">
                                @if($canReceiveLine)
                                    <strong style="color:var(--text);">{{ $formatQty($remaining) }}</strong>
                                @else
                                    <span class="muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if($canReceiveLine)
                                    <input type="hidden" name="items[{{ $poItemId }}][purchase_item_id]" value="{{ $poItemId }}">
                                    <input
                                        type="number"
                                        name="items[{{ $poItemId }}][quantity_received]"
                                        value="{{ $oldRow !== null ? $formatQty($receiveNow) : $formatQty($receiveNowDefault) }}"
                                        min="0"
                                        max="{{ $remaining }}"
                                        step="any"
                                        inputmode="decimal"
                                        data-grn-qty
                                        data-grn-max="{{ $remaining }}"
                                        required
                                    >
                                @else
                                    <span class="muted" style="font-size:12px;">Fully received</span>
                                @endif
                            </td>
                            <td class="muted" data-grn-unit-cost="{{ $poItem->unit_cost }}">{{ number_format((float) $poItem->unit_cost, 2) }}</td>
                            <td>
                                @if($canReceiveLine)
                                    <input
                                        type="number"
                                        name="items[{{ $poItemId }}][selling_unit_price]"
                                        value="{{ $sellPriceValue }}"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        data-grn-sell-price
                                        data-grn-suggested="{{ $suggestedSell !== null ? number_format($suggestedSell, 2, '.', '') : '' }}"
                                        placeholder="{{ $suggestedSell !== null ? number_format($suggestedSell, 2) : '0.00' }}"
                                    >
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                @if($canReceiveLine)
                                    <strong style="color:var(--text);white-space:nowrap;" data-grn-line-total>{{ number_format($lineTotal, 2) }}</strong>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" style="text-align:right;font-weight:700;padding-top:10px;">GRN total @if($currencyLabel)({{ $currencyLabel }})@endif</td>
                        <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);padding-top:10px;white-space:nowrap;" data-grn-grand-total>0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="muted" style="margin:8px 0 0;font-size:11px;">Stock increases by the quantities you receive. <strong>Sell price</strong> is stored per batch (defaults to unit cost + {{ rtrim(rtrim(number_format($stockSellingMarkupPercent, 2, '.', ''), '0'), '.') }}% markup). Fully received lines are shown for reference only.</p>
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('purchase.show', $purchase) }}" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Record goods receipt</button>
    </div>
</form>

@once
<script>
(function () {
    if (window.__grnReceiveFormInit) return;
    window.__grnReceiveFormInit = true;

    function parseAmount(value) {
        var n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    }

    function formatAmount(amount) {
        return (Math.round(amount * 100) / 100).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function lineAmount(row) {
        var qty = parseAmount(row.querySelector('[data-grn-qty]')?.value);
        var cost = parseAmount(row.querySelector('[data-grn-unit-cost]')?.getAttribute('data-grn-unit-cost'));
        return Math.round(qty * cost * 100) / 100;
    }

    function suggestedSellPrice(unitCost, markupPercent) {
        if (unitCost <= 0) return '';
        if (markupPercent > 0) {
            return (Math.round(unitCost * (1 + markupPercent / 100) * 100) / 100).toFixed(2);
        }
        return unitCost.toFixed(2);
    }

    function refreshForm(form) {
        var total = 0;
        form.querySelectorAll('[data-grn-line]:not([data-grn-line-complete])').forEach(function (row) {
            var lineTotal = lineAmount(row);
            var el = row.querySelector('[data-grn-line-total]');
            if (el) el.textContent = formatAmount(lineTotal);
            total += lineTotal;
        });
        var grand = form.querySelector('[data-grn-grand-total]');
        if (grand) grand.textContent = formatAmount(total);
    }

    document.querySelectorAll('[data-grn-receive-form]').forEach(function (form) {
        var markup = parseAmount(form.getAttribute('data-stock-markup-percent'));
        form.querySelectorAll('[data-grn-line]:not([data-grn-line-complete])').forEach(function (row) {
            var sellInput = row.querySelector('[data-grn-sell-price]');
            var cost = parseAmount(row.querySelector('[data-grn-unit-cost]')?.getAttribute('data-grn-unit-cost'));
            if (sellInput && sellInput.value === '' && cost > 0) {
                sellInput.value = suggestedSellPrice(cost, markup);
            }
        });
        form.addEventListener('input', function (e) {
            if (!e.target.matches('[data-grn-qty]')) return;
            var max = parseAmount(e.target.getAttribute('data-grn-max'));
            var val = parseAmount(e.target.value);
            if (val > max) e.target.value = max > 0 ? String(max) : '';
            refreshForm(form);
        });
        refreshForm(form);
    });
})();
</script>
@endonce
