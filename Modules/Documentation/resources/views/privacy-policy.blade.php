<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — {{ config('app.name') }}</title>
    <meta name="description" content="Privacy Policy for {{ config('app.name') }}">
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

        /* ── Top bar ── */
        .pp-topbar {
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
        .pp-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .pp-brand img {
            display: block;
            height: 36px;
            width: auto;
            object-fit: contain;
            object-position: left center;
        }
        .pp-brand__mark {
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
        .pp-brand__name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }
        /* Search */
        .pp-search {
            flex: 1;
            max-width: 420px;
            position: relative;
        }
        .pp-search__icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 12px;
            pointer-events: none;
        }
        .pp-search__input {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 36px 8px 32px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: border-color .15s, background .15s;
        }
        .pp-search__input::placeholder { color: var(--muted); }
        .pp-search__input:focus {
            border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
            background: var(--card);
        }
        .pp-search__clear {
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
        .pp-search__clear.visible { display: block; }
        /* Right side */
        .pp-topbar-right {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
            flex-shrink: 0;
        }
        .pp-topbar-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            padding: 7px 13px;
            border-radius: 8px;
            border: 1px solid transparent;
            transition: background .15s, color .15s, border-color .15s;
            white-space: nowrap;
        }
        .pp-topbar-link:hover {
            background: var(--bg);
            color: var(--text);
            border-color: var(--border);
        }
        .pp-topbar-btn {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            background: var(--primary-dark);
            text-decoration: none;
            padding: 7px 16px;
            border-radius: 8px;
            border: 1px solid var(--primary-dark);
            transition: background .15s, color .15s, border-color .15s;
            white-space: nowrap;
        }
        .pp-topbar-btn:hover { background: #facc15; color: #0a0a0a; border-color: #facc15; }
        /* User dropdown */
        .pp-user-dropdown { position: relative; }
        .pp-user-trigger {
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
            transition: border-color .15s, background .15s;
            white-space: nowrap;
        }
        .pp-user-trigger:hover { border-color: color-mix(in srgb, var(--primary) 45%, var(--border)); background: var(--card); }
        .pp-user-avatar {
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
        .pp-user-chevron { font-size: 9px; color: var(--muted); }
        .pp-user-menu {
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
        .pp-user-menu.open { display: block; }
        .pp-user-menu__head {
            padding: 8px 10px 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 6px;
        }
        .pp-user-menu__name { font-size: 13px; font-weight: 700; color: var(--text); }
        .pp-user-menu__email { font-size: 11px; color: var(--muted); margin-top: 1px; }
        .pp-user-menu__item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            text-decoration: none;
            transition: background .12s;
            width: 100%;
            box-sizing: border-box;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
        }
        .pp-user-menu__item:hover { background: var(--bg); }
        .pp-user-menu__item i { width: 14px; text-align: center; color: var(--muted); font-size: 11px; }
        .pp-user-menu__item--danger { color: #ef4444; }
        .pp-user-menu__item--danger i { color: #ef4444; }
        .pp-user-menu__divider { height: 1px; background: var(--border); margin: 4px 0; }
        @media (max-width: 640px) {
            .pp-search { display: none; }
            .pp-topbar { padding: 0 14px; gap: 10px; }
        }

        /* ── Hero ── */
        .pp-hero {
            background: linear-gradient(135deg, var(--primary-light) 0%, #fefce8 100%);
            border-bottom: 1px solid var(--border);
            padding: 56px 24px 48px;
            text-align: center;
        }
        .pp-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--primary);
            background: var(--primary-light);
            border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border));
            border-radius: 999px;
            padding: 4px 12px;
            margin-bottom: 18px;
        }
        .pp-hero h1 {
            font-size: clamp(26px, 5vw, 38px);
            font-weight: 800;
            letter-spacing: -.03em;
            color: var(--text);
            margin-bottom: 10px;
        }
        .pp-hero__sub {
            font-size: 15px;
            color: var(--muted);
            max-width: 520px;
            margin: 0 auto 20px;
        }
        .pp-hero__date {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--muted);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 5px 12px;
        }

        /* ── Layout ── */
        .pp-layout {
            display: flex;
            gap: 32px;
            max-width: 1040px;
            margin: 40px auto;
            padding: 0 24px 64px;
            align-items: flex-start;
        }

        /* ── Sidebar TOC ── */
        .pp-toc {
            flex-shrink: 0;
            width: 220px;
            position: sticky;
            top: 80px;
        }
        .pp-toc__title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .pp-toc__list { list-style: none; }
        .pp-toc__item { margin-bottom: 2px; }
        .pp-toc__link {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
            padding: 5px 8px;
            border-radius: 7px;
            transition: background .12s, color .12s;
            border-left: 2px solid transparent;
        }
        .pp-toc__link:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        @media (max-width: 740px) {
            .pp-toc { display: none; }
            .pp-layout { flex-direction: column; }
            .pp-content { width: 100%; }
        }

        /* ── Content ── */
        .pp-content { flex: 1; min-width: 0; }

        .pp-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 30px;
            margin-bottom: 18px;
            scroll-margin-top: 80px;
        }
        .pp-section__num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 7px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .pp-section h2 {
            display: flex;
            align-items: center;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.02em;
            color: var(--text);
            margin-bottom: 14px;
        }
        .pp-section p {
            font-size: 14px;
            color: #1c1917;
            line-height: 1.75;
            margin-bottom: 12px;
        }
        .pp-section p:last-child { margin-bottom: 0; }
        .pp-section ul {
            margin: 10px 0 12px 0;
            padding-left: 0;
            list-style: none;
        }
        .pp-section ul li {
            font-size: 14px;
            color: #1c1917;
            line-height: 1.7;
            padding: 4px 0 4px 22px;
            position: relative;
        }
        .pp-section ul li::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 13px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
            opacity: .6;
        }
        .pp-section strong { font-weight: 700; color: var(--text); }
        .pp-section a { color: var(--primary); text-decoration: underline; text-underline-offset: 3px; }

        /* ── Highlight box ── */
        .pp-highlight {
            background: var(--primary-light);
            border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .pp-highlight p {
            font-size: 13px;
            color: #713f12;
            margin-bottom: 0;
        }

        /* ── Contact card ── */
        .pp-contact {
            background: linear-gradient(135deg, #171717 0%, #292524 100%);
            border-radius: 16px;
            padding: 28px 30px;
            color: #fff;
            text-align: center;
            margin-top: 20px;
        }
        .pp-contact h3 { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
        .pp-contact p { font-size: 13px; opacity: .85; margin-bottom: 16px; }
        .pp-contact a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #facc15;
            border: 1px solid #fde047;
            color: #0a0a0a;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            padding: 9px 20px;
            border-radius: 9px;
            transition: background .15s;
        }
        .pp-contact a:hover { background: #fde047; }

        /* ── Footer ── */
        .pp-footer {
            text-align: center;
            padding: 24px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
        }
        .pp-footer a { color: var(--muted); text-decoration: underline; text-underline-offset: 3px; }
    </style>
</head>
<body>

{{-- Top bar --}}
<nav class="pp-topbar">

    {{-- Logo --}}
    <a href="{{ route('home') }}" class="pp-brand" aria-label="{{ config('app.name') }}">
        @if(file_exists(public_path('logo.png')))
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}">
        @else
            <div class="pp-brand__mark">{{ strtoupper(substr(config('app.name'), 0, 1)) }}</div>
            <span class="pp-brand__name">{{ config('app.name') }}</span>
        @endif
    </a>

    {{-- Search --}}
    <div class="pp-search">
        <i class="fa fa-magnifying-glass pp-search__icon" aria-hidden="true"></i>
        <input type="text" id="ppSearchInput" class="pp-search__input"
               placeholder="Search policy…" autocomplete="off" spellcheck="false"
               aria-label="Search privacy policy">
        <button type="button" id="ppSearchClear" class="pp-search__clear" aria-label="Clear search">&#x2715;</button>
    </div>

    {{-- Right actions --}}
    <div class="pp-topbar-right">
        <a href="{{ route('documentation.documents.index') }}" class="pp-topbar-link">
            <i class="fa fa-book-open" style="margin-right:5px;font-size:11px;"></i>Documentation
        </a>
        @auth
            <a href="{{ route('dashboard') }}" class="pp-topbar-link">
                <i class="fa fa-gauge-high" style="margin-right:5px;font-size:11px;"></i>Dashboard
            </a>
            <div class="pp-user-dropdown">
                <button type="button" class="pp-user-trigger" id="ppUserBtn" aria-haspopup="true" aria-expanded="false">
                    <div class="pp-user-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span>{{ auth()->user()->name ?? 'Account' }}</span>
                    <i class="fa fa-chevron-down pp-user-chevron" aria-hidden="true"></i>
                </button>
                <div class="pp-user-menu" id="ppUserMenu" role="menu">
                    <div class="pp-user-menu__head">
                        <div class="pp-user-menu__name">{{ auth()->user()->name ?? 'User' }}</div>
                        <div class="pp-user-menu__email">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                    <a href="{{ route('dashboard') }}" class="pp-user-menu__item" role="menuitem">
                        <i class="fa fa-gauge-high" aria-hidden="true"></i> Dashboard
                    </a>
                    <a href="{{ route('settings.user') }}" class="pp-user-menu__item" role="menuitem">
                        <i class="fa fa-user-gear" aria-hidden="true"></i> Settings
                    </a>
                    <div class="pp-user-menu__divider"></div>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="pp-user-menu__item pp-user-menu__item--danger" role="menuitem">
                            <i class="fa fa-right-from-bracket" aria-hidden="true"></i> Sign out
                        </button>
                    </form>
                </div>
            </div>
        @else
            <a href="{{ route('login') }}" class="pp-topbar-link">Sign in</a>
            <a href="{{ route('register') }}" class="pp-topbar-btn">Sign up free</a>
        @endauth
    </div>

</nav>

{{-- Hero --}}
<div class="pp-hero">
    <div class="pp-hero__badge">
        <i class="fa fa-lock" aria-hidden="true"></i> Legal
    </div>
    <h1>Privacy Policy</h1>
    <p class="pp-hero__sub">We respect your privacy. This policy explains what data we collect, how we use it, and the choices you have.</p>
    <div class="pp-hero__date">
        <i class="fa fa-calendar-days" aria-hidden="true"></i> Last updated: {{ \Carbon\Carbon::parse('2026-06-12')->format('F j, Y') }}
    </div>
</div>

<div class="pp-layout">

    {{-- Sidebar TOC --}}
    <aside class="pp-toc">
        <p class="pp-toc__title">Contents</p>
        <ul class="pp-toc__list">
            <li class="pp-toc__item"><a href="#s1"  class="pp-toc__link">1. Information We Collect</a></li>
            <li class="pp-toc__item"><a href="#s2"  class="pp-toc__link">2. How We Use Your Data</a></li>
            <li class="pp-toc__item"><a href="#s3"  class="pp-toc__link">3. Sharing Your Data</a></li>
            <li class="pp-toc__item"><a href="#s4"  class="pp-toc__link">4. Data Storage & Security</a></li>
            <li class="pp-toc__item"><a href="#s5"  class="pp-toc__link">5. Cookies</a></li>
            <li class="pp-toc__item"><a href="#s6"  class="pp-toc__link">6. Third-Party Services</a></li>
            <li class="pp-toc__item"><a href="#s7"  class="pp-toc__link">7. Your Rights</a></li>
            <li class="pp-toc__item"><a href="#s8"  class="pp-toc__link">8. Data Retention</a></li>
            <li class="pp-toc__item"><a href="#s9"  class="pp-toc__link">9. Children's Privacy</a></li>
            <li class="pp-toc__item"><a href="#s10" class="pp-toc__link">10. Changes to Policy</a></li>
            <li class="pp-toc__item"><a href="#s11" class="pp-toc__link">11. Contact Us</a></li>
        </ul>
    </aside>

    {{-- Main content --}}
    <main class="pp-content">

        <div class="pp-highlight">
            <p><strong>Summary:</strong> {{ config('app.name') }} collects only the data needed to provide our services. We do not sell your personal information to third parties. You can request deletion of your data at any time.</p>
        </div>

        {{-- 1 --}}
        <section class="pp-section" id="s1">
            <h2><span class="pp-section__num">1</span> Information We Collect</h2>
            <p>We collect information you provide directly to us and information generated when you use our services.</p>
            <p><strong>Account & profile data</strong></p>
            <ul>
                <li>Name, email address, and password when you register</li>
                <li>Business name, address, phone number, and logo</li>
                <li>Profile picture and preferences you set in the application</li>
            </ul>
            <p><strong>Business operational data</strong></p>
            <ul>
                <li>Products, inventory, and pricing information you enter</li>
                <li>Sales transactions, customer records, and purchase orders</li>
                <li>Employee records, payroll data, and HR information</li>
                <li>Financial accounts, invoices, and ledger entries</li>
            </ul>
            <p><strong>Technical data</strong></p>
            <ul>
                <li>IP address, browser type, and operating system</li>
                <li>Pages visited, features used, and timestamps</li>
                <li>Device identifiers and session tokens</li>
            </ul>
        </section>

        {{-- 2 --}}
        <section class="pp-section" id="s2">
            <h2><span class="pp-section__num">2</span> How We Use Your Data</h2>
            <p>We use the information we collect to provide, maintain, and improve our services:</p>
            <ul>
                <li>Create and manage your account and business workspace</li>
                <li>Process and record sales, inventory, and financial transactions</li>
                <li>Generate reports, analytics, and business insights</li>
                <li>Send important service notifications and transactional emails</li>
                <li>Provide customer support and respond to inquiries</li>
                <li>Detect and prevent fraud, abuse, and security threats</li>
                <li>Comply with legal obligations and enforce our terms</li>
                <li>Improve and personalize the product experience</li>
            </ul>
            <p>We do <strong>not</strong> use your business data to train AI models or share it with advertisers.</p>
        </section>

        {{-- 3 --}}
        <section class="pp-section" id="s3">
            <h2><span class="pp-section__num">3</span> Sharing Your Data</h2>
            <p>We do <strong>not sell or rent</strong> your personal information. We may share data only in the following limited circumstances:</p>
            <ul>
                <li><strong>Service providers:</strong> Trusted vendors (cloud hosting, email delivery, analytics) who process data on our behalf under strict confidentiality agreements</li>
                <li><strong>Legal requirements:</strong> When required by law, court order, or government authority</li>
                <li><strong>Business transfers:</strong> In connection with a merger, acquisition, or sale of assets, with appropriate notice</li>
                <li><strong>With your consent:</strong> When you explicitly authorise us to share specific data with a third party (e.g., social media platform integrations you connect)</li>
            </ul>
        </section>

        {{-- 4 --}}
        <section class="pp-section" id="s4">
            <h2><span class="pp-section__num">4</span> Data Storage &amp; Security</h2>
            <p>Your data is stored on secure servers. We implement industry-standard technical and organisational measures to protect your information:</p>
            <ul>
                <li>Encryption in transit (TLS/HTTPS) and at rest for sensitive fields</li>
                <li>Regular security audits and vulnerability assessments</li>
                <li>Access controls — employees access only the data needed to do their job</li>
                <li>Automatic session expiration and secure authentication tokens</li>
            </ul>
            <p>No method of transmission over the internet is 100% secure. We strive to use commercially acceptable means but cannot guarantee absolute security.</p>
        </section>

        {{-- 5 --}}
        <section class="pp-section" id="s5">
            <h2><span class="pp-section__num">5</span> Cookies</h2>
            <p>We use cookies and similar tracking technologies to operate and improve our services:</p>
            <ul>
                <li><strong>Essential cookies:</strong> Required to keep you logged in and maintain your session</li>
                <li><strong>Preference cookies:</strong> Remember your settings (theme, language, selected business)</li>
                <li><strong>Analytics cookies:</strong> Help us understand how you use the application so we can improve it</li>
            </ul>
            <p>You can control cookies through your browser settings. Disabling essential cookies will prevent you from logging in.</p>
        </section>

        {{-- 6 --}}
        <section class="pp-section" id="s6">
            <h2><span class="pp-section__num">6</span> Third-Party Services</h2>
            <p>Our platform integrates with third-party services to extend functionality. Each integration is subject to that provider's own privacy policy:</p>
            <ul>
                <li><strong>Google:</strong> Used for OAuth sign-in and Google Business Profile features (<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>)</li>
                <li><strong>Facebook / Meta:</strong> Used for Facebook Page connections in the Social Media feature (<a href="https://www.facebook.com/policy.php" target="_blank" rel="noopener">Meta Privacy Policy</a>)</li>
                <li><strong>Gemini AI:</strong> Used for AI-assisted content and design generation</li>
            </ul>
            <p>You may revoke third-party integrations at any time from within the application settings.</p>
        </section>

        {{-- 7 --}}
        <section class="pp-section" id="s7">
            <h2><span class="pp-section__num">7</span> Your Rights</h2>
            <p>Depending on your jurisdiction, you may have the following rights regarding your personal data:</p>
            <ul>
                <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
                <li><strong>Correction:</strong> Ask us to correct inaccurate or incomplete data</li>
                <li><strong>Deletion:</strong> Request deletion of your personal data ("right to be forgotten")</li>
                <li><strong>Portability:</strong> Receive your data in a structured, machine-readable format</li>
                <li><strong>Objection:</strong> Object to certain types of processing, including direct marketing</li>
                <li><strong>Restriction:</strong> Request that we limit how we use your data in certain circumstances</li>
            </ul>
            <p>To exercise any of these rights, contact us using the details in section 11. We will respond within 30 days.</p>
        </section>

        {{-- 8 --}}
        <section class="pp-section" id="s8">
            <h2><span class="pp-section__num">8</span> Data Retention</h2>
            <p>We retain your personal data for as long as your account is active or as needed to provide services. Specifically:</p>
            <ul>
                <li>Account and profile data: retained until you delete your account</li>
                <li>Business transaction data: retained for 7 years to comply with financial record-keeping requirements</li>
                <li>Server logs: retained for 90 days</li>
                <li>Deleted account data: permanently purged within 30 days of account deletion request</li>
            </ul>
            <p>After the retention period, data is securely deleted or anonymised.</p>
        </section>

        {{-- 9 --}}
        <section class="pp-section" id="s9">
            <h2><span class="pp-section__num">9</span> Children's Privacy</h2>
            <p>{{ config('app.name') }} is a business management platform intended for use by individuals aged 18 and older. We do not knowingly collect personal information from children under 13.</p>
            <p>If you believe a child has provided us with personal information, please contact us immediately and we will take steps to delete such information.</p>
        </section>

        {{-- 10 --}}
        <section class="pp-section" id="s10">
            <h2><span class="pp-section__num">10</span> Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time to reflect changes in our practices or for legal reasons. When we make material changes, we will:</p>
            <ul>
                <li>Update the "Last updated" date at the top of this page</li>
                <li>Notify registered users via email or in-app notification</li>
                <li>For significant changes, request renewed consent where required by law</li>
            </ul>
            <p>Your continued use of {{ config('app.name') }} after changes become effective constitutes your acceptance of the revised policy.</p>
        </section>

        {{-- 11 --}}
        <section class="pp-section" id="s11">
            <h2><span class="pp-section__num">11</span> Contact Us</h2>
            <p>If you have questions about this Privacy Policy, wish to exercise your rights, or need to report a privacy concern, please contact our privacy team:</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:privacy@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }}">privacy@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }}</a></li>
                <li><strong>Subject line:</strong> Privacy Request — {{ config('app.name') }}</li>
            </ul>
        </section>

        {{-- Contact CTA --}}
        <div class="pp-contact">
            <h3>Have a privacy question?</h3>
            <p>Our team is happy to help with any data or privacy concerns.</p>
            <a href="mailto:privacy@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }}">
                <i class="fa fa-envelope" aria-hidden="true"></i> Contact Privacy Team
            </a>
        </div>

    </main>
</div>

<footer class="pp-footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    &nbsp;&middot;&nbsp;
    <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
    &nbsp;&middot;&nbsp;
    <a href="{{ route('home') }}">Home</a>
</footer>

<script>
(function () {
    // ── User dropdown ────────────────────────────────────────────────
    var userBtn  = document.getElementById('ppUserBtn');
    var userMenu = document.getElementById('ppUserMenu');
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function () {
            var open = userMenu.classList.toggle('open');
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.remove('open');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && userMenu.classList.contains('open')) {
                userMenu.classList.remove('open');
                userBtn.setAttribute('aria-expanded', 'false');
                userBtn.focus();
            }
        });
    }

    // ── Page search ─────────────────────────────────────────────────
    var searchInput = document.getElementById('ppSearchInput');
    var searchClear = document.getElementById('ppSearchClear');
    if (!searchInput) return;

    // Collect all searchable sections
    var sections = Array.from(document.querySelectorAll('.pp-section'));

    function doSearch(q) {
        var term = q.trim().toLowerCase();
        searchClear.classList.toggle('visible', term.length > 0);
        sections.forEach(function (sec) {
            if (!term) {
                sec.style.display = '';
                clearHighlights(sec);
                return;
            }
            var text = sec.textContent.toLowerCase();
            if (text.includes(term)) {
                sec.style.display = '';
                highlightText(sec, term);
            } else {
                sec.style.display = 'none';
                clearHighlights(sec);
            }
        });
    }

    function highlightText(el, term) {
        clearHighlights(el);
        // Only highlight text nodes inside <p> and <li>
        el.querySelectorAll('p, li').forEach(function (node) {
            node.innerHTML = node.textContent.replace(
                new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
                '<mark style="background:#fef08a;color:#0a0a0a;border-radius:3px;padding:0 1px;">$1</mark>'
            );
        });
    }

    function clearHighlights(el) {
        el.querySelectorAll('mark').forEach(function (m) {
            m.replaceWith(document.createTextNode(m.textContent));
        });
    }

    searchInput.addEventListener('input', function () { doSearch(this.value); });
    searchClear.addEventListener('click', function () {
        searchInput.value = '';
        doSearch('');
        searchInput.focus();
    });
})();
</script>
</body>
</html>
