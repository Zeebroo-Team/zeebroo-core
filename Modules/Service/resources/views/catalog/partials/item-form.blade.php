@php
    $isEdit       = isset($item) && $item instanceof \Modules\Service\Models\ServiceItem;
    $action       = $isEdit ? route('service.catalog.update', $item) : route('service.catalog.store');
    $pfx          = $isEdit ? 'edit' : 'new';
    $hasEmployees = $isEdit && isset($item) && $item->relationLoaded('employees') && $item->employees->isNotEmpty();
    $hasProducts  = $isEdit && isset($item) && $item->relationLoaded('products')  && $item->products->isNotEmpty();
    $empOn        = old('assign_employees', $hasEmployees ? '1' : '0') === '1';
    $prodOn       = old('assign_products',  $hasProducts  ? '1' : '0') === '1';
    $curr         = isset($currency) && $currency ? $currency : null;
@endphp

@once
<style>
/* ── form shell ── */
.svcf-form{display:flex;flex-direction:column;gap:0;}
.svcf-section{border:1px solid var(--border);border-radius:12px;margin-bottom:14px;}
.svcf-section__head{
    display:flex;align-items:center;gap:11px;
    padding:13px 16px;border-bottom:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 70%,transparent);
    border-radius:12px 12px 0 0;
}
.svcf-section__icon{
    display:grid;place-items:center;width:32px;height:32px;flex-shrink:0;
    border-radius:8px;font-size:14px;
    background:color-mix(in srgb,var(--primary) 12%,transparent);
    color:var(--primary);
}
.svcf-section__titles{flex:1;min-width:0;}
.svcf-section__title{font-size:13px;font-weight:800;color:var(--text);line-height:1.2;}
.svcf-section__sub{font-size:11px;color:var(--muted);margin-top:1px;}
.svcf-section__body{padding:16px;display:flex;flex-direction:column;gap:14px;}
.svcf-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
@media(max-width:560px){.svcf-grid-2{grid-template-columns:1fr;}}

/* ── fields ── */
.svcf-field{}
.svcf-field label{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:5px;}
.svcf-field label .svcf-req{color:#ef4444;font-size:11px;}
.svcf-field label .svcf-opt{font-size:9px;font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);opacity:.8;background:color-mix(in srgb,var(--border) 60%,transparent);padding:1px 5px;border-radius:4px;}
.svcf-input,.svcf-textarea{
    width:100%;box-sizing:border-box;padding:9px 12px;
    border:1px solid var(--border);border-radius:8px;
    background:var(--card);color:var(--text);font-size:13px;outline:none;
    transition:border-color .15s,box-shadow .15s;font-family:inherit;
}
.svcf-input:focus,.svcf-textarea:focus{
    border-color:color-mix(in srgb,var(--primary) 55%,var(--border));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 15%,transparent);
}
.svcf-input::placeholder,.svcf-textarea::placeholder{color:var(--muted);opacity:.75;}
.svcf-textarea{min-height:80px;resize:vertical;line-height:1.5;}
.svcf-err{color:#f87171;font-size:11px;margin-top:3px;}

/* ── price input with prefix ── */
.svcf-price-wrap{position:relative;}
.svcf-price-prefix{
    position:absolute;left:11px;top:50%;transform:translateY(-50%);
    font-size:12px;font-weight:700;color:var(--muted);pointer-events:none;
}
.svcf-price-wrap .svcf-input{padding-left:12px;}
.svcf-price-wrap.has-prefix .svcf-input{padding-left:32px;}

/* ── duration ── */
.svcf-dur-wrap{position:relative;}
.svcf-dur-hint{
    position:absolute;right:11px;top:50%;transform:translateY(-50%);
    font-size:11px;font-weight:600;color:var(--primary);pointer-events:none;
    opacity:0;transition:opacity .15s;
}
.svcf-dur-hint.is-visible{opacity:1;}

/* ── status toggle ── */
.svcf-status-cards{display:flex;gap:8px;}
.svcf-status-card{
    flex:1;display:flex;align-items:center;gap:9px;
    padding:9px 14px;border:2px solid var(--border);border-radius:9px;cursor:pointer;
    transition:border-color .15s,background .15s;user-select:none;
}
.svcf-status-card input{display:none;}
.svcf-status-card__dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;transition:background .15s;}
.svcf-status-card__label{font-size:13px;font-weight:700;color:var(--text);}
.svcf-status-card--active:has(input:checked){border-color:#10b981;background:color-mix(in srgb,#10b981 8%,transparent);}
.svcf-status-card--active:has(input:checked) .svcf-status-card__dot{background:#10b981;}
.svcf-status-card--inactive:has(input:checked){border-color:var(--muted);background:color-mix(in srgb,var(--muted) 6%,transparent);}
.svcf-status-card--inactive:has(input:checked) .svcf-status-card__dot{background:var(--muted);}

/* ── optional section toggles ── */
.svcf-opt-section{border:1px solid var(--border);border-radius:12px;margin-bottom:14px;}
.svcf-opt-toggle{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 16px;cursor:pointer;user-select:none;
    background:color-mix(in srgb,var(--card) 70%,transparent);
    transition:background .15s;
    border-radius:12px;
}
.svcf-opt-section.is-on .svcf-opt-toggle{border-radius:12px 12px 0 0;}
.svcf-opt-toggle:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
.svcf-opt-toggle__left{display:flex;align-items:center;gap:10px;}
.svcf-opt-toggle__icon{
    display:grid;place-items:center;width:28px;height:28px;border-radius:7px;
    font-size:12px;background:color-mix(in srgb,var(--border) 80%,transparent);color:var(--muted);
    transition:background .15s,color .15s;flex-shrink:0;
}
.svcf-opt-toggle__text{font-size:13px;font-weight:700;color:var(--text);}
.svcf-opt-toggle__sub{font-size:11px;color:var(--muted);margin-top:1px;}
.svcf-opt-toggle__switch{
    position:relative;width:38px;height:22px;flex-shrink:0;
}
.svcf-opt-toggle__switch input{display:none;}
.svcf-switch-track{
    display:block;width:38px;height:22px;border-radius:999px;
    background:var(--border);transition:background .2s;cursor:pointer;
}
.svcf-switch-thumb{
    position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;
    background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);
    transition:transform .2s;pointer-events:none;
}
.svcf-opt-toggle__switch input:checked ~ .svcf-switch-track{background:var(--primary);}
.svcf-opt-toggle__switch input:checked ~ .svcf-switch-thumb{transform:translateX(16px);}
.svcf-opt-body{border-top:1px solid var(--border);padding:16px;display:none;}
.svcf-opt-body.is-open{display:block;}
.svcf-opt-section.is-on .svcf-opt-toggle__icon{
    background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);
}

/* ── footer ── */
.svcf-footer{display:flex;justify-content:flex-end;align-items:center;gap:10px;padding-top:4px;}
.svcf-btn-cancel{
    padding:9px 18px;border:1px solid var(--border);border-radius:9px;
    background:transparent;color:var(--text);font-size:13px;font-weight:600;
    cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;
    transition:background .15s;
}
.svcf-btn-cancel:hover{background:color-mix(in srgb,var(--border) 50%,transparent);}
.svcf-btn-submit{
    padding:9px 22px;border:none;border-radius:9px;
    background:var(--primary);color:#fff;font-size:13px;font-weight:700;
    cursor:pointer;display:inline-flex;align-items:center;gap:7px;
    box-shadow:0 2px 8px color-mix(in srgb,var(--primary) 35%,transparent);
    transition:opacity .15s,box-shadow .15s;
}
.svcf-btn-submit:hover{opacity:.9;box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 40%,transparent);}
</style>
@endonce

<form method="POST" action="{{ $action }}" class="svcf-form" id="{{ $pfx }}-svcf">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- ══ SECTION 1 — Basic info ══ --}}
    <div class="svcf-section">
        <div class="svcf-section__head">
            <span class="svcf-section__icon"><i class="fa fa-pen-nib" aria-hidden="true"></i></span>
            <div class="svcf-section__titles">
                <div class="svcf-section__title">Basic Information</div>
                <div class="svcf-section__sub">Name and description of the service</div>
            </div>
        </div>
        <div class="svcf-section__body">
            <div class="svcf-field">
                <label for="{{ $pfx }}-name">Service name <span class="svcf-req">*</span></label>
                <input id="{{ $pfx }}-name" class="svcf-input" type="text" name="name"
                       value="{{ old('name', $item->name ?? '') }}" maxlength="255" required
                       placeholder="e.g. Oil Change, Web Design, Haircut…">
                @error('name')<p class="svcf-err">{{ $message }}</p>@enderror
            </div>

            <div class="svcf-field">
                <label for="{{ $pfx }}-desc">Description <span class="svcf-opt">optional</span></label>
                <textarea id="{{ $pfx }}-desc" class="svcf-textarea" name="description"
                          maxlength="5000" rows="3"
                          placeholder="What does this service include? Any special notes for the team…">{{ old('description', $item->description ?? '') }}</textarea>
                @error('description')<p class="svcf-err">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ══ SECTION 2 — Pricing & Duration ══ --}}
    <div class="svcf-section">
        <div class="svcf-section__head">
            <span class="svcf-section__icon"><i class="fa fa-tag" aria-hidden="true"></i></span>
            <div class="svcf-section__titles">
                <div class="svcf-section__title">Pricing &amp; Duration</div>
                <div class="svcf-section__sub">Set price, how long the service takes, and its status</div>
            </div>
        </div>
        <div class="svcf-section__body">
            <div class="svcf-grid-2">
                <div class="svcf-field">
                    <label for="{{ $pfx }}-price">
                        {{ $curr ? 'Price (' . $curr . ')' : 'Price' }}
                        <span class="svcf-opt">optional</span>
                    </label>
                    <div class="svcf-price-wrap {{ $curr ? 'has-prefix' : '' }}">
                        @if($curr)
                            <span class="svcf-price-prefix">{{ $curr }}</span>
                        @endif
                        <input id="{{ $pfx }}-price" class="svcf-input" type="number" name="price"
                               value="{{ old('price', $item->price ?? '') }}"
                               min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                    </div>
                    @error('price')<p class="svcf-err">{{ $message }}</p>@enderror
                </div>

                <div class="svcf-field">
                    <label for="{{ $pfx }}-dur">Duration <span class="svcf-opt">optional</span></label>
                    <div class="svcf-dur-wrap">
                        <input id="{{ $pfx }}-dur" class="svcf-input" type="number" name="duration_minutes"
                               value="{{ old('duration_minutes', $item->duration_minutes ?? '') }}"
                               min="1" max="99999" placeholder="Minutes, e.g. 90">
                        <span class="svcf-dur-hint" id="{{ $pfx }}-dur-hint"></span>
                    </div>
                    @error('duration_minutes')<p class="svcf-err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="svcf-field">
                <label>Status</label>
                <div class="svcf-status-cards">
                    <label class="svcf-status-card svcf-status-card--active">
                        <input type="radio" name="is_active" value="1"
                               @checked(old('is_active', $item->is_active ?? true) == '1')>
                        <span class="svcf-status-card__dot" style="background:var(--border);"></span>
                        <div>
                            <div class="svcf-status-card__label">Active</div>
                            <div style="font-size:11px;color:var(--muted);">Visible &amp; bookable</div>
                        </div>
                    </label>
                    <label class="svcf-status-card svcf-status-card--inactive">
                        <input type="radio" name="is_active" value="0"
                               @checked(old('is_active', $item->is_active ?? true) == '0')>
                        <span class="svcf-status-card__dot" style="background:var(--border);"></span>
                        <div>
                            <div class="svcf-status-card__label">Inactive</div>
                            <div style="font-size:11px;color:var(--muted);">Hidden from catalog</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ SECTION 3 — Categories ══ --}}
    <div class="svcf-section">
        <div class="svcf-section__head">
            <span class="svcf-section__icon"><i class="fa fa-folder-tree" aria-hidden="true"></i></span>
            <div class="svcf-section__titles">
                <div class="svcf-section__title">Categories</div>
                <div class="svcf-section__sub">Organise this service for filtering &amp; reporting</div>
            </div>
        </div>
        <div class="svcf-section__body">
            @include('service::catalog.partials.category-field', [
                'fieldIdPrefix'     => $pfx,
                'serviceCategories' => $serviceCategories ?? collect(),
                'item'              => $item ?? null,
            ])
        </div>
    </div>

    {{-- ══ OPTIONAL — Employees ══ --}}
    <div class="svcf-opt-section {{ $empOn ? 'is-on' : '' }}" id="{{ $pfx }}-emp-section">
        <label class="svcf-opt-toggle" for="{{ $pfx }}-emp-chk">
            <div class="svcf-opt-toggle__left">
                <span class="svcf-opt-toggle__icon"><i class="fa fa-users" aria-hidden="true"></i></span>
                <div>
                    <div class="svcf-opt-toggle__text">Assign Employees</div>
                    <div class="svcf-opt-toggle__sub">Choose who can perform this service</div>
                </div>
            </div>
            <div class="svcf-opt-toggle__switch">
                <input type="checkbox" id="{{ $pfx }}-emp-chk" name="assign_employees" value="1"
                       {{ $empOn ? 'checked' : '' }}>
                <span class="svcf-switch-track"></span>
                <span class="svcf-switch-thumb"></span>
            </div>
        </label>
        <div class="svcf-opt-body {{ $empOn ? 'is-open' : '' }}" id="{{ $pfx }}-emp-body">
            @include('service::catalog.partials.employee-field', [
                'fieldIdPrefix' => $pfx,
                'employees'     => $employees ?? collect(),
                'item'          => $item ?? null,
            ])
        </div>
    </div>

    {{-- ══ OPTIONAL — Products / Materials ══ --}}
    <div class="svcf-opt-section {{ $prodOn ? 'is-on' : '' }}" id="{{ $pfx }}-prod-section">
        <label class="svcf-opt-toggle" for="{{ $pfx }}-prod-chk">
            <div class="svcf-opt-toggle__left">
                <span class="svcf-opt-toggle__icon"><i class="fa fa-box" aria-hidden="true"></i></span>
                <div>
                    <div class="svcf-opt-toggle__text">Assign Products / Materials</div>
                    <div class="svcf-opt-toggle__sub">Track materials consumed when delivering this service</div>
                </div>
            </div>
            <div class="svcf-opt-toggle__switch">
                <input type="checkbox" id="{{ $pfx }}-prod-chk" name="assign_products" value="1"
                       {{ $prodOn ? 'checked' : '' }}>
                <span class="svcf-switch-track"></span>
                <span class="svcf-switch-thumb"></span>
            </div>
        </label>
        <div class="svcf-opt-body {{ $prodOn ? 'is-open' : '' }}" id="{{ $pfx }}-prod-body">
            @include('service::catalog.partials.product-field', [
                'fieldIdPrefix' => $pfx,
                'products'      => $products ?? collect(),
                'item'          => $item ?? null,
            ])
        </div>
    </div>

    {{-- ══ FOOTER ══ --}}
    <div class="svcf-footer">
        @if($isEdit)
            <a href="{{ route('service.catalog.show', $item) }}" class="svcf-btn-cancel">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> Cancel
            </a>
        @endif
        <button type="submit" class="svcf-btn-submit">
            <i class="fa fa-{{ $isEdit ? 'floppy-disk' : 'plus' }}" aria-hidden="true"></i>
            {{ $isEdit ? 'Save changes' : 'Add service' }}
        </button>
    </div>
</form>

<script>
(function () {
    // ── Duration hint ──
    const durInp  = document.getElementById(@json($pfx . '-dur'));
    const durHint = document.getElementById(@json($pfx . '-dur-hint'));
    if (durInp && durHint) {
        function updateDurHint() {
            const v = parseInt(durInp.value);
            if (!v || v < 1) { durHint.textContent = ''; durHint.classList.remove('is-visible'); return; }
            const h = Math.floor(v / 60), m = v % 60;
            durHint.textContent = h > 0 && m > 0 ? h + 'h ' + m + 'm' : h > 0 ? h + 'h' : m + 'm';
            durHint.classList.add('is-visible');
        }
        durInp.addEventListener('input', updateDurHint);
        updateDurHint();
    }

    // ── Optional section toggles ──
    [
        { chk: @json($pfx . '-emp-chk'),  body: @json($pfx . '-emp-body'),  section: @json($pfx . '-emp-section') },
        { chk: @json($pfx . '-prod-chk'), body: @json($pfx . '-prod-body'), section: @json($pfx . '-prod-section') },
    ].forEach(({ chk, body, section }) => {
        const chkEl  = document.getElementById(chk);
        const bodyEl = document.getElementById(body);
        const secEl  = document.getElementById(section);
        if (!chkEl || !bodyEl) return;
        chkEl.addEventListener('change', () => {
            bodyEl.classList.toggle('is-open', chkEl.checked);
            secEl?.classList.toggle('is-on', chkEl.checked);
        });
    });
})();
</script>
