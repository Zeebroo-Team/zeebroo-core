<!DOCTYPE html>
<html lang="en" data-docs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Zeebroo POS Online API — catalog, checkout, sales, and authentication for mobile and integrations.">
    <title>{{ $appName }} · POS Online API</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --docs-bg: #0b0f1a;
            --docs-bg-elevated: #111827;
            --docs-bg-card: #151d2e;
            --docs-border: rgba(148, 163, 184, 0.14);
            --docs-border-strong: rgba(148, 163, 184, 0.22);
            --docs-text: #f1f5f9;
            --docs-muted: #94a3b8;
            --docs-accent: #818cf8;
            --docs-accent-soft: rgba(129, 140, 248, 0.14);
            --docs-accent-glow: rgba(99, 102, 241, 0.35);
            --docs-success: #34d399;
            --docs-sidebar-w: 300px;
            --docs-header-h: 56px;
            --docs-font: 'Inter', system-ui, -apple-system, sans-serif;
            --docs-mono: 'JetBrains Mono', ui-monospace, monospace;
            --docs-radius: 12px;
            --docs-shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
        }

        html[data-docs-theme="light"] {
            --docs-bg: #f4f6fb;
            --docs-bg-elevated: #ffffff;
            --docs-bg-card: #ffffff;
            --docs-border: rgba(15, 23, 42, 0.1);
            --docs-border-strong: rgba(15, 23, 42, 0.16);
            --docs-text: #0f172a;
            --docs-muted: #64748b;
            --docs-accent: #4f46e5;
            --docs-accent-soft: rgba(79, 70, 229, 0.1);
            --docs-accent-glow: rgba(79, 70, 229, 0.2);
            --docs-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            font-family: var(--docs-font);
            background: var(--docs-bg);
            color: var(--docs-text);
            -webkit-font-smoothing: antialiased;
        }

        body { display: flex; flex-direction: column; overflow: hidden; }

        .docs-topbar {
            flex-shrink: 0;
            height: var(--docs-header-h);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 20px;
            border-bottom: 1px solid var(--docs-border);
            background: color-mix(in srgb, var(--docs-bg-elevated) 92%, transparent);
            backdrop-filter: blur(12px);
            z-index: 40;
        }

        .docs-topbar__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .docs-topbar__logo {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%);
            color: #fff;
            font-size: 16px;
            box-shadow: 0 8px 20px var(--docs-accent-glow);
        }

        .docs-topbar__titles { min-width: 0; }

        .docs-topbar__titles h1 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .docs-topbar__titles p {
            margin: 2px 0 0;
            font-size: 11px;
            color: var(--docs-muted);
            font-weight: 500;
        }

        .docs-topbar__actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .docs-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 14px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            border-radius: 9px;
            border: 1px solid var(--docs-border-strong);
            background: var(--docs-bg-card);
            color: var(--docs-text);
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.15s, background 0.15s, transform 0.12s;
            white-space: nowrap;
        }

        .docs-btn:hover {
            border-color: var(--docs-accent);
            background: var(--docs-accent-soft);
        }

        .docs-btn--primary {
            border-color: color-mix(in srgb, var(--docs-accent) 50%, var(--docs-border));
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff;
            box-shadow: 0 4px 14px var(--docs-accent-glow);
        }

        .docs-btn--primary:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }

        .docs-btn--ghost {
            background: transparent;
        }

        .docs-body {
            flex: 1;
            min-height: 0;
            height: calc(100vh - var(--docs-header-h));
            display: grid;
            grid-template-columns: var(--docs-sidebar-w) minmax(0, 1fr);
        }

        .docs-sidebar {
            border-right: 1px solid var(--docs-border);
            background: var(--docs-bg-elevated);
            overflow-y: auto;
            padding: 18px 16px 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .docs-card {
            padding: 14px;
            border-radius: var(--docs-radius);
            border: 1px solid var(--docs-border);
            background: var(--docs-bg-card);
            box-shadow: var(--docs-shadow);
        }

        .docs-card__label {
            margin: 0 0 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--docs-muted);
        }

        .docs-url-box {
            display: flex;
            align-items: stretch;
            gap: 0;
            border-radius: 9px;
            border: 1px solid var(--docs-border-strong);
            overflow: hidden;
            background: color-mix(in srgb, var(--docs-bg) 60%, transparent);
        }

        .docs-url-box code {
            flex: 1;
            min-width: 0;
            padding: 9px 10px;
            font-family: var(--docs-mono);
            font-size: 11px;
            line-height: 1.4;
            word-break: break-all;
            color: var(--docs-accent);
        }

        .docs-url-box button {
            flex-shrink: 0;
            width: 40px;
            border: 0;
            border-left: 1px solid var(--docs-border);
            background: var(--docs-accent-soft);
            color: var(--docs-accent);
            cursor: pointer;
            font-size: 13px;
        }

        .docs-url-box button:hover { background: var(--docs-accent); color: #fff; }

        .docs-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .docs-pill {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px solid var(--docs-border);
            color: var(--docs-muted);
            background: color-mix(in srgb, var(--docs-bg) 50%, transparent);
        }

        .docs-pill--live {
            border-color: color-mix(in srgb, var(--docs-success) 45%, var(--docs-border));
            color: var(--docs-success);
            background: color-mix(in srgb, var(--docs-success) 12%, transparent);
        }

        .docs-nav { display: flex; flex-direction: column; gap: 4px; }

        .docs-nav__title {
            margin: 0 0 8px;
            padding: 0 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--docs-muted);
        }

        .docs-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            color: var(--docs-muted);
            text-decoration: none;
            transition: background 0.12s, color 0.12s;
        }

        .docs-nav a i {
            width: 18px;
            text-align: center;
            font-size: 12px;
            opacity: 0.85;
        }

        .docs-nav a:hover,
        .docs-nav a.is-active {
            color: var(--docs-text);
            background: var(--docs-accent-soft);
        }

        .docs-nav a.is-active { color: var(--docs-accent); }

        .docs-steps {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .docs-steps li {
            display: flex;
            gap: 10px;
            font-size: 12px;
            line-height: 1.45;
            color: var(--docs-muted);
        }

        .docs-steps__num {
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 7px;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 800;
            color: var(--docs-accent);
            background: var(--docs-accent-soft);
            border: 1px solid color-mix(in srgb, var(--docs-accent) 30%, var(--docs-border));
        }

        .docs-steps strong { color: var(--docs-text); font-weight: 600; }

        .docs-snippet {
            margin-top: 10px;
            padding: 10px;
            border-radius: 9px;
            border: 1px solid var(--docs-border);
            background: color-mix(in srgb, var(--docs-bg) 80%, transparent);
            font-family: var(--docs-mono);
            font-size: 10px;
            line-height: 1.5;
            color: var(--docs-muted);
            overflow-x: auto;
            white-space: pre;
        }

        .docs-main {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: var(--docs-bg);
        }

        .docs-main__hero {
            flex-shrink: 0;
            padding: 20px 24px 0;
        }

        .docs-hero-inner {
            padding: 20px 22px;
            border-radius: 14px;
            border: 1px solid var(--docs-border);
            background:
                radial-gradient(ellipse 80% 120% at 100% 0%, rgba(99, 102, 241, 0.18), transparent 55%),
                radial-gradient(ellipse 60% 80% at 0% 100%, rgba(168, 85, 247, 0.12), transparent 50%),
                var(--docs-bg-card);
        }

        .docs-hero-inner h2 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .docs-hero-inner > p {
            margin: 0;
            max-width: 62ch;
            font-size: 14px;
            line-height: 1.55;
            color: var(--docs-muted);
        }

        .docs-hero-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .docs-hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 999px;
            border: 1px solid var(--docs-border);
            background: color-mix(in srgb, var(--docs-bg) 40%, transparent);
            color: var(--docs-text);
        }

        .docs-hero-tag i { color: var(--docs-accent); font-size: 11px; }

        .docs-reference-wrap {
            flex: 1;
            min-height: 520px;
            margin: 16px 16px 16px;
            border-radius: 14px;
            border: 1px solid var(--docs-border);
            overflow: auto;
            background: var(--docs-bg-elevated);
            box-shadow: var(--docs-shadow);
            position: relative;
        }

        #redoc-container {
            width: 100%;
            min-height: 520px;
        }

        .docs-reference-loading {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--docs-muted);
            background: var(--docs-bg-elevated);
            z-index: 2;
        }

        .docs-reference-loading.is-hidden {
            display: none;
        }

        .docs-toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(12px);
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            background: var(--docs-text);
            color: var(--docs-bg);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 100;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }

        .docs-toast.is-visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        @media (max-width: 960px) {
            .docs-body { grid-template-columns: 1fr; }
            .docs-sidebar {
                display: none;
            }
            .docs-main__hero { padding: 14px 14px 0; }
            .docs-reference-wrap { margin: 12px; }
        }
    </style>
</head>
<body>
    <header class="docs-topbar">
        <div class="docs-topbar__brand">
            <div class="docs-topbar__logo" aria-hidden="true">
                <i class="fa fa-cash-register"></i>
            </div>
            <div class="docs-topbar__titles">
                <h1>{{ $appName }} POS API</h1>
                <p>Online retail · REST v1</p>
            </div>
        </div>
        <div class="docs-topbar__actions">
            <button type="button" class="docs-btn docs-btn--ghost" id="docs-theme-toggle" title="Toggle light/dark">
                <i class="fa fa-moon" id="docs-theme-icon" aria-hidden="true"></i>
            </button>
            <a href="{{ $specUrl }}" class="docs-btn" download target="_blank" rel="noopener">
                <i class="fa fa-file-code" aria-hidden="true"></i> OpenAPI
            </a>
            <a href="{{ url('/api/v1/pos/docs/readme') }}" class="docs-btn" target="_blank" rel="noopener">
                <i class="fa fa-book" aria-hidden="true"></i> Guide
            </a>
            <button type="button" class="docs-btn docs-btn--primary" id="docs-try-auth">
                <i class="fa fa-key" aria-hidden="true"></i> Try auth
            </button>
        </div>
    </header>

    <div class="docs-body">
        <aside class="docs-sidebar" aria-label="API overview">
            <div class="docs-card">
                <p class="docs-card__label">Base URL</p>
                <div class="docs-url-box">
                    <code id="docs-base-url">{{ $apiBaseUrl }}</code>
                    <button type="button" id="docs-copy-base" aria-label="Copy base URL">
                        <i class="fa fa-copy" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="docs-pills">
                    <span class="docs-pill docs-pill--live"><i class="fa fa-circle" style="font-size:7px;"></i> OpenAPI 3.0</span>
                    <span class="docs-pill">Bearer auth</span>
                    <span class="docs-pill">JSON</span>
                </div>
            </div>

            <nav class="docs-nav" aria-label="Sections">
                <p class="docs-nav__title">Quick start</p>
                <ol class="docs-steps">
                    <li>
                        <span class="docs-steps__num">1</span>
                        <span><strong>Get token</strong> — POST <code style="font-family:var(--docs-mono);font-size:11px;">/auth/token</code></span>
                    </li>
                    <li>
                        <span class="docs-steps__num">2</span>
                        <span><strong>Set headers</strong> — <code style="font-family:var(--docs-mono);font-size:11px;">Authorization</code> + <code style="font-family:var(--docs-mono);font-size:11px;">X-Business-Id</code></span>
                    </li>
                    <li>
                        <span class="docs-steps__num">3</span>
                        <span><strong>Bootstrap</strong> — load catalog, accounts &amp; settings in one call</span>
                    </li>
                    <li>
                        <span class="docs-steps__num">4</span>
                        <span><strong>Checkout</strong> — complete sale with cart items</span>
                    </li>
                </ol>
                <pre class="docs-snippet" id="docs-auth-snippet">POST /auth/token
Authorization: (none)

{
  "email": "you@example.com",
  "password": "••••••••",
  "device_name": "pos-client"
}</pre>
            </nav>

            <nav class="docs-nav">
                <p class="docs-nav__title">Jump to</p>
                <a href="#tag/Auth" data-docs-nav><i class="fa fa-key"></i> Authentication</a>
                <a href="#tag/Businesses" data-docs-nav><i class="fa fa-building"></i> Businesses</a>
                <a href="#tag/Online-bootstrap" data-docs-nav><i class="fa fa-rocket"></i> Bootstrap</a>
                <a href="#tag/Catalog" data-docs-nav><i class="fa fa-box"></i> Catalog</a>
                <a href="#tag/Checkout" data-docs-nav><i class="fa fa-cart-shopping"></i> Checkout</a>
                <a href="#tag/Products" data-docs-nav><i class="fa fa-boxes-stacked"></i> Products</a>
                <a href="#tag/Categories" data-docs-nav><i class="fa fa-tags"></i> Categories</a>
                <a href="#tag/Units" data-docs-nav><i class="fa fa-ruler"></i> Units</a>
                <a href="#tag/Brands" data-docs-nav><i class="fa fa-trademark"></i> Brands</a>
                <a href="#tag/Discounts" data-docs-nav><i class="fa fa-percent"></i> Discounts</a>
                <a href="#tag/Sales" data-docs-nav><i class="fa fa-receipt"></i> Sales</a>
                <a href="#tag/Sale-returns" data-docs-nav><i class="fa fa-rotate-left"></i> Sale Returns</a>
                <a href="#tag/Quotations" data-docs-nav><i class="fa fa-file-lines"></i> Quotations</a>
                <a href="#tag/Invoices" data-docs-nav><i class="fa fa-file-invoice-dollar"></i> Invoices</a>
                <a href="#tag/Customers" data-docs-nav><i class="fa fa-user-group"></i> Customers</a>
                <a href="#tag/Suppliers" data-docs-nav><i class="fa fa-truck"></i> Suppliers</a>
                <a href="#tag/Purchase-orders" data-docs-nav><i class="fa fa-clipboard-list"></i> Purchase Orders</a>
                <a href="#tag/GRNs" data-docs-nav><i class="fa fa-boxes-stacked"></i> GRNs</a>
                <a href="#tag/Cheques" data-docs-nav><i class="fa fa-money-check"></i> Cheques</a>
                <a href="#tag/Expense-bills" data-docs-nav><i class="fa fa-file-invoice"></i> Expense Bills</a>
                <a href="#tag/Expense-modifications" data-docs-nav><i class="fa fa-hammer"></i> Modifications</a>
                <a href="#tag/End-of-day" data-docs-nav><i class="fa fa-moon"></i> End of Day</a>
                <a href="#tag/Finance-flow" data-docs-nav><i class="fa fa-chart-line"></i> Finance Flow</a>
                <a href="#tag/Loans" data-docs-nav><i class="fa fa-hand-holding-dollar"></i> Loans</a>
                <a href="#tag/Properties" data-docs-nav><i class="fa fa-house"></i> Properties</a>
                <a href="#tag/Rentals" data-docs-nav><i class="fa fa-door-open"></i> Rentals</a>
                <a href="#tag/Accounts-&-banks" data-docs-nav><i class="fa fa-landmark"></i> Accounts &amp; Banks</a>
                <a href="#tag/HR-employees" data-docs-nav><i class="fa fa-id-badge"></i> HR Employees</a>
                <a href="#tag/HR-departments" data-docs-nav><i class="fa fa-sitemap"></i> HR Departments</a>
                <a href="#tag/HR-allowance-types" data-docs-nav><i class="fa fa-coins"></i> Allowance Types</a>
                <a href="#tag/HR-payroll-rule-sets" data-docs-nav><i class="fa fa-sliders"></i> Payroll Rules</a>
                <a href="#tag/HR-payroll-cycles" data-docs-nav><i class="fa fa-calendar-check"></i> Payroll Cycles</a>
                <a href="#tag/Design-studio" data-docs-nav><i class="fa fa-pen-ruler"></i> Design Studio</a>
                <a href="#tag/Stock-audits" data-docs-nav><i class="fa fa-clipboard-check"></i> Stock Audits</a>
                <a href="#tag/File-manager" data-docs-nav><i class="fa fa-folder-open"></i> File Manager</a>
                <a href="#tag/Settings" data-docs-nav><i class="fa fa-gear"></i> Settings</a>
            </nav>
        </aside>

        <main class="docs-main">
            <div class="docs-main__hero">
                <div class="docs-hero-inner">
                    <h2>Full Zeebroo POS REST API</h2>
                    <p>
                        115 endpoints across catalog, checkout, invoicing, quotations, purchasing,
                        expenses, payroll, design studio, and more — all available as a typed REST API
                        for tablets, kiosks, desktop clients, and custom integrations.
                    </p>
                    <div class="docs-hero-tags">
                        <span class="docs-hero-tag"><i class="fa fa-layer-group"></i> Stock layers</span>
                        <span class="docs-hero-tag"><i class="fa fa-barcode"></i> SKU scan</span>
                        <span class="docs-hero-tag"><i class="fa fa-file-invoice-dollar"></i> Invoicing</span>
                        <span class="docs-hero-tag"><i class="fa fa-people-group"></i> Payroll</span>
                        <span class="docs-hero-tag"><i class="fa fa-percent"></i> Discounts</span>
                        <span class="docs-hero-tag"><i class="fa fa-building"></i> Multi-business</span>
                    </div>
                </div>
            </div>
            <div class="docs-reference-wrap">
                <div class="docs-reference-loading" id="docs-reference-loading">Loading API reference…</div>
                <div id="redoc-container"></div>
            </div>
        </main>
    </div>

    <div class="docs-toast" id="docs-toast" role="status" aria-live="polite">Copied to clipboard</div>

    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js" crossorigin="anonymous"></script>
    <script>
        (function () {
            const spec = @json($specJson);
            const specUrl = @json($specUrl);
            const apiBase = @json($apiBaseUrl);
            const toast = document.getElementById('docs-toast');
            const html = document.documentElement;
            const loadingEl = document.getElementById('docs-reference-loading');
            const redocEl = document.getElementById('redoc-container');

            function showToast(msg) {
                if (!toast) return;
                toast.textContent = msg || 'Copied to clipboard';
                toast.classList.add('is-visible');
                clearTimeout(showToast._t);
                showToast._t = setTimeout(function () {
                    toast.classList.remove('is-visible');
                }, 2000);
            }

            function copyText(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text).then(function () {
                        showToast();
                    });
                }
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast();
            }

            document.getElementById('docs-copy-base')?.addEventListener('click', function () {
                copyText(apiBase);
            });

            document.getElementById('docs-theme-toggle')?.addEventListener('click', function () {
                const next = html.getAttribute('data-docs-theme') === 'light' ? 'dark' : 'light';
                html.setAttribute('data-docs-theme', next);
                const icon = document.getElementById('docs-theme-icon');
                if (icon) {
                    icon.className = next === 'light' ? 'fa fa-sun' : 'fa fa-moon';
                }
                try {
                    localStorage.setItem('pos-api-docs-theme', next);
                } catch (e) {}
                setTimeout(function () {
                    if (redocEl) redocEl.innerHTML = '';
                    if (loadingEl) loadingEl.classList.remove('is-hidden');
                    mountRedoc();
                }, 50);
            });

            try {
                const saved = localStorage.getItem('pos-api-docs-theme');
                if (saved === 'light' || saved === 'dark') {
                    html.setAttribute('data-docs-theme', saved);
                    const icon = document.getElementById('docs-theme-icon');
                    if (icon) icon.className = saved === 'light' ? 'fa fa-sun' : 'fa fa-moon';
                }
            } catch (e) {}

            document.getElementById('docs-try-auth')?.addEventListener('click', function () {
                const target = document.querySelector('[data-section-id="section/Auth"]')
                    || document.querySelector('[data-section-id="tag/Auth"]');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            document.querySelectorAll('[data-docs-nav]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('[data-docs-nav]').forEach(function (a) {
                        a.classList.remove('is-active');
                    });
                    link.classList.add('is-active');
                    const hash = (link.getAttribute('href') || '').replace('#', '');
                    const section = document.querySelector('[data-section-id="' + hash + '"]');
                    if (section) {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });

            function redocTheme() {
                const dark = html.getAttribute('data-docs-theme') !== 'light';
                return {
                    colors: {
                        primary: { main: dark ? '#818cf8' : '#4f46e5' },
                        success: { main: '#34d399' },
                        text: { primary: dark ? '#f1f5f9' : '#0f172a' },
                        http: { get: '#34d399', post: '#818cf8', put: '#fbbf24', delete: '#f87171' },
                    },
                    typography: {
                        fontSize: '15px',
                        fontFamily: 'Inter, system-ui, sans-serif',
                        headings: { fontFamily: 'Inter, system-ui, sans-serif', fontWeight: '700' },
                        code: { fontFamily: 'JetBrains Mono, ui-monospace, monospace', fontSize: '13px' },
                    },
                    sidebar: {
                        backgroundColor: dark ? '#0f172a' : '#f8fafc',
                        textColor: dark ? '#94a3b8' : '#64748b',
                    },
                    rightPanel: {
                        backgroundColor: dark ? '#111827' : '#ffffff',
                    },
                };
            }

            function mountRedoc() {
                if (typeof Redoc === 'undefined' || !Redoc.init) {
                    if (loadingEl) loadingEl.textContent = 'Could not load API viewer.';
                    if (redocEl) {
                        redocEl.innerHTML = '<p style="padding:24px;color:#94a3b8;">Download the <a href="' + specUrl + '" style="color:#818cf8;">OpenAPI spec</a> instead.</p>';
                    }
                    return;
                }
                try {
                    Redoc.init(spec, {
                        scrollYOffset: 0,
                        hideDownloadButton: false,
                        expandResponses: '200,201',
                        jsonSampleExpandLevel: 2,
                        requiredPropsFirst: true,
                        theme: redocTheme(),
                    }, redocEl, function () {
                        if (loadingEl) loadingEl.classList.add('is-hidden');
                    });
                } catch (err) {
                    console.error(err);
                    if (loadingEl) {
                        loadingEl.textContent = 'Failed to render API docs.';
                    }
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', mountRedoc);
            } else {
                mountRedoc();
            }
        })();
    </script>
</body>
</html>
