@extends('theme::layouts.app', ['title' => 'Sale campaigns', 'heading' => 'Sale campaigns'])

@section('content')
@include('product::partials.catalog-hub-styles')

<style>
.pd-section{border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--card) 98%,transparent);overflow:hidden;}
.pd-section + .pd-section{margin-top:16px;}
.pd-section__head{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--card) 93%,var(--border) 7%);}
.pd-section__icon{width:34px;height:34px;flex-shrink:0;display:grid;place-items:center;border-radius:9px;border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);font-size:14px;}
.pd-section__title{font-size:13px;font-weight:800;color:var(--text);line-height:1.2;}
.pd-section__sub{font-size:12px;color:var(--muted);margin-top:2px;line-height:1.35;}
.pd-section__body{padding:18px 16px;display:flex;flex-direction:column;gap:14px;}
.pd-two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
@media(max-width:600px){.pd-two-col{grid-template-columns:1fr;}}
.pd-field-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.pd-req{color:color-mix(in srgb,#f87171 80%,var(--text));font-weight:700;}
.pd-optional{font-size:10px;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;margin-left:4px;}
.pd-err{color:#f87171;font-size:12px;margin:4px 0 0;}
.pd-type-cards{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.pd-type-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:2px solid var(--border);background:color-mix(in srgb,var(--card) 97%,transparent);cursor:pointer;transition:border-color .15s,background .15s;position:relative;}
.pd-type-card:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pd-type-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
.pd-type-card--selected{border-color:var(--primary) !important;background:color-mix(in srgb,var(--primary) 8%,transparent);}
.pd-type-card__icon{width:32px;height:32px;flex-shrink:0;display:grid;place-items:center;border-radius:8px;font-size:13px;background:color-mix(in srgb,var(--card) 90%,var(--border) 10%);color:var(--muted);border:1px solid var(--border);transition:background .15s,color .15s;}
.pd-type-card--selected .pd-type-card__icon{background:color-mix(in srgb,var(--primary) 15%,transparent);color:var(--primary);border-color:color-mix(in srgb,var(--primary) 35%,var(--border));}
.pd-type-card__body{flex:1;min-width:0;}
.pd-type-card__label{display:block;font-size:12px;font-weight:700;color:var(--text);line-height:1.2;}
.pd-type-card__hint{display:block;font-size:10px;color:var(--muted);margin-top:1px;}
.pd-type-card__check{font-size:14px;color:var(--primary);opacity:0;transition:opacity .15s;}
.pd-type-card--selected .pd-type-card__check{opacity:1;}
.pd-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid var(--border);}
.pd-badge--active{border-color:color-mix(in srgb,#22c55e 42%,var(--border));background:color-mix(in srgb,#22c55e 9%,transparent);color:#15803d;}
.pd-badge--inactive{color:var(--muted);}
.pd-badge--expired{border-color:color-mix(in srgb,#f87171 32%,var(--border));background:color-mix(in srgb,#f87171 7%,transparent);color:#dc2626;}
html[data-theme="night"] .pd-badge--active, html[data-theme="night_blue"] .pd-badge--active, html[data-theme="ocean"] .pd-badge--active{color:#4ade80;}
html[data-theme="night"] .pd-badge--expired, html[data-theme="night_blue"] .pd-badge--expired, html[data-theme="ocean"] .pd-badge--expired{color:#f87171;}
.pd-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:color-mix(in srgb,#f59e0b 11%,transparent);border:1px solid color-mix(in srgb,#f59e0b 32%,var(--border));color:#b45309;}
html[data-theme="night"] .pd-pill, html[data-theme="night_blue"] .pd-pill, html[data-theme="ocean"] .pd-pill{color:#fbbf24;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('product::partials.product-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('campaign'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('campaign') }}</div>
    @endif

    <div style="margin-bottom:18px;">
        <p class="muted" style="margin:0;font-size:13px;line-height:1.5;">
            Time-boxed sale campaigns for <strong style="color:var(--text);">{{ $business->name }}</strong> —
            discount the entire store or a hand-picked set of products for a limited (or long-term) window.
        </p>
    </div>

    <div class="pcat-toolbar" style="margin-bottom:16px;">
        <span class="muted" style="font-size:13px;">
            @if($campaigns->isEmpty())
                Create your <strong style="color:var(--text);">first campaign</strong> below.
            @else
                {{ $campaigns->count() }} {{ $campaigns->count() === 1 ? 'campaign' : 'campaigns' }}.
            @endif
        </span>
        @if($campaigns->isNotEmpty())
            <button type="button" id="sc-modal-open" class="linkbtn"
                    style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                <i class="fa fa-plus"></i> New campaign
            </button>
        @endif
    </div>

    @if($campaigns->isEmpty())

        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('product.campaigns.store') }}">
            @csrf
            @include('product::campaigns.partials.form-fields', ['idPfx' => 'sc-inline'])
            <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                <button type="submit" class="linkbtn" style="padding:10px 24px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                    <i class="fa fa-check"></i> Save campaign
                </button>
            </div>
        </form>

    @else

        <div class="pcat-table-wrap">
            <table class="pcat-table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Scope</th>
                        <th>Discount</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($campaigns as $c)
                        <tr>
                            <td>
                                <strong style="color:var(--text);font-size:13px;">{{ $c->name }}</strong>
                                @if($c->description)
                                    <div style="font-size:11px;color:var(--muted);">{{ \Illuminate\Support\Str::limit($c->description, 60) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($c->mode === 'storewide')
                                    <span style="font-size:12px;color:var(--text);"><i class="fa fa-store" style="opacity:.6;margin-right:4px;"></i>Storewide</span>
                                @else
                                    <span style="font-size:12px;color:var(--text);"><i class="fa fa-list" style="opacity:.6;margin-right:4px;"></i>{{ $c->items->count() }} {{ $c->items->count() === 1 ? 'product' : 'products' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($c->mode === 'storewide')
                                    <span class="pd-pill">
                                        @if($c->discount_type === 'percentage')
                                            <i class="fa fa-percent" style="font-size:8px;"></i>{{ rtrim(rtrim(number_format((float)$c->discount_value,2),'0'),'.') }}%
                                        @else
                                            <i class="fa fa-minus" style="font-size:8px;"></i>{{ number_format((float)$c->discount_value,2) }}
                                        @endif
                                    </span>
                                @else
                                    <span class="muted" style="font-size:12px;">Per product</span>
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--muted);white-space:nowrap;min-width:100px;">
                                @if($c->is_long_term)
                                    <span>Long-term</span>
                                    @if($c->starts_at)<div style="margin-top:2px;">from {{ $c->starts_at->format('d M Y') }}</div>@endif
                                @elseif($c->starts_at || $c->ends_at)
                                    @if($c->starts_at)<div><i class="fa fa-play" style="font-size:9px;opacity:.6;"></i> {{ $c->starts_at->format('d M Y') }}</div>@endif
                                    @if($c->ends_at)<div style="margin-top:2px;"><i class="fa fa-stop" style="font-size:9px;opacity:.6;"></i> {{ $c->ends_at->format('d M Y') }}</div>@endif
                                @else
                                    <span>No limit</span>
                                @endif
                            </td>
                            <td>
                                @if($c->isExpired())
                                    <span class="pd-badge pd-badge--expired"><i class="fa fa-clock"></i> Expired</span>
                                @elseif($c->isCurrentlyActive())
                                    <span class="pd-badge pd-badge--active"><i class="fa fa-circle-check"></i> Active</span>
                                @else
                                    <span class="pd-badge pd-badge--inactive">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a href="{{ route('product.campaigns.edit', $c) }}" class="pcat-link" style="margin-right:10px;">
                                    <i class="fa fa-pen"></i> Edit
                                </a>
                                <form method="post" action="{{ route('product.campaigns.destroy', $c) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete « {{ addslashes($c->name) }} »?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-btn-del">
                                        <i class="fa fa-trash-can"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="sc-modal"
             class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}"
             role="dialog" aria-modal="true" aria-labelledby="sc-modal-title"
             aria-hidden="{{ $modalOpen ? 'false' : 'true' }}">
            <div class="pcat-modal__backdrop" data-sc-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:640px;">
                <div class="pcat-modal__head">
                    <h2 id="sc-modal-title" style="font-size:15px;">
                        <i class="fa fa-bullhorn" style="margin-right:6px;opacity:.7;"></i> New campaign
                    </h2>
                    <button type="button" class="pcat-modal__close" data-sc-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body" style="padding:20px 16px 24px;">
                    @if($errors->any())
                        <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('product.campaigns.store') }}">
                        @csrf
                        @include('product::campaigns.partials.form-fields', ['idPfx' => 'sc-modal'])
                        <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                            <button type="submit" class="linkbtn" style="padding:10px 24px;font-size:13px;display:inline-flex;align-items:center;gap:7px;">
                                <i class="fa fa-check"></i> Save campaign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endif
</div>

<script>
(function () {
    var modal  = document.getElementById('sc-modal');
    var openBtn = document.getElementById('sc-modal-open');
    function lockScroll(on) { document.documentElement.classList.toggle('pcat-modal-open-html', Boolean(on)); }
    function openModal() {
        if (!modal) return;
        modal.classList.add('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('pcat-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        lockScroll(false);
        if (openBtn) openBtn.focus();
    }
    openBtn && openBtn.addEventListener('click', openModal);
    modal && modal.querySelectorAll('[data-sc-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('pcat-modal--open')) closeModal();
    });
    if (modal && modal.classList.contains('pcat-modal--open')) lockScroll(true);
})();
</script>
@endsection
