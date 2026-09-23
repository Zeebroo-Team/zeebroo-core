@extends('theme::layouts.app', ['title' => 'Budgets', 'heading' => 'Budgets'])

@section('content')
@include('product::partials.catalog-hub-styles')
<div class="bud-page">
    <style>
        .bud-page{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .bud-hero{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:flex-start;padding:0 0 12px;margin-bottom:2px;border-bottom:1px solid var(--border);}
        .bud-hero__badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--primary);padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
        .bud-btn--primary,.bud-btn--primary:visited{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;text-decoration:none;}
        .bud-btn--primary:hover{background:var(--btn-hover);color:#111827;}
        .bud-btn--ghost{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;}
        .bud-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .bud-body{padding:14px 0 0;}
        .bud-empty{text-align:center;padding:22px 16px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 26%,var(--border));border-radius:11px;background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .bud-empty__ico{width:44px;height:44px;margin:0 auto 10px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 7%,transparent));color:var(--primary);font-size:18px;}
        .bud-empty h2{margin:0;font-size:15px;font-weight:700;color:var(--text);}
        .bud-empty p{margin:7px auto 0;max-width:40ch;color:var(--muted);font-size:13px;line-height:1.45;}
        .bud-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}
        .bud-card{position:relative;border-radius:12px;border:1px solid var(--border);background:linear-gradient(165deg,color-mix(in srgb,var(--card) 98%,transparent) 0%,color-mix(in srgb,var(--card) 91%,#000));box-shadow:0 12px 36px -30px rgba(0,0,0,.42);padding:14px 15px;text-decoration:none;color:inherit;display:block;transition:border-color .2s ease,transform .2s ease;}
        .bud-card:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));transform:translateY(-1px);}
        .bud-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px;}
        .bud-card__title{margin:0;font-size:14px;font-weight:800;color:var(--text);}
        .bud-card__sub{margin:3px 0 0;font-size:11px;color:var(--muted);}
        .bud-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid var(--border);white-space:nowrap;}
        .bud-pill--active{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);color:color-mix(in srgb,#bbf7d0 70%,var(--text));}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .bud-pill--active{color:#166534;}
        .bud-card__totals{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;}
        .bud-card__tile{border-radius:9px;padding:8px 9px;border:1px solid color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 94%,transparent);}
        .bud-card__tile-lab{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;display:block;}
        .bud-card__tile-val{font-size:13px;font-weight:800;color:var(--text);}
        .bud-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(12px,2.5vh) 14px calc(14px + env(safe-area-inset-bottom));overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
        .bud-modal.bud-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .bud-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
        .bud-modal__panel{position:relative;z-index:1;width:100%;max-width:480px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);}
        .bud-modal__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
        .bud-modal__head h2{margin:0;font-size:15px;font-weight:800;}
        .bud-modal__close{width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;line-height:1;}
        .bud-modal__body{padding:14px;}
        .bud-field{margin-bottom:10px;}
        .bud-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
        .bud-field input,.bud-field select{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
        html.bud-modal-open-html,html.bud-modal-open-html body{overflow:hidden;}
    </style>

    <header class="bud-hero">
        <div>
            <span class="bud-hero__badge"><i class="fa fa-sack-dollar"></i> Departmental budgets</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:7px;align-items:center;">
            @if($budgets->isNotEmpty())
                <button type="button" id="bud-modal-open" class="bud-btn--primary"><i class="fa fa-plus"></i>New budget</button>
            @endif
            <a class="bud-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i> Overview</a>
        </div>
    </header>

    <div class="bud-body">
        @if(session('status'))
            <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
        @endif

        @if($budgets->isEmpty())
            <div class="bud-empty">
                <div class="bud-empty__ico"><i class="fa fa-sack-dollar"></i></div>
                <h2>No budgets yet</h2>
                <p>Create a monthly or yearly budget to allocate spend across departments and track actuals against it.</p>
            </div>
            <div style="max-width:480px;margin:16px auto 0;">
                @include('budget::partials.create-form')
            </div>
        @else
            <div class="bud-cards">
                @foreach($budgets as $b)
                    <a class="bud-card" href="{{ route('budget.show', $b['id']) }}">
                        <div class="bud-card__head">
                            <div>
                                <h2 class="bud-card__title">{{ $b['name'] }}</h2>
                                <p class="bud-card__sub">{{ $b['type_label'] }} &middot; {{ $b['start_date'] }} — {{ $b['end_date'] }}</p>
                            </div>
                            @if($b['is_active'])
                                <span class="bud-pill bud-pill--active"><i class="fa fa-circle-check"></i> Active</span>
                            @endif
                        </div>
                        <div class="bud-card__totals">
                            <div class="bud-card__tile">
                                <span class="bud-card__tile-lab">Monthly allocation</span>
                                <span class="bud-card__tile-val">{{ $b['total_monthly_fmt'] }}</span>
                            </div>
                            <div class="bud-card__tile">
                                <span class="bud-card__tile-lab">Yearly allocation</span>
                                <span class="bud-card__tile-val">{{ $b['total_yearly_fmt'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div id="bud-modal" class="bud-modal {{ $errors->any() ? 'bud-modal--open' : '' }}" role="dialog" aria-modal="true" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
                <div class="bud-modal__backdrop" data-bud-modal-close></div>
                <div class="bud-modal__panel">
                    <div class="bud-modal__head">
                        <h2>New budget</h2>
                        <button type="button" class="bud-modal__close" data-bud-modal-close aria-label="Close">&times;</button>
                    </div>
                    <div class="bud-modal__body">
                        @include('budget::partials.create-form')
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('bud-modal');
    var openBtn = document.getElementById('bud-modal-open');
    if (!modal) return;
    function setOpen(open) {
        modal.classList.toggle('bud-modal--open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('bud-modal-open-html', open);
    }
    openBtn?.addEventListener('click', function () { setOpen(true); });
    modal.querySelectorAll('[data-bud-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });
})();
</script>
@endsection
