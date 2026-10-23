@php
    $customerPickerLabel = $customerPickerLabel ?? 'Customer (optional)';
@endphp

@once
<style>
.pos-customer-search-wrap{position:relative;}
.pos-customer-search-input{width:100%;box-sizing:border-box;padding:9px 12px 9px 32px;font-size:13px;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-customer-search-input:focus{outline:none;border-color:var(--primary);}
.pos-customer-dropdown{position:absolute;left:0;right:0;top:calc(100% + 4px);background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);z-index:9999;max-height:240px;overflow-y:auto;}
.pos-customer-dropdown[hidden]{display:none!important;}
.pos-customer-option{padding:9px 13px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;}
.pos-customer-option:last-child{border-bottom:none;}
.pos-customer-option:hover,.pos-customer-option.is-focused{background:color-mix(in srgb,var(--primary) 10%,transparent);}
.pos-customer-option__name{font-weight:700;color:var(--text);}
.pos-customer-option__sub{font-size:11px;color:var(--muted);}
.pos-customer-option--add{color:var(--primary);font-weight:700;display:flex;align-items:center;gap:6px;}
.pos-customer-chip{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px;}
.pos-customer-chip[hidden]{display:none!important;}
.pos-customer-chip button{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;line-height:1;padding:0;}
/* Quick-add customer inline form */
.pos-customer-quickadd{margin-top:10px;padding:12px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-customer-quickadd[hidden]{display:none!important;}
.pos-customer-quickadd__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:10px;}
.pos-customer-quickadd__row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;}
.pos-customer-quickadd__field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:4px;}
.pos-customer-quickadd__field input{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.pos-customer-quickadd__field input:focus{outline:none;border-color:var(--primary);}
.pos-customer-quickadd__actions{display:flex;gap:6px;justify-content:flex-end;}
.pos-customer-quickadd__btn{padding:7px 12px;font-size:12px;font-weight:700;border-radius:7px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;}
.pos-customer-quickadd__btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent);}
</style>
@endonce

{{-- Wired up by initPosPaymentField(); the hidden input posts with #pos-checkout-form even when rendered outside it. --}}
<div class="pos-customer-field" id="pos-customer-field">
    @if($customerPickerLabel !== '')
        <span class="pos-pay-field__label">{{ $customerPickerLabel }}</span>
    @endif
    <input type="hidden" name="pos_customer_id" id="pos-customer-id" value="" form="pos-checkout-form">

    <div id="pos-customer-selected" class="pos-customer-chip" hidden>
        <span><i class="fa fa-user" aria-hidden="true" style="margin-right:6px;color:var(--muted);"></i><span id="pos-customer-chip-label"></span></span>
        <button type="button" id="pos-customer-clear" aria-label="Remove customer">&times;</button>
    </div>

    <div id="pos-customer-search-wrap" class="pos-customer-search-wrap">
        <div style="position:relative;">
            <i class="fa fa-user" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;pointer-events:none;"></i>
            <input type="text" id="pos-customer-search-input"
                   class="pos-customer-search-input"
                   placeholder="Walk-in — search or add customer…"
                   aria-label="Customer"
                   autocomplete="off" spellcheck="false">
        </div>
        <div id="pos-customer-dropdown" class="pos-customer-dropdown" hidden></div>
    </div>
</div>
