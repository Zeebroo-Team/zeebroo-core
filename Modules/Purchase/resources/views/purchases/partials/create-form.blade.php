@php
    use Modules\Purchase\Models\Purchase;

    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';
    $products = $products ?? collect();
    $suppliers = $suppliers ?? collect();
    $purchase = $purchase ?? null;
    $isEdit = $purchase instanceof Purchase;
    $formAction = $formAction ?? route('purchase.store');
    $formMethod = strtoupper($formMethod ?? 'POST');
    $branchStockSeparate = $branchStockSeparate ?? false;
    $branchOptions = $branchOptions ?? collect();

    if ($isEdit) {
        $oldItems = old('items');
        if (!is_array($oldItems) || $oldItems === []) {
            $oldItems = $purchase->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
            ])->all();
        }
    } else {
        $oldItems = old('items', [['product_id' => '', 'quantity' => '1', 'unit_cost' => '']]);
    }
    if (!is_array($oldItems) || $oldItems === []) {
        $oldItems = [['product_id' => '', 'quantity' => '1', 'unit_cost' => '']];
    }

    $statusOptions = $isEdit
        ? [Purchase::STATUS_DRAFT => 'Draft', Purchase::STATUS_ORDERED => 'Ordered (sent to supplier)']
        : [
            Purchase::STATUS_DRAFT => 'Draft',
            Purchase::STATUS_ORDERED => 'Ordered (sent to supplier)',
        ];
    $defaultStatus = $isEdit ? $purchase->status : Purchase::STATUS_DRAFT;
@endphp
@if($errors->any() && filter_var($showPurchaseCreateErrorBanner ?? true, FILTER_VALIDATE_BOOLEAN))
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ $formAction }}" class="pcat-form-grid pcat-form-grid--2 purchase-create-form" data-purchase-create-form @if($currencyLabel) data-purchase-currency="{{ $currencyLabel }}" @endif>
    @csrf
    @if($formMethod !== 'POST')
        @method($formMethod)
    @endif

    @if($isEdit && $purchase->po_number)
        <div class="pcat-field">
            <label>PO number</label>
            <input type="text" value="{{ $purchase->po_number }}" readonly style="opacity:.85;cursor:default;">
        </div>
    @endif

    <div class="pcat-field">
        <label for="purchase-date">Order date</label>
        <input id="purchase-date" type="date" name="purchase_date" value="{{ old('purchase_date', $isEdit ? $purchase->purchase_date->toDateString() : now()->toDateString()) }}" required>
        @error('purchase_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="pcat-field">
        <label for="purchase-expected">Expected delivery</label>
        <input id="purchase-expected" type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $isEdit && $purchase->expected_delivery_date ? $purchase->expected_delivery_date->toDateString() : '') }}">
        @error('expected_delivery_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="pcat-field">
        <label for="purchase-status">Status</label>
        <select id="purchase-status" name="status">
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $defaultStatus) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="pcat-field">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
            <label for="purchase-supplier" style="margin:0;">Supplier</label>
            <button type="button" class="linkbtn" style="padding:4px 10px;font-size:11px;" data-po-supplier-open>Add supplier</button>
        </div>
        <select id="purchase-supplier" name="supplier_id" data-purchase-supplier-select>
            <option value="">— None —</option>
            @foreach($suppliers as $supplierRow)
                <option value="{{ $supplierRow->id }}" @selected((string) old('supplier_id', $isEdit ? $purchase->supplier_id : '') === (string) $supplierRow->id)>{{ $supplierRow->name }}</option>
            @endforeach
        </select>
        @error('supplier_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    @if($branchStockSeparate && $branchOptions->isNotEmpty())
    <div class="pcat-field">
        <label for="purchase-branch">Branch</label>
        <select id="purchase-branch" name="branch_id">
            <option value="">— Not assigned —</option>
            @foreach($branchOptions as $branchRow)
                <option value="{{ $branchRow->id }}" @selected((string) old('branch_id', $isEdit ? $purchase->branch_id : '') === (string) $branchRow->id)>{{ $branchRow->name }}</option>
            @endforeach
        </select>
        @error('branch_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    @endif
    <div class="pcat-field">
        <label for="purchase-reference">Supplier reference</label>
        <input id="purchase-reference" type="text" name="reference" value="{{ old('reference', $isEdit ? $purchase->reference : '') }}" maxlength="120" placeholder="Their quote or invoice #…">
        @error('reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>
    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="purchase-notes">Notes</label>
        <textarea id="purchase-notes" name="notes" maxlength="5000" placeholder="Delivery instructions, payment terms…">{{ old('notes', $isEdit ? $purchase->notes : '') }}</textarea>
    </div>
    <div class="pcat-field" style="grid-column:1/-1;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
            <label style="margin:0;">Line items</label>
            <button type="button" class="linkbtn" style="padding:6px 12px;font-size:12px;" data-purchase-add-line><i class="fa fa-plus"></i> Add line</button>
        </div>
        @include('purchase::purchases.partials.quick-add-product-modal')
        @error('items')<div style="color:#f87171;font-size:12px;margin-bottom:8px;">{{ $message }}</div>@enderror
        <div class="pcat-table-wrap">
            <table class="pcat-table purchase-lines-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width:100px;">Qty</th>
                        <th style="width:120px;">Unit cost @if($currencyLabel)({{ $currencyLabel }})@endif</th>
                        <th style="width:110px;text-align:right;">Line total @if($currencyLabel)({{ $currencyLabel }})@endif</th>
                        <th style="width:44px;"></th>
                    </tr>
                </thead>
                <tbody data-purchase-lines>
                    @foreach($oldItems as $index => $line)
                        @php
                            $lineQty = (float) old('items.'.$index.'.quantity', $line['quantity'] ?? 1);
                            $lineUnitCost = (float) old('items.'.$index.'.unit_cost', $line['unit_cost'] ?? 0);
                            $lineTotalPreview = round($lineQty * $lineUnitCost, 2);
                        @endphp
                        <tr data-purchase-line>
                            <td>
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <select name="items[{{ $index }}][product_id]" class="purchase-line-product" required data-purchase-product-select style="flex:1;min-width:0;">
                                        <option value="">Select product…</option>
                                        @foreach($products as $productRow)
                                            <option value="{{ $productRow->id }}" data-unit-price="{{ $productRow->unit_price }}" @selected((string) old('items.'.$index.'.product_id', $line['product_id'] ?? '') === (string) $productRow->id)>{{ $productRow->name }}@if($productRow->sku) ({{ $productRow->sku }})@endif</option>
                                        @endforeach
                                    </select>
                                    <button type="button" data-po-product-open title="Add new product"
                                            style="flex-shrink:0;width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;"
                                            onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                                            onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $line['quantity'] ?? '1') }}" min="0.001" step="any" inputmode="decimal" required data-purchase-qty>
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][unit_cost]" value="{{ old('items.'.$index.'.unit_cost', $line['unit_cost'] ?? '') }}" min="0" step="0.01" inputmode="decimal" required data-purchase-cost>
                            </td>
                            <td style="text-align:right;vertical-align:middle;">
                                <strong style="color:var(--text);font-size:13px;white-space:nowrap;" data-purchase-line-total>{{ number_format($lineTotalPreview, 2) }}</strong>
                            </td>
                            <td>
                                <button type="button" class="pcat-btn-del" data-purchase-remove-line title="Remove line" @if(count($oldItems) <= 1) hidden @endif>&times;</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right;font-weight:700;padding-top:10px;">Order total @if($currencyLabel)({{ $currencyLabel }})@endif</td>
                        <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);padding-top:10px;white-space:nowrap;" data-purchase-grand-total>
                            @php
                                $orderTotalPreview = 0;
                                foreach ($oldItems as $index => $line) {
                                    $orderTotalPreview += round(
                                        (float) old('items.'.$index.'.quantity', $line['quantity'] ?? 0)
                                        * (float) old('items.'.$index.'.unit_cost', $line['unit_cost'] ?? 0),
                                        2
                                    );
                                }
                            @endphp
                            {{ number_format($orderTotalPreview, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="muted" style="margin:8px 0 0;font-size:11px;line-height:1.4;">Receiving the order adds each line quantity to product stock.</p>
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($isEdit)
            <a href="{{ route('purchase.show', $purchase) }}" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ $submitLabel ?? ($isEdit ? 'Save changes' : 'Create purchase order') }}</button>
    </div>
</form>

@once
<script>
(function () {
    if (window.__purchaseLineItemsInit) return;
    window.__purchaseLineItemsInit = true;

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
        var qty = parseAmount(row.querySelector('[data-purchase-qty]')?.value);
        var cost = parseAmount(row.querySelector('[data-purchase-cost]')?.value);
        return Math.round(qty * cost * 100) / 100;
    }

    function updateLineTotal(row) {
        var el = row.querySelector('[data-purchase-line-total]');
        if (el) {
            el.textContent = formatAmount(lineAmount(row));
        }
    }

    function updateGrandTotal(form) {
        var total = 0;
        form.querySelectorAll('[data-purchase-line]').forEach(function (row) {
            total += lineAmount(row);
        });
        var el = form.querySelector('[data-purchase-grand-total]');
        if (el) {
            el.textContent = formatAmount(total);
        }
    }

    function refreshTotals(form) {
        form.querySelectorAll('[data-purchase-line]').forEach(updateLineTotal);
        updateGrandTotal(form);
    }

    function reindexLines(tbody) {
        tbody.querySelectorAll('[data-purchase-line]').forEach(function (row, index) {
            var product = row.querySelector('[data-purchase-product-select]');
            var qty = row.querySelector('[data-purchase-qty]');
            var cost = row.querySelector('[data-purchase-cost]');
            if (product) product.name = 'items[' + index + '][product_id]';
            if (qty) qty.name = 'items[' + index + '][quantity]';
            if (cost) cost.name = 'items[' + index + '][unit_cost]';
        });
    }

    function bindForm(form) {
        if (!form || form.dataset.purchaseLinesBound === '1') return;
        form.dataset.purchaseLinesBound = '1';
        var tbody = form.querySelector('[data-purchase-lines]');
        if (!tbody) return;

        form.querySelector('[data-purchase-add-line]')?.addEventListener('click', function () {
            var first = tbody.querySelector('[data-purchase-line]');
            if (!first) return;
            var clone = first.cloneNode(true);
            clone.querySelectorAll('select, input').forEach(function (el) {
                if (el.matches('[data-purchase-product-select]')) el.selectedIndex = 0;
                if (el.matches('[data-purchase-qty]')) el.value = '1';
                if (el.matches('[data-purchase-cost]')) el.value = '';
            });
            var lineTotal = clone.querySelector('[data-purchase-line-total]');
            if (lineTotal) lineTotal.textContent = formatAmount(0);
            clone.querySelector('[data-purchase-remove-line]')?.removeAttribute('hidden');
            tbody.appendChild(clone);
            reindexLines(tbody);
            updateRemoveButtons(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('click', function (e) {
            if (!e.target.closest('[data-purchase-remove-line]')) return;
            var rows = tbody.querySelectorAll('[data-purchase-line]');
            if (rows.length <= 1) return;
            e.target.closest('[data-purchase-line]')?.remove();
            reindexLines(tbody);
            updateRemoveButtons(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('input', function (e) {
            if (!e.target.matches('[data-purchase-qty], [data-purchase-cost]')) return;
            var row = e.target.closest('[data-purchase-line]');
            if (!row) return;
            updateLineTotal(row);
            updateGrandTotal(form);
        });

        tbody.addEventListener('change', function (e) {
            var select = e.target.closest('[data-purchase-product-select]');
            if (!select) return;
            var row = select.closest('[data-purchase-line]');
            var cost = row?.querySelector('[data-purchase-cost]');
            var opt = select.options[select.selectedIndex];
            var price = opt?.getAttribute('data-unit-price');
            if (cost && price && cost.value === '') {
                cost.value = price;
            }
            if (row) {
                updateLineTotal(row);
                updateGrandTotal(form);
            }
        });

        function updateRemoveButtons(body) {
            var rows = body.querySelectorAll('[data-purchase-line]');
            rows.forEach(function (row) {
                var btn = row.querySelector('[data-purchase-remove-line]');
                if (btn) btn.hidden = rows.length <= 1;
            });
        }


        updateRemoveButtons(tbody);
        refreshTotals(form);
    }

    document.querySelectorAll('[data-purchase-create-form]').forEach(bindForm);
})();
</script>
@endonce
