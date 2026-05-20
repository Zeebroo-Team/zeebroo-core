@php
    $productUnits = $productUnits ?? collect();
    $currency = $currency ?? '';
@endphp

@once
<style>
.pos-add-product-modal{position:fixed;inset:0;z-index:320;display:flex;justify-content:center;align-items:center;padding:16px;overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-add-product-modal.is-open{opacity:1;visibility:visible;pointer-events:auto;}
.pos-add-product-modal__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.pos-add-product-modal__panel{position:relative;z-index:1;width:min(100%,480px);max-height:min(90vh,640px);display:flex;flex-direction:column;border-radius:12px;border:1px solid var(--border);background:var(--card);box-shadow:0 16px 40px rgba(0,0,0,.28);}
.pos-add-product-modal__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.pos-add-product-modal__head h2{margin:0;font-size:15px;font-weight:800;}
.pos-add-product-modal__close{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);cursor:pointer;font-size:16px;line-height:1;}
.pos-add-product-modal__body{flex:1;min-height:0;overflow:auto;padding:14px;}
.pos-add-product-modal__foot{flex-shrink:0;padding:12px 14px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;}
.pos-add-product-form{display:grid;gap:12px;}
.pos-add-product-form .pos-add-product-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.pos-add-product-form .pos-add-product-field input,.pos-add-product-form .pos-add-product-field select{width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-add-product-form .pos-add-product-field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;cursor:pointer;}
.pos-add-product-form__row--sku{display:flex;gap:6px;align-items:stretch;}
.pos-add-product-form__row--sku input{flex:1;min-width:0;}
.pos-add-product-form__sku-gen{flex-shrink:0;padding:9px 11px;font-size:11px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;white-space:nowrap;}
.pos-add-product-form__row--2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.pos-add-product-form__err{margin:0;font-size:12px;color:color-mix(in srgb,#f87171 90%,var(--text));}
.pos-add-product-form__banner{margin:0;padding:10px 12px;border-radius:8px;border:1px solid color-mix(in srgb,#f87171 40%,var(--border));background:color-mix(in srgb,#f87171 8%,transparent);font-size:12px;color:var(--text);}
.pos-add-product-modal__btn{padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;}
.pos-add-product-modal__btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);}
.pos-add-product-modal__btn:disabled{opacity:.5;cursor:not-allowed;}
html.pos-add-product-modal-open,html.pos-add-product-modal-open body{overflow:hidden;}
</style>
@endonce

<div id="pos-add-product-modal" class="pos-add-product-modal" role="dialog" aria-modal="true" aria-labelledby="pos-add-product-title" aria-hidden="true">
    <div class="pos-add-product-modal__backdrop" data-pos-add-product-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-add-product-modal__panel">
        <div class="pos-add-product-modal__head">
            <h2 id="pos-add-product-title">Add product</h2>
            <button type="button" class="pos-add-product-modal__close" data-pos-add-product-close aria-label="Close">&times;</button>
        </div>
        <form id="pos-add-product-form" class="pos-add-product-form pos-add-product-modal__body" autocomplete="off">
            <p id="pos-add-product-form-banner" class="pos-add-product-form__banner" hidden role="alert"></p>
            <div class="pos-add-product-field">
                <label for="pos-add-product-name">Product name</label>
                <input type="text" id="pos-add-product-name" name="name" maxlength="255" required placeholder="e.g. Mineral water 500ml">
                <p class="pos-add-product-form__err" data-pos-add-product-error="name" hidden></p>
            </div>
            <div class="pos-add-product-field">
                <label for="pos-add-product-sku">SKU / code</label>
                <div class="pos-add-product-form__row--sku">
                    <input type="text" id="pos-add-product-sku" name="sku" maxlength="120" placeholder="Optional" data-product-sku-input>
                    <button type="button" class="pos-add-product-form__sku-gen" data-product-sku-generate data-sku-input-id="pos-add-product-sku" data-generate-url="{{ route('product.sku.generate') }}" aria-label="Generate SKU">
                        <i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="pos-add-product-form__err" data-pos-add-product-error="sku" hidden></p>
            </div>
            <div class="pos-add-product-form__row--2">
                <div class="pos-add-product-field">
                    <label for="pos-add-product-price">Unit price{{ filled($currency) ? ' ('.$currency.')' : '' }}</label>
                    <input type="number" id="pos-add-product-price" name="unit_price" step="0.01" min="0" inputmode="decimal" placeholder="0.00" required>
                    <p class="pos-add-product-form__err" data-pos-add-product-error="unit_price" hidden></p>
                </div>
                <div class="pos-add-product-field">
                    <label for="pos-add-product-stock">Stock on hand</label>
                    <input type="number" id="pos-add-product-stock" name="stock_quantity" step="0.001" min="0" inputmode="decimal" value="0">
                    <p class="pos-add-product-form__err" data-pos-add-product-error="stock_quantity" hidden></p>
                </div>
            </div>
            @if($productUnits->isNotEmpty())
                <div class="pos-add-product-field">
                    <label for="pos-add-product-unit">Unit of measure</label>
                    <select id="pos-add-product-unit" name="product_unit_id">
                        <option value="">— None —</option>
                        @foreach($productUnits as $unitRow)
                            <option value="{{ $unitRow->id }}">{{ $unitRow->displayLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </form>
        <div class="pos-add-product-modal__foot">
            <button type="button" class="pos-add-product-modal__btn" data-pos-add-product-close>Cancel</button>
            <button type="submit" form="pos-add-product-form" class="pos-add-product-modal__btn pos-add-product-modal__btn--primary" id="pos-add-product-submit">Save product</button>
        </div>
    </div>
</div>

@once
<script>
window.initPosAddProductModal = function (options) {
    options = options || {};
    const modal = document.getElementById('pos-add-product-modal');
    const openBtns = document.querySelectorAll('[data-pos-add-product-open]');
    const form = document.getElementById('pos-add-product-form');
    const banner = document.getElementById('pos-add-product-form-banner');
    const submitBtn = document.getElementById('pos-add-product-submit');
    const productsEl = options.productsEl;
    const productsBySku = options.productsBySku || {};
    const currencySuffix = options.currencySuffix || '';
    const gridVariant = options.gridVariant || 'online';
    const storeUrl = options.storeUrl || '';
    const onAdded = typeof options.onProductAdded === 'function' ? options.onProductAdded : null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function formatQty(value) {
        const n = Number(value) || 0;
        return String(n.toFixed(3)).replace(/\.?0+$/, '');
    }

    function clearErrors() {
        if (banner) {
            banner.hidden = true;
            banner.textContent = '';
        }
        form?.querySelectorAll('[data-pos-add-product-error]').forEach(function (el) {
            el.hidden = true;
            el.textContent = '';
        });
    }

    function showErrors(payload) {
        clearErrors();
        const errors = payload?.errors || {};
        const keys = Object.keys(errors);
        if (keys.length) {
            keys.forEach(function (key) {
                const el = form?.querySelector('[data-pos-add-product-error="' + key + '"]');
                const msg = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                if (el && msg) {
                    el.textContent = msg;
                    el.hidden = false;
                }
            });
        }
        if (banner && (payload?.message || keys.length)) {
            banner.textContent = payload.message || errors[keys[0]]?.[0] || 'Could not save product.';
            banner.hidden = false;
        }
    }

    function resetForm() {
        clearErrors();
        form?.reset();
        const stock = document.getElementById('pos-add-product-stock');
        if (stock) stock.value = '0';
    }

    function setOpen(open) {
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-add-product-modal-open', open);
        if (open) {
            document.getElementById('pos-add-product-name')?.focus();
        }
    }

    function buildProductButton(product) {
        const stock = parseFloat(product.stock_quantity) || 0;
        const outOfStock = stock <= 0;
        const unitPrice = product.unit_sell_price != null
            ? Number(product.unit_sell_price).toFixed(2)
            : '0';
        const sku = product.sku || '';
        const stockLabel = formatQty(stock);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.posProduct = '1';
        btn.dataset.productId = String(product.id);
        btn.dataset.productName = product.name || 'Product';
        btn.dataset.productSku = sku;
        btn.dataset.unitPrice = unitPrice;
        btn.dataset.stock = stockLabel;
        btn.dataset.productLayers = JSON.stringify(product.layers || []);
        btn.dataset.requiresLayerPick = product.requires_layer_pick ? '1' : '0';

        if (gridVariant === 'register') {
            btn.className = 'pos-product' + (outOfStock ? ' is-disabled' : '');
            if (outOfStock) btn.disabled = true;
            if (product.image_url) {
                const img = document.createElement('img');
                img.src = product.image_url;
                img.alt = '';
                img.className = 'pos-product__img';
                img.loading = 'lazy';
                btn.appendChild(img);
            } else {
                const ph = document.createElement('div');
                ph.className = 'pos-product__placeholder';
                ph.innerHTML = '<i class="fa fa-box" aria-hidden="true"></i>';
                btn.appendChild(ph);
            }
            const name = document.createElement('span');
            name.className = 'pos-product__name';
            name.textContent = product.name;
            const meta = document.createElement('span');
            meta.className = 'pos-product__meta';
            meta.textContent = (sku ? sku + ' · ' : '') + 'Stock ' + stockLabel
                + (product.layer_count > 1 ? ' · ' + product.layer_count + ' batches' : '');
            const price = document.createElement('span');
            price.className = 'pos-product__price';
            price.textContent = product.unit_sell_price != null
                ? Number(product.unit_sell_price).toFixed(2) + currencySuffix
                : 'No price';
            btn.append(name, meta, price);
        } else {
            btn.className = 'pos-online__item' + (outOfStock ? ' is-out' : '');
            if (outOfStock) btn.disabled = true;
            if (product.image_url) {
                const img = document.createElement('img');
                img.src = product.image_url;
                img.alt = '';
                img.loading = 'lazy';
                btn.appendChild(img);
            } else {
                const ph = document.createElement('div');
                ph.className = 'pos-online__item__ph';
                ph.innerHTML = '<i class="fa fa-box" aria-hidden="true"></i>';
                btn.appendChild(ph);
            }
            const name = document.createElement('span');
            name.className = 'pos-online__item__name';
            name.textContent = product.name;
            const meta = document.createElement('span');
            meta.className = 'pos-online__item__meta';
            meta.textContent = (sku ? sku + ' · ' : '') + stockLabel + ' in stock'
                + (product.layer_count > 1 ? ' · ' + product.layer_count + ' batches' : '');
            const price = document.createElement('span');
            price.className = 'pos-online__item__price';
            price.textContent = product.unit_sell_price != null
                ? Number(product.unit_sell_price).toFixed(2) + currencySuffix
                : '—';
            btn.append(name, meta, price);
        }

        return btn;
    }

    function appendProduct(product) {
        if (!productsEl || !product) return null;
        const btn = buildProductButton(product);
        productsEl.appendChild(btn);
        if (product.sku) {
            productsBySku[product.sku] = product;
        }
        const emptyBanner = document.querySelector('[data-pos-empty-products-banner]');
        if (emptyBanner) emptyBanner.hidden = true;
        return btn;
    }

    openBtns.forEach(function (openBtn) {
        openBtn.addEventListener('click', function () {
            resetForm();
            setOpen(true);
        });
    });

    modal?.querySelectorAll('[data-pos-add-product-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setOpen(false);
            resetForm();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal?.classList.contains('is-open')) {
            setOpen(false);
            resetForm();
        }
    });

    form?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!storeUrl) return;

        clearErrors();
        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());

        const prevLabel = submitBtn?.textContent;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';
        }

        fetch(storeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (r) {
                if (!r.ok) {
                    showErrors(r.data || {});
                    return;
                }
                const product = r.data?.product;
                const btn = appendProduct(product);
                setOpen(false);
                resetForm();
                if (onAdded && btn) onAdded(btn, product);
                if (typeof window.playPosBeep === 'function') {
                    window.playPosBeep();
                }
            })
            .catch(function () {
                showErrors({ message: 'Could not reach the server. Try again.' });
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = prevLabel || 'Save product';
                }
            });
    });

    return { appendProduct: appendProduct, open: function () { resetForm(); setOpen(true); } };
};

(function () {
    if (window.__productSkuGenerateInit) return;
    window.__productSkuGenerateInit = true;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function nameForSku(btn) {
        const form = btn.closest('form');
        if (!form) return '';
        const nameInput = form.querySelector('[name="name"]');
        return nameInput ? String(nameInput.value || '').trim() : '';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-product-sku-generate]');
        if (!btn || btn.disabled) return;

        const inputId = btn.getAttribute('data-sku-input-id');
        const input = inputId ? document.getElementById(inputId) : btn.closest('.pos-add-product-form__row--sku')?.querySelector('[data-product-sku-input]');
        const url = btn.getAttribute('data-generate-url');
        if (!input || !url) return;

        const payload = { name: nameForSku(btn) };
        const prevHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (r) {
                if (!r.ok || !r.data?.sku) {
                    window.alert((r.data && r.data.message) || 'Could not generate SKU.');
                    return;
                }
                input.value = String(r.data.sku);
                input.focus();
            })
            .catch(function () {
                window.alert('Could not reach the server.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = prevHtml;
            });
    });
})();
</script>
@endonce
