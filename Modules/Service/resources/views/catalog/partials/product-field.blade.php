@php
    $spfId    = isset($fieldIdPrefix) ? $fieldIdPrefix . '-svc-prod' : 'svc-prod';
    $prodList = isset($products) ? $products : collect();
    $prodForJs = $prodList->map(function ($p) {
        // ── unit cost: weighted avg from stock layers ──
        $layers   = $p->relationLoaded('stockLayers') ? $p->stockLayers : collect();
        $totalQty = $layers->sum(fn ($l) => max(0, (float) ($l->quantity_remaining ?? 0)));
        if ($layers->isNotEmpty() && $totalQty > 0) {
            $weighted = $layers->sum(fn ($l) => (float) $l->unit_cost * max(0, (float) ($l->quantity_remaining ?? 0)));
            $unitCost    = round($weighted / $totalQty, 4);
            $costIsEst   = false;
        } elseif ($layers->isNotEmpty()) {
            // layers exist but all consumed — use last known purchase cost
            $raw         = (float) ($layers->last()?->unit_cost ?? 0);
            $unitCost    = $raw > 0 ? round($raw, 4) : null;
            $costIsEst   = false;
        } else {
            $unitCost    = null;
            $costIsEst   = false;
        }
        // ── fallback: use unit_price when no stock cost is available ──
        if ($unitCost === null && $p->unit_price !== null && (float) $p->unit_price > 0) {
            $unitCost  = round((float) $p->unit_price, 4);
            $costIsEst = true;  // flags this as selling-price estimate, not purchase cost
        }
        return [
            'id'        => (int) $p->id,
            'name'      => $p->name,
            'sku'       => $p->sku ?? '',
            'price'     => $p->unit_price !== null ? number_format((float) $p->unit_price, 2) : null,
            'unit'      => $p->unit ?? '',
            'unitCost'  => $unitCost,
            'costIsEst' => $costIsEst,
        ];
    })->values();

    // look up precomputed cost info from $prodForJs
    $lookupCost = function (int $id) use ($prodForJs): array {
        $entry = $prodForJs->firstWhere('id', $id);
        return ['unitCost' => $entry['unitCost'] ?? null, 'costIsEst' => $entry['costIsEst'] ?? false];
    };

    $initLines = [];
    if (old('svc_product_ids')) {
        $ids  = (array) old('svc_product_ids', []);
        $qtys = (array) old('svc_product_qtys', []);
        foreach ($ids as $i => $pid) {
            $p = $prodList->firstWhere('id', (int) $pid);
            if ($p) {
                $cost = $lookupCost((int) $p->id);
                $initLines[] = ['id' => (int) $p->id, 'name' => $p->name, 'sku' => $p->sku ?? '',
                                'qty' => (float) ($qtys[$i] ?? 1)] + $cost;
            }
        }
    } elseif (isset($item) && $item?->relationLoaded('products')) {
        foreach ($item->products as $p) {
            $cost = $lookupCost((int) $p->id);
            $initLines[] = ['id' => (int) $p->id, 'name' => $p->name, 'sku' => $p->sku ?? '',
                            'qty' => (float) ($p->pivot->qty ?? 1)] + $cost;
        }
    }
@endphp

@once
<style>
.svc-prod-field__label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.svc-prod-search-row{display:flex;gap:8px;align-items:center;margin-bottom:10px;}
.svc-prod-search-wrap{position:relative;flex:1;}
.svc-prod-search-input{
    width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;
    background:var(--card);color:var(--text);font-size:13px;outline:none;
    transition:border-color .15s,box-shadow .15s;
}
.svc-prod-search-input:focus{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-prod-add-btn{
    display:inline-flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;
    padding:8px 14px;border:1px solid var(--primary);border-radius:8px;
    background:color-mix(in srgb,var(--primary) 10%,transparent);
    color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;
    transition:background .15s,box-shadow .15s;
}
.svc-prod-add-btn:hover{background:color-mix(in srgb,var(--primary) 18%,transparent);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 20%,transparent);}
.svc-prod-suggest{
    position:fixed;z-index:9000;
    margin:0;padding:4px 0;list-style:none;max-height:220px;overflow:auto;
    border:1px solid var(--border);border-radius:10px;background:var(--card);
    box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.svc-prod-suggest[hidden]{display:none;}
.svc-prod-suggest li{margin:0;}
.svc-prod-suggest button{
    display:flex;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;
    font-size:13px;color:var(--text);cursor:pointer;align-items:baseline;gap:8px;
}
.svc-prod-suggest button:hover,.svc-prod-suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.svc-prod-suggest .prod-sku{font-size:11px;color:var(--muted);}
.svc-prod-suggest .prod-price{margin-left:auto;font-size:11px;color:var(--muted);white-space:nowrap;}
.svc-prod-suggest .prod-empty{color:var(--muted);cursor:default;font-style:italic;}
.svc-prod-suggest .prod-empty:hover{background:transparent;}
.svc-prod-lines{border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:4px;}
.svc-prod-lines:empty{display:none;}
.svc-prod-line{
    display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:10px;
    padding:8px 12px;border-bottom:1px solid var(--border);background:var(--card);
}
.svc-prod-line:last-child{border-bottom:none;}
.svc-prod-line__name{font-size:13px;font-weight:600;color:var(--text);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.svc-prod-line__sku{font-size:11px;color:var(--muted);}
.svc-prod-line__qty-wrap{display:flex;align-items:center;gap:5px;}
.svc-prod-line__qty-label{font-size:11px;color:var(--muted);white-space:nowrap;}
.svc-prod-line__qty{
    width:72px;padding:4px 8px;border:1px solid var(--border);border-radius:6px;
    background:var(--card);color:var(--text);font-size:13px;text-align:right;outline:none;
    transition:border-color .15s;
}
.svc-prod-line__qty:focus{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));}
.svc-prod-line__remove{
    display:grid;place-items:center;width:26px;height:26px;padding:0;border:none;border-radius:6px;
    background:transparent;color:var(--muted);font-size:14px;cursor:pointer;
    transition:background .12s,color .12s;
}
.svc-prod-line__remove:hover{background:color-mix(in srgb,#f87171 15%,transparent);color:#f87171;}
.svc-prod-field__hint{margin:6px 0 0;font-size:11px;color:var(--muted);}
.svc-prod-field__hidden{display:none;}
.svc-prod-cost-bar{
    display:none;align-items:center;justify-content:space-between;
    gap:12px;padding:9px 13px;border-radius:8px;margin-top:6px;
    background:color-mix(in srgb,var(--primary) 6%,transparent);
    border:1px solid color-mix(in srgb,var(--primary) 18%,var(--border));
}
.svc-prod-cost-bar.is-visible{display:flex;}
.svc-prod-cost-bar__label{font-size:12px;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:6px;}
.svc-prod-cost-bar__total{font-size:15px;font-weight:800;color:var(--primary);}
.svc-prod-cost-bar__items{display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
.svc-prod-cost-bar__item{font-size:11px;color:var(--muted);white-space:nowrap;}
.svc-prod-cost-bar__item b{color:var(--text);font-weight:700;}
.svc-prod-cost-bar__unknown{font-size:10px;color:var(--muted);font-style:italic;}

/* Quick-add product modal */
.svc-qprod-backdrop{
    display:none;position:fixed;inset:0;z-index:200;
    background:rgba(0,0,0,.45);backdrop-filter:blur(2px);
    align-items:center;justify-content:center;
}
.svc-qprod-backdrop.is-open{display:flex;}
.svc-qprod-modal{
    width:100%;max-width:420px;margin:16px;border-radius:14px;
    background:var(--card);border:1px solid var(--border);
    box-shadow:0 24px 60px rgba(0,0,0,.35);overflow:hidden;
}
.svc-qprod-modal__head{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
}
.svc-qprod-modal__title{font-size:15px;font-weight:700;color:var(--text);}
.svc-qprod-modal__close{
    display:grid;place-items:center;width:30px;height:30px;border:none;border-radius:8px;
    background:transparent;color:var(--muted);font-size:18px;cursor:pointer;
}
.svc-qprod-modal__close:hover{background:color-mix(in srgb,var(--border) 60%,transparent);}
.svc-qprod-modal__body{padding:20px;display:flex;flex-direction:column;gap:14px;}
.svc-qprod-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px;}
.svc-qprod-field input{
    width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;
    background:var(--card);color:var(--text);font-size:13px;outline:none;box-sizing:border-box;
    transition:border-color .15s,box-shadow .15s;
}
.svc-qprod-field input:focus{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.svc-qprod-modal__foot{
    display:flex;justify-content:flex-end;gap:8px;
    padding:14px 20px;border-top:1px solid var(--border);
}
.svc-qprod-cancel{
    padding:8px 16px;border:1px solid var(--border);border-radius:8px;
    background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;
}
.svc-qprod-cancel:hover{background:color-mix(in srgb,var(--border) 50%,transparent);}
.svc-qprod-submit{
    padding:8px 18px;border:none;border-radius:8px;
    background:var(--primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;
    display:inline-flex;align-items:center;gap:7px;
    transition:opacity .15s;
}
.svc-qprod-submit:disabled{opacity:.6;cursor:not-allowed;}
.svc-qprod-error{font-size:12px;color:#f87171;display:none;margin-top:-6px;}
.svc-qprod-error.is-visible{display:block;}
</style>
@endonce

<div class="product-field svc-prod-field" id="{{ $spfId }}-field" style="grid-column:1/-1;">
    <label class="svc-prod-field__label">Required Products / Materials</label>

    <div class="svc-prod-search-row">
        <div class="svc-prod-search-wrap">
            <input type="text" class="svc-prod-search-input" id="{{ $spfId }}-search"
                   placeholder="Search products to add…"
                   autocomplete="off">
            <ul class="svc-prod-suggest" id="{{ $spfId }}-suggest" hidden
                data-catalog='@json($prodForJs)'></ul>
        </div>
        <button type="button" class="svc-prod-add-btn" id="{{ $spfId }}-add-btn"
                aria-label="Create a new product">
            <i class="fa fa-plus" aria-hidden="true"></i> New Product
        </button>
    </div>

    <div class="svc-prod-lines" id="{{ $spfId }}-lines"></div>

    {{-- cost summary bar --}}
    <div class="svc-prod-cost-bar" id="{{ $spfId }}-cost-bar">
        <span class="svc-prod-cost-bar__label">
            <i class="fa fa-calculator" aria-hidden="true"></i> Total Material Cost
        </span>
        <div class="svc-prod-cost-bar__items">
            <span class="svc-prod-cost-bar__item" id="{{ $spfId }}-cost-lines"></span>
            <span class="svc-prod-cost-bar__unknown" id="{{ $spfId }}-cost-unknown"></span>
            <span class="svc-prod-cost-bar__total" id="{{ $spfId }}-cost-total"></span>
        </div>
    </div>

    <p class="svc-prod-field__hint">
        Search existing products or click <strong>New Product</strong> to create one instantly.
        Set the quantity needed for each.
    </p>

    <div class="svc-prod-field__hidden" id="{{ $spfId }}-hidden" aria-hidden="true"></div>
    @error('svc_product_ids.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    @error('svc_product_qtys.*')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

{{-- Quick-add product modal --}}
<div class="svc-qprod-backdrop" id="{{ $spfId }}-qmodal" role="dialog" aria-modal="true" aria-label="Create new product">
    <div class="svc-qprod-modal">
        <div class="svc-qprod-modal__head">
            <span class="svc-qprod-modal__title"><i class="fa fa-box" aria-hidden="true" style="margin-right:7px;opacity:.7;"></i>New Product</span>
            <button type="button" class="svc-qprod-modal__close" id="{{ $spfId }}-qmodal-close" aria-label="Close">&times;</button>
        </div>
        <div class="svc-qprod-modal__body">
            <div class="svc-qprod-field">
                <label for="{{ $spfId }}-qp-name">Product name <span style="color:#ef4444;">*</span></label>
                <input type="text" id="{{ $spfId }}-qp-name" placeholder="e.g. Engine Oil, Safety Gloves…" maxlength="255" autocomplete="off">
                <div class="svc-qprod-error" id="{{ $spfId }}-qp-name-err"></div>
            </div>
            <div class="svc-qprod-field">
                <label for="{{ $spfId }}-qp-sku">SKU <span style="color:var(--muted);font-weight:400;text-transform:none;">(optional)</span></label>
                <input type="text" id="{{ $spfId }}-qp-sku" placeholder="e.g. OIL-5W30" maxlength="120" autocomplete="off">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="svc-qprod-field">
                    <label for="{{ $spfId }}-qp-price">Unit price <span style="color:var(--muted);font-weight:400;text-transform:none;">(optional)</span></label>
                    <input type="number" id="{{ $spfId }}-qp-price" placeholder="0.00" min="0" step="0.01" inputmode="decimal">
                </div>
                <div class="svc-qprod-field">
                    <label for="{{ $spfId }}-qp-unit">Unit <span style="color:var(--muted);font-weight:400;text-transform:none;">(optional)</span></label>
                    <input type="text" id="{{ $spfId }}-qp-unit" placeholder="e.g. pcs, L, kg" maxlength="40" autocomplete="off">
                </div>
            </div>
            <div class="svc-qprod-error" id="{{ $spfId }}-qp-global-err"></div>
        </div>
        <div class="svc-qprod-modal__foot">
            <button type="button" class="svc-qprod-cancel" id="{{ $spfId }}-qmodal-cancel">Cancel</button>
            <button type="button" class="svc-qprod-submit" id="{{ $spfId }}-qmodal-submit">
                <i class="fa fa-plus" aria-hidden="true"></i> Create &amp; Add
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const PREFIX    = @json($spfId);
    const searchEl  = document.getElementById(PREFIX + '-search');
    const suggestEl = document.getElementById(PREFIX + '-suggest');
    const linesEl   = document.getElementById(PREFIX + '-lines');
    const hiddenEl  = document.getElementById(PREFIX + '-hidden');
    const addBtn    = document.getElementById(PREFIX + '-add-btn');
    const modal     = document.getElementById(PREFIX + '-qmodal');
    const closeBtn  = document.getElementById(PREFIX + '-qmodal-close');
    const cancelBtn = document.getElementById(PREFIX + '-qmodal-cancel');
    const submitBtn = document.getElementById(PREFIX + '-qmodal-submit');
    const nameInp   = document.getElementById(PREFIX + '-qp-name');
    const skuInp    = document.getElementById(PREFIX + '-qp-sku');
    const priceInp  = document.getElementById(PREFIX + '-qp-price');
    const unitInp   = document.getElementById(PREFIX + '-qp-unit');
    const nameErr   = document.getElementById(PREFIX + '-qp-name-err');
    const globalErr = document.getElementById(PREFIX + '-qp-global-err');

    if (!searchEl || !suggestEl || !linesEl || !modal) return;

    const catalog = JSON.parse(suggestEl.dataset.catalog || '[]');
    const lines   = new Map();

    const QUICK_URL  = @json(route('product.quick-store'));
    const CSRF_TOKEN = @json(csrf_token());

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── hidden inputs ── */
    function syncHidden() {
        hiddenEl.innerHTML = '';
        lines.forEach(line => {
            const i1 = document.createElement('input');
            i1.type = 'hidden'; i1.name = 'svc_product_ids[]'; i1.value = line.id;
            const i2 = document.createElement('input');
            i2.type = 'hidden'; i2.name = 'svc_product_qtys[]'; i2.value = line.qty;
            hiddenEl.appendChild(i1);
            hiddenEl.appendChild(i2);
        });
    }

    const costBar     = document.getElementById(PREFIX + '-cost-bar');
    const costLinesEl = document.getElementById(PREFIX + '-cost-lines');
    const costUnknown = document.getElementById(PREFIX + '-cost-unknown');
    const costTotal   = document.getElementById(PREFIX + '-cost-total');

    function updateCostBar() {
        if (!costBar) return;
        if (lines.size === 0) { costBar.classList.remove('is-visible'); return; }
        let total = 0, unknownCount = 0, hasEst = false;
        lines.forEach(line => {
            if (line.unitCost != null) {
                total += line.unitCost * line.qty;
                if (line.costIsEst) hasEst = true;
            } else {
                unknownCount++;
            }
        });
        if (costTotal) {
            if (unknownCount > 0 && total === 0) {
                costTotal.textContent = '—';
            } else {
                costTotal.textContent = (hasEst ? '~' : '') + total.toFixed(2);
            }
        }
        if (costUnknown) {
            const parts = [];
            if (unknownCount > 0) parts.push(unknownCount + ' item' + (unknownCount > 1 ? 's' : '') + ' without cost data');
            if (hasEst) parts.push('~ estimated from price');
            costUnknown.textContent = parts.join(' · ');
        }
        if (costLinesEl) costLinesEl.textContent = '';
        costBar.classList.add('is-visible');
    }

    /* ── line items ── */
    function renderLines() {
        linesEl.innerHTML = '';
        lines.forEach((line, id) => {
            const row = document.createElement('div');
            row.className = 'svc-prod-line';
            let lineCostStr = '';
            if (line.unitCost != null) {
                const prefix = line.costIsEst ? '~' : '';
                lineCostStr = '<div class="svc-prod-line__sku" data-cost-label style="color:var(--primary);font-weight:600;">'
                    + prefix + 'Cost: ' + (line.unitCost * line.qty).toFixed(2)
                    + '</div>';
            }
            row.innerHTML =
                '<div>'
                    + '<div class="svc-prod-line__name">' + esc(line.name) + '</div>'
                    + (line.sku ? '<div class="svc-prod-line__sku">SKU: ' + esc(line.sku) + '</div>' : '')
                    + lineCostStr
                + '</div>'
                + '<div class="svc-prod-line__qty-wrap">'
                    + '<span class="svc-prod-line__qty-label">Qty</span>'
                    + '<input type="number" class="svc-prod-line__qty" value="' + esc(line.qty) + '" min="0.001" step="any" aria-label="Quantity for ' + esc(line.name) + '">'
                + '</div>'
                + '<button type="button" class="svc-prod-line__remove" aria-label="Remove ' + esc(line.name) + '"><i class="fa fa-times" aria-hidden="true"></i></button>';

            row.querySelector('.svc-prod-line__qty').addEventListener('input', e => {
                line.qty = parseFloat(e.target.value) || 1;
                const costEl = row.querySelector('[data-cost-label]');
                if (costEl && line.unitCost != null) {
                    const prefix = line.costIsEst ? '~' : '';
                    costEl.textContent = prefix + 'Cost: ' + (line.unitCost * line.qty).toFixed(2);
                }
                syncHidden();
                updateCostBar();
            });
            row.querySelector('.svc-prod-line__remove').addEventListener('click', () => {
                lines.delete(id);
                renderLines();
                syncHidden();
                filterSuggest(searchEl.value.trim());
                updateCostBar();
            });
            linesEl.appendChild(row);
        });
        syncHidden();
        updateCostBar();
    }

    function addProduct(prod) {
        if (lines.has(prod.id)) return;
        lines.set(prod.id, { id: prod.id, name: prod.name, sku: prod.sku || '', qty: 1, unitCost: prod.unitCost ?? null, costIsEst: prod.costIsEst ?? false });
        renderLines();
        searchEl.value = '';
        closeSuggest();
        searchEl.focus();
    }

    /* ── autocomplete ── */
    function filterSuggest(q) {
        const ql = (q || '').toLowerCase();
        const matches = catalog.filter(p => {
            if (lines.has(p.id)) return false;
            return !ql || p.name.toLowerCase().includes(ql) || (p.sku || '').toLowerCase().includes(ql);
        }).slice(0, 10);

        suggestEl.innerHTML = '';
        if (matches.length === 0) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'prod-empty';
            btn.textContent = ql ? 'No matching products.' : 'All products already added.';
            li.appendChild(btn);
            suggestEl.appendChild(li);
        } else {
            matches.forEach(p => {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML =
                    '<span>' + esc(p.name) + '</span>'
                    + (p.sku   ? '<span class="prod-sku">'   + esc(p.sku)   + '</span>' : '')
                    + (p.price ? '<span class="prod-price">'  + esc(p.price) + (p.unit ? ' / ' + esc(p.unit) : '') + '</span>' : '');
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => addProduct(p));
                li.appendChild(btn);
                suggestEl.appendChild(li);
            });
        }
        const searchWrap = searchEl.closest('.svc-prod-search-wrap');
        if (searchWrap) {
            const r = searchWrap.getBoundingClientRect();
            suggestEl.style.top   = (r.bottom + 4) + 'px';
            suggestEl.style.left  = r.left + 'px';
            suggestEl.style.width = r.width + 'px';
        }
        suggestEl.hidden = false;
    }

    function closeSuggest() { suggestEl.hidden = true; }

    searchEl.addEventListener('focus', () => filterSuggest(searchEl.value.trim()));
    searchEl.addEventListener('input', () => filterSuggest(searchEl.value.trim()));
    searchEl.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSuggest(); searchEl.blur(); }
        if (e.key === 'Enter') {
            e.preventDefault();
            const ql = searchEl.value.trim().toLowerCase();
            const exact = catalog.find(p => p.name.toLowerCase() === ql && !lines.has(p.id));
            if (exact) addProduct(exact);
        }
    });
    document.addEventListener('click', e => {
        if (!searchEl.closest('.svc-prod-search-wrap').contains(e.target)) closeSuggest();
    });
    document.addEventListener('scroll', closeSuggest, true);

    /* ── quick-add modal ── */
    function openModal() {
        nameInp.value = skuInp.value = priceInp.value = unitInp.value = '';
        nameErr.textContent = ''; nameErr.classList.remove('is-visible');
        globalErr.textContent = ''; globalErr.classList.remove('is-visible');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa fa-plus" aria-hidden="true"></i> Create &amp; Add';
        modal.classList.add('is-open');
        requestAnimationFrame(() => nameInp.focus());
    }

    function closeModal() {
        modal.classList.remove('is-open');
        addBtn.focus();
    }

    addBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });

    submitBtn.addEventListener('click', () => {
        const name = nameInp.value.trim();
        nameErr.textContent = ''; nameErr.classList.remove('is-visible');
        globalErr.textContent = ''; globalErr.classList.remove('is-visible');

        if (!name) {
            nameErr.textContent = 'Product name is required.';
            nameErr.classList.add('is-visible');
            nameInp.focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Creating…';

        fetch(QUICK_URL, {
            method : 'POST',
            headers: {
                'Content-Type'    : 'application/json',
                'Accept'          : 'application/json',
                'X-CSRF-TOKEN'    : CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name       : name,
                sku        : skuInp.value.trim()  || null,
                unit_price : priceInp.value !== '' ? parseFloat(priceInp.value) : null,
                unit       : unitInp.value.trim()  || null,
            }),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, status: r.status, data: d })))
        .then(({ ok, data }) => {
            if (ok && data.product) {
                const p = data.product;
                catalog.push({
                    id    : p.id,
                    name  : p.name,
                    sku   : p.sku   || '',
                    price : p.unit_price ? Number(p.unit_price).toFixed(2) : '',
                    unit  : p.unit  || '',
                });
                addProduct({ id: p.id, name: p.name, sku: p.sku || '' });
                closeModal();
            } else {
                const msg = data.errors?.name?.[0] || data.message || 'Could not create product.';
                if (data.errors?.name) {
                    nameErr.textContent = msg; nameErr.classList.add('is-visible');
                } else {
                    globalErr.textContent = msg; globalErr.classList.add('is-visible');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa fa-plus" aria-hidden="true"></i> Create &amp; Add';
            }
        })
        .catch(() => {
            globalErr.textContent = 'Network error. Please try again.';
            globalErr.classList.add('is-visible');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa fa-plus" aria-hidden="true"></i> Create &amp; Add';
        });
    });

    /* ── init ── */
    @json($initLines).forEach(line => {
        lines.set(line.id, { id: line.id, name: line.name, sku: line.sku || '', qty: line.qty, unitCost: line.unitCost ?? null, costIsEst: line.costIsEst ?? false });
    });
    renderLines();
})();
</script>
