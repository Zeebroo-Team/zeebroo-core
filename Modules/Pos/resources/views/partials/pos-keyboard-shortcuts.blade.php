@once
<style>
.pos-kbd-help-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;font-size:15px;}
.pos-kbd-help-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-kbd-modal{position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-kbd-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.pos-kbd-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(3px);}
.pos-kbd-modal__panel{position:relative;z-index:1;width:min(100%,520px);max-height:min(88vh,640px);overflow:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 50px rgba(0,0,0,.28);padding:16px 18px;}
.pos-kbd-modal__head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;}
.pos-kbd-modal__head h2{margin:0;font-size:16px;font-weight:800;}
.pos-kbd-modal__close{width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-size:18px;line-height:1;}
.pos-kbd-modal__intro{margin:0 0 12px;font-size:12px;color:var(--muted);line-height:1.45;}
.pos-kbd-table{width:100%;border-collapse:collapse;font-size:12px;}
.pos-kbd-table th,.pos-kbd-table td{padding:7px 8px;text-align:left;border-bottom:1px solid var(--border);vertical-align:top;}
.pos-kbd-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-kbd-table kbd{display:inline-block;padding:2px 6px;border-radius:5px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);font-family:ui-monospace,monospace;font-size:11px;font-weight:700;color:var(--text);white-space:nowrap;}
.pos-kbd-table tr:last-child td,.pos-kbd-table tr:last-child th{border-bottom:0;}
html.pos-kbd-modal-open,html.pos-kbd-modal-open body{overflow:hidden;}
.pos-cart-row.is-cart-selected,.pos-online__line.is-cart-selected{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 30%,transparent);background:color-mix(in srgb,var(--primary) 8%,transparent);}
.pos-kbd-toast{position:fixed;bottom:16px;left:50%;transform:translateX(-50%) translateY(8px);z-index:220;padding:8px 14px;border-radius:999px;border:1px solid var(--border);background:var(--card);color:var(--text);font-size:12px;font-weight:700;opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;box-shadow:0 8px 24px rgba(0,0,0,.18);}
.pos-kbd-toast.is-visible{opacity:1;transform:translateX(-50%) translateY(0);}
</style>
@endonce

<button type="button" class="pos-kbd-help-btn" id="pos-kbd-help-open" title="Keyboard shortcuts (F1)" aria-haspopup="dialog" aria-controls="pos-kbd-modal">
    <i class="fa fa-keyboard" aria-hidden="true"></i>
</button>

<div id="pos-kbd-modal" class="pos-kbd-modal" role="dialog" aria-modal="true" aria-labelledby="pos-kbd-title" aria-hidden="true">
    <div class="pos-kbd-modal__backdrop" data-pos-kbd-close tabindex="-1" aria-label="Close"></div>
    <div class="pos-kbd-modal__panel">
        <div class="pos-kbd-modal__head">
            <h2 id="pos-kbd-title">Keyboard shortcuts</h2>
            <button type="button" class="pos-kbd-modal__close" data-pos-kbd-close aria-label="Close">&times;</button>
        </div>
        <p class="pos-kbd-modal__intro">Works while the cursor is not in a text field. Barcode scanners can type into the SKU field at any time.</p>
        <table class="pos-kbd-table">
            <thead>
                <tr><th scope="col">Keys</th><th scope="col">Action</th></tr>
            </thead>
            <tbody id="pos-kbd-help-body"></tbody>
        </table>
    </div>
</div>

<div id="pos-kbd-toast" class="pos-kbd-toast" role="status" aria-live="polite" hidden></div>

@once
<script>
window.initPosKeyboardShortcuts = function (options) {
    options = options || {};
    const hasSku = !!options.skuInput;
    const discountEnabled = !!options.discountEnabled;

    const helpModal = document.getElementById('pos-kbd-modal');
    const helpOpenBtn = document.getElementById('pos-kbd-help-open');
    const helpBody = document.getElementById('pos-kbd-help-body');
    const toastEl = document.getElementById('pos-kbd-toast');

    const rows = [
        ['F1', '?', 'Show this help'],
        ['F2', '', hasSku ? 'Focus SKU / barcode field' : 'Focus product search'],
        ['F3', '', 'Focus product search'],
        ['F5', '', 'Focus cash tendered amount'],
    ];
    if (discountEnabled) {
        rows.push(['F6', '', 'Focus discount %']);
    }
    rows.push(
        ['F7', '1', 'Cash payment'],
        ['F8', '2', 'Card payment'],
        ['F9', '3', 'Credit payment'],
        ['F10', 'Ctrl+Enter', 'Complete sale'],
        ['E', '', 'Exact cash (amount due)'],
        ['+', '=', 'Increase qty on selected line'],
        ['-', '', 'Decrease qty on selected line'],
        ['↑', '↓', 'Select cart line'],
        ['Delete', 'Ctrl+Backspace', 'Remove selected cart line'],
        ['Ctrl+Delete', 'Ctrl+L', 'Clear entire cart'],
        ['Esc', '', 'Blur field / close dialogs'],
        ['Alt+1 … 9', '', 'Jump to category (online)']
    );

    if (helpBody) {
        helpBody.innerHTML = rows.map(function (row) {
            const keys = row.slice(0, -1).filter(Boolean).map(function (k) {
                return '<kbd>' + k + '</kbd>';
            }).join(' ');
            return '<tr><td>' + keys + '</td><td>' + row[row.length - 1] + '</td></tr>';
        }).join('');
    }

    function setHelpOpen(open) {
        if (!helpModal) return;
        helpModal.classList.toggle('is-open', open);
        helpModal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-kbd-modal-open', open);
    }

    helpOpenBtn?.addEventListener('click', function () { setHelpOpen(true); });
    helpModal?.querySelectorAll('[data-pos-kbd-close]').forEach(function (el) {
        el.addEventListener('click', function () { setHelpOpen(false); });
    });

    options.cartItemsEl?.addEventListener('click', function (e) {
        const row = e.target.closest('[data-cart-row]');
        if (!row) return;
        getCartRows().forEach(function (r) {
            r.classList.toggle('is-cart-selected', r === row);
        });
    });

    let toastTimer = null;
    function flashToast(msg) {
        if (!toastEl || !msg) return;
        toastEl.textContent = msg;
        toastEl.hidden = false;
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            toastEl.hidden = true;
        }, 1400);
    }

    function isModalOpen() {
        return document.getElementById('pos-settings-modal')?.classList.contains('is-open')
            || document.getElementById('pos-bill-modal')?.classList.contains('is-open')
            || helpModal?.classList.contains('is-open');
    }

    function isTypingTarget(el) {
        if (!el || el === document.body) return false;
        const tag = el.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (el.isContentEditable) return true;
        return false;
    }

    function shouldBlockGlobal(e) {
        if (e.defaultPrevented || isModalOpen()) return true;
        const typing = isTypingTarget(e.target);
        const isFn = /^F\d{1,2}$/.test(e.key);
        const withMod = e.ctrlKey || e.metaKey || e.altKey;
        if (typing && !isFn && !withMod && e.key !== 'Escape') return true;
        return false;
    }

    function focusEl(el) {
        if (!el) return;
        el.focus({ preventScroll: false });
        if (el.select && (el.type === 'text' || el.type === 'search')) {
            el.select();
        }
    }

    function getCartRows() {
        const el = options.cartItemsEl;
        if (!el) return [];
        return Array.from(el.querySelectorAll('[data-cart-row]'));
    }

    function getSelectedRow() {
        const rows = getCartRows();
        return rows.find(function (r) { return r.classList.contains('is-cart-selected'); }) || null;
    }

    function selectCartRow(direction) {
        const rows = getCartRows();
        if (!rows.length) return;
        let idx = rows.findIndex(function (r) { return r.classList.contains('is-cart-selected'); });
        if (idx < 0) {
            idx = direction > 0 ? 0 : rows.length - 1;
        } else {
            idx = (idx + direction + rows.length) % rows.length;
        }
        rows.forEach(function (r, i) {
            r.classList.toggle('is-cart-selected', i === idx);
        });
        rows[idx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function adjustSelectedQty(delta) {
        const rows = getCartRows();
        if (!rows.length) return;
        let rowEl = getSelectedRow() || rows[rows.length - 1];
        rowEl.classList.add('is-cart-selected');
        const qtyInput = rowEl.querySelector('[data-qty]');
        if (!qtyInput) return;
        const current = parseFloat(qtyInput.value) || 0;
        const next = Math.round((current + delta) * 1000) / 1000;
        if (next <= 0) {
            rowEl.querySelector('[data-remove]')?.click();
            return;
        }
        qtyInput.value = String(next);
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function removeSelectedLine() {
        const rowEl = getSelectedRow() || getCartRows().slice(-1)[0];
        if (!rowEl) return;
        rowEl.querySelector('[data-remove]')?.click();
    }

    function jumpCategory(index) {
        const cats = document.querySelectorAll('.pos-online__cats .pos-online__cat');
        const link = cats[index];
        if (link) {
            window.location.href = link.href;
        }
    }

    document.addEventListener('keydown', function (e) {
        if (helpModal?.classList.contains('is-open')) {
            if (e.key === 'Escape') {
                e.preventDefault();
                setHelpOpen(false);
            }
            return;
        }

        const key = e.key;
        const lower = key.length === 1 ? key.toLowerCase() : key;

        if (key === 'F1' || (key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey && !shouldBlockGlobal(e))) {
            if (key === '?' && shouldBlockGlobal(e)) return;
            e.preventDefault();
            setHelpOpen(true);
            return;
        }

        if (shouldBlockGlobal(e)) return;

        if (key === 'Escape') {
            if (document.activeElement && document.activeElement !== document.body) {
                document.activeElement.blur();
                e.preventDefault();
            }
            return;
        }

        if (key === 'F2') {
            e.preventDefault();
            focusEl(options.skuInput || options.searchInput);
            return;
        }
        if (key === 'F3') {
            e.preventDefault();
            focusEl(options.searchInput);
            return;
        }
        if (key === 'F5') {
            e.preventDefault();
            window.posPaymentApi?.focusCash?.();
            return;
        }
        if (key === 'F6' && discountEnabled) {
            e.preventDefault();
            window.posPaymentApi?.focusDiscount?.();
            return;
        }
        if (key === 'F7' || (key === '1' && !e.ctrlKey && !e.metaKey && !e.altKey)) {
            e.preventDefault();
            window.posPaymentApi?.setMethod?.('cash');
            flashToast('Cash');
            return;
        }
        if (key === 'F8' || (key === '2' && !e.ctrlKey && !e.metaKey && !e.altKey)) {
            e.preventDefault();
            window.posPaymentApi?.setMethod?.('card');
            flashToast('Card');
            return;
        }
        if (key === 'F9' || (key === '3' && !e.ctrlKey && !e.metaKey && !e.altKey)) {
            e.preventDefault();
            window.posPaymentApi?.setMethod?.('credit');
            flashToast('Credit');
            return;
        }
        if (key === 'F10' || ((e.ctrlKey || e.metaKey) && key === 'Enter')) {
            e.preventDefault();
            if (window.posPaymentApi?.tryComplete?.()) {
                flashToast('Completing sale…');
            }
            return;
        }
        if (lower === 'e' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            window.posPaymentApi?.numpadExact?.();
            return;
        }
        if ((key === '+' || key === '=') && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            adjustSelectedQty(1);
            return;
        }
        if (key === '-' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            adjustSelectedQty(-1);
            return;
        }
        if (key === 'ArrowUp') {
            e.preventDefault();
            selectCartRow(-1);
            return;
        }
        if (key === 'ArrowDown') {
            e.preventDefault();
            selectCartRow(1);
            return;
        }
        if ((key === 'Delete' || key === 'Backspace') && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            options.clearCart?.();
            flashToast('Cart cleared');
            return;
        }
        if (key === 'Delete' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            removeSelectedLine();
            return;
        }
        if (lower === 'l' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            options.clearCart?.();
            flashToast('Cart cleared');
            return;
        }
        if (e.altKey && key >= '1' && key <= '9') {
            e.preventDefault();
            jumpCategory(parseInt(key, 10) - 1);
        }
    });
};
</script>
@endonce
