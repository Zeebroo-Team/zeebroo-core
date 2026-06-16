@php
    use Modules\Sales\Models\Invoice;
    $editing   = isset($invoice) && $invoice instanceof Invoice;
    $currency  = $currency ?? '';
    $customers = $customers ?? collect();
    $products  = $products  ?? collect();

    if ($editing) {
        $oldItems = old('items');
        if (!is_array($oldItems) || $oldItems === []) {
            $oldItems = $invoice->items->map(fn ($i) => [
                'product_id'  => $i->product_id,
                'description' => $i->description,
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
            ])->all();
        }
    } else {
        $oldItems = old('items', [['product_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '']]);
    }
    if (!is_array($oldItems) || $oldItems === []) {
        $oldItems = [['product_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '']];
    }
@endphp

@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('sales.invoices.update', $invoice) : route('sales.invoices.store') }}"
      class="pcat-form-grid pcat-form-grid--2"
      data-inv-form
      @if($currency) data-inv-currency="{{ $currency }}" @endif>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="pcat-field">
        <label for="inv-customer">Customer</label>
        <select id="inv-customer" name="customer_id">
            <option value="">— No customer —</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" @selected(old('customer_id', $editing ? $invoice->customer_id : '') == $c->id)>
                    {{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('customer_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="inv-issue-date">Issue date <span style="color:#ef4444;">*</span></label>
        <input id="inv-issue-date" type="date" name="issue_date" required
               value="{{ old('issue_date', $editing ? $invoice->issue_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
        @error('issue_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="inv-due-date">Payment due date</label>
        <input id="inv-due-date" type="date" name="due_date"
               value="{{ old('due_date', $editing && $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '') }}">
        @error('due_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="inv-ref">Your reference</label>
        <input id="inv-ref" type="text" name="reference" maxlength="120" placeholder="Optional ref / PO number"
               value="{{ old('reference', $editing ? $invoice->reference : '') }}">
        @error('reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="inv-notes">Notes</label>
        <textarea id="inv-notes" name="notes" maxlength="5000"
                  placeholder="Payment terms, bank details, conditions…">{{ old('notes', $editing ? $invoice->notes : '') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    {{-- Line items --}}
    <div class="pcat-field" style="grid-column:1/-1;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
            <label style="margin:0;">Line items</label>
            <button type="button" class="linkbtn" style="padding:6px 12px;font-size:12px;" data-inv-add-line>
                <i class="fa fa-plus"></i> Add line
            </button>
        </div>
        @error('items')<div style="color:#f87171;font-size:12px;margin-bottom:8px;">{{ $message }}</div>@enderror

        <div class="pcat-table-wrap">
            <table class="pcat-table" style="min-width:600px;">
                <thead>
                    <tr>
                        <th style="width:32%;">Product</th>
                        <th>Description</th>
                        <th style="width:80px;text-align:right;">Qty</th>
                        <th style="width:110px;text-align:right;">Unit price{{ $currency ? ' ('.$currency.')' : '' }}</th>
                        <th style="width:100px;text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody data-inv-lines>
                    @foreach($oldItems as $index => $line)
                        @php
                            $lQty   = (float) old('items.'.$index.'.quantity',  $line['quantity']  ?? 1);
                            $lPrice = (float) old('items.'.$index.'.unit_price', $line['unit_price'] ?? 0);
                        @endphp
                        <tr data-inv-line>
                            <td>
                                <select name="items[{{ $index }}][product_id]" data-inv-product-select>
                                    <option value="">Custom / no product</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" data-price="{{ $p->unit_price }}"
                                            @selected(old('items.'.$index.'.product_id', $line['product_id'] ?? '') == $p->id)>
                                            {{ $p->name }}{{ $p->sku ? ' ('.$p->sku.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="items[{{ $index }}][description]" maxlength="255"
                                       placeholder="Description"
                                       value="{{ old('items.'.$index.'.description', $line['description'] ?? '') }}">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][quantity]"
                                       min="0.001" step="any" inputmode="decimal" required
                                       value="{{ old('items.'.$index.'.quantity', $line['quantity'] ?? '1') }}"
                                       data-inv-qty style="text-align:right;">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][unit_price]"
                                       min="0" step="0.01" inputmode="decimal" required
                                       value="{{ old('items.'.$index.'.unit_price', $line['unit_price'] ?? '') }}"
                                       data-inv-price style="text-align:right;">
                            </td>
                            <td style="text-align:right;vertical-align:middle;">
                                <strong style="color:var(--text);font-size:13px;white-space:nowrap;" data-inv-line-total>
                                    {{ number_format($lQty * $lPrice, 2) }}
                                </strong>
                            </td>
                            <td>
                                <button type="button" class="pcat-btn-del" data-inv-remove-line title="Remove"
                                        @if(count($oldItems) <= 1) hidden @endif>&times;</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;font-weight:700;padding-top:10px;font-size:12px;color:var(--muted);">Subtotal</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;font-weight:700;font-size:13px;color:var(--text);padding-top:10px;white-space:nowrap;" data-inv-subtotal>
                            @php $sub = array_sum(array_map(fn($l) => (float)($l['quantity']??0) * (float)($l['unit_price']??0), $oldItems)); @endphp
                            {{ number_format($sub, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Discount / Tax / Total --}}
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <div style="min-width:280px;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid var(--border);gap:12px;">
                <label style="font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap;">Discount{{ $currency ? ' ('.$currency.')' : '' }}</label>
                <input type="number" name="discount_amount" id="inv-discount" min="0" step="0.01" inputmode="decimal"
                       value="{{ old('discount_amount', $editing ? $invoice->discount_amount : '0') }}"
                       style="width:110px;text-align:right;padding:6px 8px;border-radius:7px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid var(--border);gap:12px;">
                <label style="font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap;">Tax{{ $currency ? ' ('.$currency.')' : '' }}</label>
                <input type="number" name="tax_amount" id="inv-tax" min="0" step="0.01" inputmode="decimal"
                       value="{{ old('tax_amount', $editing ? $invoice->tax_amount : '0') }}"
                       style="width:110px;text-align:right;padding:6px 8px;border-radius:7px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:2px solid var(--border);">
                <span style="font-size:15px;font-weight:800;color:var(--text);">Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <strong style="font-size:16px;color:var(--text);" data-inv-total>
                    @php $total = max(0, $sub - (float)old('discount_amount', $editing ? $invoice->discount_amount : 0) + (float)old('tax_amount', $editing ? $invoice->tax_amount : 0)); @endphp
                    {{ number_format($total, 2) }}
                </strong>
            </div>
        </div>
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($editing)
            <a href="{{ route('sales.invoices.show', $invoice) }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $editing ? 'Save changes' : 'Create invoice' }}
        </button>
    </div>
</form>

@once
<script>
(function () {
    if (window.__invFormInit) return;
    window.__invFormInit = true;

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); }
    function lineAmt(row) {
        return Math.round(
            (parseFloat(row.querySelector('[data-inv-qty]')?.value) || 0) *
            (parseFloat(row.querySelector('[data-inv-price]')?.value) || 0) * 100
        ) / 100;
    }

    function refreshTotals(form) {
        let sub = 0;
        form.querySelectorAll('[data-inv-line]').forEach(row => {
            const amt = lineAmt(row);
            sub += amt;
            const el = row.querySelector('[data-inv-line-total]');
            if (el) el.textContent = fmt(amt);
        });
        const disc  = parseFloat(form.querySelector('#inv-discount')?.value) || 0;
        const tax   = parseFloat(form.querySelector('#inv-tax')?.value)      || 0;
        const total = Math.max(0, sub - disc + tax);
        const subEl = form.querySelector('[data-inv-subtotal]');
        if (subEl) subEl.textContent = fmt(sub);
        const totEl = form.querySelector('[data-inv-total]');
        if (totEl) totEl.textContent = fmt(total);
    }

    function reindex(tbody) {
        tbody.querySelectorAll('[data-inv-line]').forEach((row, i) => {
            row.querySelector('[data-inv-product-select]')?.setAttribute('name', `items[${i}][product_id]`);
            row.querySelector('input[name*="[description]"]')?.setAttribute('name', `items[${i}][description]`);
            row.querySelector('[data-inv-qty]')?.setAttribute('name', `items[${i}][quantity]`);
            row.querySelector('[data-inv-price]')?.setAttribute('name', `items[${i}][unit_price]`);
        });
    }

    function updateRemoveBtns(tbody) {
        const rows = tbody.querySelectorAll('[data-inv-line]');
        rows.forEach(row => { const b = row.querySelector('[data-inv-remove-line]'); if (b) b.hidden = rows.length <= 1; });
    }

    function bindForm(form) {
        if (!form || form.dataset.invBound === '1') return;
        form.dataset.invBound = '1';
        const tbody = form.querySelector('[data-inv-lines]');
        if (!tbody) return;

        form.querySelector('[data-inv-add-line]')?.addEventListener('click', () => {
            const first = tbody.querySelector('[data-inv-line]');
            if (!first) return;
            const clone = first.cloneNode(true);
            clone.querySelector('[data-inv-product-select]').selectedIndex = 0;
            clone.querySelector('[data-inv-qty]').value = '1';
            clone.querySelector('[data-inv-price]').value = '';
            clone.querySelector('input[name*="[description]"]').value = '';
            const lt = clone.querySelector('[data-inv-line-total]');
            if (lt) lt.textContent = '0.00';
            clone.querySelector('[data-inv-remove-line]')?.removeAttribute('hidden');
            tbody.appendChild(clone);
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('click', e => {
            if (!e.target.closest('[data-inv-remove-line]')) return;
            const rows = tbody.querySelectorAll('[data-inv-line]');
            if (rows.length <= 1) return;
            e.target.closest('[data-inv-line]')?.remove();
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('input', e => {
            if (e.target.matches('[data-inv-qty],[data-inv-price]')) refreshTotals(form);
        });

        tbody.addEventListener('change', e => {
            const sel = e.target.closest('[data-inv-product-select]');
            if (!sel) return;
            const opt = sel.options[sel.selectedIndex];
            const row = sel.closest('[data-inv-line]');
            const priceInput = row?.querySelector('[data-inv-price]');
            if (priceInput && opt?.dataset?.price && priceInput.value === '') {
                priceInput.value = opt.dataset.price;
            }
            if (row) refreshTotals(form);
        });

        form.querySelector('#inv-discount')?.addEventListener('input', () => refreshTotals(form));
        form.querySelector('#inv-tax')?.addEventListener('input', () => refreshTotals(form));

        updateRemoveBtns(tbody);
        refreshTotals(form);
    }

    document.querySelectorAll('[data-inv-form]').forEach(bindForm);
})();
</script>
@endonce
