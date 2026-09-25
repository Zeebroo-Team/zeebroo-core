@extends('theme::layouts.app', [
    'title' => 'Get Started',
    'heading' => 'Get Started',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
<script>document.documentElement.classList.add('business-wizard-active');</script>
<style>
    /* This page renders with minimalAppShell + hideNavbar (no sidebar/topbar),
       so it needs its own full-height flex chain for vertical centering —
       the dashboard page normally supplies this via the same class toggle. */
    html.business-wizard-active,html.business-wizard-active body{overflow:hidden;height:100%;}
    html.business-wizard-active .layout{height:100vh;max-height:100vh;overflow:hidden;}
    html.business-wizard-active .content{display:flex;flex-direction:column;min-height:0;height:100vh;max-height:100vh;overflow:hidden;margin-left:0!important;border-left:0!important;width:100%!important;}
    html.business-wizard-active .content-inner{flex:1;min-height:0;display:flex;flex-direction:column;padding:0!important;overflow:hidden;max-width:100%!important;}
    .plat-topbar{
        position:relative;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-shrink:0;
        padding:14px clamp(16px,4vw,32px);border-bottom:1px solid var(--border);
        background:var(--bg);
    }
    .plat-topbar-center{
        display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--muted);
        position:absolute;left:50%;transform:translateX(-50%);
    }
    .plat-topbar-dot{opacity:.5;}
    @media(max-width:640px){
        .plat-topbar{flex-wrap:wrap;row-gap:8px;}
        .plat-topbar-center{position:static;transform:none;order:3;flex-basis:100%;justify-content:center;}
    }
    .plat-logout-form{margin:0;}
    .plat-logout-btn{
        display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;
        border:1.5px solid var(--border);background:transparent;color:var(--muted);
        font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;
        transition:border-color .16s,color .16s,background .16s;
    }
    .plat-logout-btn:hover{border-color:#ef4444;color:#ef4444;background:color-mix(in srgb,#ef4444 6%,transparent);}
    .plat-shell{
        position:relative;flex:1;min-height:0;width:100%;overflow:auto;
        display:flex;align-items:center;justify-content:center;
        padding:clamp(24px,5vh,56px) clamp(16px,4vw,32px);box-sizing:border-box;
        background:var(--bg);
    }
    .plat-shell::before{
        content:'';position:absolute;inset:0;pointer-events:none;
        background-image:radial-gradient(circle,color-mix(in srgb,var(--primary) 7%,transparent) 1px,transparent 1px);
        background-size:28px 28px;
    }
    .plat-card{position:relative;z-index:1;width:100%;max-width:1240px;text-align:center;margin:auto;}
    .plat-logo{display:block;margin:0 auto 18px;height:44px;width:auto;object-fit:contain;}
    .plat-title{margin:0 0 8px;font-size:clamp(24px,3.2vw,32px);font-weight:800;color:var(--text);letter-spacing:-.025em;}
    .plat-sub{margin:0 0 18px;font-size:15px;color:var(--muted);line-height:1.55;}
    .plat-plan-pill{
        display:inline-flex;align-items:center;gap:7px;margin:0 auto 34px;padding:6px 16px;border-radius:999px;
        background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);
        font-size:12.5px;font-weight:600;
    }
    .plat-plan-pill strong{font-weight:800;}
    .plat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;text-align:left;}
    @media(max-width:1100px){.plat-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:560px){.plat-grid{grid-template-columns:1fr;}}
    .plat-option{
        position:relative;display:flex;flex-direction:column;border-radius:16px;
        border:1.5px solid var(--border);background:var(--card);padding:22px 20px;
        transition:border-color .18s,box-shadow .18s,transform .18s;
    }
    .plat-option--reco{border-color:var(--primary);box-shadow:0 10px 30px -10px color-mix(in srgb,var(--primary) 35%,transparent);}
    .plat-option--disabled{opacity:.72;}
    .plat-reco-pill{
        position:absolute;top:-11px;left:20px;padding:3px 11px;border-radius:999px;
        background:var(--primary);color:var(--card);font-size:10.5px;font-weight:800;
        letter-spacing:.04em;text-transform:uppercase;box-shadow:0 3px 10px color-mix(in srgb,var(--primary) 45%,transparent);
    }
    .plat-soon-pill{
        display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;
        background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);
        font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
    }
    .plat-icon{
        width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:19px;margin-bottom:14px;
        background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);
    }
    .plat-option--disabled .plat-icon{background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);}
    .plat-opt-title{margin:0 0 6px;font-size:15.5px;font-weight:800;color:var(--text);letter-spacing:-.01em;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .plat-opt-desc{margin:0 0 16px;font-size:12.5px;color:var(--muted);line-height:1.55;flex:1;}
    .plat-btn{
        display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;
        padding:11px 16px;border-radius:11px;border:none;cursor:pointer;text-decoration:none;
        background:var(--btn-bg);color:var(--card);font-size:13.5px;font-weight:700;
        box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 30%,transparent);
        transition:opacity .2s,transform .15s;box-sizing:border-box;
    }
    .plat-btn:hover{opacity:.9;transform:translateY(-1px);}
    .plat-btn--outline{
        background:transparent;color:var(--text);border:1.5px solid var(--border);box-shadow:none;
    }
    .plat-btn--outline:hover{border-color:var(--primary);color:var(--primary);}
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
    .plat-os-link:hover{border-color:var(--primary);color:var(--primary);}
    .plat-os-link[aria-disabled="true"]{opacity:.45;pointer-events:none;}
    .plat-os-link.is-active{border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,transparent);}
    .plat-qr{
        width:96px;height:96px;margin:0 auto 14px;border-radius:10px;padding:8px;
        background:#fff;border:1px solid var(--border);
    }
    .plat-qr-wrap{display:flex;justify-content:center;}
    .plat-mobile-body{display:flex;flex-direction:column;}
    .plat-status{
        max-width:640px;margin:0 auto 22px;display:flex;align-items:center;justify-content:center;gap:8px;
        padding:11px 16px;border-radius:11px;font-size:13px;font-weight:600;
        border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));
        background:color-mix(in srgb,#16a34a 9%,var(--card));color:#15803d;
    }
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

    // Purely decorative "dummy" QR code — a plausible-looking placeholder for
    // the coming-soon mobile app card, not a real scannable code.
    $qrSize = 21; $qrCell = 8;
    $qrModules = [];
    for ($qy = 0; $qy < $qrSize; $qy++) {
        for ($qx = 0; $qx < $qrSize; $qx++) {
            $inFinder = ($qx < 7 && $qy < 7) || ($qx >= $qrSize - 7 && $qy < 7) || ($qx < 7 && $qy >= $qrSize - 7);
            if ($inFinder) continue;
            if ((($qx * 13 + $qy * 7 + $qx * $qy) % 5) < 2) {
                $qrModules[] = [$qx, $qy];
            }
        }
    }
    $qrFinderAt = function (int $fx, int $fy) use ($qrCell) {
        $ox = $fx * $qrCell; $oy = $fy * $qrCell;
        return '<rect x="'.$ox.'" y="'.$oy.'" width="'.($qrCell*7).'" height="'.($qrCell*7).'" fill="#000"/>'
             . '<rect x="'.($ox+$qrCell).'" y="'.($oy+$qrCell).'" width="'.($qrCell*5).'" height="'.($qrCell*5).'" fill="#fff"/>'
             . '<rect x="'.($ox+$qrCell*2).'" y="'.($oy+$qrCell*2).'" width="'.($qrCell*3).'" height="'.($qrCell*3).'" fill="#000"/>';
    };
    $platPlanLabel = $currentPackage
        ? $currentPackage->name . ($currentPackage->is_free ? ' (Free Trial)' : ' plan')
        : 'Free Trial';
@endphp
<div class="plat-topbar">
    <div>
        <div class="navtitle">Get Started</div>
        <div class="navmeta">{{ __('Welcome, :name', ['name' => auth()->user()->name ?? __('User')]) }}</div>
    </div>
    <div class="plat-topbar-center">
        <span id="platTopbarDate">{{ now()->format('d M Y') }}</span>
        <span class="plat-topbar-dot">•</span>
        <span id="platTopbarTime">{{ now()->format('h:i A') }}</span>
    </div>
    <form method="post" action="{{ route('logout') }}" class="plat-logout-form">
        @csrf
        <button type="submit" class="plat-logout-btn">
            <i class="fa fa-right-from-bracket" aria-hidden="true"></i> Logout
        </button>
    </form>
</div>
<script>
(function () {
    var timeEl = document.getElementById('platTopbarTime');
    if (!timeEl) return;
    function tick() {
        timeEl.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    tick();
    setInterval(tick, 1000 * 30);
})();
</script>
<div class="plat-shell">
    <div class="plat-card">
        @if(session('status'))
            <div class="plat-status"><i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('status') }}</div>
        @endif
        <img src="{{ asset('logo.png') }}" alt="Zeebroo" class="plat-logo">
        <h1 class="plat-title">Welcome to Zeebroo!</h1>
        <p class="plat-sub">Your workspace is ready — pick how you'd like to use Zeebroo. You can always switch later.</p>
        <div class="plat-plan-pill"><i class="fa fa-crown" aria-hidden="true"></i> You're on the <strong>{{ $platPlanLabel }}</strong></div>

        <div class="plat-grid">
            {{-- Desktop app — recommended --}}
            <div class="plat-option plat-option--reco">
                <span class="plat-reco-pill"><i class="fa fa-star" aria-hidden="true"></i> Recommended</span>
                <div class="plat-icon"><i class="fa fa-desktop" aria-hidden="true"></i></div>
                <h3 class="plat-opt-title">Download Zeebroo Desktop App</h3>
                <p class="plat-opt-desc">Faster performance, offline access and native OS integration — the best experience for daily use.</p>
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
                <div class="plat-icon"><i class="fa fa-globe" aria-hidden="true"></i></div>
                <h3 class="plat-opt-title">Open Zeebroo on the Web</h3>
                <p class="plat-opt-desc">Jump straight into your workspace in the browser — nothing to install, works anywhere.</p>
                <a href="{{ route('dashboard') }}" class="plat-btn plat-btn--outline">
                    <i class="fa fa-arrow-right" aria-hidden="true"></i> Continue to Dashboard
                </a>
            </div>

            {{-- Mobile app — coming soon --}}
            <div class="plat-option plat-option--disabled">
                <span class="plat-soon-pill" style="position:absolute;top:-11px;left:20px;"><i class="fa fa-clock" aria-hidden="true"></i> Coming Soon</span>
                <div class="plat-mobile-body">
                    <div class="plat-icon"><i class="fa fa-mobile-screen" aria-hidden="true"></i></div>
                    <h3 class="plat-opt-title">Download Zeebroo Mobile App</h3>
                    <p class="plat-opt-desc">Manage your business on the go, for iOS and Android.</p>
                    <div class="plat-qr-wrap">
                        <svg class="plat-qr" viewBox="0 0 {{ $qrSize * $qrCell }} {{ $qrSize * $qrCell }}" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect width="100%" height="100%" fill="#fff"/>
                            {!! $qrFinderAt(0, 0) !!}
                            {!! $qrFinderAt($qrSize - 7, 0) !!}
                            {!! $qrFinderAt(0, $qrSize - 7) !!}
                            @foreach($qrModules as [$mx, $my])
                                <rect x="{{ $mx * $qrCell }}" y="{{ $my * $qrCell }}" width="{{ $qrCell }}" height="{{ $qrCell }}" fill="#000"/>
                            @endforeach
                        </svg>
                    </div>
                    <span class="plat-btn plat-btn--disabled"><i class="fa fa-qrcode" aria-hidden="true"></i> Scan when available</span>
                </div>
            </div>

            {{-- Zeebroo Lite — coming soon --}}
            <div class="plat-option plat-option--disabled">
                <span class="plat-soon-pill" style="position:absolute;top:-11px;left:20px;"><i class="fa fa-clock" aria-hidden="true"></i> Coming Soon</span>
                <div class="plat-icon"><i class="fa fa-feather" aria-hidden="true"></i></div>
                <h3 class="plat-opt-title">Zeebroo POS Light</h3>
                <p class="plat-opt-desc">A lightweight version for low-end devices — is on its way.</p>
                <span class="plat-btn plat-btn--disabled"><i class="fa fa-clock" aria-hidden="true"></i> Notify me</span>
            </div>
        </div>
    </div>
</div>
@endsection
