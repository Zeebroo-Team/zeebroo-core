@once
<style>
.grn-pay-settlement{
    grid-column:1/-1;
    margin-top:4px;
    padding:14px 16px;
    border-radius:12px;
    border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));
    background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 7%,var(--card)) 0%,color-mix(in srgb,var(--card) 92%,transparent) 48%);
    box-shadow:0 1px 0 color-mix(in srgb,#fff 6%,transparent) inset;
}
.grn-pay-settlement__head{
    display:flex;
    flex-wrap:wrap;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px 16px;
    margin-bottom:14px;
    padding-bottom:12px;
    border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);
}
.grn-pay-settlement__title{margin:0;font-size:14px;font-weight:800;color:var(--text);letter-spacing:-0.01em;}
.grn-pay-settlement__lead{margin:4px 0 0;font-size:12px;line-height:1.45;color:var(--muted);}
.grn-pay-settlement__total{
    flex-shrink:0;
    text-align:right;
    padding:8px 12px;
    border-radius:10px;
    border:1px solid color-mix(in srgb,var(--border) 80%,transparent);
    background:color-mix(in srgb,var(--card) 85%,transparent);
    min-width:120px;
}
.grn-pay-settlement__total-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:2px;}
.grn-pay-settlement__total-value{display:block;font-size:18px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.2;}
.grn-pay-settlement__total-value--muted{font-size:13px;font-weight:600;color:var(--muted);}
.grn-pay-choices{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    margin:0 0 14px;
    padding:0;
    border:0;
}
@media(max-width:560px){.grn-pay-choices{grid-template-columns:1fr;}}
.grn-pay-choices__card{
    display:flex;
    align-items:flex-start;
    gap:10px;
    margin:0;
    padding:12px 14px;
    border-radius:10px;
    border:1px solid color-mix(in srgb,var(--border) 88%,transparent);
    background:color-mix(in srgb,var(--card) 78%,transparent);
    cursor:pointer;
    line-height:1.35;
    transition:border-color .15s ease,background .15s ease,box-shadow .15s ease;
}
.grn-pay-choices__card:hover{
    border-color:color-mix(in srgb,var(--primary) 35%,var(--border));
    background:color-mix(in srgb,var(--primary) 5%,var(--card));
}
.grn-pay-choices__card:has(input:checked){
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
    background:color-mix(in srgb,var(--primary) 10%,var(--card));
    box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 18%,transparent);
}
.grn-pay-choices__card input{
    margin-top:2px;
    width:17px;
    height:17px;
    flex-shrink:0;
    accent-color:var(--primary);
    cursor:pointer;
}
.grn-pay-choices__title{display:block;font-size:13px;font-weight:800;color:var(--text);}
.grn-pay-choices__hint{display:block;margin-top:3px;font-size:11px;font-weight:500;color:var(--muted);line-height:1.4;}
.grn-pay-choices__amount{display:block;margin-top:6px;font-size:12px;font-weight:700;color:color-mix(in srgb,var(--primary) 85%,var(--text));font-variant-numeric:tabular-nums;}
.grn-pay-partial-box{
    margin:0 0 14px;
    padding:12px 14px;
    border-radius:10px;
    border:1px dashed color-mix(in srgb,var(--primary) 28%,var(--border));
    background:color-mix(in srgb,var(--card) 90%,transparent);
}
.grn-pay-partial-box .pcat-field label{font-size:12px;font-weight:700;}
.grn-pay-partial-box input[type=number]{
    width:100%;
    max-width:220px;
    box-sizing:border-box;
}
.grn-pay-partial-cap{margin:6px 0 0;font-size:11px;color:var(--muted);line-height:1.4;}
.grn-pay-settlement .grn-pay-account-field{margin:0;}
.grn-pay-settlement .grn-pay-account-field label{font-size:12px;font-weight:700;}
.grn-pay-credit-note{
    grid-column:1/-1;
    margin:4px 0 0;
    padding:10px 12px;
    border-radius:10px;
    border:1px solid color-mix(in srgb,var(--border) 80%,transparent);
    background:color-mix(in srgb,var(--muted) 8%,var(--card));
    font-size:12px;
    line-height:1.45;
    color:var(--muted);
}
.grn-pay-credit-note i{margin-right:6px;opacity:.85;}
.grn-record-payment-panel{
    margin-top:12px;
    padding-top:14px;
    border-top:1px solid color-mix(in srgb,var(--border) 72%,transparent);
}
.grn-record-payment-panel .grn-pay-settlement{margin-top:0;}
.grn-record-payment-panel .grn-pay-submit{margin-top:14px;}
.grn-pay-status{display:flex;flex-direction:column;align-items:flex-start;gap:6px;min-width:0;}
.grn-pay-status--compact{gap:4px;}
.grn-pay-status__badge{
    display:inline-flex;align-items:center;gap:6px;
    font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;
    border:1px solid var(--border);white-space:nowrap;line-height:1.2;
}
.grn-pay-status__badge i{font-size:10px;opacity:.9;}
.grn-pay-status__badge--paid_full{
    border-color:color-mix(in srgb,#22c55e 45%,var(--border));
    background:color-mix(in srgb,#22c55e 12%,transparent);
    color:color-mix(in srgb,#22c55e 75%,var(--text));
}
.grn-pay-status__badge--paid_partial{
    border-color:color-mix(in srgb,#f59e0b 45%,var(--border));
    background:color-mix(in srgb,#f59e0b 12%,transparent);
    color:color-mix(in srgb,#f59e0b 80%,var(--text));
}
.grn-pay-status__badge--pending{
    border-color:color-mix(in srgb,#3b82f6 40%,var(--border));
    background:color-mix(in srgb,#3b82f6 10%,transparent);
    color:color-mix(in srgb,#3b82f6 75%,var(--text));
}
.grn-pay-status__badge--no_amount{opacity:.75;}
.grn-pay-status__amounts{
    display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;
    font-size:12px;font-variant-numeric:tabular-nums;
}
.grn-pay-status--compact .grn-pay-status__amounts{font-size:11px;gap:4px 8px;}
.grn-pay-status__chip{
    display:inline-flex;align-items:baseline;gap:4px;
    padding:3px 8px;border-radius:8px;
    border:1px solid color-mix(in srgb,var(--border) 80%,transparent);
    background:color-mix(in srgb,var(--card) 75%,transparent);
}
.grn-pay-status__chip-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.grn-pay-status__chip strong{font-weight:800;color:var(--text);}
.grn-pay-status__chip--due{border-color:color-mix(in srgb,#f59e0b 35%,var(--border));background:color-mix(in srgb,#f59e0b 8%,transparent);}
.grn-pay-status__chip--due strong{color:color-mix(in srgb,#f59e0b 85%,var(--text));}
.grn-pay-status__chip--clear strong{color:color-mix(in srgb,#22c55e 80%,var(--text));}
.grn-pay-status__currency{font-size:10px;align-self:center;}
.grn-pay-status__method{font-size:11px;}
.grn-pay-status__chip--paid strong{color:color-mix(in srgb,#22c55e 75%,var(--text));}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary{
    flex-direction:row;align-items:center;gap:4px;flex-wrap:wrap;
}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary .grn-pay-status__chip{
    padding:1px 6px;font-size:10px;
}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary .grn-pay-status__chip-label{
    display:inline;font-size:9px;
}
.grn-pay-status--dense .grn-pay-status__amounts--partial-summary .grn-pay-status__currency{
    display:inline;font-size:9px;
}
.grn-pay-status-cell{min-width:168px;}
.grn-pay-open-btn{
    padding:4px 8px;font-size:10px;font-weight:700;
    display:inline-flex;align-items:center;gap:4px;white-space:nowrap;
    background:color-mix(in srgb,var(--primary) 12%,var(--card));
    border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));
    color:var(--text);
}
.grn-pay-open-btn:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));color:var(--primary);}
.grn-pay-modal__panel{max-width:520px;width:100%;}
.grn-pay-modal__summary{
    display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;
    margin:0 0 14px;padding:10px 12px;border-radius:10px;
    border:1px solid color-mix(in srgb,var(--border) 80%,transparent);
    background:color-mix(in srgb,var(--card) 92%,var(--primary) 8%);
}
.grn-pay-modal__summary dt{margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);}
.grn-pay-modal__summary dd{margin:2px 0 0;font-size:14px;font-weight:800;color:var(--text);}
@media(max-width:480px){.grn-pay-modal__summary{grid-template-columns:1fr;}}
.grn-po-group__table .grn-pay-open-btn__label{display:none;}
@media(min-width:720px){.grn-po-group__table .grn-pay-open-btn__label{display:inline;}}
</style>
@endonce
