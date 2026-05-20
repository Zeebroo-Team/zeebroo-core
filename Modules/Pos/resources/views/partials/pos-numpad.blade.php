@once
<style>
.pos-numpad{width:100%;box-sizing:border-box;}
.pos-numpad__keys{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;width:100%;}
.pos-numpad__key{display:flex;align-items:center;justify-content:center;width:100%;min-width:0;min-height:42px;padding:6px 4px;box-sizing:border-box;font-size:17px;font-weight:800;line-height:1;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);cursor:pointer;transition:border-color .12s ease,background .12s ease,transform .08s ease;-webkit-tap-highlight-color:transparent;}
.pos-numpad__key:hover:not(:disabled){border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.pos-numpad__key:active:not(:disabled){transform:scale(0.97);}
.pos-numpad__key:disabled{opacity:.4;cursor:not-allowed;}
.pos-numpad__key--back{font-size:15px;}
.pos-numpad__actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-top:6px;width:100%;}
.pos-numpad__key--action{min-height:38px;font-size:11px;font-weight:700;line-height:1.2;padding:6px 8px;white-space:nowrap;}
.pos-numpad.is-disabled .pos-numpad__keys,.pos-numpad.is-disabled .pos-numpad__actions{opacity:.5;}
.pos-numpad.is-disabled .pos-numpad__key{pointer-events:none;}
body.pos-walking-active .pos-checkout-form__footer .pos-numpad__key{min-height:38px;font-size:16px;}
body.pos-walking-active .pos-checkout-form__footer .pos-numpad__key--action{min-height:36px;font-size:11px;}
</style>
@endonce

<div class="pos-numpad" id="pos-numpad" aria-label="Number pad">
    <div class="pos-numpad__keys" role="group" aria-label="Numeric keys">
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="7">7</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="8">8</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="9">9</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="4">4</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="5">5</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="6">6</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="1">1</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="2">2</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="3">3</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key=".">.</button>
        <button type="button" class="pos-numpad__key" data-pos-numpad-key="0">0</button>
        <button type="button" class="pos-numpad__key pos-numpad__key--back" data-pos-numpad-action="back" aria-label="Backspace">⌫</button>
    </div>
    <div class="pos-numpad__actions">
        <button type="button" class="pos-numpad__key pos-numpad__key--action" data-pos-numpad-action="exact">Exact due</button>
        <button type="button" class="pos-numpad__key pos-numpad__key--action" data-pos-numpad-action="clear">Clear</button>
    </div>
</div>
