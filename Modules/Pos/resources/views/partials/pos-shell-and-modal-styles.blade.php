@include('pos::partials.pos-three-panel-styles')
@once
<style>
.pos-shell{--pos-bg:var(--bg);--pos-card:var(--card);--pos-text:var(--text);--pos-muted:var(--muted);--pos-border:var(--border);--pos-primary:var(--primary);--pos-btn-bg:var(--btn-bg);--pos-btn-hover:var(--btn-hover);}
.pos-shell--light{--pos-bg:#fafaf9;--pos-card:#ffffff;--pos-text:#0a0a0a;--pos-muted:#57534e;--pos-border:#d6d3d1;--pos-primary:#ca8a04;--pos-btn-bg:#171717;--pos-btn-hover:#facc15;}
.pos-shell--dark{--pos-bg:#0f172a;--pos-card:#111827;--pos-text:#e5e7eb;--pos-muted:#9ca3af;--pos-border:#334155;--pos-primary:#7c3aed;--pos-btn-bg:#7c3aed;--pos-btn-hover:#facc15;}
.pos-shell--light,.pos-shell--dark{background:var(--pos-bg);color:var(--pos-text);}
.pos-shell--light .pos-online__top,.pos-shell--light .pos-online__catalog,.pos-shell--light .pos-online__sale-panel,.pos-shell--light .pos-online__checkout,
.pos-shell--light .pos-panel,.pos-shell--light .pcat-page-card.card{background:var(--pos-card);color:var(--pos-text);border-color:var(--pos-border);}
.pos-shell--dark .pos-online__top,.pos-shell--dark .pos-online__catalog,.pos-shell--dark .pos-online__sale-panel,.pos-shell--dark .pos-online__checkout,
.pos-shell--dark .pos-panel,.pos-shell--dark .pcat-page-card.card{background:var(--pos-card);color:var(--pos-text);border-color:var(--pos-border);}
.pos-shell--light .pos-online__stat,.pos-shell--light .pos-online__link,.pos-shell--light .pos-online__cat,
.pos-shell--light .pos-online__item,.pos-shell--light .pos-online__line,.pos-shell--light input,.pos-shell--light select,.pos-shell--light textarea,
.pos-shell--light .pos-product,.pos-shell--light .pos-cart-row{border-color:var(--pos-border);background:color-mix(in srgb,var(--pos-card) 96%,transparent);color:var(--pos-text);}
.pos-shell--dark .pos-online__stat,.pos-shell--dark .pos-online__link,.pos-shell--dark .pos-online__cat,
.pos-shell--dark .pos-online__item,.pos-shell--dark .pos-online__line,.pos-shell--dark input,.pos-shell--dark select,.pos-shell--dark textarea,
.pos-shell--dark .pos-product,.pos-shell--dark .pos-cart-row{border-color:var(--pos-border);background:color-mix(in srgb,var(--pos-card) 96%,transparent);color:var(--pos-text);}
.pos-shell--light .muted,.pos-shell--dark .muted{color:var(--pos-muted);}
.pos-shell--light .pos-walking-toggle,.pos-shell--dark .pos-walking-toggle{border-color:var(--pos-border);background:color-mix(in srgb,var(--pos-card) 92%,transparent);}
.pos-settings-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;font-size:16px;}
.pos-settings-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-shell--light .pos-settings-btn,.pos-shell--dark .pos-settings-btn{border-color:var(--pos-border);background:color-mix(in srgb,var(--pos-card) 90%,transparent);color:var(--pos-text);}
.pos-settings-modal{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-settings-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.pos-settings-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(3px);}
.pos-settings-modal__panel{position:relative;z-index:1;width:min(100%,420px);max-height:min(88vh,560px);overflow:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 50px rgba(0,0,0,.28);padding:16px 18px;}
.pos-settings-modal__head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;}
.pos-settings-modal__head h2{margin:0;font-size:16px;font-weight:800;}
.pos-settings-modal__close{width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-size:18px;line-height:1;}
.pos-settings-field{margin-bottom:14px;}
.pos-settings-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.pos-settings-field select,.pos-settings-field input[type="number"]{width:100%;box-sizing:border-box;padding:9px 11px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-settings-field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;cursor:pointer;}
.pos-settings-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--border);}
.pos-settings-row:first-of-type{border-top:none;padding-top:0;}
.pos-settings-row--theme{flex-wrap:wrap;}
.pos-settings-row__label{font-size:13px;font-weight:600;color:var(--text);}
.pos-settings-row__label--end{text-align:right;flex:1;}
html.pos-settings-modal-open,html.pos-settings-modal-open body{overflow:hidden;}
.pos-settings-save{width:100%;margin-top:8px;padding:10px 14px;font-size:14px;font-weight:700;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--text);cursor:pointer;}
.pos-online__summary-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;color:var(--muted);margin-bottom:6px;}
.pos-online__summary-row strong{color:var(--text);font-weight:700;}
.pos-bill-modal{position:fixed;inset:0;z-index:210;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;}
.pos-bill-modal.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.pos-bill-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(3px);}
.pos-bill-modal__panel{position:relative;z-index:1;width:min(100%,480px);max-height:min(90vh,640px);display:flex;flex-direction:column;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.32);overflow:hidden;}
.pos-bill-modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);}
.pos-bill-modal__head h2{margin:0;font-size:16px;font-weight:800;line-height:1.3;}
.pos-bill-modal__head p{margin:4px 0 0;font-size:12px;color:var(--muted);line-height:1.4;}
.pos-bill-modal__close{width:34px;height:34px;flex-shrink:0;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-size:18px;line-height:1;}
.pos-bill-modal__body{flex:1;min-height:0;overflow:auto;padding:14px 16px;}
.pos-bill-modal__meta{display:grid;gap:8px;margin-bottom:12px;font-size:12px;}
.pos-bill-modal__meta-row{display:flex;justify-content:space-between;gap:12px;color:var(--muted);}
.pos-bill-modal__meta-row strong{color:var(--text);font-weight:700;text-align:right;}
.pos-bill-receipt-table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:12px;}
.pos-bill-receipt-table th,.pos-bill-receipt-table td{padding:7px 6px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;}
.pos-bill-receipt-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-bill-receipt-table td.muted{color:var(--muted);}
.pos-bill-receipt-table tfoot td{border-bottom:none;padding-top:10px;}
.pos-bill-receipt-table tfoot .pos-bill-receipt-total{font-size:15px;font-weight:800;color:var(--text);}
.pos-bill-modal__foot{display:flex;flex-wrap:wrap;gap:8px;padding:12px 16px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-bill-modal__btn{padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.pos-bill-modal__btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-bill-modal__btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);}
html.pos-bill-modal-open,html.pos-bill-modal-open body{overflow:hidden;}
body.pos-walking-active .pos-online__top,body.pos-walking-active .pos-page__top{padding:5px 10px!important;gap:6px;flex-wrap:nowrap;}
body.pos-walking-active .pos-online__top-fields{gap:6px;min-width:0;}
body.pos-walking-active .pos-online__brand{gap:6px;min-width:0;flex-shrink:0;}
body.pos-walking-active .pos-online__brand-icon{width:28px;height:28px;font-size:13px;border-radius:7px;}
body.pos-walking-active .pos-online__brand h1{font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
body.pos-walking-active .pos-online__brand p{display:none;}
body.pos-walking-active .pos-online__stats{display:none;}
body.pos-walking-active .pos-online__actions{gap:4px;flex-shrink:0;margin-left:0;}
body.pos-walking-active .pos-online__link{padding:6px 8px;}
body.pos-walking-active .pos-settings-btn{width:30px;height:30px;font-size:14px;border-radius:8px;}
body.pos-walking-active .pos-walking-toggle{padding:3px 7px;gap:4px;}
body.pos-walking-active .pos-walking-toggle__lbl{font-size:10px;gap:5px;}
body.pos-walking-active .pos-walking-toggle__lbl > i{display:none;}
body.pos-walking-active .pos-walking-switch{width:30px;height:16px;}
body.pos-walking-active .pos-walking-switch__slider:before{width:12px;height:12px;}
body.pos-walking-active .pos-walking-switch input:checked + .pos-walking-switch__slider:before{transform:translateX(14px);}
body.pos-walking-active .pos-page__top .pcat-toolbar{margin:0!important;}
body.pos-walking-active .pos-page__top{flex-direction:row;flex-wrap:nowrap;align-items:center;gap:8px;}
body.pos-walking-active .pos-page__top-search{flex:1 1 auto;min-width:0;margin:0;}
body.pos-walking-active .pos-page__top-search .pos-search{flex-wrap:nowrap;}
body.pos-walking-active .pos-page__top-actions{flex-shrink:0;display:flex;gap:4px;align-items:center;}
body.pos-walking-active .pos-online__sale-head,body.pos-walking-active .pos-online__checkout-head,body.pos-walking-active .pos-register__sale-head,body.pos-walking-active .pos-fixed-cart > .pos-panel__head{flex-shrink:0;padding:8px 10px;}
body.pos-walking-active .pos-online__sale-head strong,body.pos-walking-active .pos-online__checkout-head strong,body.pos-walking-active .pos-register__sale-head h2,body.pos-walking-active .pos-fixed-cart > .pos-panel__head h2{font-size:13px;}
body.pos-walking-active .pos-online__checkout{gap:6px;}
body.pos-walking-active .pos-online__checkout-body,body.pos-walking-active .pos-fixed-cart > .pos-panel__body{padding:0;}
body.pos-walking-active .pos-checkout-form__scroll{padding:8px 10px;}
body.pos-walking-active .pos-checkout-form__footer{padding:8px 10px 10px;}
body.pos-walking-active .pos-online__field textarea{min-height:36px;font-size:12px;padding:6px 8px;}
body.pos-walking-active .pos-online__pay-btn{padding:9px 10px;font-size:13px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-field{margin-bottom:6px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-method{padding:6px 4px;font-size:9px;line-height:1.2;}
body.pos-walking-active .pos-fixed-cart .pos-pay-method i{font-size:11px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-cash-panel{margin-top:6px;padding:8px 10px;gap:6px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-cash-input input{padding:8px 10px;font-size:16px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-cash-row{font-size:12px;}
body.pos-walking-active .pos-fixed-cart .pos-pay-cash-row--change strong{font-size:14px;}
body.pos-walking-active .pos-fixed-cart .pos-checkout-grid{gap:6px;}
</style>
<script>
(function () {
    function syncPosWalkingTopHeight() {
        if (!document.body.classList.contains('pos-walking-active')) {
            return;
        }
        var top = document.querySelector('.pos-online__top, .pos-page__top');
        if (!top) {
            return;
        }
        var height = Math.ceil(top.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--pos-walking-top-h', height + 'px');
    }
    syncPosWalkingTopHeight();
    window.addEventListener('resize', syncPosWalkingTopHeight);
    window.addEventListener('load', syncPosWalkingTopHeight);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(syncPosWalkingTopHeight);
    }
    if (typeof ResizeObserver !== 'undefined') {
        var topEl = document.querySelector('.pos-online__top, .pos-page__top');
        if (topEl) {
            new ResizeObserver(syncPosWalkingTopHeight).observe(topEl);
        }
    }
})();
</script>
@endonce
