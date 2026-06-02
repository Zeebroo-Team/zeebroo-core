@extends('theme::layouts.app', ['title' => 'Sales & POS', 'heading' => 'Sales & point of sale'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pos-hub-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:8px;}
.pos-hub-tile{border:1px solid var(--border);border-radius:12px;padding:16px;background:color-mix(in srgb,var(--card) 96%,transparent);text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:8px;transition:border-color .2s ease,transform .15s ease;}
.pos-hub-tile:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pos-hub-tile__icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);font-size:18px;}
.pos-hub-tile__title{margin:0;font-size:15px;font-weight:800;color:var(--text);}
.pos-hub-tile__desc{margin:0;font-size:12px;line-height:1.45;color:var(--muted);}
.pos-hub-stats{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin:14px 0;}
.pos-hub-stat{border:1px solid var(--border);border-radius:10px;padding:12px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-hub-stat__label{margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-hub-stat__value{margin:0;font-size:20px;font-weight:800;color:var(--text);}
.pos-hub-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
.pos-hub-toolbar p{margin:0;font-size:13px;line-height:1.45;color:var(--muted);}

/* ── Download button ─────────────────────────────────────────────── */
.pos-dl-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;font-weight:700;border-radius:10px;border:1px solid color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--text);cursor:pointer;transition:background .15s,box-shadow .15s;white-space:nowrap;}
.pos-dl-btn:hover{background:color-mix(in srgb,var(--primary) 22%,transparent);box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 20%,transparent);}

/* ── Download modal ──────────────────────────────────────────────── */
.pdl-overlay{position:fixed;inset:0;z-index:9200;display:flex;align-items:center;justify-content:center;padding:16px;visibility:hidden;opacity:0;pointer-events:none;transition:opacity .22s ease,visibility .22s;}
.pdl-overlay.is-open{visibility:visible;opacity:1;pointer-events:auto;}
.pdl-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.62);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);}
.pdl-dialog{position:relative;z-index:1;width:min(100%,680px);border-radius:20px;overflow:hidden;border:1px solid var(--border);background:var(--card);box-shadow:0 32px 80px rgba(0,0,0,.38),0 0 0 1px rgba(255,255,255,.05);transform:translateY(12px) scale(.97);transition:transform .3s cubic-bezier(.34,1.15,.64,1);}
.pdl-overlay.is-open .pdl-dialog{transform:translateY(0) scale(1);}
html.pdl-open,html.pdl-open body{overflow:hidden;}

/* Hero header */
.pdl-hero{position:relative;padding:32px 32px 28px;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,var(--card)) 0%,color-mix(in srgb,var(--primary) 8%,var(--card)) 100%);border-bottom:1px solid var(--border);overflow:hidden;}
.pdl-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
.pdl-hero__close{position:absolute;top:16px;right:16px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid color-mix(in srgb,var(--border) 60%,transparent);background:color-mix(in srgb,var(--card) 30%,transparent);color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;backdrop-filter:blur(4px);}
.pdl-hero__close:hover{background:color-mix(in srgb,var(--card) 60%,transparent);color:var(--text);}
.pdl-hero__badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:color-mix(in srgb,var(--primary) 20%,transparent);border:1px solid color-mix(in srgb,var(--primary) 35%,transparent);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);margin-bottom:12px;}
.pdl-hero__title{margin:0 0 6px;font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.03em;line-height:1.15;}
.pdl-hero__sub{margin:0;font-size:13px;color:var(--muted);line-height:1.5;max-width:420px;}
.pdl-hero__icon{position:absolute;right:32px;bottom:-10px;font-size:80px;color:color-mix(in srgb,var(--primary) 12%,transparent);line-height:1;}

/* Platform grid */
.pdl-body{padding:24px 28px 28px;}
.pdl-section-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 14px;display:flex;align-items:center;gap:6px;}
.pdl-section-label::after{content:'';flex:1;height:1px;background:var(--border);}
.pdl-platforms{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:22px;}
.pdl-platform{display:flex;flex-direction:column;align-items:center;gap:10px;padding:18px 12px;border-radius:14px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);cursor:pointer;text-decoration:none;color:inherit;transition:all .18s ease;position:relative;}
.pdl-platform:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,var(--card));transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1);}
.pdl-platform__icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;}
.pdl-platform__icon--win{background:linear-gradient(135deg,#0078d4,#00b7ff);color:#fff;}
.pdl-platform__icon--mac{background:linear-gradient(135deg,#555,#888);color:#fff;}
.pdl-platform__icon--linux{background:linear-gradient(135deg,#e95420,#ff8a00);color:#fff;}
.pdl-platform__name{font-size:13px;font-weight:800;color:var(--text);}
.pdl-platform__meta{font-size:10px;color:var(--muted);text-align:center;line-height:1.4;}
.pdl-platform__dl{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:7px;font-size:11px;font-weight:700;background:color-mix(in srgb,var(--primary) 14%,transparent);border:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));color:var(--text);transition:background .15s;}
.pdl-platform:hover .pdl-platform__dl{background:color-mix(in srgb,var(--primary) 24%,transparent);}

/* Mobile row */
.pdl-mobile{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.pdl-mobile-card{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:12px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 96%,var(--border) 4%);text-decoration:none;color:inherit;transition:all .15s;}
.pdl-mobile-card:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pdl-mobile-card__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.pdl-mobile-card__icon--ios{background:linear-gradient(135deg,#555,#999);color:#fff;}
.pdl-mobile-card__icon--android{background:linear-gradient(135deg,#3ddc84,#00a86b);color:#fff;}
.pdl-mobile-card__name{font-size:13px;font-weight:700;color:var(--text);}
.pdl-mobile-card__sub{font-size:10px;color:var(--muted);}

/* Version note */
.pdl-footer{margin-top:20px;padding-top:16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.pdl-version{font-size:11px;color:var(--muted);}
.pdl-version strong{color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div class="pos-hub-toolbar">
        <p>Retail sales and online point of sale for <strong style="color:var(--text);">{{ $business->name }}</strong>.
        Stock is sold using FIFO batch pricing from goods receipts.</p>
        <button type="button" class="pos-dl-btn" id="pos-dl-open">
            <i class="fa fa-download" aria-hidden="true"></i> Download Desktop POS
        </button>
    </div>

    <div class="pos-hub-stats" aria-label="Today's sales">
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Today's sales</p>
            <p class="pos-hub-stat__value">{{ (int) ($today['count'] ?? 0) }}</p>
        </div>
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Today's revenue @if(filled($currency))({{ $currency }})@endif</p>
            <p class="pos-hub-stat__value">{{ number_format((float) ($today['total'] ?? 0), 2) }}</p>
        </div>
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Online POS today</p>
            <p class="pos-hub-stat__value">{{ (int) ($today['online_count'] ?? 0) }}</p>
        </div>
    </div>

    @if(!$hasProducts)
        <div class="pcat-banner pcat-banner--err" role="alert" style="margin-bottom:14px;">
            Add active <a href="{{ route('product.index') }}" class="pcat-link">products</a> and stock before opening the register.
        </div>
    @endif

    <div class="pos-hub-grid">
        <a href="{{ route('pos.online') }}" class="pos-hub-tile" @if(!$hasProducts) style="opacity:.65;pointer-events:none;" @endif>
            <span class="pos-hub-tile__icon"><i class="fa fa-store" aria-hidden="true"></i></span>
            <h3 class="pos-hub-tile__title">Online retail POS</h3>
            <p class="pos-hub-tile__desc">Full-screen terminal with categories, SKU scan, and quick checkout for retail & online sales.</p>
        </a>
        <a href="{{ route('pos.sales.index') }}" class="pos-hub-tile">
            <span class="pos-hub-tile__icon"><i class="fa fa-receipt" aria-hidden="true"></i></span>
            <h3 class="pos-hub-tile__title">Sales history</h3>
            <p class="pos-hub-tile__desc">
                @if($hasSales)
                    View receipts, void sales, and track completed transactions.
                @else
                    Completed sales will appear here after your first checkout.
                @endif
            </p>
        </a>
    </div>
</div>

{{-- ── Download Desktop POS modal ───────────────────────────────── --}}
<div id="pos-dl-modal" class="pdl-overlay" role="dialog" aria-modal="true" aria-labelledby="pdl-title" aria-hidden="true">
    <div class="pdl-backdrop" id="pdl-backdrop"></div>
    <div class="pdl-dialog">

        {{-- Hero --}}
        <div class="pdl-hero">
            <button type="button" class="pdl-hero__close" id="pdl-close" aria-label="Close">
                <i class="fa fa-times" aria-hidden="true"></i>
            </button>
            <div class="pdl-hero__badge"><i class="fa fa-desktop" aria-hidden="true"></i> Desktop App</div>
            <h2 id="pdl-title" class="pdl-hero__title">Download Platform POS</h2>
            <p class="pdl-hero__sub">Run your point of sale as a native desktop app — offline-ready, faster performance, and dedicated hardware support.</p>
            <div class="pdl-hero__icon" aria-hidden="true"><i class="fa fa-cash-register"></i></div>
        </div>

        {{-- Body --}}
        <div class="pdl-body">

            {{-- Desktop platforms --}}
            <p class="pdl-section-label"><i class="fa fa-desktop"></i> Desktop</p>
            <div class="pdl-platforms">

                {{-- Windows --}}
                <a href="https://github.com/Zeebroo-Team/zeebroo-pos-desktop/releases/tag/v1.0.0#:~:text=ZeebrooPosDesktop%2D1.0.0%2Dwindows%2Dx64.zip" class="pdl-platform" aria-label="Download for Windows" target="_blank" rel="noopener">
                    <div class="pdl-platform__icon pdl-platform__icon--win">
                        <i class="fa-brands fa-windows" aria-hidden="true"></i>
                    </div>
                    <span class="pdl-platform__name">Windows</span>
                    <span class="pdl-platform__meta">Windows 10 / 11<br>64-bit installer</span>
                    <span class="pdl-platform__dl">
                        <i class="fa fa-download" aria-hidden="true"></i> Download .exe
                    </span>
                </a>

                {{-- macOS --}}
                <a href="https://github.com/Zeebroo-Team/zeebroo-pos-desktop/releases/tag/v1.0.0#:~:text=ZeebrooPosDesktop%2D1.0.0%2Dmacos.zip" class="pdl-platform" aria-label="Download for macOS" target="_blank" rel="noopener">
                    <div class="pdl-platform__icon pdl-platform__icon--mac">
                        <i class="fa-brands fa-apple" aria-hidden="true"></i>
                    </div>
                    <span class="pdl-platform__name">macOS</span>
                    <span class="pdl-platform__meta">macOS 12+<br>Apple Silicon &amp; Intel</span>
                    <span class="pdl-platform__dl">
                        <i class="fa fa-download" aria-hidden="true"></i> Download .dmg
                    </span>
                </a>

                {{-- Linux --}}
                <a href="https://github.com/Zeebroo-Team/zeebroo-pos-desktop/releases/download/v1.0.0/ZeebrooPosDesktop-1.0.0-linux-x86_64.tar.gz" class="pdl-platform" aria-label="Download for Linux" target="_blank" rel="noopener">
                    <div class="pdl-platform__icon pdl-platform__icon--linux">
                        <i class="fa-brands fa-linux" aria-hidden="true"></i>
                    </div>
                    <span class="pdl-platform__name">Linux</span>
                    <span class="pdl-platform__meta">Ubuntu / Debian<br>AppImage &amp; .deb</span>
                    <span class="pdl-platform__dl">
                        <i class="fa fa-download" aria-hidden="true"></i> Download .deb
                    </span>
                </a>

            </div>

            {{-- Mobile platforms --}}
            <p class="pdl-section-label"><i class="fa fa-mobile-screen"></i> Mobile</p>
            <div class="pdl-mobile">

                <a href="#" class="pdl-mobile-card" aria-label="Download for iOS">
                    <div class="pdl-mobile-card__icon pdl-mobile-card__icon--ios">
                        <i class="fa-brands fa-apple" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="pdl-mobile-card__name">iOS / iPadOS</div>
                        <div class="pdl-mobile-card__sub">Requires iOS 15 or later</div>
                    </div>
                </a>

                <a href="#" class="pdl-mobile-card" aria-label="Download for Android">
                    <div class="pdl-mobile-card__icon pdl-mobile-card__icon--android">
                        <i class="fa-brands fa-android" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="pdl-mobile-card__name">Android</div>
                        <div class="pdl-mobile-card__sub">Requires Android 8.0+</div>
                    </div>
                </a>

            </div>

            <div class="pdl-footer">
                <p class="pdl-version">Current version: <strong>v1.0.0</strong> &nbsp;·&nbsp; Released June 2025</p>
                <p class="pdl-version">All platforms sync with your <strong>{{ $business->name }}</strong> account automatically.</p>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    var modal    = document.getElementById('pos-dl-modal');
    var openBtn  = document.getElementById('pos-dl-open');
    var closeBtn = document.getElementById('pdl-close');
    var backdrop = document.getElementById('pdl-backdrop');
    if (!modal) return;

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pdl-open', open);
        if (open && closeBtn) closeBtn.focus();
    }

    openBtn?.addEventListener('click', function () { setOpen(true); });
    closeBtn?.addEventListener('click', function () { setOpen(false); });
    backdrop?.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) setOpen(false);
    });
})();
</script>
@endsection
