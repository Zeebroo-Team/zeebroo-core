@once
<style>
#po-supplier-modal.pcat-modal{z-index:130;}
</style>
@endonce

<div id="po-supplier-modal" class="pcat-modal po-supplier-modal" role="dialog" aria-modal="true" aria-labelledby="po-supplier-modal-title" aria-hidden="true">
    <div class="pcat-modal__backdrop" data-po-supplier-close tabindex="-1"></div>
    <div class="pcat-modal__panel">
        <div class="pcat-modal__head">
            <h2 id="po-supplier-modal-title">Add supplier</h2>
            <button type="button" class="pcat-modal__close" data-po-supplier-close aria-label="Close">&times;</button>
        </div>
        <div class="pcat-modal__body">
            <div id="po-supplier-modal-err" class="pcat-banner pcat-banner--err" style="display:none;margin-bottom:12px;" role="alert"></div>
            <form id="po-supplier-modal-form" class="pcat-form-grid pcat-form-grid--2" novalidate>
                @include('purchase::suppliers.partials.form-fields', [
                    'fieldIdPrefix' => 'po-',
                    'toggleId' => 'po-supplier-active',
                ])
                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);" data-po-supplier-close>Cancel</button>
                    <button type="button" class="linkbtn" style="padding:8px 16px;font-size:13px;" data-po-supplier-save>Save supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
<script>
(function () {
    var STORE_URL = @json(route('purchase.suppliers.store'));
    var modal = document.getElementById('po-supplier-modal');
    var form = document.getElementById('po-supplier-modal-form');
    var errBanner = document.getElementById('po-supplier-modal-err');
    var lastOpenBtn = null;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && m.getAttribute('content')) return m.getAttribute('content');
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    function selects() {
        return document.querySelectorAll('[data-purchase-supplier-select]');
    }

    function showErr(msg) {
        if (!errBanner) return;
        errBanner.textContent = msg;
        errBanner.style.display = msg ? 'block' : 'none';
    }

    function lock(on) {
        if (on) {
            document.documentElement.classList.add('pcat-modal-open-html');
            return;
        }
        var purchaseModal = document.getElementById('purchase-modal');
        if (!purchaseModal || !purchaseModal.classList.contains('pcat-modal--open')) {
            document.documentElement.classList.remove('pcat-modal-open-html');
        }
    }

    function openModal(btn) {
        if (!modal) return;
        lastOpenBtn = btn || null;
        showErr('');
        if (form) form.reset();
        var active = document.getElementById('po-supplier-active');
        if (active) active.checked = true;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lock(true);
        document.getElementById('po-supplier-name')?.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lock(false);
        lastOpenBtn?.focus();
    }

    function appendSupplierOption(id, name) {
        var sid = String(id);
        selects().forEach(function (sel) {
            var found = false;
            sel.querySelectorAll('option').forEach(function (opt) {
                if (opt.value === sid) found = true;
            });
            if (!found) {
                var opt = document.createElement('option');
                opt.value = sid;
                opt.textContent = name;
                sel.appendChild(opt);
            }
            sel.value = sid;
        });
    }

    function readPayload() {
        var nameEl = document.getElementById('po-supplier-name');
        var contactEl = document.getElementById('po-supplier-contact');
        var phoneEl = document.getElementById('po-supplier-phone');
        var emailEl = document.getElementById('po-supplier-email');
        var notesEl = document.getElementById('po-supplier-notes');
        var activeEl = document.getElementById('po-supplier-active');
        return {
            name: (nameEl?.value || '').trim(),
            contact_name: (contactEl?.value || '').trim() || null,
            phone: (phoneEl?.value || '').trim() || null,
            email: (emailEl?.value || '').trim() || null,
            notes: (notesEl?.value || '').trim() || null,
            is_active: activeEl && activeEl.checked ? 1 : 0
        };
    }

    document.querySelectorAll('[data-po-supplier-open]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openModal(btn);
        });
    });

    modal?.querySelectorAll('[data-po-supplier-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.getElementById('po-supplier-modal')?.querySelector('[data-po-supplier-save]')?.addEventListener('click', function () {
        var payload = readPayload();
        if (!payload.name) {
            showErr(@json(__('Supplier name is required.')));
            return;
        }
        showErr('');
        var saveBtn = document.querySelector('[data-po-supplier-save]');
        if (saveBtn) saveBtn.disabled = true;

        fetch(STORE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = {};
                if (text) {
                    try { data = JSON.parse(text); } catch (e) { /* ignore */ }
                }
                return { ok: res.ok, data: data };
            });
        }).then(function (r) {
            if (!r.ok) {
                var msg = @json(__('Could not save supplier.'));
                if (r.data && r.data.errors) {
                    msg = Object.values(r.data.errors).flat().join(' ');
                } else if (r.data && r.data.message) {
                    msg = r.data.message;
                }
                showErr(msg);
                return;
            }
            var s = r.data.supplier;
            if (!s) return;
            appendSupplierOption(s.id, s.name);
            closeModal();
        }).catch(function () {
            showErr(@json(__('Could not save supplier.')));
        }).finally(function () {
            if (saveBtn) saveBtn.disabled = false;
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
