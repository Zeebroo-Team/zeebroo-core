@once
<style>
.pos-sale-foot{flex-shrink:0;padding:10px 12px;border-top:1px solid var(--border);background:color-mix(in srgb,var(--card) 98%,transparent);}
.pos-sale-clear-btn{width:100%;box-sizing:border-box;padding:10px 12px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 10%,transparent);color:color-mix(in srgb,#f87171 90%,var(--text));cursor:pointer;transition:opacity .15s ease,border-color .15s ease;}
.pos-sale-clear-btn:hover:not(:disabled){border-color:color-mix(in srgb,#ef4444 65%,var(--border));background:color-mix(in srgb,#ef4444 16%,transparent);}
.pos-sale-clear-btn:disabled{opacity:.45;cursor:not-allowed;}
body.pos-walking-active .pos-sale-foot{padding:8px 10px;}
body.pos-walking-active .pos-sale-clear-btn{padding:8px 10px;font-size:12px;}
</style>
@endonce

<div class="pos-sale-foot">
    <button type="button" class="pos-sale-clear-btn" id="pos-clear-cart" disabled>Clear sale</button>
</div>
