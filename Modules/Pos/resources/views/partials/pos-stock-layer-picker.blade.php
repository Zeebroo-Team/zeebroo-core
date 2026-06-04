@php
    $currency = $currency ?? '';
@endphp

@once
<style>
.pos-layer-picker{position:fixed;inset:0;z-index:340;display:flex;justify-content:center;align-items:center;padding:16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-layer-picker.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-layer-picker__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-layer-picker__panel{position:relative;z-index:1;width:min(100%,520px);max-height:min(85vh,560px);display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-layer-picker__head{padding:12px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.pos-layer-picker__head h2{margin:0 0 4px;font-size:15px;font-weight:800;}
.pos-layer-picker__head p{margin:0;font-size:12px;color:var(--muted);}
.pos-layer-picker__list{flex:1;min-height:0;overflow:auto;padding:10px 14px;display:grid;gap:8px;}
.pos-layer-picker__option{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:10px 12px;text-align:left;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);cursor:pointer;transition:border-color .15s,background .15s;}
.pos-layer-picker__option:hover,.pos-layer-picker__option:focus-visible{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);outline:none;}
.pos-layer-picker__option__main{min-width:0;}
.pos-layer-picker__option__label{font-size:13px;font-weight:700;}
.pos-layer-picker__option__meta{font-size:11px;color:var(--muted);margin-top:2px;}
.pos-layer-picker__option__price{font-size:14px;font-weight:800;white-space:nowrap;}
.pos-layer-picker__foot{padding:10px 14px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;}
.pos-layer-picker__cancel{padding:8px 14px;font-size:13px;font-weight:600;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;}
html.pos-layer-picker-open,html.pos-layer-picker-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-layer-picker" class="pos-layer-picker" role="dialog" aria-modal="true" aria-labelledby="pos-layer-picker-title" aria-hidden="true">
    <div class="pos-layer-picker__backdrop" data-pos-layer-picker-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-layer-picker__panel">
        <div class="pos-layer-picker__head">
            <h2 id="pos-layer-picker-title">Choose stock &amp; price</h2>
            <p id="pos-layer-picker-subtitle"></p>
        </div>
        <div class="pos-layer-picker__list" id="pos-layer-picker-list" role="listbox" aria-label="Stock batches"></div>
        <div class="pos-layer-picker__foot">
            <button type="button" class="pos-layer-picker__cancel" data-pos-layer-picker-close>Cancel</button>
        </div>
    </div>
</div>

@once
<script>
(function () {
    let currencySuffix = '';
    let pendingResolve = null;
    let pickerBound = false;

    function modalEl() {
        return document.getElementById('pos-layer-picker');
    }

    function listEl() {
        return document.getElementById('pos-layer-picker-list');
    }

    function money(n) {
        return Number(n || 0).toFixed(2) + currencySuffix;
    }

    function formatQty(value) {
        const n = Number(value) || 0;
        return String(n.toFixed(3)).replace(/\.?0+$/, '');
    }

    function setOpen(open) {
        const modal = modalEl();
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-layer-picker-open', open);
        if (!open && pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            resolve(null);
        }
    }

    function bindPickerOnce() {
        if (pickerBound) return;
        pickerBound = true;
        const modal = modalEl();
        modal?.querySelectorAll('[data-pos-layer-picker-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                setOpen(false);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal?.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    }

    window.posPickStockLayer = function (product, layers) {
        return new Promise(function (resolve) {
            bindPickerOnce();
            const modal = modalEl();
            const list = listEl();
            const subtitleEl = document.getElementById('pos-layer-picker-subtitle');

            if (!modal || !list || !layers || layers.length === 0) {
                resolve(null);
                return;
            }
            if (layers.length === 1) {
                resolve(layers[0]);
                return;
            }

            pendingResolve = resolve;
            if (subtitleEl) {
                subtitleEl.textContent = (product?.name || 'Product')
                    + ' — tap a batch to add it to the sale at that price.';
            }

            // Group layers by price — same-price batches become one row
            var priceMap = {};
            layers.forEach(function (layer) {
                var key = Number(layer.unit_sell_price || 0).toFixed(2);
                if (!priceMap[key]) priceMap[key] = [];
                priceMap[key].push(layer);
            });
            var displayOptions = Object.keys(priceMap).map(function (priceKey) {
                var group = priceMap[priceKey];
                if (group.length === 1) return { layer: group[0], merged: false };
                // Merge: use first (oldest) layer for id/price, combined stock for display
                var totalStock = group.reduce(function (s, l) { return s + (parseFloat(l.quantity_remaining) || 0); }, 0);
                var labels = group.map(function (l) { return l.label || ('Batch #' + l.id); });
                var mergedLabel = labels[0] + ' + ' + (group.length - 1) + ' more batch' + (group.length > 2 ? 'es' : '');
                return {
                    layer: Object.assign({}, group[0], { quantity_remaining: totalStock }),
                    merged: true,
                    mergedLabel: mergedLabel,
                    batchCount: group.length,
                    totalStock: totalStock,
                };
            });

            list.innerHTML = '';
            displayOptions.forEach(function (opt) {
                var layer = opt.layer;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-layer-picker__option';
                btn.setAttribute('role', 'option');
                btn.innerHTML =
                    '<span class="pos-layer-picker__option__main">' +
                        '<span class="pos-layer-picker__option__label"></span>' +
                        '<span class="pos-layer-picker__option__meta"></span>' +
                    '</span>' +
                    '<span class="pos-layer-picker__option__price"></span>';
                btn.querySelector('.pos-layer-picker__option__label').textContent =
                    opt.merged ? opt.mergedLabel : (layer.label || ('Batch #' + layer.id));
                var metaStock = opt.merged
                    ? formatQty(opt.totalStock) + ' total in stock (' + opt.batchCount + ' batches)'
                    : formatQty(layer.quantity_remaining) + ' in stock · cost ' + money(layer.unit_cost);
                btn.querySelector('.pos-layer-picker__option__meta').textContent = metaStock;
                btn.querySelector('.pos-layer-picker__option__price').textContent =
                    money(layer.unit_sell_price);
                btn.addEventListener('click', function () {
                    pendingResolve = null;
                    setOpen(false);
                    resolve(layer);
                });
                list.appendChild(btn);
            });

            // Auto-resolve if grouping reduced to a single price option
            if (displayOptions.length === 1) {
                pendingResolve = null;
                resolve(displayOptions[0].layer);
                return;
            }

            setOpen(true);
        });
    };

    window.initPosStockLayerPicker = function (options) {
        options = options || {};
        currencySuffix = options.currencySuffix || '';
        bindPickerOnce();
    };
})();
</script>
@endonce
