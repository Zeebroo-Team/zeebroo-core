@extends('theme::layouts.app', ['title' => 'Properties', 'heading' => 'Properties'])

@section('content')
@php
    $propertyCurrency = $business ? (string) (get_settings('business.currency', '', $business) ?: '') : '';
@endphp
<style>
    .property-page{max-width:none;width:100%}
    .property-hero{display:flex;justify-content:space-between;align-items:center;gap:12px;padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid var(--border)}
    .property-actions{display:flex;gap:8px;align-items:center}
    .property-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer}
    .property-btn--ghost{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none}
    .property-alert{padding:10px 12px;border-radius:10px;font-size:12px;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;line-height:1.4;border:1px solid}
    .property-alert--ok{border-color:color-mix(in srgb,#22c55e 38%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,#22c55e 8%,transparent),color-mix(in srgb,var(--card) 96%,transparent))}
    .property-alert--err{border-color:color-mix(in srgb,#f87171 42%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent)}
    .property-empty{text-align:center;padding:24px 18px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 24%,var(--border));border-radius:10px;background:linear-gradient(165deg,color-mix(in srgb,var(--primary) 7%,transparent),color-mix(in srgb,var(--card) 98%,transparent))}
    .property-empty h2{font-size:16px}
    .property-empty p{font-size:12px;line-height:1.45}
    .property-inline{margin-top:10px;padding:16px;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 16%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 5%,transparent),var(--card))}
    .property-inline h2{font-size:16px}
    .property-grid{display:grid;gap:12px}
    @media(min-width:700px){.property-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px}}
    .property-field--full{grid-column:1/-1}
    .property-field label{display:block;margin-bottom:5px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
    .property-field input:not(.property-switch-input),.property-field textarea,.property-field select{width:100%;box-sizing:border-box;padding:8px 10px;font-size:12px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text)}
    .property-field select{appearance:none;-webkit-appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 14px) calc(50% - 2px),calc(100% - 9px) calc(50% - 2px);background-size:5px 5px,5px 5px;background-repeat:no-repeat;padding-right:30px}
    .property-field textarea{resize:vertical;min-height:64px;line-height:1.45}
    .property-field .property-switch-row{
        position:relative;
        display:inline-flex;
        align-items:center;
        justify-content:flex-start;
        gap:10px;
        width:100%;
        padding:9px 11px;
        margin-bottom:0;
        border:1px solid var(--border);
        border-radius:8px;
        font-size:12px;
        font-weight:500;
        text-transform:none;
        letter-spacing:0;
        color:var(--text);
        cursor:pointer;
        user-select:none;
        background:color-mix(in srgb,var(--card) 96%,transparent);
        transition:border-color .18s ease,background .18s ease,box-shadow .18s ease;
    }
    .property-switch-row:hover{
        border-color:color-mix(in srgb,var(--primary) 34%,var(--border));
        background:color-mix(in srgb,var(--primary) 5%,var(--card));
    }
    .property-switch-input{
        position:absolute!important;
        left:-9999px!important;
        top:auto!important;
        width:1px!important;
        height:1px!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        clip:rect(0 0 0 0)!important;
        clip-path:inset(50%)!important;
        overflow:hidden!important;
        pointer-events:none!important;
        appearance:none!important;
        -webkit-appearance:none!important;
    }
    .property-switch-ui{
        display:inline-block;
        position:relative;
        width:42px;
        height:24px;
        border-radius:999px;
        background:color-mix(in srgb,var(--muted) 36%,var(--border));
        box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--border) 88%,transparent);
        transition:background .2s ease,box-shadow .2s ease;
        flex-shrink:0;
    }
    .property-switch-knob{
        position:absolute;
        top:2px;
        left:2px;
        width:20px;
        height:20px;
        border-radius:50%;
        background:#fff;
        border:1px solid color-mix(in srgb,var(--border) 76%,transparent);
        box-shadow:0 1px 3px rgba(0,0,0,.2);
        transition:left .2s ease,transform .2s ease;
    }
    .property-switch-input:checked + .property-switch-ui{
        background:#16a34a;
        box-shadow:inset 0 0 0 1px color-mix(in srgb,#16a34a 70%,#14532d);
    }
    .property-switch-input:checked + .property-switch-ui .property-switch-knob{
        left:calc(100% - 2px - 20px);
    }
    .property-switch-row:active .property-switch-knob{transform:scale(.96);}
    .property-switch-input:focus-visible + .property-switch-ui{
        outline:2px solid color-mix(in srgb,var(--primary) 48%,transparent);
        outline-offset:2px;
    }
    .property-field .property-switch-text{
        font-size:12px;
        line-height:1.25;
        margin-top:1px;
        color:var(--text);
        font-weight:600;
        letter-spacing:0;
        text-transform:none;
    }
    #property-expire-date-wrap[hidden]{display:none!important}
    .property-field-err{display:block;color:#f87171;font-size:11px;margin-top:5px}
    .property-submit-wrap{margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding-top:10px;border-top:1px solid var(--border)}
    .property-submit-wrap .linkbtn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        padding:8px 12px;
        font-size:12px;
        font-weight:700;
        border-radius:8px;
        line-height:1.1;
    }
    .property-submit-wrap .linkbtn i{
        font-size:11px;
        line-height:1;
    }
    .property-submit-note{font-size:11px;color:var(--muted)}
    .property-list{display:flex;flex-direction:column;gap:6px}
    .property-card{display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid var(--border);border-radius:9px;padding:10px}
    .property-card__title{margin:0;font-size:13px;font-weight:800}
    .property-card__meta{margin:3px 0 0;color:var(--muted);font-size:11px;line-height:1.4}
    .property-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
    .property-delete{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;font-size:10px;font-weight:600;border-radius:7px;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));background:transparent;color:#f97373;cursor:pointer}
    .property-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:20px 16px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease}
    .property-modal--open{opacity:1;visibility:visible;pointer-events:auto}
    .property-modal__backdrop{position:fixed;inset:0;background:rgba(15,23,42,.52)}
    .property-modal__panel{position:relative;z-index:1;width:100%;max-width:760px;max-height:min(92vh,calc(100dvh - 40px));display:flex;flex-direction:column;border-radius:10px;border:1px solid var(--border);background:var(--card)}
    .property-modal__head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid var(--border)}
    .property-modal__head h2{font-size:15px}
    .property-modal__body{padding:14px;overflow:auto}
    .property-modal__close{width:30px;height:30px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);cursor:pointer;font-size:18px;line-height:1}
    html.property-modal-open-html,html.property-modal-open-html body{overflow:hidden}
</style>

<div class="property-page">
    <header class="property-hero">
        <div>
            <div class="property-chip"><i class="fa fa-building"></i> Property hub</div>
        </div>
        <div class="property-actions">
            @if($business && $properties->isNotEmpty())
                <button type="button" id="property-modal-open" class="property-btn"><i class="fa fa-plus"></i>Add property</button>
            @endif
            <a class="property-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i>Overview</a>
        </div>
    </header>

    @if(!$business)
        <div class="property-empty">
            <h2 style="margin:0 0 8px;">No business selected</h2>
            <p style="margin:0;">Select a business from the navbar, then add your property assets and expiry details.</p>
        </div>
    @else
        @if(session('status'))
            <div class="property-alert property-alert--ok" role="status">
                <i class="fa fa-circle-check"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($properties->isEmpty())
            <section class="property-inline">
                <h2 style="margin:0 0 12px;">Add your first property</h2>
                @include('account::properties.partials.create-form')
            </section>
        @else
            <div class="property-list">
                @foreach($properties as $property)
                    <article class="property-card">
                        <div>
                            <h3 class="property-card__title">{{ $property->property_name }}</h3>
                            <p class="property-card__meta">
                                {{ $property->property_type }}
                                · Cost: @if($propertyCurrency){{ $propertyCurrency }} @endif{{ number_format((float) $property->cost, 2, '.', ',') }}
                                · Expiry: {{ $property->has_expiry ? ($property->expire_date?->format('M j, Y') ?? 'Not set') : 'No' }}
                            </p>
                            @if($property->description)
                                <p class="property-card__meta" style="margin-top:6px;">{{ \Illuminate\Support\Str::limit($property->description, 180) }}</p>
                            @endif
                        </div>
                        <form method="post" action="{{ route('account.properties.destroy', $property) }}" style="margin:0;" onsubmit="return confirm('Remove this property record?');">
                            @csrf
                            @method('delete')
                            <button type="submit" class="property-delete"><i class="fa fa-trash-can"></i>Remove</button>
                        </form>
                    </article>
                @endforeach
            </div>

            <div id="property-modal" class="property-modal {{ $errors->any() ? 'property-modal--open' : '' }}" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
                <div class="property-modal__backdrop" data-property-modal-close></div>
                <div class="property-modal__panel" role="dialog" aria-modal="true" aria-labelledby="property-modal-title">
                    <div class="property-modal__head">
                        <h2 id="property-modal-title" style="margin:0;">New property</h2>
                        <button type="button" class="property-modal__close" data-property-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="property-modal__body">
                        @include('account::properties.partials.create-form', ['propertyFormErrorBannerClass' => 'property-modal__banner'])
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

@if($business)
<script>
(function () {
    function wireExpiryToggle(scope) {
        const expiryToggle = scope.querySelector('#property-expire-toggle');
        const expiryDate = scope.querySelector('#property-expire-date');
        const expiryDateWrap = scope.querySelector('#property-expire-date-wrap');
        if (!expiryToggle || !expiryDate || !expiryDateWrap) return;

        function syncExpiryRequired() {
            const on = Boolean(expiryToggle.checked);
            expiryDateWrap.hidden = !on;
            expiryDate.required = on;
            if (!on) expiryDate.value = '';
        }

        expiryToggle.addEventListener('change', syncExpiryRequired);
        syncExpiryRequired();
    }

    function wireTypeToggle(scope) {
        const typeSelect = scope.querySelector('[data-property-type-select]');
        const otherWrap = scope.querySelector('[data-property-type-other-wrap]');
        const otherInput = otherWrap ? otherWrap.querySelector('[name="property_type_other"]') : null;
        if (!typeSelect || !otherWrap || !otherInput) return;

        function syncTypeOther() {
            const on = typeSelect.value === 'other';
            otherWrap.hidden = !on;
            otherInput.disabled = !on;
            otherInput.required = on;
            if (!on) otherInput.value = '';
        }

        typeSelect.addEventListener('change', syncTypeOther);
        syncTypeOther();
    }

    document.querySelectorAll('form[id="property-form"]').forEach((formEl) => wireExpiryToggle(formEl));
    document.querySelectorAll('form[id="property-form"]').forEach((formEl) => wireTypeToggle(formEl));

    @if($properties->isNotEmpty())
    const modal = document.getElementById('property-modal');
    const openBtn = document.getElementById('property-modal-open');

    function lockScroll(on) {
        document.documentElement.classList.toggle('property-modal-open-html', Boolean(on));
    }

    function openModal() {
        if (!modal) return;
        modal.classList.add('property-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        document.getElementById('property-name')?.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('property-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        openBtn?.focus();
    }

    openBtn?.addEventListener('click', openModal);
    modal?.querySelectorAll('[data-property-modal-close]').forEach((el) => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal?.classList.contains('property-modal--open')) closeModal();
    });

    if (modal?.classList.contains('property-modal--open')) lockScroll(true);
    @endif
})();
</script>
@endif
@endsection
