<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — {{ config('app.name') }}</title>
    <meta name="description" content="Privacy Policy for {{ config('app.name') }} — how we collect, use, and protect your information.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:      #f8f9fb;
            --card:    #ffffff;
            --text:    #0f172a;
            --muted:   #64748b;
            --border:  #e2e8f0;
            --accent:  #4f46e5;
            --accent2: #6366f1;
            --radius:  14px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:    #0c0e14;
                --card:  #141720;
                --text:  #f0f4ff;
                --muted: #8893a8;
                --border:#232838;
                --accent:#818cf8;
                --accent2:#a5b4fc;
            }
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.7;
            font-size: 15px;
        }

        /* ── Top nav ── */
        .pp-nav {
            position: sticky; top: 0; z-index: 50;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0 clamp(16px,3vw,32px);
            display: flex; align-items: center; justify-content: space-between;
            height: 60px;
            backdrop-filter: blur(8px);
        }
        .pp-nav-brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; color: var(--text);
            font-weight: 800; font-size: 17px; letter-spacing: -.02em;
        }
        .pp-nav-mark {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--accent); color: #fff;
            display: grid; place-items: center;
            font-size: 14px; font-weight: 800; letter-spacing: -.02em;
        }
        .pp-nav-back {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 600; color: var(--muted);
            text-decoration: none; padding: 7px 14px;
            border-radius: 8px; border: 1px solid var(--border);
            transition: .14s;
        }
        .pp-nav-back:hover { color: var(--text); border-color: var(--accent); }

        /* ── Hero ── */
        .pp-hero {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: clamp(32px,4vw,56px) clamp(16px,3vw,32px) clamp(24px,3vw,40px);
            text-align: center;
        }
        .pp-hero-badge {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 5px 14px; border-radius: 999px;
            background: color-mix(in srgb,var(--accent) 11%,transparent);
            color: var(--accent2); font-size: 12px; font-weight: 700;
            letter-spacing: .06em; text-transform: uppercase; margin-bottom: 18px;
        }
        .pp-hero h1 {
            font-size: clamp(26px,4vw,40px); font-weight: 800;
            letter-spacing: -.03em; line-height: 1.15; margin-bottom: 14px;
        }
        .pp-hero p {
            font-size: 15px; color: var(--muted); max-width: 560px; margin: 0 auto 22px;
        }
        .pp-hero-meta {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12.5px; color: var(--muted); font-weight: 500;
        }
        .pp-hero-meta span { color: var(--text); font-weight: 700; }

        /* ── Layout ── */
        .pp-layout {
            max-width: 1200px; margin: 0 auto;
            padding: clamp(24px,3vw,40px) clamp(16px,3vw,28px);
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 40px;
            align-items: start;
        }
        @media (max-width: 720px) {
            .pp-layout { grid-template-columns: 1fr; }
            .pp-toc { display: none; }
        }

        /* ── TOC sidebar ── */
        .pp-toc {
            position: sticky; top: 76px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 0;
        }
        .pp-toc-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: var(--muted);
            padding: 0 18px 12px; border-bottom: 1px solid var(--border); margin-bottom: 8px;
        }
        .pp-toc a {
            display: block; padding: 7px 18px;
            font-size: 13px; color: var(--muted); text-decoration: none;
            font-weight: 500; transition: .12s;
            border-left: 2px solid transparent;
        }
        .pp-toc a:hover { color: var(--text); background: color-mix(in srgb,var(--accent) 6%,transparent); }
        .pp-toc a.active { color: var(--accent2); border-left-color: var(--accent); font-weight: 700; }

        /* ── Content ── */
        .pp-content { min-width: 0; }
        .pp-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: clamp(24px,4vw,36px);
            margin-bottom: 20px;
        }
        .pp-section:last-child { margin-bottom: 0; }
        .pp-section-head {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px; padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .pp-icon {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            background: color-mix(in srgb,var(--accent) 12%,transparent);
            display: grid; place-items: center;
            color: var(--accent2); font-size: 16px;
        }
        .pp-section h2 {
            font-size: 17px; font-weight: 800; letter-spacing: -.02em;
            color: var(--text); line-height: 1.2;
        }
        .pp-section p {
            color: var(--muted); margin-bottom: 14px; font-size: 14.5px;
        }
        .pp-section p:last-child { margin-bottom: 0; }
        .pp-section ul, .pp-section ol {
            padding-left: 20px; margin-bottom: 14px;
        }
        .pp-section li {
            color: var(--muted); font-size: 14.5px; margin-bottom: 7px;
        }
        .pp-section li strong { color: var(--text); }
        .pp-section a { color: var(--accent2); text-decoration: underline; }
        .pp-section a:hover { color: var(--accent); }

        /* ── Info grid ── */
        .pp-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px; margin-top: 16px;
        }
        .pp-grid-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px; padding: 14px 16px;
        }
        .pp-grid-item .pp-grid-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: var(--muted); margin-bottom: 5px;
        }
        .pp-grid-item .pp-grid-val {
            font-size: 13.5px; font-weight: 700; color: var(--text);
        }

        /* ── Highlight box ── */
        .pp-note {
            background: color-mix(in srgb,var(--accent) 8%,transparent);
            border: 1px solid color-mix(in srgb,var(--accent) 22%,var(--border));
            border-radius: 10px; padding: 14px 18px;
            font-size: 13.5px; color: var(--muted);
            margin-top: 16px; margin-bottom: 0;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .pp-note i { color: var(--accent2); margin-top: 2px; flex-shrink: 0; }

        /* ── Contact card ── */
        .pp-contact {
            display: flex; align-items: center; gap: 14px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-top: 16px;
        }
        .pp-contact-icon {
            width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
            background: color-mix(in srgb,var(--accent) 12%,transparent);
            display: grid; place-items: center;
            color: var(--accent2); font-size: 18px;
        }
        .pp-contact-label { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
        .pp-contact-val { font-size: 14px; font-weight: 700; color: var(--text); margin-top: 2px; }
        .pp-contact-val a { color: var(--accent2); text-decoration: none; }
        .pp-contact-val a:hover { text-decoration: underline; }

        /* ── Footer ── */
        .pp-footer {
            text-align: center; padding: 32px 20px;
            font-size: 13px; color: var(--muted);
            border-top: 1px solid var(--border);
        }
        .pp-footer a { color: var(--accent2); text-decoration: none; }
        .pp-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

{{-- ── Nav ── --}}
<nav class="pp-nav">
    <a href="{{ url('/') }}" class="pp-nav-brand">
        <span class="pp-nav-mark">Z</span>
        {{ config('app.name') }}
    </a>
    <a href="{{ url('/') }}" class="pp-nav-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back
    </a>
</nav>

{{-- ── Hero ── --}}
<div class="pp-hero">
    <div class="pp-hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Privacy Policy
    </div>
    <h1>Your privacy matters to us</h1>
    <p>We keep your data safe, use it only to run the platform, and never sell it to anyone.</p>
    <div class="pp-hero-meta">
        Last updated: <span>{{ now()->format('d F Y') }}</span>
        &nbsp;·&nbsp; Effective: <span>{{ now()->format('d F Y') }}</span>
    </div>
</div>

{{-- ── Main layout ── --}}
<div class="pp-layout">

    {{-- TOC --}}
    <aside class="pp-toc" id="pp-toc">
        <div class="pp-toc-title">On this page</div>
        <a href="#overview">Overview</a>
        <a href="#data-collected">Data We Collect</a>
        <a href="#how-we-use">How We Use It</a>
        <a href="#data-sharing">Data Sharing</a>
        <a href="#google-oauth">Google Sign-In</a>
        <a href="#cookies">Cookies</a>
        <a href="#data-retention">Data Retention</a>
        <a href="#security">Security</a>
        <a href="#your-rights">Your Rights</a>
        <a href="#children">Children</a>
        <a href="#changes">Policy Changes</a>
        <a href="#contact">Contact Us</a>
    </aside>

    {{-- Content --}}
    <main class="pp-content">

        {{-- Overview --}}
        <section class="pp-section" id="overview">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h2>Overview</h2>
            </div>
            <p>This Privacy Policy explains how <strong>{{ config('app.name') }}</strong> ("we", "our", or "us") collects, uses, stores, and protects information about you when you use our platform — including the web application, the desktop POS application, and any related services.</p>
            <p>By creating an account or using our services, you agree to this policy. If you do not agree, please do not use the platform.</p>
            <div class="pp-grid">
                <div class="pp-grid-item">
                    <div class="pp-grid-label">Company</div>
                    <div class="pp-grid-val">{{ config('app.name') }}</div>
                </div>
                <div class="pp-grid-item">
                    <div class="pp-grid-label">Jurisdiction</div>
                    <div class="pp-grid-val">Sri Lanka</div>
                </div>
                <div class="pp-grid-item">
                    <div class="pp-grid-label">Last Revised</div>
                    <div class="pp-grid-val">{{ now()->format('d M Y') }}</div>
                </div>
            </div>
        </section>

        {{-- Data collected --}}
        <section class="pp-section" id="data-collected">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                </div>
                <h2>Data We Collect</h2>
            </div>
            <p>We only collect information necessary to provide and improve the service:</p>
            <ul>
                <li><strong>Account information:</strong> name, email address, hashed password when you register directly.</li>
                <li><strong>Google profile data:</strong> name, email address, and Google account ID when you sign in with Google. We never access your Google Drive, Gmail, or other Google services beyond what is needed for authentication.</li>
                <li><strong>Business data:</strong> business names, branch details, products, customers, sales records, and other data you enter into the platform.</li>
                <li><strong>Employee data:</strong> names, contact details, roles, payroll context, and leave records entered by HR administrators for their staff.</li>
                <li><strong>Activity logs:</strong> login events, registration events, and platform actions for security and audit purposes.</li>
                <li><strong>Technical data:</strong> IP address, browser/OS information, and session identifiers collected automatically when you use the service.</li>
                <li><strong>Desktop app data:</strong> the desktop POS app stores configuration (API URL, authentication tokens) locally on your device in an encrypted config file.</li>
            </ul>
        </section>

        {{-- How we use --}}
        <section class="pp-section" id="how-we-use">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <h2>How We Use Your Data</h2>
            </div>
            <p>Your information is used solely to:</p>
            <ul>
                <li>Create and manage your account and authenticate your identity.</li>
                <li>Provide the features of the platform — POS, accounts, HR, CRM, inventory, and reporting.</li>
                <li>Send transactional communications such as password resets or important service notices.</li>
                <li>Detect and prevent fraud, abuse, and security incidents.</li>
                <li>Maintain audit trails required for business and compliance purposes.</li>
                <li>Improve the platform through anonymous, aggregated usage analytics.</li>
                <li>Deliver desktop app updates and notify you of new versions.</li>
            </ul>
            <div class="pp-note">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                We do not use your data for advertising, profiling, or any purpose beyond operating the platform.
            </div>
        </section>

        {{-- Data sharing --}}
        <section class="pp-section" id="data-sharing">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <h2>Data Sharing</h2>
            </div>
            <p><strong>We do not sell, rent, or trade your personal information.</strong> We only share data in the following limited cases:</p>
            <ul>
                <li><strong>Within your organisation:</strong> users with admin or HR roles in your workspace can see employee and business data you have given them access to.</li>
                <li><strong>Infrastructure providers:</strong> our hosting and database providers process data on our behalf under strict data processing agreements.</li>
                <li><strong>Google (OAuth only):</strong> when you choose to sign in with Google, Google shares your basic profile with us. We do not share your data back with Google beyond what the OAuth handshake requires.</li>
                <li><strong>Legal obligations:</strong> if required by law, court order, or to protect the rights and safety of our users, we may disclose data to relevant authorities.</li>
            </ul>
        </section>

        {{-- Google OAuth --}}
        <section class="pp-section" id="google-oauth">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                </div>
                <h2>Google Sign-In</h2>
            </div>
            <p>When you sign in with Google, we receive from Google only your <strong>name</strong>, <strong>email address</strong>, and a unique <strong>Google account identifier</strong>.</p>
            <p>We do not access your Google Drive, Gmail, contacts, calendar, or any other Google service. The Google OAuth consent screen will clearly show exactly which permissions are requested before you authorise sign-in.</p>
            <p>If you want to unlink your Google account, you can do so from your Google account's security settings at <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">myaccount.google.com/permissions</a>. Your {{ config('app.name') }} account and all its data will remain unless you also request deletion.</p>
        </section>

        {{-- Cookies --}}
        <section class="pp-section" id="cookies">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/></svg>
                </div>
                <h2>Cookies &amp; Sessions</h2>
            </div>
            <p>We use only essential cookies — no advertising or tracking cookies:</p>
            <ul>
                <li><strong>Session cookie:</strong> keeps you signed in during your browser session. Stored in our database and linked to your account.</li>
                <li><strong>CSRF token:</strong> a security token that prevents cross-site request forgery attacks on form submissions.</li>
                <li><strong>Desktop app config:</strong> the desktop POS application stores your authentication token locally on your device in a system-protected config directory — not in a browser cookie.</li>
            </ul>
            <p>We do not use third-party analytics cookies, advertising networks, or pixel trackers.</p>
        </section>

        {{-- Data retention --}}
        <section class="pp-section" id="data-retention">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h2>Data Retention</h2>
            </div>
            <p>We retain your personal data for as long as your account is active. When you request account deletion:</p>
            <ul>
                <li>Your profile, login credentials, and personal details are deleted within <strong>30 days</strong>.</li>
                <li>Business data (sales, inventory, accounts) associated with your workspace may be retained for up to <strong>90 days</strong> for accounting and legal compliance before permanent deletion.</li>
                <li>Activity logs are retained for up to <strong>12 months</strong> for security audit purposes, then permanently deleted.</li>
            </ul>
            <p>You may request deletion at any time by contacting us at the address below.</p>
        </section>

        {{-- Security --}}
        <section class="pp-section" id="security">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h2>Security</h2>
            </div>
            <p>We apply industry-standard measures to protect your data:</p>
            <ul>
                <li>Passwords are <strong>bcrypt-hashed</strong> and never stored or transmitted in plain text.</li>
                <li>All communication between your browser/desktop app and our servers is encrypted via <strong>HTTPS/TLS</strong>.</li>
                <li>Authentication tokens issued to the desktop app expire automatically after a short window and are stored only in a system-protected config directory on your device.</li>
                <li>Role-based access control ensures users only see data their role permits.</li>
                <li>Activity logs capture login and key events for security monitoring.</li>
            </ul>
            <p>While we take security seriously, no system is 100% immune. If you discover a vulnerability, please contact us immediately so we can address it.</p>
        </section>

        {{-- Your rights --}}
        <section class="pp-section" id="your-rights">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h2>Your Rights</h2>
            </div>
            <p>You have the right to:</p>
            <ul>
                <li><strong>Access:</strong> request a copy of the personal data we hold about you.</li>
                <li><strong>Correction:</strong> ask us to correct inaccurate or incomplete data.</li>
                <li><strong>Deletion:</strong> request that your account and personal data be deleted.</li>
                <li><strong>Portability:</strong> request your data in a machine-readable format.</li>
                <li><strong>Objection:</strong> object to processing your data in certain circumstances.</li>
                <li><strong>Withdraw consent:</strong> if processing is based on consent, you may withdraw it at any time without affecting prior lawful processing.</li>
            </ul>
            <p>To exercise any of these rights, contact us using the details below. We will respond within <strong>14 business days</strong>.</p>
        </section>

        {{-- Children --}}
        <section class="pp-section" id="children">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h2>Children's Privacy</h2>
            </div>
            <p>{{ config('app.name') }} is a business management platform intended for use by adults and organisations. We do not knowingly collect personal data from anyone under the age of <strong>16</strong>.</p>
            <p>If you believe a child has provided us with personal information, please contact us and we will promptly delete it.</p>
        </section>

        {{-- Changes --}}
        <section class="pp-section" id="changes">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <h2>Policy Changes</h2>
            </div>
            <p>We may update this Privacy Policy from time to time. When we do, we will revise the "Last updated" date at the top of the page. For significant changes that affect how we handle your data, we will notify registered users by email or through a prominent notice in the platform at least <strong>14 days</strong> before the change takes effect.</p>
            <p>Continued use of the platform after a policy update constitutes acceptance of the revised terms.</p>
        </section>

        {{-- Contact --}}
        <section class="pp-section" id="contact">
            <div class="pp-section-head">
                <div class="pp-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h2>Contact Us</h2>
            </div>
            <p>If you have any questions, concerns, or requests about this Privacy Policy or your personal data, please reach out:</p>
            <div class="pp-contact">
                <div class="pp-contact-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                    <div class="pp-contact-label">Email</div>
                    <div class="pp-contact-val"><a href="mailto:support@zeebroo.com">support@zeebroo.com</a></div>
                </div>
            </div>
            <div class="pp-contact" style="margin-top:10px">
                <div class="pp-contact-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div>
                    <div class="pp-contact-label">Website</div>
                    <div class="pp-contact-val"><a href="https://zeebroo.com" target="_blank" rel="noopener">zeebroo.com</a></div>
                </div>
            </div>
        </section>

    </main>
</div>

{{-- Footer --}}
<footer class="pp-footer">
    &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
    &nbsp;·&nbsp;
    <a href="{{ url('/') }}">Home</a>
    &nbsp;·&nbsp;
    <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
</footer>

<script>
// Active TOC highlight on scroll
(function () {
    var links = document.querySelectorAll('#pp-toc a');
    var sections = Array.from(links).map(function (a) {
        return document.querySelector(a.getAttribute('href'));
    });
    function onScroll() {
        var scrollY = window.scrollY + 100;
        var active = null;
        sections.forEach(function (s) {
            if (s && s.offsetTop <= scrollY) active = s;
        });
        links.forEach(function (a) { a.classList.remove('active'); });
        if (active) {
            var link = document.querySelector('#pp-toc a[href="#' + active.id + '"]');
            if (link) link.classList.add('active');
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();
</script>
</body>
</html>
