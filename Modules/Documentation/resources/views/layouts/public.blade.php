<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Documentation') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description', config('app.name') . ' Documentation')">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --text:          #0a0a0a;
            --muted:         #57534e;
            --border:        #d6d3d1;
            --bg:            #fafaf9;
            --card:          #ffffff;
            --primary:       #ca8a04;
            --primary-dark:  #171717;
            --primary-light: #fef9c3;
        }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Topbar ── */
        .pub-topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }

        /* Logo */
        .pub-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .pub-brand img {
            display: block;
            height: 36px;
            width: auto;
            object-fit: contain;
            object-position: left center;
        }
        .pub-brand__mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--primary-dark);
            color: #facc15;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -.03em;
            flex-shrink: 0;
        }
        .pub-brand__name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        /* Search */
        .pub-search {
            flex: 1;
            max-width: 380px;
            position: relative;
        }
        .pub-search__icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 12px;
            pointer-events: none;
        }
        .pub-search__input {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 32px 8px 32px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: border-color .15s, background .15s;
        }
        .pub-search__input::placeholder { color: var(--muted); }
        .pub-search__input:focus {
            border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
            background: var(--card);
        }
        .pub-search__clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 11px;
            padding: 2px 4px;
            display: none;
        }
        .pub-search__clear.visible { display: block; }

        /* Nav links */
        .pub-nav {
            display: flex;
            align-items: center;
            gap: 2px;
            flex-shrink: 0;
        }
        .pub-nav__link {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            transition: background .15s, color .15s, border-color .15s;
        }
        .pub-nav__link:hover,
        .pub-nav__link.active {
            background: var(--primary-light);
            color: var(--primary-dark);
            border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
        }
        .pub-nav__link i { font-size: 11px; }

        /* Right actions */
        .pub-topbar-right {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
            flex-shrink: 0;
        }
        .pub-topbar-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            padding: 7px 13px;
            border-radius: 8px;
            border: 1px solid transparent;
            white-space: nowrap;
            transition: background .15s, color .15s, border-color .15s;
        }
        .pub-topbar-link:hover {
            background: var(--bg);
            color: var(--text);
            border-color: var(--border);
        }
        .pub-topbar-btn {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            background: var(--primary-dark);
            text-decoration: none;
            padding: 7px 16px;
            border-radius: 8px;
            border: 1px solid var(--primary-dark);
            white-space: nowrap;
            transition: background .15s, color .15s, border-color .15s;
        }
        .pub-topbar-btn:hover { background: #facc15; color: #0a0a0a; border-color: #facc15; }

        /* User dropdown */
        .pub-user-dropdown { position: relative; }
        .pub-user-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px 6px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color .15s, background .15s;
        }
        .pub-user-trigger:hover { border-color: color-mix(in srgb, var(--primary) 45%, var(--border)); background: var(--card); }
        .pub-user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary-dark);
            color: #facc15;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .pub-user-chevron { font-size: 9px; color: var(--muted); }
        .pub-user-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            min-width: 220px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 12px 28px rgba(0,0,0,.12);
            z-index: 200;
        }
        .pub-user-menu.open { display: block; }
        .pub-user-menu__head {
            padding: 8px 10px 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 6px;
        }
        .pub-user-menu__name { font-size: 13px; font-weight: 700; color: var(--text); }
        .pub-user-menu__email { font-size: 11px; color: var(--muted); margin-top: 1px; }
        .pub-user-menu__item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            width: 100%;
            box-sizing: border-box;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
            transition: background .12s;
        }
        .pub-user-menu__item:hover { background: var(--bg); }
        .pub-user-menu__item i { width: 14px; text-align: center; color: var(--muted); font-size: 11px; }
        .pub-user-menu__item--danger { color: #ef4444; }
        .pub-user-menu__item--danger i { color: #ef4444; }
        .pub-user-menu__divider { height: 1px; background: var(--border); margin: 4px 0; }

        /* ── Page content ── */
        .pub-main {
            max-width: 1060px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        /* Banners */
        .pub-banner {
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 14px;
            border: 1px solid var(--border);
        }
        .pub-banner--ok  { background: color-mix(in srgb,#10b981 10%,transparent); border-color: color-mix(in srgb,#10b981 35%,var(--border)); color: #065f46; }
        .pub-banner--err { background: color-mix(in srgb,#ef4444 10%,transparent); border-color: color-mix(in srgb,#ef4444 35%,var(--border)); color: #991b1b; }

        /* ── Footer ── */
        .pub-footer {
            text-align: center;
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
        }
        .pub-footer a { color: var(--muted); text-decoration: underline; text-underline-offset: 3px; }

        @media (max-width: 640px) {
            .pub-search, .pub-nav { display: none; }
            .pub-topbar { padding: 0 14px; gap: 10px; }
            .pub-main { padding: 20px 14px 48px; }
        }
    </style>
    @yield('head')
</head>
<body>

<nav class="pub-topbar">

    {{-- Logo --}}
    <a href="{{ route('home') }}" class="pub-brand" aria-label="{{ config('app.name') }}">
        @if(file_exists(public_path('logo.png')))
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}">
        @else
            <div class="pub-brand__mark">{{ strtoupper(substr(config('app.name'), 0, 1)) }}</div>
            <span class="pub-brand__name">{{ config('app.name') }}</span>
        @endif
    </a>

    {{-- Search --}}
    <div class="pub-search">
        <i class="fa fa-magnifying-glass pub-search__icon" aria-hidden="true"></i>
        <input type="text" id="pubSearchInput" class="pub-search__input"
               placeholder="@yield('search_placeholder', 'Search...')"
               autocomplete="off" spellcheck="false" aria-label="Search">
        <button type="button" id="pubSearchClear" class="pub-search__clear" aria-label="Clear search">
            <i class="fa fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    {{-- Nav links --}}
    <nav class="pub-nav" aria-label="Site navigation">
        <a href="{{ route('documentation.documents.index') }}"
           class="pub-nav__link {{ request()->routeIs('documentation.*') ? 'active' : '' }}">
            <i class="fa fa-book-open" aria-hidden="true"></i> Documentation
        </a>
    </nav>

    {{-- Right: user dropdown or guest buttons --}}
    <div class="pub-topbar-right">
        @auth
            <a href="{{ route('dashboard') }}" class="pub-topbar-link">
                <i class="fa fa-gauge-high" style="margin-right:5px;font-size:11px;" aria-hidden="true"></i>Dashboard
            </a>
            <div class="pub-user-dropdown">
                <button type="button" class="pub-user-trigger" id="pubUserBtn"
                        aria-haspopup="true" aria-expanded="false">
                    <div class="pub-user-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span>{{ auth()->user()->name ?? 'Account' }}</span>
                    <i class="fa fa-chevron-down pub-user-chevron" aria-hidden="true"></i>
                </button>
                <div class="pub-user-menu" id="pubUserMenu" role="menu">
                    <div class="pub-user-menu__head">
                        <div class="pub-user-menu__name">{{ auth()->user()->name ?? 'User' }}</div>
                        <div class="pub-user-menu__email">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                    <a href="{{ route('dashboard') }}" class="pub-user-menu__item" role="menuitem">
                        <i class="fa fa-gauge-high" aria-hidden="true"></i> Dashboard
                    </a>
                    <a href="{{ route('settings.user') }}" class="pub-user-menu__item" role="menuitem">
                        <i class="fa fa-user-gear" aria-hidden="true"></i> Settings
                    </a>
                    <div class="pub-user-menu__divider"></div>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="pub-user-menu__item pub-user-menu__item--danger" role="menuitem">
                            <i class="fa fa-right-from-bracket" aria-hidden="true"></i> Sign out
                        </button>
                    </form>
                </div>
            </div>
        @else
            <a href="{{ route('login') }}" class="pub-topbar-link">Sign in</a>
            <a href="{{ route('register') }}" class="pub-topbar-btn">Sign up free</a>
        @endauth
    </div>

</nav>

<main class="pub-main">
    @yield('content')
</main>

<footer class="pub-footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    &nbsp;&middot;&nbsp;
    <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route('documentation.documents.index') }}">Documentation</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route('home') }}">Home</a>
</footer>

<script>
(function () {
    // User dropdown
    var btn  = document.getElementById('pubUserBtn');
    var menu = document.getElementById('pubUserMenu');
    if (btn && menu) {
        btn.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.classList.contains('open')) {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
    }

    // Search — delegates to page-level handler if provided, otherwise no-op
    var searchInput = document.getElementById('pubSearchInput');
    var searchClear = document.getElementById('pubSearchClear');
    if (searchInput && searchClear) {
        searchInput.addEventListener('input', function () {
            searchClear.classList.toggle('visible', this.value.length > 0);
            if (typeof window.pubSearchHandler === 'function') window.pubSearchHandler(this.value);
        });
        searchClear.addEventListener('click', function () {
            searchInput.value = '';
            searchClear.classList.remove('visible');
            if (typeof window.pubSearchHandler === 'function') window.pubSearchHandler('');
            searchInput.focus();
        });
    }
})();
</script>

@yield('scripts')

</body>
</html>
