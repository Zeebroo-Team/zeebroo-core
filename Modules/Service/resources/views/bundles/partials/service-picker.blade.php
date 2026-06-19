@php
    $spkId    = isset($fieldPrefix) ? $fieldPrefix . '-bsvc' : 'bsvc';
    $svcList  = isset($allServices) ? $allServices : collect();
    $svcForJs = $svcList->map(fn ($s) => [
        'id'       => (int) $s->id,
        'name'     => $s->name,
        'price'    => $s->price !== null ? number_format((float) $s->price, 2) : null,
        'duration' => $s->duration_minutes,
    ])->values();

    $initLines = [];
    if (old('bundle_svc_ids')) {
        $ids  = (array) old('bundle_svc_ids', []);
        $qtys = (array) old('bundle_svc_qtys', []);
        foreach ($ids as $i => $sid) {
            $s = $svcList->firstWhere('id', (int) $sid);
            if ($s) $initLines[] = ['id' => (int) $s->id, 'name' => $s->name, 'price' => $s->price, 'qty' => (int) ($qtys[$i] ?? 1)];
        }
    } elseif (isset($bundle) && $bundle?->relationLoaded('services')) {
        foreach ($bundle->services as $s) {
            $initLines[] = ['id' => (int) $s->id, 'name' => $s->name, 'price' => $s->price, 'qty' => (int) ($s->pivot->qty ?? 1)];
        }
    }
@endphp

@once
<style>
.bsvc-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.bsvc-search-row{display:flex;gap:8px;align-items:center;margin-bottom:10px;}
.bsvc-search-wrap{position:relative;flex:1;}
.bsvc-search-input{
    width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;
    background:var(--card);color:var(--text);font-size:13px;outline:none;
    transition:border-color .15s,box-shadow .15s;
}
.bsvc-search-input:focus{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent);}
.bsvc-suggest{
    position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);
    margin:0;padding:4px 0;list-style:none;max-height:220px;overflow:auto;
    border:1px solid var(--border);border-radius:10px;background:var(--card);
    box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.bsvc-suggest[hidden]{display:none;}
.bsvc-suggest li{margin:0;}
.bsvc-suggest button{
    display:flex;width:100%;align-items:baseline;gap:8px;text-align:left;
    padding:8px 12px;border:none;background:transparent;font-size:13px;color:var(--text);cursor:pointer;
}
.bsvc-suggest button:hover,.bsvc-suggest button:focus-visible{background:color-mix(in srgb,var(--primary) 10%,transparent);outline:none;}
.bsvc-suggest .bsvc-price{margin-left:auto;font-size:11px;color:var(--muted);white-space:nowrap;}
.bsvc-suggest .bsvc-empty{color:var(--muted);font-style:italic;cursor:default;}
.bsvc-suggest .bsvc-empty:hover{background:transparent;}
.bsvc-lines{border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:6px;}
.bsvc-lines:empty{display:none;}
.bsvc-line{
    display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:10px;
    padding:9px 12px;border-bottom:1px solid var(--border);background:var(--card);
}
.bsvc-line:last-child{border-bottom:none;}
.bsvc-line__icon{color:var(--primary);font-size:13px;margin-right:2px;}
.bsvc-line__name{font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.bsvc-line__price{font-size:11px;color:var(--muted);}
.bsvc-line__qty-wrap{display:flex;align-items:center;gap:5px;}
.bsvc-line__qty-label{font-size:11px;color:var(--muted);white-space:nowrap;}
.bsvc-line__qty{
    width:60px;padding:4px 8px;border:1px solid var(--border);border-radius:6px;
    background:var(--card);color:var(--text);font-size:13px;text-align:center;outline:none;
    transition:border-color .15s;
}
.bsvc-line__qty:focus{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));}
.bsvc-line__remove{
    display:grid;place-items:center;width:26px;height:26px;padding:0;border:none;border-radius:6px;
    background:transparent;color:var(--muted);font-size:13px;cursor:pointer;
}
.bsvc-line__remove:hover{background:color-mix(in srgb,#f87171 14%,transparent);color:#f87171;}
.bsvc-total{
    display:flex;align-items:center;justify-content:space-between;
    padding:8px 12px;border:1px solid var(--border);border-radius:8px;
    background:color-mix(in srgb,var(--primary) 5%,transparent);font-size:13px;
}
.bsvc-total__label{color:var(--muted);font-weight:600;}
.bsvc-total__val{font-weight:800;color:var(--primary);}
.bsvc-hidden{display:none;}
</style>
@endonce

<div class="bsvc-picker" id="{{ $spkId }}-picker">
    <label class="bsvc-label">Included Services <span style="color:#ef4444;">*</span></label>

    <div class="bsvc-search-row">
        <div class="bsvc-search-wrap">
            <input type="text" class="bsvc-search-input" id="{{ $spkId }}-search"
                   placeholder="{{ $svcList->isEmpty() ? 'No services available' : 'Search services to add…' }}"
                   autocomplete="off" {{ $svcList->isEmpty() ? 'readonly' : '' }}>
            <ul class="bsvc-suggest" id="{{ $spkId }}-suggest" hidden
                data-catalog='@json($svcForJs)'></ul>
        </div>
    </div>

    <div class="bsvc-lines" id="{{ $spkId }}-lines"></div>

    <div class="bsvc-total" id="{{ $spkId }}-total" style="display:none;">
        <span class="bsvc-total__label"><i class="fa fa-calculator" style="margin-right:5px;opacity:.7;"></i>Individual total</span>
        <span class="bsvc-total__val" id="{{ $spkId }}-total-val">—</span>
    </div>

    @if($svcList->isEmpty())
        <p style="margin:6px 0 0;font-size:11px;color:var(--muted);">No active services yet. <a href="{{ route('service.catalog.index') }}" style="color:var(--primary);font-weight:600;">Add services</a> first.</p>
    @else
        <p style="margin:6px 0 0;font-size:11px;color:var(--muted);">Search and add the services included in this bundle. Set quantity per service.</p>
    @endif

    <div class="bsvc-hidden" id="{{ $spkId }}-hidden" aria-hidden="true"></div>
    @error('bundle_svc_ids')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
</div>

<script>
(function () {
    const PREFIX    = @json($spkId);
    const CURRENCY  = @json($currency ?? '');
    const searchEl  = document.getElementById(PREFIX + '-search');
    const suggestEl = document.getElementById(PREFIX + '-suggest');
    const linesEl   = document.getElementById(PREFIX + '-lines');
    const hiddenEl  = document.getElementById(PREFIX + '-hidden');
    const totalEl   = document.getElementById(PREFIX + '-total');
    const totalVal  = document.getElementById(PREFIX + '-total-val');

    if (!searchEl || !linesEl) return;

    const catalog = JSON.parse(suggestEl?.dataset.catalog || '[]');
    const lines   = new Map(); // id -> { id, name, price, qty }

    function fmt(n) { return (CURRENCY ? CURRENCY + ' ' : '') + Number(n).toFixed(2); }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function syncHidden() {
        hiddenEl.innerHTML = '';
        lines.forEach(line => {
            const i1 = document.createElement('input');
            i1.type = 'hidden'; i1.name = 'bundle_svc_ids[]'; i1.value = line.id;
            const i2 = document.createElement('input');
            i2.type = 'hidden'; i2.name = 'bundle_svc_qtys[]'; i2.value = line.qty;
            hiddenEl.appendChild(i1);
            hiddenEl.appendChild(i2);
        });
    }

    function updateTotal() {
        if (!totalEl) return;
        let total = 0; let allKnown = true;
        lines.forEach(l => {
            if (l.price === null || l.price === undefined) { allKnown = false; }
            else total += parseFloat(l.price) * l.qty;
        });
        if (lines.size === 0) { totalEl.style.display = 'none'; return; }
        totalEl.style.display = '';
        totalVal.textContent = allKnown ? fmt(total) : '—';
    }

    function renderLines() {
        linesEl.innerHTML = '';
        lines.forEach((line, id) => {
            const row = document.createElement('div');
            row.className = 'bsvc-line';
            row.innerHTML =
                '<div style="min-width:0;">'
                    + '<div class="bsvc-line__name"><i class="fa fa-wrench bsvc-line__icon" aria-hidden="true"></i>' + esc(line.name) + '</div>'
                    + (line.price !== null ? '<div class="bsvc-line__price">' + fmt(line.price) + ' each</div>' : '')
                + '</div>'
                + '<div class="bsvc-line__qty-wrap">'
                    + '<span class="bsvc-line__qty-label">Qty</span>'
                    + '<input type="number" class="bsvc-line__qty" value="' + esc(line.qty) + '" min="1" max="999" step="1" aria-label="Quantity for ' + esc(line.name) + '">'
                + '</div>'
                + '<button type="button" class="bsvc-line__remove" aria-label="Remove ' + esc(line.name) + '"><i class="fa fa-times" aria-hidden="true"></i></button>';

            row.querySelector('.bsvc-line__qty').addEventListener('input', e => {
                line.qty = Math.max(1, parseInt(e.target.value) || 1);
                e.target.value = line.qty;
                syncHidden();
                updateTotal();
            });
            row.querySelector('.bsvc-line__remove').addEventListener('click', () => {
                lines.delete(id);
                renderLines();
                syncHidden();
                updateTotal();
                filterSuggest(searchEl.value.trim());
            });
            linesEl.appendChild(row);
        });
        syncHidden();
        updateTotal();
    }

    function addService(svc) {
        if (lines.has(svc.id)) return;
        lines.set(svc.id, { id: svc.id, name: svc.name, price: svc.price, qty: 1 });
        renderLines();
        searchEl.value = '';
        closeSuggest();
        searchEl.focus();
    }

    function filterSuggest(q) {
        const ql = (q || '').toLowerCase();
        const matches = catalog.filter(s => {
            if (lines.has(s.id)) return false;
            return !ql || s.name.toLowerCase().includes(ql);
        }).slice(0, 10);

        suggestEl.innerHTML = '';
        if (matches.length === 0) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'bsvc-empty';
            btn.textContent = ql ? 'No matching services.' : 'All services added.';
            li.appendChild(btn); suggestEl.appendChild(li);
        } else {
            matches.forEach(s => {
                const li  = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML = '<span>' + esc(s.name) + '</span>'
                    + (s.price !== null ? '<span class="bsvc-price">' + fmt(s.price) + '</span>' : '');
                btn.addEventListener('mousedown', e => e.preventDefault());
                btn.addEventListener('click', () => addService(s));
                li.appendChild(btn); suggestEl.appendChild(li);
            });
        }
        suggestEl.hidden = false;
    }

    function closeSuggest() { suggestEl.hidden = true; }

    searchEl.addEventListener('focus', () => filterSuggest(searchEl.value.trim()));
    searchEl.addEventListener('input', () => filterSuggest(searchEl.value.trim()));
    searchEl.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSuggest(); searchEl.blur(); }
        if (e.key === 'Enter') {
            e.preventDefault();
            const ql = searchEl.value.trim().toLowerCase();
            const exact = catalog.find(s => s.name.toLowerCase() === ql && !lines.has(s.id));
            if (exact) addService(exact);
        }
    });
    document.addEventListener('click', e => {
        if (!searchEl.closest('.bsvc-search-wrap').contains(e.target)) closeSuggest();
    });

    @json($initLines).forEach(l => {
        lines.set(l.id, { id: l.id, name: l.name, price: l.price, qty: l.qty });
    });
    renderLines();
})();
</script>
