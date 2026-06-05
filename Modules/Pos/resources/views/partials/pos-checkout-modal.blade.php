@php
    $defaultDepositAccountId = $defaultDepositAccountId ?? null;
    $currency                = $currency ?? '';
    $channel                 = $channel ?? 'online';
    $discountFieldEnabled    = (bool) ($discountFieldEnabled ?? false);
    $currencyLabel           = filled($currency) ? ' '.$currency : '';
@endphp

<div id="pos-checkout-modal" class="pco-modal" role="dialog" aria-modal="true" aria-labelledby="pco-title" aria-hidden="true">
    <div class="pco-backdrop" id="pos-checkout-backdrop" aria-hidden="true"></div>

    <div class="pco-dialog">

        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="pco-head">
            <div class="pco-head__info">
                <span class="pco-head__icon"><i class="fa fa-bag-shopping" aria-hidden="true"></i></span>
                <div>
                    <h3 id="pco-title" class="pco-head__title">Checkout</h3>
                    <p class="pco-head__sub">Review your order and choose payment</p>
                </div>
            </div>
            <button type="button" class="pco-close" id="pos-checkout-modal-close" aria-label="Close checkout">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
        </div>

        {{-- ── Two-column form body ────────────────────────────────── --}}
        <form method="post" action="{{ $checkoutFormAction ?? route('pos.checkout') }}" id="pos-checkout-form" class="pco-form">
            @csrf
            <input type="hidden" name="channel" value="{{ $channel }}">

            {{-- LEFT — Order summary --}}
            <div class="pco-left">
                <p class="pco-section-label">Order summary</p>

                <div class="pco-summary">
                    <div class="pco-summary__row">
                        <span>Subtotal</span>
                        <strong id="pco-subtotal-display">—</strong>
                    </div>

                    @if($discountFieldEnabled)
                    <div class="pco-summary__row pco-summary__discount-row">
                        <label for="pos-discount-percent" class="pco-summary__disc-label">
                            <i class="fa fa-tag" aria-hidden="true"></i> Discount
                        </label>
                        <div class="pco-summary__disc-input-wrap">
                            <input type="text" name="discount_percent" id="pos-discount-percent"
                                   class="pco-summary__disc-input"
                                   value="{{ old('discount_percent', '0') }}"
                                   inputmode="none" data-pos-numpad="percent" readonly
                                   placeholder="0">
                            <span class="pco-summary__disc-pct">%</span>
                        </div>
                    </div>
                    <div class="pco-summary__row pco-summary__disc-amt-row" id="pco-discount-amount-row" hidden>
                        <span>Discount saved</span>
                        <strong id="pco-discount-amount" class="pco-summary__disc-amt">—</strong>
                    </div>
                    @endif
                </div>

                <div class="pco-total-display">
                    <span class="pco-total-display__label">Total</span>
                    <strong class="pco-total-display__value" id="pco-total-display">—</strong>
                    @if(filled($currency))
                    <span class="pco-total-display__currency">{{ $currency }}</span>
                    @endif
                </div>

                <div class="pco-left__footer">
                    <p class="pco-left__tip"><i class="fa fa-keyboard" aria-hidden="true"></i> Use the numpad to enter amounts</p>
                </div>
            </div>

            {{-- RIGHT — Payment + notes + numpad + submit --}}
            <div class="pco-right">
                {{-- Payment method --}}
                @include('pos::partials.pos-payment-field', ['defaultDepositAccountId' => $defaultDepositAccountId])

                {{-- Notes --}}
                <div class="pco-right__notes">
                    <label for="pos-notes" class="pco-right__notes-label">Notes</label>
                    <textarea name="notes" id="pos-notes" class="pco-right__notes-input" maxlength="2000" placeholder="Optional note…" rows="2">{{ old('notes') }}</textarea>
                </div>

                {{-- Numpad --}}
                <div class="pco-right__numpad">
                    @include('pos::partials.pos-numpad')
                </div>

                {{-- Submit --}}
                <button type="submit" class="pco-submit" id="pos-complete-sale" disabled>
                    <i class="fa fa-check" aria-hidden="true"></i> Complete sale
                </button>
            </div>
        </form>

    </div>
</div>

@once
<style>
/* ── Overlay ─────────────────────────────────────────────────────── */
.pco-modal{position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;padding:12px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .22s ease,visibility .22s;}
.pco-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.pco-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.6);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);}
html.pco-open,html.pco-open body{overflow:hidden;}

/* ── Dialog shell ────────────────────────────────────────────────── */
.pco-dialog{position:relative;z-index:1;width:min(100%,860px);display:flex;flex-direction:column;border-radius:20px;border:1px solid var(--border);background:var(--card);box-shadow:0 32px 80px rgba(0,0,0,.38),0 0 0 1px rgba(255,255,255,.05);overflow:hidden;transform:translateY(12px) scale(.97);transition:transform .3s cubic-bezier(.34,1.15,.64,1);}
.pco-modal.is-open .pco-dialog{transform:translateY(0) scale(1);}

/* ── Header ──────────────────────────────────────────────────────── */
.pco-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 93%,var(--border));}
.pco-head__info{display:flex;align-items:center;gap:12px;min-width:0;}
.pco-head__icon{width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,var(--primary) 16%,transparent);border:1px solid color-mix(in srgb,var(--primary) 28%,transparent);display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--primary);flex-shrink:0;}
.pco-head__title{margin:0;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.02em;line-height:1;}
.pco-head__sub{margin:3px 0 0;font-size:11px;color:var(--muted);line-height:1;}
.pco-close{width:30px;height:30px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-size:12px;transition:all .15s;}
.pco-close:hover{border-color:var(--text);color:var(--text);background:color-mix(in srgb,var(--border) 40%,transparent);}

/* ── Two-column form ─────────────────────────────────────────────── */
.pco-form{display:flex;flex-direction:row;flex:1;min-height:0;overflow:hidden;}

/* ── LEFT panel ──────────────────────────────────────────────────── */
.pco-left{width:280px;flex-shrink:0;display:flex;flex-direction:column;padding:18px 16px;border-right:1px solid var(--border);background:color-mix(in srgb,var(--card) 95%,var(--border) 5%);gap:0;overflow:hidden;}
.pco-section-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 12px;}

/* Summary rows */
.pco-summary{display:flex;flex-direction:column;gap:0;border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card);}
.pco-summary__row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 13px;font-size:13px;border-bottom:1px solid var(--border);}
.pco-summary__row:last-child{border-bottom:none;}
.pco-summary__row span{color:var(--muted);}
.pco-summary__row strong{color:var(--text);font-weight:700;}
.pco-summary__disc-amt{color:color-mix(in srgb,#22c55e 70%,var(--text)) !important;}

/* Discount input row */
.pco-summary__discount-row{flex-direction:column;align-items:stretch;gap:6px;padding:10px 13px;}
.pco-summary__disc-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:flex;align-items:center;gap:5px;}
.pco-summary__disc-input-wrap{position:relative;display:flex;align-items:center;}
.pco-summary__disc-input{width:100%;box-sizing:border-box;padding:9px 28px 9px 12px;font-size:20px;font-weight:800;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-align:left;cursor:pointer;transition:border-color .15s;}
.pco-summary__disc-input.is-numpad-target,.pco-summary__disc-input:focus{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 18%,transparent);outline:none;}
.pco-summary__disc-pct{position:absolute;right:10px;font-size:14px;font-weight:700;color:var(--muted);pointer-events:none;}

/* Total display */
.pco-total-display{margin-top:14px;padding:14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;}
.pco-total-display__label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);flex-shrink:0;}
.pco-total-display__value{font-size:28px;font-weight:800;color:var(--text);letter-spacing:-.03em;line-height:1;flex:1;min-width:0;}
.pco-total-display__currency{font-size:13px;font-weight:700;color:var(--muted);flex-shrink:0;}

/* Left footer tip */
.pco-left__footer{margin-top:auto;padding-top:12px;}
.pco-left__tip{font-size:10px;color:var(--muted);display:flex;align-items:center;gap:5px;margin:0;}

/* ── RIGHT panel ─────────────────────────────────────────────────── */
.pco-right{flex:1;min-width:0;display:flex;flex-direction:column;padding:14px 16px;gap:10px;overflow:hidden;}

/* Notes */
.pco-right__notes{display:flex;flex-direction:column;gap:5px;}
.pco-right__notes-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.pco-right__notes-input{width:100%;box-sizing:border-box;padding:7px 10px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);resize:none;font-family:inherit;}
.pco-right__notes-input:focus{outline:none;border-color:var(--primary);}

/* Numpad (compact inside modal) */
.pco-right__numpad .pos-numpad__keys{gap:5px;}
.pco-right__numpad .pos-numpad__key{min-height:36px;font-size:15px;border-radius:8px;}
.pco-right__numpad .pos-numpad__actions{gap:5px;margin-top:5px;}
.pco-right__numpad .pos-numpad__key--action{min-height:30px;font-size:10px;}

/* Submit button */
.pco-submit{width:100%;box-sizing:border-box;padding:12px 14px;font-size:14px;font-weight:800;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s,box-shadow .15s;}
.pco-submit:hover:not(:disabled){background:color-mix(in srgb,var(--primary) 28%,transparent);box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 22%,transparent);}
.pco-submit:disabled{opacity:.4;cursor:not-allowed;}

/* Right-panel overrides for payment field to remove its own scroll wrapper */
.pco-right .pos-pay-field{margin-bottom:0;}
.pco-right .pos-pay-cash-panel{margin-top:6px;}
.pco-right .pos-checkout-form__scroll{display:contents;}
.pco-right .pos-checkout-form__footer{display:contents;}
.pco-right .pos-online__checkout{display:contents;}

/* ── Trigger button in totals bar ────────────────────────────────── */
.pco-trigger{width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 14px;font-size:14px;font-weight:800;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--text);cursor:pointer;margin-top:8px;transition:background .15s,border-color .15s,box-shadow .15s;}
.pco-trigger:hover:not(:disabled){background:color-mix(in srgb,var(--primary) 28%,transparent);box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 22%,transparent);}
.pco-trigger:disabled{opacity:.4;cursor:not-allowed;}

/* ── Responsive: single column on narrow screens ─────────────────── */
@media(max-width:620px){
    .pco-form{flex-direction:column;overflow-y:auto;}
    .pco-left{width:100%;border-right:none;border-bottom:1px solid var(--border);}
    .pco-right{overflow-y:auto;}
}
</style>

<script>
(function () {
    var modal    = document.getElementById('pos-checkout-modal');
    var backdrop = document.getElementById('pos-checkout-backdrop');
    var closeBtn = document.getElementById('pos-checkout-modal-close');
    var openBtn  = document.getElementById('pos-open-checkout-modal');
    var pcoTotal = document.getElementById('pco-total-display');
    if (!modal) return;

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pco-open', open);
        if (open && closeBtn) closeBtn.focus();
    }

    if (openBtn)  openBtn.addEventListener('click', function () { setOpen(true); });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false);
    });

    // Mirror cart total → left panel total display
    var cartTotalEl = document.getElementById('pos-cart-total');
    if (cartTotalEl && pcoTotal) {
        function syncTotal() { pcoTotal.textContent = cartTotalEl.textContent.replace(/[^\d.,]/g, '') || '—'; }
        new MutationObserver(syncTotal).observe(cartTotalEl, { childList: true, characterData: true, subtree: true });
        syncTotal();
    }

    // Mirror subtotal + discount amount → left panel summary
    var srcSubtotal   = document.getElementById('pos-cart-subtotal');
    var srcDiscAmt    = document.getElementById('pos-cart-discount');
    var srcDiscAmtRow = document.getElementById('pos-discount-amount-row');
    var dstSubtotal   = document.getElementById('pco-subtotal-display');
    var dstDiscAmt    = document.getElementById('pco-discount-amount');
    var dstDiscAmtRow = document.getElementById('pco-discount-amount-row');

    function syncSummary() {
        if (dstSubtotal && srcSubtotal) dstSubtotal.textContent = srcSubtotal.textContent || '—';
        if (dstDiscAmtRow && srcDiscAmtRow) {
            dstDiscAmtRow.hidden = srcDiscAmtRow.hidden;
            if (!srcDiscAmtRow.hidden && dstDiscAmt && srcDiscAmt) {
                dstDiscAmt.textContent = srcDiscAmt.textContent || '—';
            }
        }
    }

    if (srcSubtotal)   new MutationObserver(syncSummary).observe(srcSubtotal,   { childList: true, characterData: true, subtree: true });
    if (srcDiscAmt)    new MutationObserver(syncSummary).observe(srcDiscAmt,     { childList: true, characterData: true, subtree: true });
    if (srcDiscAmtRow) new MutationObserver(syncSummary).observe(srcDiscAmtRow,  { attributes: true });
    syncSummary();

    // Auto-close on sale completion
    document.addEventListener('pos-clear-cart-and-reset', function () { setOpen(false); });
})();
</script>
@endonce
