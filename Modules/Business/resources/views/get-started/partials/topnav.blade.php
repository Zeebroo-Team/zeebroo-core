{{--
    Floating pill navbar shared by the Get Started, Community and Support pages.
    These pages render with minimalAppShell + hideNavbar (no sidebar/topbar), so
    this partial also supplies the full-height flex chain they need.

    Expects: $gsActive — one of 'platforms', 'community', 'support', 'billing'.
--}}
@php
    $gsUser = auth()->user();
    $gsName = trim((string) ($gsUser->name ?? '')) ?: __('User');
    $gsInitial = mb_strtoupper(mb_substr($gsName, 0, 1));
    $gsActive = $gsActive ?? 'platforms';
    $gsLinks = [
        ['key' => 'platforms', 'label' => 'Platforms', 'icon' => 'fa-layer-group', 'href' => route('business.platform-choice'), 'external' => false],
        ['key' => 'docs', 'label' => 'Docs', 'icon' => 'fa-book-open', 'href' => 'https://zeebroo.com/tutorial', 'external' => true],
        ['key' => 'community', 'label' => 'Community', 'icon' => 'fa-users', 'href' => route('business.get-started.community'), 'external' => false],
        ['key' => 'videos', 'label' => 'Video Tutorials', 'icon' => 'fa-circle-play', 'href' => 'https://www.youtube.com/@zeebroo-erp', 'external' => true],
        ['key' => 'support', 'label' => 'Contact Support', 'icon' => 'fa-headset', 'href' => route('business.get-started.support'), 'external' => false],
    ];
@endphp
<script>document.documentElement.classList.add('business-wizard-active', 'gs-theme');</script>
<style>
    /* Fixed white / black / touch-of-gold palette for the Get Started pages,
       independent of the user's selected app theme. */
    html.gs-theme{
        --bg:#ffffff;--card:#ffffff;--text:#0a0a0a;--muted:#6b6b6b;--border:#ebebeb;
        --primary:#0a0a0a;--btn-bg:#0a0a0a;--gs-gold:#c9971c;--gs-soft:#f6f6f5;
    }
    html.gs-theme body,html.gs-theme .layout,html.gs-theme .content{background:#fff!important;color:var(--text);}
    /* One background image behind the navbar and page body on every Get Started page. */
    html.gs-theme .content-inner{
        color:var(--text);
        background-color:#fff!important;
        background-image:
            linear-gradient(180deg,rgba(255,255,255,.55) 0%,rgba(255,255,255,.35) 45%,rgba(255,255,255,.7) 100%),
            url('{{ asset('images/5435623667567.webp') }}')!important;
        background-size:cover!important;
        background-position:center!important;
        background-repeat:no-repeat!important;
    }

    html.business-wizard-active,html.business-wizard-active body{overflow:hidden;height:100%;}
    html.business-wizard-active .layout{height:100vh;max-height:100vh;overflow:hidden;}
    html.business-wizard-active .content{display:flex;flex-direction:column;min-height:0;height:100vh;max-height:100vh;overflow:hidden;margin-left:0!important;border-left:0!important;width:100%!important;}
    html.business-wizard-active .content-inner{flex:1;min-height:0;display:flex;flex-direction:column;padding:0!important;overflow:hidden;max-width:100%!important;}

    .gs-nav-wrap{
        position:relative;z-index:40;flex-shrink:0;display:flex;justify-content:center;
        padding:16px clamp(12px,3vw,28px) 6px;background:transparent;
    }
    .gs-nav{
        --gs-nav-bg:transparent;--gs-nav-fg:#3f3f46;--gs-nav-muted:#71717a;
        position:relative;display:flex;align-items:center;gap:6px;width:100%;max-width:1080px;
        padding:6px;border-radius:999px;background:var(--gs-nav-bg);
        border:1px solid rgba(0,0,0,.08);
        box-shadow:inset 0 1px 0 rgba(255,255,255,.6);
    }
    .gs-nav-logo{
        display:flex;align-items:center;flex-shrink:0;height:40px;padding:0 14px;border-radius:999px;
        background:transparent;text-decoration:none;
    }
    .gs-nav-logo img{height:26px;width:auto;object-fit:contain;display:block;}
    .gs-nav-links{flex:1;display:flex;align-items:center;justify-content:center;gap:2px;min-width:0;}
    .gs-nav-link{
        display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:999px;white-space:nowrap;
        color:var(--gs-nav-fg);font-size:13px;font-weight:600;text-decoration:none;
        transition:background .16s,color .16s;
    }
    .gs-nav-link:hover{background:rgba(0,0,0,.04);color:var(--text);}
    .gs-nav-link.is-active,.gs-nav-link.is-active:hover{background:transparent;color:#8a6510;position:relative;}
    .gs-nav-link.is-active::after{
        content:'';position:absolute;left:50%;bottom:3px;width:4px;height:4px;margin-left:-2px;border-radius:999px;background:var(--gs-gold);
    }
    .gs-nav-link .gs-ext{font-size:9px;opacity:.5;}
    .gs-nav-link > .fa:first-child{display:none;}

    .gs-user{position:relative;flex-shrink:0;}
    .gs-user-btn{
        display:inline-flex;align-items:center;gap:9px;height:40px;padding:0 12px 0 4px;border-radius:999px;
        background:transparent;color:var(--text);border:1px solid rgba(0,0,0,.07);cursor:pointer;font-family:inherit;
        font-size:13px;font-weight:700;max-width:230px;
    }
    .gs-user-btn:focus-visible{outline:2px solid var(--gs-gold);outline-offset:2px;}
    .gs-avatar{
        width:32px;height:32px;border-radius:999px;flex-shrink:0;display:grid;place-items:center;
        background:var(--gs-gold);color:#fff;font-size:14px;font-weight:800;
    }
    .gs-user-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .gs-user-caret{font-size:10px;opacity:.55;transition:transform .18s;}
    .gs-user.is-open .gs-user-caret{transform:rotate(180deg);}
    .gs-menu{
        position:absolute;right:0;top:calc(100% + 10px);min-width:220px;padding:6px;border-radius:14px;
        background:var(--card);border:1px solid var(--border);
        box-shadow:0 18px 40px -16px rgba(0,0,0,.35);
        opacity:0;visibility:hidden;transform:translateY(-4px);transition:opacity .16s,transform .16s,visibility .16s;
    }
    .gs-user.is-open .gs-menu,.gs-mobile.is-open .gs-menu{opacity:1;visibility:visible;transform:none;}
    .gs-menu-head{padding:10px 12px 10px;border-bottom:1px solid var(--border);margin-bottom:4px;}
    .gs-menu-name{font-size:13px;font-weight:800;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .gs-menu-email{font-size:11.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;}
    .gs-menu form{margin:0;}
    .gs-menu-item{
        display:flex;align-items:center;gap:10px;width:100%;padding:9px 12px;border-radius:9px;box-sizing:border-box;
        background:transparent;border:none;cursor:pointer;font-family:inherit;text-align:left;
        color:var(--text);font-size:13px;font-weight:600;text-decoration:none;
    }
    .gs-menu-item i{width:16px;text-align:center;color:var(--muted);}
    .gs-menu-item:hover{background:var(--gs-soft);}
    .gs-menu-item.is-active i{color:var(--gs-gold);}
    .gs-menu-item--danger:hover{background:color-mix(in srgb,#ef4444 9%,transparent);color:#ef4444;}
    .gs-menu-item--danger:hover i{color:#ef4444;}

    .gs-mobile{display:none;position:relative;flex-shrink:0;}
    .gs-mobile-btn{
        width:40px;height:40px;border-radius:999px;border:none;cursor:pointer;
        background:var(--gs-soft);color:var(--text);font-size:15px;
    }
    .gs-mobile .gs-menu{right:auto;left:50%;transform:translate(-50%,-4px);}
    .gs-mobile.is-open .gs-menu{transform:translate(-50%,0);}

    @media(max-width:960px){
        .gs-nav-link{padding:9px 10px;font-size:12.5px;}
        .gs-nav-link .gs-ext{display:none;}
    }
    @media(max-width:820px){
        .gs-nav-links{display:none;}
        .gs-mobile{display:block;margin-left:auto;}
        .gs-user-name,.gs-user-caret{display:none;}
        .gs-user-btn{padding:0 4px;}
    }

    .gs-shell{
        position:relative;flex:1;min-height:0;width:100%;overflow:auto;
        padding:clamp(20px,4vh,44px) clamp(16px,4vw,32px);box-sizing:border-box;background:transparent;
    }
</style>

<div class="gs-nav-wrap">
    <nav class="gs-nav" aria-label="Get started">
        <a href="{{ route('business.platform-choice') }}" class="gs-nav-logo" aria-label="Zeebroo">
            <img src="{{ asset('logo.png') }}" alt="Zeebroo">
        </a>

        <div class="gs-nav-links">
            @foreach($gsLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="gs-nav-link{{ $gsActive === $link['key'] ? ' is-active' : '' }}"
                   @if($gsActive === $link['key']) aria-current="page" @endif
                   @if($link['external']) target="_blank" rel="noopener" @endif>
                    <i class="fa {{ $link['icon'] }}" aria-hidden="true"></i>
                    {{ $link['label'] }}
                    @if($link['external'])<i class="fa fa-arrow-up-right-from-square gs-ext" aria-hidden="true"></i>@endif
                </a>
            @endforeach
        </div>

        <div class="gs-mobile" data-gs-dropdown>
            <button type="button" class="gs-mobile-btn" aria-haspopup="true" aria-expanded="false" aria-label="Menu" data-gs-toggle>
                <i class="fa fa-bars" aria-hidden="true"></i>
            </button>
            <div class="gs-menu" role="menu">
                @foreach($gsLinks as $link)
                    <a href="{{ $link['href'] }}" class="gs-menu-item" role="menuitem"
                       @if($link['external']) target="_blank" rel="noopener" @endif>
                        <i class="fa {{ $link['icon'] }}" aria-hidden="true"></i> {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="gs-user" data-gs-dropdown>
            <button type="button" class="gs-user-btn" aria-haspopup="true" aria-expanded="false" data-gs-toggle>
                <span class="gs-avatar" aria-hidden="true">{{ $gsInitial }}</span>
                <span class="gs-user-name">{{ $gsName }}</span>
                <i class="fa fa-chevron-down gs-user-caret" aria-hidden="true"></i>
            </button>
            <div class="gs-menu" role="menu">
                <div class="gs-menu-head">
                    <div class="gs-menu-name">{{ $gsName }}</div>
                    @if($gsUser?->email)
                        <div class="gs-menu-email">{{ $gsUser->email }}</div>
                    @endif
                </div>
                <a href="{{ route('business.get-started.billing') }}" class="gs-menu-item{{ $gsActive === 'billing' ? ' is-active' : '' }}" role="menuitem">
                    <i class="fa fa-credit-card" aria-hidden="true"></i> Billing
                </a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="gs-menu-item gs-menu-item--danger" role="menuitem">
                        <i class="fa fa-right-from-bracket" aria-hidden="true"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>
</div>
<script>
(function () {
    var dropdowns = document.querySelectorAll('[data-gs-dropdown]');
    function closeAll(except) {
        dropdowns.forEach(function (dd) {
            if (dd === except) return;
            dd.classList.remove('is-open');
            var t = dd.querySelector('[data-gs-toggle]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    }
    dropdowns.forEach(function (dd) {
        var toggle = dd.querySelector('[data-gs-toggle]');
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !dd.classList.contains('is-open');
            closeAll(dd);
            dd.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-gs-dropdown]')) closeAll(null);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
    });
})();
</script>
