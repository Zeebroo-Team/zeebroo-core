@php
    $posWalkingCustomer = (bool) ($posWalkingCustomer ?? session('pos_walking_customer', true));
@endphp
@once
<style>
.pos-walking-toggle{display:inline-flex;align-items:center;gap:8px;margin:0;padding:6px 10px;border:1px solid var(--border);border-radius:999px;background:color-mix(in srgb,var(--card) 92%,transparent);}
.pos-walking-toggle__lbl{font-size:11px;font-weight:700;color:var(--text);white-space:nowrap;cursor:pointer;user-select:none;display:inline-flex;align-items:center;gap:8px;}
.pos-walking-switch{position:relative;display:inline-block;width:34px;height:18px;flex-shrink:0;}
.pos-walking-switch input{opacity:0;width:0;height:0;margin:0;position:absolute;}
.pos-walking-switch__slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s;}
.pos-walking-switch__slider:before{content:"";position:absolute;height:14px;width:14px;left:2px;top:2px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.22);}
.pos-walking-switch input:checked + .pos-walking-switch__slider{background:#22c55e;}
.pos-walking-switch input:checked + .pos-walking-switch__slider:before{transform:translateX(16px);}
.pos-walking-switch input:focus-visible + .pos-walking-switch__slider{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 45%,transparent);}
.pos-walking-toggle__text--short{display:none;}
body.pos-walking-active .pos-walking-toggle__text--full{display:none;}
body.pos-walking-active .pos-walking-toggle__text--short{display:inline;}
</style>
@endonce
<form method="post" action="{{ route('pos.walking-customer.toggle') }}" class="pos-walking-toggle">
    @csrf
    <input type="hidden" name="enabled" value="0">
    <input type="hidden" name="redirect" value="{{ url()->current() }}">
    <label class="pos-walking-toggle__lbl" for="pos-walking-customer-switch">
        <i class="fa fa-person-walking" aria-hidden="true"></i>
        <span class="pos-walking-toggle__text pos-walking-toggle__text--full">Walking customer</span>
        <span class="pos-walking-toggle__text pos-walking-toggle__text--short">Walk-in</span>
        <span class="pos-walking-switch">
            <input
                type="checkbox"
                id="pos-walking-customer-switch"
                name="enabled"
                value="1"
                @checked($posWalkingCustomer)
                onchange="this.form.submit()"
            >
            <span class="pos-walking-switch__slider" aria-hidden="true"></span>
        </span>
    </label>
</form>
