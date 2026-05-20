@php
    $pfxRaw = isset($fieldIdPrefix) ? (string) $fieldIdPrefix : '';
    $rootId = $pfxRaw !== '' ? $pfxRaw . '-bundle' : 'product-bundle';
    $toggleId = $rootId . '-toggle';
    $panelId = $rootId . '-panel';
    $listId = $rootId . '-list';
    $searchId = $rootId . '-search';
    $productModel = $product ?? null;
    $catalog = collect($bundlePickerCatalog ?? []);
    $isBundle = (bool) old('is_bundle', $productModel?->is_bundle ?? false);
    $oldItems = old('bundle_items');
    if (is_array($oldItems)) {
        $initialRows = collect($oldItems)->map(function ($row) use ($catalog) {
            $id = (int) ($row['product_id'] ?? 0);
            $match = $catalog->firstWhere('id', $id);

            return [
                'product_id' => $id,
                'name' => $match['name'] ?? ('Product #'.$id),
                'sku' => $match['sku'] ?? null,
                'unit_price' => $match['unit_price'] ?? null,
                'quantity' => (float) ($row['quantity'] ?? 1),
            ];
        })->filter(fn ($r) => $r['product_id'] > 0)->values();
    } elseif ($productModel?->is_bundle) {
        $initialRows = $productModel->bundleItems->map(fn ($row) => [
            'product_id' => (int) $row->item_product_id,
            'name' => $row->itemProduct?->name ?? ('Product #'.$row->item_product_id),
            'sku' => $row->itemProduct?->sku,
            'unit_price' => $row->itemProduct?->unit_price !== null ? (float) $row->itemProduct->unit_price : null,
            'quantity' => (float) $row->quantity,
        ])->values();
    } else {
        $initialRows = collect();
    }
    $currencyLabel = filled($currency ?? null) ? (string) $currency : '';
    $modalBundleField = ($fieldIdPrefix ?? '') === 'modal';
@endphp

<div class="product-field product-bundle-field @if($modalBundleField) product-bundle-field--modal-managed @endif" style="grid-column:1/-1;" id="{{ $rootId }}" data-bundle-root @if($modalBundleField) data-product-bundle-modal-field hidden @endif data-catalog='@json($catalog->values())'>
    <section class="product-bundle-card" aria-labelledby="{{ $rootId }}-title">
        <header class="product-bundle-card__head">
            <div class="product-bundle-card__head-text">
                <h3 class="product-bundle-card__title" id="{{ $rootId }}-title">
                    <i class="fa fa-boxes-stacked" aria-hidden="true"></i> Product bundle
                </h3>
                <p class="product-bundle-card__lead muted">Combine other products into one sellable kit.</p>
            </div>
            <div class="product-bundle-card__toggle">
                <input type="hidden" name="is_bundle" value="0">
                <label class="product-bundle-toggle" for="{{ $toggleId }}">
                    <input type="checkbox" name="is_bundle" id="{{ $toggleId }}" value="1" data-bundle-toggle @checked($isBundle)>
                    <span class="product-bundle-toggle__lbl">Bundle product</span>
                </label>
            </div>
        </header>
        <div id="{{ $panelId }}" class="product-bundle-card__body" data-bundle-panel @unless($isBundle) hidden @endunless>
        <p class="product-bundle-panel__hint muted">Add products included in this bundle and set how many of each per bundle sold.</p>
        <div class="product-bundle-search-wrap">
            <label class="sr-only" for="{{ $searchId }}">Search products to add</label>
            <input type="text" id="{{ $searchId }}" class="product-bundle-search" placeholder="Search products to add…" data-bundle-search autocomplete="off">
            <ul class="product-bundle-suggest" data-bundle-suggest hidden></ul>
        </div>
        <ul id="{{ $listId }}" class="product-bundle-list" data-bundle-list>
            @foreach($initialRows as $index => $row)
                <li class="product-bundle-row" data-bundle-row data-product-id="{{ $row['product_id'] }}">
                    <div class="product-bundle-row__info">
                        <strong class="product-bundle-row__name">{{ $row['name'] }}</strong>
                        @if($row['sku'])
                            <span class="muted product-bundle-row__sku">{{ $row['sku'] }}</span>
                        @endif
                    </div>
                    <label class="product-bundle-row__qty">
                        <span class="muted">Qty</span>
                        <input type="number" name="bundle_items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" min="0.001" step="any" inputmode="decimal" data-bundle-qty>
                    </label>
                    <input type="hidden" name="bundle_items[{{ $index }}][product_id]" value="{{ $row['product_id'] }}" data-bundle-product-id>
                    <button type="button" class="product-bundle-row__remove" data-bundle-remove aria-label="Remove">&times;</button>
                </li>
            @endforeach
        </ul>
        <p class="product-bundle-total muted" data-bundle-total hidden>
            Components total @if($currencyLabel)({{ $currencyLabel }})@endif: <strong data-bundle-total-value>0.00</strong>
        </p>
        </div>
        @error('bundle_items')<div class="product-bundle-card__err">{{ $message }}</div>@enderror
        @error('is_bundle')<div class="product-bundle-card__err">{{ $message }}</div>@enderror
    </section>
</div>

@once
<style>
.product-bundle-card{
    border:1px solid var(--border);border-radius:12px;
    background:color-mix(in srgb,var(--card) 98%,transparent);
    overflow:hidden;
}
.product-bundle-card__head{
    display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:10px 14px;
    padding:12px 14px;border-bottom:1px solid color-mix(in srgb,var(--border) 85%,transparent);
    background:color-mix(in srgb,var(--card) 94%,transparent);
}
.product-bundle-card__title{margin:0;font-size:14px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;letter-spacing:-.02em;}
.product-bundle-card__title i{font-size:13px;color:var(--primary);opacity:.9;}
.product-bundle-card__lead{margin:4px 0 0;font-size:12px;line-height:1.4;max-width:42ch;}
.product-bundle-card__toggle{flex-shrink:0;}
.product-bundle-toggle{
    display:inline-flex;align-items:center;gap:8px;padding:7px 11px;font-size:12px;font-weight:600;color:var(--text);
    border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);cursor:pointer;
}
.product-bundle-toggle input{margin:0;accent-color:var(--primary);}
.product-bundle-toggle:has(input:checked){
    border-color:color-mix(in srgb,var(--primary) 40%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,transparent);
}
.product-bundle-card__body{padding:12px 14px 14px;}
.product-bundle-card__body[hidden]{display:none;}
.product-bundle-card__err{margin:0;padding:0 14px 12px;font-size:12px;color:#f87171;}
.product-bundle-panel__hint{margin:0 0 10px;font-size:12px;line-height:1.4;}
.product-bundle-search-wrap{position:relative;margin-bottom:10px;}
.product-bundle-search{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.product-bundle-search:focus-visible{outline:none;border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.product-bundle-suggest{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 4px);margin:0;padding:4px 0;list-style:none;max-height:180px;overflow:auto;border:1px solid var(--border);border-radius:10px;background:var(--card);box-shadow:0 12px 28px rgba(0,0,0,.22);}
.product-bundle-suggest[hidden]{display:none;}
.product-bundle-suggest button{display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:transparent;font-size:13px;color:var(--text);cursor:pointer;}
.product-bundle-suggest button:hover,.product-bundle-suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.product-bundle-list{
    list-style:none;margin:0;padding:8px;display:flex;flex-direction:column;gap:6px;
    border:1px dashed color-mix(in srgb,var(--border) 80%,transparent);border-radius:9px;
    background:color-mix(in srgb,var(--card) 94%,transparent);min-height:44px;
}
.product-bundle-list:empty::before{
    content:"No products in bundle yet — search above to add.";display:block;padding:6px 4px;font-size:12px;color:var(--muted);text-align:center;
}
.product-bundle-row{display:grid;grid-template-columns:1fr auto auto;gap:8px 10px;align-items:center;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--card);}
.product-bundle-row__info{min-width:0;}
.product-bundle-row__name{display:block;font-size:13px;font-weight:700;line-height:1.25;}
.product-bundle-row__sku{font-size:11px;}
.product-bundle-row__qty{display:flex;align-items:center;gap:6px;font-size:11px;}
.product-bundle-row__qty input{width:72px;padding:6px 8px;font-size:13px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.product-bundle-row__remove{width:28px;height:28px;padding:0;border:1px solid var(--border);border-radius:7px;background:transparent;color:var(--muted);font-size:18px;line-height:1;cursor:pointer;}
.product-bundle-row__remove:hover{border-color:color-mix(in srgb,#f87171 45%,var(--border));color:#f87171;}
.product-bundle-total{margin:10px 0 0;font-size:12px;}
.product-bundle-field--modal-managed .product-bundle-card__toggle{display:none;}
.product-bundle-field--modal-managed .product-bundle-card__head{justify-content:flex-start;}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
</style>
<script>
(function () {
    if (window.__productBundleFieldInit) return;
    window.__productBundleFieldInit = true;

    function reindexRows(list) {
        list.querySelectorAll('[data-bundle-row]').forEach(function (row, index) {
            var pid = row.querySelector('[data-bundle-product-id]');
            var qty = row.querySelector('[data-bundle-qty]');
            if (pid) pid.name = 'bundle_items[' + index + '][product_id]';
            if (qty) qty.name = 'bundle_items[' + index + '][quantity]';
        });
    }

    function updateTotal(root) {
        var totalEl = root.querySelector('[data-bundle-total]');
        var valueEl = root.querySelector('[data-bundle-total-value]');
        if (!totalEl || !valueEl) return;
        var sum = 0;
        var any = false;
        root.querySelectorAll('[data-bundle-row]').forEach(function (row) {
            var price = parseFloat(row.getAttribute('data-unit-price') || '');
            var qty = parseFloat(row.querySelector('[data-bundle-qty]')?.value || '0');
            if (!isNaN(price) && qty > 0) {
                sum += price * qty;
                any = true;
            }
        });
        totalEl.hidden = !any;
        valueEl.textContent = any ? sum.toFixed(2) : '0.00';
    }

    function bindRoot(root) {
        if (!root || root._bundleBound) return;
        root._bundleBound = true;

        var catalog = [];
        try { catalog = JSON.parse(root.getAttribute('data-catalog') || '[]'); } catch (e) { catalog = []; }

        var toggle = root.querySelector('[data-bundle-toggle]');
        var panel = root.querySelector('[data-bundle-panel]');
        var list = root.querySelector('[data-bundle-list]');
        var search = root.querySelector('[data-bundle-search]');
        var suggest = root.querySelector('[data-bundle-suggest]');

        toggle && toggle.addEventListener('change', function () {
            panel.hidden = !toggle.checked;
            if (toggle.checked && search) search.focus();
        });

        function existingIds() {
            return Array.from(list.querySelectorAll('[data-bundle-row]')).map(function (row) {
                return parseInt(row.getAttribute('data-product-id') || '0', 10);
            });
        }

        function addRow(product, qty) {
            if (existingIds().indexOf(product.id) !== -1) return;
            var li = document.createElement('li');
            li.className = 'product-bundle-row';
            li.setAttribute('data-bundle-row', '');
            li.setAttribute('data-product-id', String(product.id));
            if (product.unit_price != null) li.setAttribute('data-unit-price', String(product.unit_price));
            var skuHtml = product.sku ? '<span class="muted product-bundle-row__sku">' + escapeHtml(product.sku) + '</span>' : '';
            li.innerHTML =
                '<div class="product-bundle-row__info"><strong class="product-bundle-row__name">' + escapeHtml(product.name) + '</strong>' + skuHtml + '</div>' +
                '<label class="product-bundle-row__qty"><span class="muted">Qty</span><input type="number" value="' + (qty || 1) + '" min="0.001" step="any" inputmode="decimal" data-bundle-qty></label>' +
                '<input type="hidden" value="' + product.id + '" data-bundle-product-id>' +
                '<button type="button" class="product-bundle-row__remove" data-bundle-remove aria-label="Remove">&times;</button>';
            list.appendChild(li);
            reindexRows(list);
            updateTotal(root);
            if (toggle && !toggle.checked) {
                toggle.checked = true;
                panel.hidden = false;
            }
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }

        list.addEventListener('click', function (e) {
            if (e.target.closest('[data-bundle-remove]')) {
                e.target.closest('[data-bundle-row]')?.remove();
                reindexRows(list);
                updateTotal(root);
            }
        });
        list.addEventListener('input', function (e) {
            if (e.target.matches('[data-bundle-qty]')) updateTotal(root);
        });

        function renderSuggest(query) {
            if (!suggest) return;
            var q = String(query || '').trim().toLowerCase();
            if (!q) { suggest.hidden = true; suggest.innerHTML = ''; return; }
            var used = existingIds();
            var matches = catalog.filter(function (p) {
                if (used.indexOf(p.id) !== -1) return false;
                var hay = (p.name + ' ' + (p.sku || '')).toLowerCase();
                return hay.indexOf(q) !== -1;
            }).slice(0, 12);
            if (!matches.length) { suggest.hidden = true; suggest.innerHTML = ''; return; }
            suggest.innerHTML = matches.map(function (p) {
                var sub = p.sku ? ' <span class="muted">(' + escapeHtml(p.sku) + ')</span>' : '';
                return '<li><button type="button" data-pick-id="' + p.id + '">' + escapeHtml(p.name) + sub + '</button></li>';
            }).join('');
            suggest.hidden = false;
        }

        search && search.addEventListener('input', function () { renderSuggest(search.value); });
        search && search.addEventListener('focus', function () { renderSuggest(search.value); });
        suggest && suggest.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-pick-id]');
            if (!btn) return;
            var id = parseInt(btn.getAttribute('data-pick-id'), 10);
            var product = catalog.find(function (p) { return p.id === id; });
            if (product) {
                addRow(product, 1);
                search.value = '';
                renderSuggest('');
            }
        });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) suggest && (suggest.hidden = true);
        });

        root.querySelectorAll('[data-bundle-row]').forEach(function (row) {
            var id = parseInt(row.getAttribute('data-product-id') || '0', 10);
            var product = catalog.find(function (p) { return p.id === id; });
            if (product && product.unit_price != null) row.setAttribute('data-unit-price', String(product.unit_price));
        });
        updateTotal(root);

        root._resetProductBundle = function () {
            if (toggle) {
                toggle.checked = false;
            }
            if (panel) {
                panel.hidden = true;
            }
            if (list) {
                list.innerHTML = '';
                reindexRows(list);
            }
            if (search) {
                search.value = '';
            }
            if (suggest) {
                suggest.hidden = true;
                suggest.innerHTML = '';
            }
            updateTotal(root);
        };
    }

    window.resetProductBundleFields = function (container) {
        (container || document).querySelectorAll('[data-bundle-root]').forEach(function (root) {
            if (typeof root._resetProductBundle === 'function') {
                root._resetProductBundle();
            }
        });
    };

    document.querySelectorAll('[data-bundle-root]').forEach(bindRoot);
})();
</script>
@endonce
