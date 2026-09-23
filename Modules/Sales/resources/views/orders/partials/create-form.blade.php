@php
    use Modules\Sales\Models\SalesOrder;
    $editing   = isset($order) && $order instanceof SalesOrder;
    $currency  = $currency ?? '';
    $customers = $customers ?? collect();

    $blankLine = ['item_name' => '', 'product_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => ''];

    if ($editing) {
        $oldItems = old('items');
        if (!is_array($oldItems) || $oldItems === []) {
            $oldItems = $order->items->map(fn ($i) => [
                'item_name'   => $i->product?->name ?? '',
                'product_id'  => $i->product_id ?? '',
                'description' => $i->description,
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
            ])->all();
        }
    } else {
        $oldItems = old('items', [$blankLine]);
    }
    if (!is_array($oldItems) || $oldItems === []) {
        $oldItems = [$blankLine];
    }
@endphp

@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('sales.orders.update', $order) : route('sales.orders.store') }}"
      class="pcat-form-grid pcat-form-grid--2"
      data-so-form
      @if($currency) data-so-currency="{{ $currency }}" @endif>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="pcat-field">
        <label for="so-customer">Customer</label>
        <select id="so-customer" name="customer_id">
            <option value="">— No customer —</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" @selected(old('customer_id', $editing ? $order->customer_id : '') == $c->id)>
                    {{ $c->name }}{{ $c->phone ? ' · '.$c->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('customer_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="so-order-date">Order date <span style="color:#ef4444;">*</span></label>
        <input id="so-order-date" type="date" name="order_date" required
               value="{{ old('order_date', $editing ? $order->order_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
        @error('order_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="so-delivery-date">Expected delivery date</label>
        <input id="so-delivery-date" type="date" name="expected_delivery_date"
               value="{{ old('expected_delivery_date', $editing && $order->expected_delivery_date ? $order->expected_delivery_date->format('Y-m-d') : '') }}">
        @error('expected_delivery_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="so-ref">Your reference</label>
        <input id="so-ref" type="text" name="reference" maxlength="120" placeholder="Optional ref / PO number"
               value="{{ old('reference', $editing ? $order->reference : '') }}">
        @error('reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="so-notes">Notes</label>
        <textarea id="so-notes" name="notes" maxlength="5000"
                  placeholder="Fulfilment notes, delivery instructions…">{{ old('notes', $editing ? $order->notes : '') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    {{-- Line items --}}
    <div class="pcat-field" style="grid-column:1/-1;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
            <label style="margin:0;">Line items</label>
            <button type="button" class="linkbtn" style="padding:6px 12px;font-size:12px;" data-so-add-line>
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
                <tbody data-so-lines>
                    @foreach($oldItems as $index => $line)
                        @php
                            $lQty    = (float) old('items.'.$index.'.quantity',  $line['quantity']  ?? 1);
                            $lPrice  = (float) old('items.'.$index.'.unit_price', $line['unit_price'] ?? 0);
                            $lName   = $line['item_name'] ?? '';
                            $lProdId = old('items.'.$index.'.product_id', $line['product_id'] ?? '');
                        @endphp
                        <tr data-so-line>
                            <td>
                                <div class="so-item-search" data-so-item-search>
                                    <div class="so-item-search__wrap">
                                        <input type="text" class="so-item-search__input"
                                               placeholder="Search products…"
                                               autocomplete="off"
                                               value="{{ $lName }}">
                                        <button type="button" class="so-item-search__clear" title="Clear"
                                                @if(empty($lName)) hidden @endif>&times;</button>
                                    </div>
                                    <ul class="so-item-search__dropdown" hidden></ul>
                                    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $lProdId }}" data-so-product-id>
                                </div>
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
                                       data-so-qty style="text-align:right;">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][unit_price]"
                                       min="0" step="0.01" inputmode="decimal" required
                                       value="{{ old('items.'.$index.'.unit_price', $line['unit_price'] ?? '') }}"
                                       data-so-price style="text-align:right;">
                            </td>
                            <td style="text-align:right;vertical-align:middle;">
                                <strong style="color:var(--text);font-size:13px;white-space:nowrap;" data-so-line-total>
                                    {{ number_format($lQty * $lPrice, 2) }}
                                </strong>
                            </td>
                            <td>
                                <button type="button" class="pcat-btn-del" data-so-remove-line title="Remove"
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
                        <td style="text-align:right;font-weight:700;font-size:13px;color:var(--text);padding-top:10px;white-space:nowrap;" data-so-subtotal>
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
                <input type="number" name="discount_amount" id="so-discount" min="0" step="0.01" inputmode="decimal"
                       value="{{ old('discount_amount', $editing ? $order->discount_amount : '0') }}"
                       style="width:110px;text-align:right;padding:6px 8px;border-radius:7px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid var(--border);gap:12px;">
                <label style="font-size:12px;font-weight:700;color:var(--muted);white-space:nowrap;">Tax{{ $currency ? ' ('.$currency.')' : '' }}</label>
                <input type="number" name="tax_amount" id="so-tax" min="0" step="0.01" inputmode="decimal"
                       value="{{ old('tax_amount', $editing ? $order->tax_amount : '0') }}"
                       style="width:110px;text-align:right;padding:6px 8px;border-radius:7px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:2px solid var(--border);">
                <span style="font-size:15px;font-weight:800;color:var(--text);">Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <strong style="font-size:16px;color:var(--text);" data-so-total>
                    @php $total = max(0, $sub - (float)old('discount_amount', $editing ? $order->discount_amount : 0) + (float)old('tax_amount', $editing ? $order->tax_amount : 0)); @endphp
                    {{ number_format($total, 2) }}
                </strong>
            </div>
        </div>
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($editing)
            <a href="{{ route('sales.orders.show', $order) }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $editing ? 'Save changes' : 'Create order' }}
        </button>
    </div>
</form>

@once('so-item-search-css')
<style>
.so-item-search{position:relative;}
.so-item-search__wrap{display:flex;align-items:center;gap:4px;}
.so-item-search__input{flex:1;min-width:0;}
.so-item-search__clear{background:none;border:none;color:var(--muted);cursor:pointer;padding:2px 6px;font-size:15px;line-height:1;flex-shrink:0;border-radius:4px;}
.so-item-search__clear:hover{color:var(--text);}
.so-item-search__dropdown{list-style:none;margin:0;padding:4px 0;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.18);overflow-y:auto;max-height:270px;min-width:240px;z-index:9000;}
.so-item-search__result{display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;font-size:13px;color:var(--text);}
.so-item-search__result:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
.so-item-search__result-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.so-item-search__sku{font-size:11px;color:var(--muted);flex-shrink:0;}
.so-item-search__price{font-size:12px;font-weight:700;color:var(--text);white-space:nowrap;flex-shrink:0;margin-left:auto;padding-left:8px;}
.so-item-search__no-results{padding:10px 14px;color:var(--muted);font-size:12px;}
.so-item-search__loading{padding:10px 14px;color:var(--muted);font-size:12px;}
</style>
@endonce
@once
<script>
(function () {
    if (window.__soFormInit) return;
    window.__soFormInit = true;

    var SEARCH_URL = '{{ route("sales.line-items.search") }}';

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function lineAmt(row) {
        return Math.round(
            (parseFloat(row.querySelector('[data-so-qty]')?.value) || 0) *
            (parseFloat(row.querySelector('[data-so-price]')?.value) || 0) * 100
        ) / 100;
    }

    function refreshTotals(form) {
        var sub = 0;
        form.querySelectorAll('[data-so-line]').forEach(function(row) {
            var amt = lineAmt(row);
            sub += amt;
            var el = row.querySelector('[data-so-line-total]');
            if (el) el.textContent = fmt(amt);
        });
        var disc  = parseFloat(form.querySelector('#so-discount')?.value) || 0;
        var tax   = parseFloat(form.querySelector('#so-tax')?.value)      || 0;
        var total = Math.max(0, sub - disc + tax);
        var subEl = form.querySelector('[data-so-subtotal]');
        if (subEl) subEl.textContent = fmt(sub);
        var totEl = form.querySelector('[data-so-total]');
        if (totEl) totEl.textContent = fmt(total);
    }

    function reindex(tbody) {
        tbody.querySelectorAll('[data-so-line]').forEach(function(row, i) {
            row.querySelector('[data-so-product-id]')?.setAttribute('name', 'items['+i+'][product_id]');
            row.querySelector('input[name*="[description]"]')?.setAttribute('name', 'items['+i+'][description]');
            row.querySelector('[data-so-qty]')?.setAttribute('name', 'items['+i+'][quantity]');
            row.querySelector('[data-so-price]')?.setAttribute('name', 'items['+i+'][unit_price]');
        });
    }

    function updateRemoveBtns(tbody) {
        var rows = tbody.querySelectorAll('[data-so-line]');
        rows.forEach(function(row) {
            var b = row.querySelector('[data-so-remove-line]');
            if (b) b.hidden = rows.length <= 1;
        });
    }

    function resetWidget(widget) {
        var input   = widget.querySelector('.so-item-search__input');
        var clearBtn = widget.querySelector('.so-item-search__clear');
        var dropdown = widget.querySelector('.so-item-search__dropdown');
        var prodI   = widget.querySelector('[data-so-product-id]');
        if (input)   input.value   = '';
        if (prodI)   prodI.value   = '';
        if (clearBtn) clearBtn.hidden = true;
        if (dropdown) { dropdown.hidden = true; dropdown.innerHTML = ''; }
        widget._initialized = false;
    }

    function initSearchWidget(widget) {
        if (!widget || widget._initialized) return;
        widget._initialized = true;

        var input    = widget.querySelector('.so-item-search__input');
        var dropdown = widget.querySelector('.so-item-search__dropdown');
        var clearBtn = widget.querySelector('.so-item-search__clear');
        var prodI    = widget.querySelector('[data-so-product-id]');

        if (!input || !dropdown) return;

        var debounce = null;
        var abortCtrl = null;

        function positionDropdown() {
            var r = widget.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top      = (r.bottom + 4) + 'px';
            dropdown.style.left     = r.left + 'px';
            dropdown.style.width    = r.width + 'px';
        }

        function hideDropdown() {
            dropdown.hidden = true;
        }

        function showResults(results) {
            dropdown.innerHTML = '';
            if (!results || results.length === 0) {
                var li = document.createElement('li');
                li.className = 'so-item-search__no-results';
                li.textContent = 'No products found.';
                dropdown.appendChild(li);
            } else {
                results.forEach(function(r) {
                    var li = document.createElement('li');
                    li.className = 'so-item-search__result';
                    li.innerHTML =
                        '<span class="so-item-search__result-name">'+esc(r.name)+'</span>'+
                        (r.sku ? '<span class="so-item-search__sku">'+esc(r.sku)+'</span>' : '')+
                        (r.price ? '<span class="so-item-search__price">'+esc(r.price)+'</span>' : '');
                    li.addEventListener('mousedown', function(e) { e.preventDefault(); });
                    li.addEventListener('click', function() { selectItem(r); });
                    dropdown.appendChild(li);
                });
            }
            positionDropdown();
            dropdown.hidden = false;
        }

        function selectItem(r) {
            input.value = r.name;
            if (prodI) prodI.value = r.id;
            if (clearBtn) clearBtn.hidden = false;
            hideDropdown();

            var row = widget.closest('[data-so-line]');
            if (row) {
                var descInput  = row.querySelector('input[name*="[description]"]');
                var priceInput = row.querySelector('[data-so-price]');
                if (descInput && !descInput.value.trim()) descInput.value = r.name;
                if (priceInput && !priceInput.value.trim() && r.price) {
                    priceInput.value = r.price;
                    priceInput.dispatchEvent(new Event('input', {bubbles: true}));
                }
            }
        }

        function doSearch(q) {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            dropdown.innerHTML = '<li class="so-item-search__loading">Searching…</li>';
            positionDropdown();
            dropdown.hidden = false;

            fetch(SEARCH_URL + '?type=product&q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                signal: abortCtrl.signal,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(function(res) { return res.json(); })
            .then(function(data) { showResults(Array.isArray(data) ? data : []); })
            .catch(function(err) { if (err.name !== 'AbortError') hideDropdown(); });
        }

        input.addEventListener('focus', function() {
            clearTimeout(debounce);
            doSearch(input.value.trim());
        });

        input.addEventListener('input', function() {
            clearTimeout(debounce);
            debounce = setTimeout(function() { doSearch(input.value.trim()); }, 220);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { hideDropdown(); input.blur(); }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                input.value = '';
                if (prodI) prodI.value = '';
                clearBtn.hidden = true;
                input.focus();
            });
        }

        document.addEventListener('click', function(e) {
            if (!widget.contains(e.target)) hideDropdown();
        });

        document.addEventListener('scroll', hideDropdown, true);
    }

    function createEmptyRow(tbody) {
        var tpl = tbody.querySelector('[data-so-line]');
        if (!tpl) return null;
        var clone = tpl.cloneNode(true);
        var w = clone.querySelector('[data-so-item-search]');
        if (w) resetWidget(w);
        clone.querySelector('[data-so-qty]').value = '1';
        clone.querySelector('[data-so-price]').value = '';
        var d = clone.querySelector('input[name*="[description]"]');
        if (d) d.value = '';
        var lt = clone.querySelector('[data-so-line-total]');
        if (lt) lt.textContent = '0.00';
        clone.querySelector('[data-so-remove-line]')?.removeAttribute('hidden');
        return clone;
    }

    function bindForm(form) {
        if (!form || form.dataset.soBound === '1') return;
        form.dataset.soBound = '1';
        var tbody = form.querySelector('[data-so-lines]');
        if (!tbody) return;

        tbody.querySelectorAll('[data-so-item-search]').forEach(initSearchWidget);

        form.querySelector('[data-so-add-line]')?.addEventListener('click', function() {
            var newRow = createEmptyRow(tbody);
            if (!newRow) return;
            tbody.appendChild(newRow);
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
            var w = newRow.querySelector('[data-so-item-search]');
            if (w) initSearchWidget(w);
            w?.querySelector('.so-item-search__input')?.focus();
        });

        tbody.addEventListener('click', function(e) {
            if (!e.target.closest('[data-so-remove-line]')) return;
            var rows = tbody.querySelectorAll('[data-so-line]');
            if (rows.length <= 1) return;
            e.target.closest('[data-so-line]')?.remove();
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('input', function(e) {
            if (e.target.matches('[data-so-qty],[data-so-price]')) refreshTotals(form);
        });

        form.querySelector('#so-discount')?.addEventListener('input', function() { refreshTotals(form); });
        form.querySelector('#so-tax')?.addEventListener('input', function() { refreshTotals(form); });

        updateRemoveBtns(tbody);
        refreshTotals(form);
    }

    document.querySelectorAll('[data-so-form]').forEach(bindForm);
})();
</script>
@endonce
