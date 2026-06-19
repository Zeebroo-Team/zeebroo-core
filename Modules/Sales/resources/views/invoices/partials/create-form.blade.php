@php
    use Modules\Sales\Models\Invoice;
    $editing   = isset($invoice) && $invoice instanceof Invoice;
    $currency  = $currency ?? '';
    $customers = $customers ?? collect();

    $blankLine = ['item_type' => '', 'item_name' => '', 'product_id' => '', 'service_item_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => ''];

    if ($editing) {
        $oldItems = old('items');
        if (!is_array($oldItems) || $oldItems === []) {
            $oldItems = $invoice->items->map(fn ($i) => [
                'item_type'       => $i->product_id ? 'product' : ($i->service_item_id ? 'service' : ''),
                'item_name'       => $i->product?->name ?? $i->serviceItem?->name ?? '',
                'product_id'      => $i->product_id ?? '',
                'service_item_id' => $i->service_item_id ?? '',
                'description'     => $i->description,
                'quantity'        => $i->quantity,
                'unit_price'      => $i->unit_price,
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
                        <th style="width:32%;">Product / Service</th>
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
                            $lQty    = (float) old('items.'.$index.'.quantity',  $line['quantity']  ?? 1);
                            $lPrice  = (float) old('items.'.$index.'.unit_price', $line['unit_price'] ?? 0);
                            $lName   = $line['item_name'] ?? '';
                            $lType   = old('items.'.$index.'.item_type',       $line['item_type']       ?? '');
                            $lProdId = old('items.'.$index.'.product_id',      $line['product_id']      ?? '');
                            $lSvcId  = old('items.'.$index.'.service_item_id', $line['service_item_id'] ?? '');
                        @endphp
                        <tr data-inv-line>
                            <td>
                                <div class="inv-item-search" data-inv-item-search>
                                    <div class="inv-item-search__wrap">
                                        <input type="text" class="inv-item-search__input"
                                               placeholder="Search products &amp; services…"
                                               autocomplete="off"
                                               value="{{ $lName }}">
                                        <button type="button" class="inv-item-search__clear" title="Clear"
                                                @if(empty($lName)) hidden @endif>&times;</button>
                                    </div>
                                    <ul class="inv-item-search__dropdown" hidden></ul>
                                    <input type="hidden" name="items[{{ $index }}][item_type]"       value="{{ $lType }}"   data-inv-item-type>
                                    <input type="hidden" name="items[{{ $index }}][product_id]"      value="{{ $lProdId }}" data-inv-product-id>
                                    <input type="hidden" name="items[{{ $index }}][service_item_id]" value="{{ $lSvcId }}"  data-inv-service-id>
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

@once('inv-item-search-css')
<style>
.inv-item-search{position:relative;}
.inv-item-search__wrap{display:flex;align-items:center;gap:4px;}
.inv-item-search__input{flex:1;min-width:0;}
.inv-item-search__clear{background:none;border:none;color:var(--muted);cursor:pointer;padding:2px 6px;font-size:15px;line-height:1;flex-shrink:0;border-radius:4px;}
.inv-item-search__clear:hover{color:var(--text);}
.inv-item-search__dropdown{list-style:none;margin:0;padding:4px 0;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.18);overflow-y:auto;max-height:270px;min-width:240px;z-index:9000;}
.inv-item-search__result{display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;font-size:13px;color:var(--text);}
.inv-item-search__result:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
.inv-item-search__badge{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;text-transform:uppercase;flex-shrink:0;letter-spacing:.03em;}
.inv-item-search__badge--product{background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.inv-item-search__badge--service{background:color-mix(in srgb,#8b5cf6 14%,transparent);color:#8b5cf6;}
.inv-item-search__result-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.inv-item-search__sku{font-size:11px;color:var(--muted);flex-shrink:0;}
.inv-item-search__price{font-size:12px;font-weight:700;color:var(--text);white-space:nowrap;flex-shrink:0;margin-left:auto;padding-left:8px;}
.inv-item-search__no-results{padding:10px 14px;color:var(--muted);font-size:12px;}
.inv-item-search__loading{padding:10px 14px;color:var(--muted);font-size:12px;}
</style>
@endonce
@once
<script>
(function () {
    if (window.__invFormInit) return;
    window.__invFormInit = true;

    var SEARCH_URL = '{{ route("sales.line-items.search") }}';

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function lineAmt(row) {
        return Math.round(
            (parseFloat(row.querySelector('[data-inv-qty]')?.value) || 0) *
            (parseFloat(row.querySelector('[data-inv-price]')?.value) || 0) * 100
        ) / 100;
    }

    function refreshTotals(form) {
        var sub = 0;
        form.querySelectorAll('[data-inv-line]').forEach(function(row) {
            var amt = lineAmt(row);
            sub += amt;
            var el = row.querySelector('[data-inv-line-total]');
            if (el) el.textContent = fmt(amt);
        });
        var disc  = parseFloat(form.querySelector('#inv-discount')?.value) || 0;
        var tax   = parseFloat(form.querySelector('#inv-tax')?.value)      || 0;
        var total = Math.max(0, sub - disc + tax);
        var subEl = form.querySelector('[data-inv-subtotal]');
        if (subEl) subEl.textContent = fmt(sub);
        var totEl = form.querySelector('[data-inv-total]');
        if (totEl) totEl.textContent = fmt(total);
    }

    function reindex(tbody) {
        tbody.querySelectorAll('[data-inv-line]').forEach(function(row, i) {
            row.querySelector('[data-inv-item-type]')?.setAttribute('name', 'items['+i+'][item_type]');
            row.querySelector('[data-inv-product-id]')?.setAttribute('name', 'items['+i+'][product_id]');
            row.querySelector('[data-inv-service-id]')?.setAttribute('name', 'items['+i+'][service_item_id]');
            row.querySelector('input[name*="[description]"]')?.setAttribute('name', 'items['+i+'][description]');
            row.querySelector('[data-inv-qty]')?.setAttribute('name', 'items['+i+'][quantity]');
            row.querySelector('[data-inv-price]')?.setAttribute('name', 'items['+i+'][unit_price]');
        });
    }

    function updateRemoveBtns(tbody) {
        var rows = tbody.querySelectorAll('[data-inv-line]');
        rows.forEach(function(row) {
            var b = row.querySelector('[data-inv-remove-line]');
            if (b) b.hidden = rows.length <= 1;
        });
    }

    function resetWidget(widget) {
        var input   = widget.querySelector('.inv-item-search__input');
        var clearBtn = widget.querySelector('.inv-item-search__clear');
        var dropdown = widget.querySelector('.inv-item-search__dropdown');
        var typeI   = widget.querySelector('[data-inv-item-type]');
        var prodI   = widget.querySelector('[data-inv-product-id]');
        var svcI    = widget.querySelector('[data-inv-service-id]');
        if (input)   input.value   = '';
        if (typeI)   typeI.value   = '';
        if (prodI)   prodI.value   = '';
        if (svcI)    svcI.value    = '';
        if (clearBtn) clearBtn.hidden = true;
        if (dropdown) { dropdown.hidden = true; dropdown.innerHTML = ''; }
        widget._initialized = false;
    }

    function initSearchWidget(widget) {
        if (!widget || widget._initialized) return;
        widget._initialized = true;

        var input    = widget.querySelector('.inv-item-search__input');
        var dropdown = widget.querySelector('.inv-item-search__dropdown');
        var clearBtn = widget.querySelector('.inv-item-search__clear');
        var typeI    = widget.querySelector('[data-inv-item-type]');
        var prodI    = widget.querySelector('[data-inv-product-id]');
        var svcI     = widget.querySelector('[data-inv-service-id]');

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
                li.className = 'inv-item-search__no-results';
                li.textContent = 'No products or services found.';
                dropdown.appendChild(li);
            } else {
                results.forEach(function(r) {
                    var li = document.createElement('li');
                    li.className = 'inv-item-search__result';
                    li.innerHTML =
                        '<span class="inv-item-search__badge inv-item-search__badge--'+esc(r.type)+'">'+esc(r.type === 'product' ? 'Product' : 'Service')+'</span>'+
                        '<span class="inv-item-search__result-name">'+esc(r.name)+'</span>'+
                        (r.sku ? '<span class="inv-item-search__sku">'+esc(r.sku)+'</span>' : '')+
                        (r.price ? '<span class="inv-item-search__price">'+esc(r.price)+'</span>' : '');
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
            if (r.type === 'product') {
                if (prodI) prodI.value = r.id;
                if (svcI)  svcI.value  = '';
                if (typeI) typeI.value  = 'product';
            } else {
                if (prodI) prodI.value = '';
                if (svcI)  svcI.value  = r.id;
                if (typeI) typeI.value  = 'service';
            }
            if (clearBtn) clearBtn.hidden = false;
            hideDropdown();

            var row = widget.closest('[data-inv-line]');
            if (row) {
                var descInput  = row.querySelector('input[name*="[description]"]');
                var priceInput = row.querySelector('[data-inv-price]');
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

            dropdown.innerHTML = '<li class="inv-item-search__loading">Searching…</li>';
            positionDropdown();
            dropdown.hidden = false;

            fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
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
                if (typeI) typeI.value = '';
                if (prodI) prodI.value = '';
                if (svcI)  svcI.value  = '';
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
        var tpl = tbody.querySelector('[data-inv-line]');
        if (!tpl) return null;
        var clone = tpl.cloneNode(true);
        var w = clone.querySelector('[data-inv-item-search]');
        if (w) resetWidget(w);
        clone.querySelector('[data-inv-qty]').value = '1';
        clone.querySelector('[data-inv-price]').value = '';
        var d = clone.querySelector('input[name*="[description]"]');
        if (d) d.value = '';
        var lt = clone.querySelector('[data-inv-line-total]');
        if (lt) lt.textContent = '0.00';
        clone.querySelector('[data-inv-remove-line]')?.removeAttribute('hidden');
        return clone;
    }

    function bindForm(form) {
        if (!form || form.dataset.invBound === '1') return;
        form.dataset.invBound = '1';
        var tbody = form.querySelector('[data-inv-lines]');
        if (!tbody) return;

        tbody.querySelectorAll('[data-inv-item-search]').forEach(initSearchWidget);

        form.querySelector('[data-inv-add-line]')?.addEventListener('click', function() {
            var newRow = createEmptyRow(tbody);
            if (!newRow) return;
            tbody.appendChild(newRow);
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
            var w = newRow.querySelector('[data-inv-item-search]');
            if (w) initSearchWidget(w);
            w?.querySelector('.inv-item-search__input')?.focus();
        });

        tbody.addEventListener('click', function(e) {
            if (!e.target.closest('[data-inv-remove-line]')) return;
            var rows = tbody.querySelectorAll('[data-inv-line]');
            if (rows.length <= 1) return;
            e.target.closest('[data-inv-line]')?.remove();
            reindex(tbody);
            updateRemoveBtns(tbody);
            refreshTotals(form);
        });

        tbody.addEventListener('input', function(e) {
            if (e.target.matches('[data-inv-qty],[data-inv-price]')) refreshTotals(form);
        });

        form.querySelector('#inv-discount')?.addEventListener('input', function() { refreshTotals(form); });
        form.querySelector('#inv-tax')?.addEventListener('input', function() { refreshTotals(form); });

        updateRemoveBtns(tbody);
        refreshTotals(form);
    }

    document.querySelectorAll('[data-inv-form]').forEach(bindForm);
})();
</script>
@endonce
