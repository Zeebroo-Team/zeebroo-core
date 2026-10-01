@extends('theme::layouts.app', [
    'title' => 'Get Started',
    'heading' => 'Get Started',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('business::get-started.partials.topnav', ['gsActive' => 'platforms'])
<style>
    .plat-shell{
        position:relative;flex:1;min-height:0;width:100%;overflow:auto;
        display:flex;align-items:center;justify-content:center;
        padding:clamp(24px,5vh,56px) clamp(16px,4vw,32px);box-sizing:border-box;
        background:transparent;
    }
    .plat-card{position:relative;z-index:1;width:100%;max-width:1240px;text-align:center;margin:auto;}
    .plat-logo{display:block;margin:0 auto 14px;height:38px;width:auto;object-fit:contain;}
    .plat-greet{
        display:flex;align-items:center;justify-content:center;gap:8px;margin:0 0 8px;
        font-size:14.5px;font-weight:500;color:var(--muted);
        animation:platGreetIn .4s ease both;
    }
    .plat-greet-icon{color:var(--gs-gold);font-size:13px;}
    .plat-greet-name{font-weight:700;color:var(--text);}
    @keyframes platGreetIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
    @media (prefers-reduced-motion:reduce){.plat-greet{animation:none;}}
    .plat-title{margin:0 0 8px;font-size:clamp(22px,2.8vw,28px);font-weight:800;color:var(--text);letter-spacing:-.025em;}
    .plat-sub{margin:0 0 18px;font-size:14px;color:var(--muted);line-height:1.55;}
    .plat-plan-pill{
        display:inline-flex;align-items:center;gap:7px;margin:0 auto 34px;padding:6px 16px;border-radius:999px;
        background:color-mix(in srgb,var(--gs-gold) 8%,transparent);color:#8a6510;
        border:1px solid color-mix(in srgb,var(--gs-gold) 30%,transparent);
        -webkit-backdrop-filter:blur(10px) saturate(160%);backdrop-filter:blur(10px) saturate(160%);
        font-size:12px;font-weight:600;
    }
    .plat-plan-pill strong{font-weight:800;}
    .plat-plan-pill i{color:var(--gs-gold);}
    .plat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;text-align:left;}
    @media(max-width:1100px){.plat-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:560px){.plat-grid{grid-template-columns:1fr;}}
    .plat-option{
        position:relative;display:flex;flex-direction:column;border-radius:20px;
        border:1px solid rgba(0,0,0,.07);background:rgba(255,255,255,.45);padding:10px 10px 18px;
        -webkit-backdrop-filter:blur(12px) saturate(160%);backdrop-filter:blur(12px) saturate(160%);
        box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px -16px rgba(0,0,0,.18);
        transition:border-color .2s,box-shadow .2s,transform .2s;
    }
    .plat-option:not(.plat-option--disabled):hover{
        transform:translateY(-2px);border-color:rgba(0,0,0,.12);
        box-shadow:0 1px 2px rgba(0,0,0,.04),0 16px 32px -18px rgba(0,0,0,.25);
    }
    .plat-option--reco,.plat-option--reco:hover{border-color:color-mix(in srgb,var(--gs-gold) 55%,transparent) !important;}
    .plat-option--disabled{opacity:.7;}
    /* Text and actions sit inset from the image panel, like an app-store tile. */
    .plat-option > :not(.plat-media):not(.plat-reco-pill):not(.plat-soon-pill){margin-left:8px;margin-right:8px;}
    .plat-option > .plat-btn{width:calc(100% - 16px);}

    /* Product shot: inset rounded panel, image fills it. */
    .plat-media{
        position:relative;aspect-ratio:16/11;margin:0 0 16px;border-radius:14px;overflow:hidden;
        background:#f4f4f3;
    }
    .plat-media-img{
        display:block;width:100%;height:100%;object-fit:cover;object-position:center;
        transition:transform .4s ease;
    }
    .plat-option:not(.plat-option--disabled):hover .plat-media-img{transform:scale(1.03);}
    .plat-option--disabled .plat-media-img{filter:grayscale(.7);}
    @media (prefers-reduced-motion:reduce){.plat-media-img{transition:none;}}

    .plat-reco-pill,.plat-soon-pill{
        position:absolute;top:20px;left:20px;z-index:2;
        display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;
        font-size:10.5px;font-weight:700;letter-spacing:.03em;
        -webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);
    }
    .plat-reco-pill{background:var(--gs-gold);color:#fff;}
    .plat-soon-pill{background:rgba(255,255,255,.85);color:var(--muted);border:1px solid rgba(0,0,0,.06);}
    .plat-opt-title{
        margin:0 0 6px;font-size:15px;font-weight:700;color:var(--text);letter-spacing:-.01em;
        display:flex;align-items:center;gap:8px;
    }
    .plat-opt-title i{font-size:14px;color:var(--muted);width:16px;text-align:center;}
    .plat-option--reco .plat-opt-title i{color:var(--gs-gold);}
    .plat-opt-desc{margin:0 0 18px;font-size:13px;color:var(--muted);line-height:1.55;flex:1;}
    .plat-btn{
        display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;
        padding:11px 16px;border-radius:11px;border:none;cursor:pointer;text-decoration:none;
        background:var(--btn-bg);color:var(--card);font-size:13.5px;font-weight:700;
        box-shadow:0 6px 16px -6px rgba(0,0,0,.35);
        transition:opacity .2s,transform .15s;box-sizing:border-box;
    }
    .plat-btn:hover{opacity:.9;transform:translateY(-1px);}
    .plat-btn--outline{
        background:transparent;color:var(--text);border:1.5px solid var(--border);box-shadow:none;
    }
    .plat-btn--outline:hover{border-color:var(--text);color:var(--text);background:var(--gs-soft);}
    .plat-btn--disabled{
        background:color-mix(in srgb,var(--muted) 16%,transparent);color:var(--muted);
        box-shadow:none;cursor:not-allowed;pointer-events:none;
    }
    .plat-os-row{display:flex;gap:6px;margin-top:8px;}
    .plat-os-link{
        flex:1;display:flex;align-items:center;justify-content:center;gap:5px;
        padding:6px 4px;border-radius:8px;border:1px solid var(--border);
        color:var(--muted);font-size:11px;font-weight:700;text-decoration:none;
        transition:border-color .16s,color .16s;
    }
    .plat-os-link:hover{border-color:var(--text);color:var(--text);}
    .plat-os-link[aria-disabled="true"]{opacity:.45;pointer-events:none;}
    .plat-os-link.is-active{border-color:var(--gs-gold);color:#8a6510;background:color-mix(in srgb,var(--gs-gold) 8%,#fff);}
    .plat-status{
        max-width:640px;margin:0 auto 22px;display:flex;align-items:center;justify-content:center;gap:8px;
        padding:11px 16px;border-radius:11px;font-size:13px;font-weight:600;
        border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
        background:color-mix(in srgb,#16a34a 9%,var(--card));color:#15803d;
    }
    .plat-payment-alert{
        --pa:#e11d48;
        position:relative;z-index:1;max-width:720px;margin:0 auto 28px;text-align:left;
        display:flex;align-items:center;gap:12px;padding:10px 10px 10px 16px;border-radius:12px;
        background:rgba(255,255,255,.5);border:1px solid rgba(0,0,0,.07);
        -webkit-backdrop-filter:blur(10px) saturate(160%);backdrop-filter:blur(10px) saturate(160%);
        animation:platAlertIn .3s ease both;
    }
    .plat-payment-alert-dot{
        width:8px;height:8px;border-radius:999px;flex-shrink:0;background:var(--pa);
        box-shadow:0 0 0 4px color-mix(in srgb,var(--pa) 14%,transparent);
    }
    .plat-payment-alert-body{flex:1;min-width:0;font-size:13px;line-height:1.45;color:var(--muted);}
    .plat-payment-alert-title{color:var(--text);font-weight:700;}
    .plat-payment-alert-form{margin:0;flex-shrink:0;}
    .plat-payment-alert-btn{
        display:inline-flex;align-items:center;gap:6px;white-space:nowrap;
        padding:8px 14px;font-size:12.5px;font-weight:600;border-radius:9px;
        background:var(--text);color:var(--card);border:none;cursor:pointer;font-family:inherit;
        transition:opacity .2s;
    }
    .plat-payment-alert-btn:hover{opacity:.85;}
    .plat-payment-alert-amount{opacity:.7;font-weight:500;}
    @media(max-width:640px){
        .plat-payment-alert{flex-wrap:wrap;}
        .plat-payment-alert-form{flex-basis:100%;}
        .plat-payment-alert-btn{width:100%;justify-content:center;}
    }
    @keyframes platAlertIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
</style>
@php
    $platOs = [
        'windows' => ['label' => 'Windows', 'icon' => 'fa-brands fa-windows', 'url' => $latestDesktopRelease?->windows_url],
        'macos'   => ['label' => 'macOS',   'icon' => 'fa-brands fa-apple',   'url' => $latestDesktopRelease?->macos_url],
        'linux'   => ['label' => 'Linux',   'icon' => 'fa-brands fa-linux',   'url' => $latestDesktopRelease?->linux_url],
    ];
    $platAnyDesktopUrl = collect($platOs)->pluck('url')->filter()->first();
    $platDetectedOs = $platOs[$detectedOs ?? 'windows']['url'] ?? null ? ($detectedOs ?? 'windows') : null;
    $platMainOsKey = $platDetectedOs ?? collect($platOs)->filter(fn ($os) => $os['url'])->keys()->first();
    $platMainUrl = $platMainOsKey ? $platOs[$platMainOsKey]['url'] : null;

    $liteOs = [
        'windows' => ['label' => 'Windows', 'icon' => 'fa-brands fa-windows', 'url' => $latestLiteRelease?->windows_url],
        'macos'   => ['label' => 'macOS',   'icon' => 'fa-brands fa-apple',   'url' => $latestLiteRelease?->macos_url],
        'linux'   => ['label' => 'Linux',   'icon' => 'fa-brands fa-linux',   'url' => $latestLiteRelease?->linux_url],
    ];
    $liteMainOsKey = ($liteOs[$detectedOs ?? 'windows']['url'] ?? null)
        ? ($detectedOs ?? 'windows')
        : collect($liteOs)->filter(fn ($os) => $os['url'])->keys()->first();
    $liteMainUrl = $liteMainOsKey ? $liteOs[$liteMainOsKey]['url'] : null;
    $platPlanLabel = $currentPackage
        ? $currentPackage->name . ($currentPackage->is_free ? ' (Free Trial)' : ' plan')
        : 'Free Trial';
    // Product shots live in public/images/platform/ — a card whose file is missing shows an empty panel.
    $platImgFiles = [
        'desktop'  => 'Desktop App.webp',
        'web'      => 'Web.webp',
        'mobile'   => 'Mobile App.png',
        'pos-lite' => 'POS Lite.png',
    ];
    $platImg = fn (string $key) => isset($platImgFiles[$key]) && file_exists(public_path('images/platform/' . $platImgFiles[$key]))
        ? asset('images/platform/' . rawurlencode($platImgFiles[$key]))
        : null;
    $platFirstName =\Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::before(trim((string) auth()->user()?->name), ' '));
@endphp
<div class="plat-shell">
    <div class="plat-card">
        @if($errors->has('payment') || $pendingPayment)
            <div class="plat-payment-alert" role="alert">
                <span class="plat-payment-alert-dot" aria-hidden="true"></span>
                <div class="plat-payment-alert-body">
                    <span class="plat-payment-alert-title">{{ rtrim($errors->first('payment') ?: 'Your subscription payment needs attention', '.') }}.</span>
                    @if($pendingPayment)
                        Your setup is saved — complete payment to activate your subscription.
                    @endif
                </div>
                @if($pendingPayment)
                    <form method="post" action="{{ route('payment.checkout.resume', $pendingPayment) }}" class="plat-payment-alert-form">
                        @csrf
                        <button type="submit" class="plat-payment-alert-btn">
                            Complete payment
                            <span class="plat-payment-alert-amount">· {{ $pendingPayment->currencySymbol() }}{{ number_format((float) $pendingPayment->amount, 2) }}/mo</span>
                        </button>
                    </form>
                @endif
            </div>
        @endif
        @if(session('status'))
            <div class="plat-status"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('status') }}</div>
        @endif
        <img src="{{ asset('logo.png') }}" alt="Zeebroo" class="plat-logo">
        <p class="plat-greet">
            <i class="fa fa-sun plat-greet-icon" id="platGreetIcon" aria-hidden="true"></i>
            <span><span id="platGreetText">Hello</span>@if($platFirstName), <span class="plat-greet-name">{{ $platFirstName }}</span>@endif</span>
        </p>
        <h1 class="plat-title">Welcome to Zeebroo!</h1>
        <p class="plat-sub">Your workspace is ready — pick how you'd like to use Zeebroo. You can always switch later.</p>
        <div class="plat-plan-pill"><i class="fa fa-crown" aria-hidden="true"></i> You're on the <strong>{{ $platPlanLabel }}</strong></div>

        <div class="plat-grid">
            {{-- Desktop app — recommended --}}
            <div class="plat-option plat-option--reco">
                <span class="plat-reco-pill"><i class="fa fa-star" aria-hidden="true"></i> Recommended</span>
                <div class="plat-media">
                    @if($src = $platImg('desktop'))<img src="{{ $src }}" alt="" class="plat-media-img" loading="lazy">@endif
                </div>
                <h3 class="plat-opt-title"><i class="fa fa-desktop" aria-hidden="true"></i> Desktop App</h3>
                <p class="plat-opt-desc">Fastest performance with offline access — the best choice for daily use.</p>
                @if($platAnyDesktopUrl)
                    <a href="{{ $platMainUrl }}" class="plat-btn" id="platDesktopBtn" target="_blank" rel="noopener">
                        <i class="fa fa-download" aria-hidden="true"></i>
                        <span id="platDesktopBtnLabel">Download for {{ $platOs[$platMainOsKey]['label'] }}</span>
                    </a>
                    <div class="plat-os-row">
                        @foreach($platOs as $osKey => $os)
                            <a href="{{ $os['url'] ?? '#' }}" target="_blank" rel="noopener"
                               class="plat-os-link{{ $osKey === $platMainOsKey ? ' is-active' : '' }}" data-plat-os="{{ $osKey }}"
                               aria-disabled="{{ $os['url'] ? 'false' : 'true' }}">
                                <i class="{{ $os['icon'] }}" aria-hidden="true"></i> {{ $os['label'] }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <span class="plat-btn plat-btn--disabled"><i class="fa fa-clock" aria-hidden="true"></i> Coming soon</span>
                @endif
            </div>

            {{-- Web platform --}}
            <div class="plat-option">
                <div class="plat-media">
                    @if($src = $platImg('web'))<img src="{{ $src }}" alt="" class="plat-media-img" loading="lazy">@endif
                </div>
                <h3 class="plat-opt-title"><i class="fa fa-globe" aria-hidden="true"></i> Web App</h3>
                <p class="plat-opt-desc">Use Zeebroo in your browser — nothing to install, works anywhere.</p>
                <a href="{{ route('dashboard') }}" class="plat-btn plat-btn--outline">
                    <i class="fa fa-arrow-right" aria-hidden="true"></i> Continue to Dashboard
                </a>
            </div>

            {{-- Mobile app — store links not published yet, placeholders point to "#" --}}
            <div class="plat-option">
                <div class="plat-media">
                    @if($src = $platImg('mobile'))<img src="{{ $src }}" alt="" class="plat-media-img" loading="lazy">@endif
                </div>
                <h3 class="plat-opt-title"><i class="fa fa-mobile-screen" aria-hidden="true"></i> Mobile App</h3>
                <p class="plat-opt-desc">Manage your business on the go, for iOS and Android.</p>
                <a href="#" class="plat-btn plat-btn--outline">
                    <i class="fa fa-download" aria-hidden="true"></i> Download Mobile App
                </a>
                <div class="plat-os-row">
                    <a href="#" class="plat-os-link"><i class="fa-brands fa-android" aria-hidden="true"></i> Android</a>
                    <a href="#" class="plat-os-link"><i class="fa-brands fa-apple" aria-hidden="true"></i> iOS</a>
                </div>
            </div>

            {{-- Zeebroo POS Lite — latest stable release from the admin panel --}}
            @if($liteMainUrl)
                <div class="plat-option">
                    <div class="plat-media">
                        @if($src = $platImg('pos-lite'))<img src="{{ $src }}" alt="" class="plat-media-img" loading="lazy">@endif
                    </div>
                    <h3 class="plat-opt-title"><i class="fa fa-feather" aria-hidden="true"></i> POS Lite</h3>
                    <p class="plat-opt-desc">A lightweight POS for low-end devices — fast checkout, returns and day-end closing.</p>
                    <a href="{{ $liteMainUrl }}" class="plat-btn plat-btn--outline" target="_blank" rel="noopener">
                        <i class="fa fa-download" aria-hidden="true"></i>
                        Download for {{ $liteOs[$liteMainOsKey]['label'] }}
                    </a>
                    <div class="plat-os-row">
                        @foreach($liteOs as $osKey => $os)
                            <a href="{{ $os['url'] ?? '#' }}" target="_blank" rel="noopener"
                               class="plat-os-link{{ $osKey === $liteMainOsKey ? ' is-active' : '' }}"
                               aria-disabled="{{ $os['url'] ? 'false' : 'true' }}">
                                <i class="{{ $os['icon'] }}" aria-hidden="true"></i> {{ $os['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="plat-option plat-option--disabled">
                    <span class="plat-soon-pill"><i class="fa fa-clock" aria-hidden="true"></i> Coming Soon</span>
                    <div class="plat-media">
                        @if($src = $platImg('pos-lite'))<img src="{{ $src }}" alt="" class="plat-media-img" loading="lazy">@endif
                    </div>
                    <h3 class="plat-opt-title"><i class="fa fa-feather" aria-hidden="true"></i> POS Lite</h3>
                    <p class="plat-opt-desc">A lightweight POS for low-end devices — on its way.</p>
                    <span class="plat-btn plat-btn--disabled"><i class="fa fa-clock" aria-hidden="true"></i> Notify me</span>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
    (function () {
        // Greeting uses the viewer's local time, not the server's.
        var el = document.getElementById('platGreetText');
        if (!el) return;
        var h = new Date().getHours();
        var part = h < 12 ? ['Good morning', 'fa-sun'] : (h < 17 ? ['Good afternoon', 'fa-cloud-sun'] : ['Good evening', 'fa-moon']);
        el.textContent = part[0];
        var icon = document.getElementById('platGreetIcon');
        if (icon) icon.className = 'fa ' + part[1] + ' plat-greet-icon';
    })();
</script>
@endsection
