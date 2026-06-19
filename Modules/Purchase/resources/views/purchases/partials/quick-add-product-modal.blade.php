@once
<style>
#po-product-modal.pcat-modal{z-index:135;}
</style>
@endonce

<div id="po-product-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="po-product-modal-title" aria-hidden="true">
    <div class="pcat-modal__backdrop" data-po-product-close tabindex="-1"></div>
    <div class="pcat-modal__panel">
        <div class="pcat-modal__head">
            <h2 id="po-product-modal-title">Add product</h2>
            <button type="button" class="pcat-modal__close" data-po-product-close aria-label="Close">&times;</button>
        </div>
        <div class="pcat-modal__body">
            <div id="po-product-modal-err" class="pcat-banner pcat-banner--err" style="display:none;margin-bottom:12px;" role="alert"></div>
            <div class="pcat-form-grid pcat-form-grid--2">
                <div class="pcat-field" style="grid-column:1/-1;">
                    <label for="po-product-name">Product name <span style="color:#ef4444;">*</span></label>
                    <input id="po-product-name" type="text" maxlength="255" placeholder="e.g. A4 Copy Paper" autocomplete="off">
                </div>
                <div class="pcat-field">
                    <label for="po-product-sku">SKU / Code</label>
                    <input id="po-product-sku" type="text" maxlength="120" placeholder="Optional">
                </div>
                <div class="pcat-field">
                    <label for="po-product-price">Unit price</label>
                    <input id="po-product-price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                </div>
                <div class="pcat-field" style="grid-column:1/-1;">
                    <label for="po-product-description">Description</label>
                    <textarea id="po-product-description" maxlength="5000" rows="2" placeholder="Optional"></textarea>
                </div>
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);" data-po-product-close>Cancel</button>
                    <button type="button" class="linkbtn" style="padding:8px 16px;font-size:13px;" id="po-product-save-btn">Save product</button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    var STORE_URL = @json(route('product.quick-store'));
    var modal      = document.getElementById('po-product-modal');
    var errBanner  = document.getElementById('po-product-modal-err');
    var saveBtn    = document.getElementById('po-product-save-btn');
    var targetRow  = null;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.getAttribute('content') || '';
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    function showErr(msg) {
        if (!errBanner) return;
        errBanner.textContent = msg;
        errBanner.style.display = msg ? 'block' : 'none';
    }

    function resetForm() {
        ['po-product-name','po-product-sku','po-product-price','po-product-description'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
    }

    function openModal(row) {
        if (!modal) return;
        targetRow = row || null;
        showErr('');
        resetForm();
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('pcat-modal-open-html');
        document.getElementById('po-product-name')?.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        var others = document.querySelector('.pcat-modal--open');
        if (!others) document.documentElement.classList.remove('pcat-modal-open-html');
        targetRow = null;
    }

    function appendProductOption(product) {
        var sid    = String(product.id);
        var label  = product.name + (product.sku ? ' (' + product.sku + ')' : '');
        var price  = product.unit_price != null ? String(product.unit_price) : '';
        document.querySelectorAll('[data-purchase-product-select]').forEach(function (sel) {
            var exists = false;
            sel.querySelectorAll('option').forEach(function (opt) { if (opt.value === sid) exists = true; });
            if (!exists) {
                var opt = document.createElement('option');
                opt.value = sid;
                opt.textContent = label;
                if (price) opt.setAttribute('data-unit-price', price);
                sel.appendChild(opt);
            }
        });

        // Select in the row that triggered the modal; fill unit cost if empty
        if (targetRow) {
            var rowSel  = targetRow.querySelector('[data-purchase-product-select]');
            var rowCost = targetRow.querySelector('[data-purchase-cost]');
            if (rowSel) {
                rowSel.value = sid;
                rowSel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (rowCost && rowCost.value === '' && price) {
                rowCost.value = price;
                rowCost.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    }

    // Open button delegated — works for dynamically added rows too
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-po-product-open]');
        if (!btn) return;
        e.preventDefault();
        openModal(btn.closest('[data-purchase-line]'));
    });

    modal?.querySelectorAll('[data-po-product-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    saveBtn?.addEventListener('click', function () {
        var name = (document.getElementById('po-product-name')?.value || '').trim();
        if (!name) { showErr('Product name is required.'); return; }
        showErr('');
        saveBtn.disabled = true;

        var payload = {
            name:        name,
            sku:         (document.getElementById('po-product-sku')?.value || '').trim() || null,
            unit_price:  document.getElementById('po-product-price')?.value || null,
            description: (document.getElementById('po-product-description')?.value || '').trim() || null,
        };

        fetch(STORE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = {};
                try { data = JSON.parse(text); } catch (e) {}
                return { ok: res.ok, data: data };
            });
        }).then(function (r) {
            if (!r.ok) {
                var msg = 'Could not save product.';
                if (r.data && r.data.errors) msg = Object.values(r.data.errors).flat().join(' ');
                else if (r.data && r.data.message) msg = r.data.message;
                showErr(msg);
                return;
            }
            if (r.data && r.data.product) appendProductOption(r.data.product);
            closeModal();
        }).catch(function () {
            showErr('Could not save product.');
        }).finally(function () {
            saveBtn.disabled = false;
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        }
    }, true);
})();
</script>
@endonce
