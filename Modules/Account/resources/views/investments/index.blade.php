@extends('theme::layouts.app', ['title' => 'Investments', 'heading' => 'Investments'])

@section('content')
<div class="inv-page">
    <style>
        .inv-page{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .inv-hero{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:flex-start;padding:0 0 12px;margin-bottom:2px;border-bottom:1px solid var(--border);}
        .inv-hero__badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--primary);padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
        .inv-hero__actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
        .inv-btn--ghost{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;transition:border-color .18s ease,background .18s ease;}
        .inv-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .inv-btn--primary,.inv-btn--primary:visited{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));background:var(--btn-bg);color:#fff;cursor:pointer;text-decoration:none;}
        .inv-btn--primary:hover{background:var(--btn-hover);color:#111827;}
        .inv-btn--danger{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:8px;font-size:11px;font-weight:600;border:1px solid color-mix(in srgb,#ef4444 50%,var(--border));background:transparent;color:#f97373;cursor:pointer;}
        :is(html[data-theme="light"],html[data-theme="light_blue"]) .inv-btn--danger{color:#dc2626;}
        .inv-body{padding:12px 0 0;}
        .inv-alert{padding:8px 11px;border-radius:10px;font-size:12px;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;line-height:1.4;}
        .inv-alert--ok{border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);}
        .inv-alert--err{border:1px solid color-mix(in srgb,#f87171 45%,var(--border));background:color-mix(in srgb,#f87171 10%,transparent);}
        .inv-empty{text-align:center;padding:22px 16px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 26%,var(--border));border-radius:11px;background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .inv-empty__ico{width:44px;height:44px;margin:0 auto 10px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 7%,transparent));color:var(--primary);font-size:18px;}
        .inv-empty h2{margin:0;font-size:15px;font-weight:700;color:var(--text);}
        .inv-empty p{margin:7px auto 0;max-width:40ch;color:var(--muted);font-size:13px;line-height:1.45;}
        .inv-snapshot{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:8px 12px;padding:11px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 12%,transparent) 0%,color-mix(in srgb,var(--card) 94%,transparent) 48%);margin-bottom:14px;}
        .inv-snap-stat__lbl{font-size:9px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);font-weight:700;}
        .inv-snap-stat__val{font-size:15px;font-weight:800;letter-spacing:-.03em;line-height:1.15;color:var(--text);}
        .inv-cards{display:flex;flex-direction:column;gap:12px;}
        .inv-li{position:relative;border-radius:12px;border:1px solid var(--border);background:linear-gradient(165deg,color-mix(in srgb,var(--card) 98%,transparent) 0%,color-mix(in srgb,var(--card) 91%,#000));box-shadow:0 12px 36px -30px rgba(0,0,0,.42);overflow:hidden;}
        .inv-li--overdue{border-color:color-mix(in srgb,#fb923c 58%,var(--border));}
        .inv-li__ribbon{position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,var(--primary),color-mix(in srgb,var(--primary) 35%,#1e293b));}
        .inv-li--overdue .inv-li__ribbon{background:linear-gradient(180deg,#f97316,#ef4444);}
        .inv-li__layout{display:grid;grid-template-columns:1fr auto;gap:8px 12px;align-items:start;padding:12px 12px 12px 16px;}
        @media (max-width:720px){.inv-li__layout{grid-template-columns:1fr;}}
        a.inv-li__main-link{display:block;min-width:0;color:inherit;text-decoration:none;}
        .inv-li__header{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px;margin-bottom:2px;}
        .inv-li__icon{flex-shrink:0;width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:linear-gradient(142deg,var(--primary),color-mix(in srgb,var(--primary) 55%,#0f172a));color:#fff;font-size:14px;}
        .inv-li__title{margin:0;font-size:14px;font-weight:800;letter-spacing:-.025em;line-height:1.2;color:var(--text);}
        .inv-li__meta{margin:3px 0 0;font-size:11px;color:var(--muted);}
        .inv-li__pill{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid color-mix(in srgb,var(--primary) 38%,var(--border));background:color-mix(in srgb,var(--primary) 11%,transparent);color:color-mix(in srgb,var(--primary) 70%,var(--text));white-space:nowrap;}
        .inv-li__pill--overdue{border-color:color-mix(in srgb,#f97316 55%,var(--border));background:color-mix(in srgb,#f97316 16%,transparent);}
        .inv-li__metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin-top:9px;}
        @media (max-width:620px){.inv-li__metrics{grid-template-columns:1fr;}}
        .inv-li__tile{border-radius:9px;padding:8px 9px;border:1px solid color-mix(in srgb,var(--border) 90%,transparent);background:color-mix(in srgb,var(--card) 94%,transparent);}
        .inv-li__tile--hero{border-color:color-mix(in srgb,var(--primary) 42%,var(--border));background:linear-gradient(160deg,color-mix(in srgb,var(--primary) 14%,transparent),color-mix(in srgb,var(--card) 92%,transparent));}
        .inv-li__tile-lab{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px;display:block;}
        .inv-li__tile-val{font-size:13px;font-weight:800;line-height:1.2;letter-spacing:-.025em;color:var(--text);}
        .inv-li__aside{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}
        .inv-form-section{margin-bottom:11px;padding:11px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border) 80%,transparent);background:color-mix(in srgb,var(--card) 88%,transparent);}
        .inv-fields{display:grid;gap:10px;}
        @media (min-width:640px){.inv-fields--2{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 12px;}}
        .inv-field label{display:block;margin-bottom:4px;font-size:10px;font-weight:600;letter-spacing:.035em;text-transform:uppercase;color:var(--muted);}
        .inv-field input,.inv-field select,.inv-field textarea{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);}
        .inv-field textarea{min-height:64px;line-height:1.42;resize:vertical;font-family:inherit;}
        .inv-modal{position:fixed;inset:0;z-index:120;display:flex;justify-content:center;align-items:flex-start;padding:max(12px,2.5vh) 14px calc(14px + env(safe-area-inset-bottom));overflow:auto;box-sizing:border-box;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;}
        .inv-modal.inv-modal--open{opacity:1;visibility:visible;pointer-events:auto;}
        .inv-modal__backdrop{position:fixed;inset:0;z-index:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
        .inv-modal__panel{position:relative;z-index:1;width:100%;max-width:640px;margin:auto;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 20px 48px rgba(0,0,0,.32);max-height:min(94vh,calc(100dvh - 48px));display:flex;flex-direction:column;}
        .inv-modal__head{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
        .inv-modal__head h2{margin:0;font-size:15px;font-weight:800;}
        .inv-modal__close{width:32px;height:32px;display:grid;place-items:center;padding:0;border:1px solid var(--border);border-radius:9px;background:transparent;color:inherit;cursor:pointer;font-size:17px;line-height:1;}
        .inv-modal__body{padding:12px 14px 16px;overflow:auto;}
        html.inv-modal-open-html,html.inv-modal-open-html body{overflow:hidden;}
        .inv-inline-create{border-radius:14px;border:1px solid var(--border);background:var(--card);padding:14px 16px 18px;margin-top:4px;}
    </style>

    <header class="inv-hero">
        <div>
            <span class="inv-hero__badge"><i class="fa fa-chart-line"></i> Investments</span>
        </div>
        <div class="inv-hero__actions">
            @if($business && $investments->isNotEmpty())
                <button type="button" id="inv-modal-open" class="inv-btn--primary"><i class="fa fa-plus"></i>New investment</button>
            @endif
            <a class="inv-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i> Overview</a>
        </div>
    </header>

    <div class="inv-body">
        @if(!$business)
            <div class="inv-empty">
                <div class="inv-empty__ico"><i class="fa fa-briefcase"></i></div>
                <h2>No business selected</h2>
                <p>Create or select a business from the navbar to manage investments for that entity.</p>
            </div>
        @else
            @if(session('status'))
                <div class="inv-alert inv-alert--ok" role="status"><i class="fa fa-circle-check"></i><span>{{ session('status') }}</span></div>
            @endif
            @if($errors->any())
                <div class="inv-alert inv-alert--err" role="alert"><i class="fa fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
            @endif

            @if($investments->isNotEmpty())
                <div class="inv-snapshot" aria-label="Investment portfolio summary">
                    <div><span class="inv-snap-stat__lbl">Total plans</span><br><span class="inv-snap-stat__val">{{ $investments->count() }}</span></div>
                    <div><span class="inv-snap-stat__lbl">Active</span><br><span class="inv-snap-stat__val">{{ $totals['active_count'] }}</span></div>
                    <div><span class="inv-snap-stat__lbl">Overdue</span><br><span class="inv-snap-stat__val">{{ $totals['overdue_count'] }}</span></div>
                    <div><span class="inv-snap-stat__lbl">Total contributed</span><br><span class="inv-snap-stat__val">@if($currency){{ $currency }} @endif{{ number_format($totals['total_invested'], 2, '.', ',') }}</span></div>
                </div>

                <div class="inv-cards">
                    @foreach($investments as $inv)
                        @php($s = $summaries[$inv->id] ?? null)
                        <article @class(['inv-li', 'inv-li--overdue' => ($s['overdue_count'] ?? 0) > 0])>
                            <div class="inv-li__ribbon" aria-hidden="true"></div>
                            <div class="inv-li__layout">
                                <a class="inv-li__main-link" href="{{ route('account.investments.show', $inv) }}">
                                    <header class="inv-li__header">
                                        <span class="inv-li__icon"><i class="fa fa-chart-line"></i></span>
                                        <div>
                                            <h2 class="inv-li__title">{{ $inv->name }}</h2>
                                            <p class="inv-li__meta"><i class="fa fa-tag"></i> {{ $inv->typeDisplayLabel() }} @if($inv->provider) &middot; {{ $inv->provider }} @endif</p>
                                        </div>
                                        @if(($s['overdue_count'] ?? 0) > 0)
                                            <span class="inv-li__pill inv-li__pill--overdue"><i class="fa fa-circle-exclamation"></i> Overdue</span>
                                        @endif
                                        <span class="inv-li__pill">{{ ucfirst($inv->status) }}</span>
                                    </header>
                                    @if($s)
                                        <div class="inv-li__metrics">
                                            <div class="inv-li__tile inv-li__tile--hero">
                                                <span class="inv-li__tile-lab">Total invested</span>
                                                <span class="inv-li__tile-val">@if($currency){{ $currency }} @endif{{ number_format($s['total_invested'], 2, '.', ',') }}</span>
                                            </div>
                                            <div class="inv-li__tile">
                                                <span class="inv-li__tile-lab">Contribution / period</span>
                                                <span class="inv-li__tile-val">@if($currency){{ $currency }} @endif{{ number_format((float) $inv->contribution_amount, 2, '.', ',') }}</span>
                                            </div>
                                            <div class="inv-li__tile">
                                                <span class="inv-li__tile-lab">Progress to goal</span>
                                                <span class="inv-li__tile-val">{{ $s['progress_pct'] }}%</span>
                                            </div>
                                        </div>
                                    @endif
                                </a>
                                <aside class="inv-li__aside">
                                    <form method="post" action="{{ route('account.investments.destroy', $inv) }}" onsubmit="return confirm('Remove this investment record?');">
                                        @csrf @method('delete')
                                        <button type="submit" class="inv-btn--danger"><i class="fa fa-trash-can"></i> Remove</button>
                                    </form>
                                </aside>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div id="inv-modal" class="inv-modal {{ $errors->any() ? 'inv-modal--open' : '' }}" role="dialog" aria-modal="true" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
                    <div class="inv-modal__backdrop" data-inv-modal-close></div>
                    <div class="inv-modal__panel">
                        <div class="inv-modal__head">
                            <h2>New investment</h2>
                            <button type="button" class="inv-modal__close" data-inv-modal-close aria-label="Close">&times;</button>
                        </div>
                        <div class="inv-modal__body">
                            @include('account::investments.partials.create-form')
                        </div>
                    </div>
                </div>
            @else
                <section class="inv-inline-create">
                    <h2 style="margin:0 0 6px;font-size:16px;font-weight:800;">Add your first investment</h2>
                    <p style="margin:0 0 12px;font-size:12px;color:var(--muted);max-width:60ch;">Track a fixed deposit, savings plan, shares, or any other capital investment — with a contribution schedule and ledger history.</p>
                    @include('account::investments.partials.create-form')
                </section>
            @endif
        @endif
    </div>
</div>
@if($business)
<script>
(function () {
    var modal = document.getElementById('inv-modal');
    var openBtn = document.getElementById('inv-modal-open');
    function setOpen(open) {
        if (!modal) return;
        modal.classList.toggle('inv-modal--open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('inv-modal-open-html', open);
    }
    openBtn?.addEventListener('click', function () { setOpen(true); });
    modal?.querySelectorAll('[data-inv-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });

    document.querySelectorAll('.js-inv-type-form').forEach(function (form) {
        var typeSel = form.querySelector('[name="investment_type"]');
        var otherWrap = form.querySelector('.js-inv-type-other-wrap');
        var modeSel = form.querySelector('[name="payment_mode"]');
        var recurringWrap = form.querySelector('.js-inv-recurring-wrap');
        function syncType() {
            if (!typeSel || !otherWrap) return;
            otherWrap.style.display = typeSel.value === 'other' ? 'block' : 'none';
        }
        function syncMode() {
            if (!modeSel || !recurringWrap) return;
            recurringWrap.style.display = modeSel.value === 'recurring' ? 'grid' : 'none';
        }
        typeSel?.addEventListener('change', syncType);
        modeSel?.addEventListener('change', syncMode);
        syncType();
        syncMode();
    });
})();
</script>
@endif
@endsection
