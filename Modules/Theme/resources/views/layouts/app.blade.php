@php
    $__zeebrooUiThemesAllowed = ['night', 'light', 'light_blue', 'ocean', 'night_blue'];
    $__zeebrooUiThemeStored = auth()->check() ? get_settings('ui.theme', 'light') : null;
    $__ui_theme = ($__zeebrooUiThemeStored !== null && in_array((string) $__zeebrooUiThemeStored, $__zeebrooUiThemesAllowed, true))
        ? (string) $__zeebrooUiThemeStored
        : 'light';
@endphp
<!doctype html>
<html lang="en" data-theme="{{ $__ui_theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Overview' }}</title>
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <style>
        :root{--bg:#0f172a;--card:#111827;--text:#e5e7eb;--muted:#9ca3af;--border:#334155;--primary:#7c3aed;--btn-bg:#7c3aed;--btn-hover:#facc15}
        /* Light: yellow/amber accent, near-black text, warm grays (no blue primary) */
        html[data-theme="light"]{--bg:#fafaf9;--card:#ffffff;--text:#0a0a0a;--muted:#57534e;--border:#d6d3d1;--primary:#ca8a04;--btn-bg:#171717;--btn-hover:#facc15}
        html[data-theme="light"] .brand:before{background:#171717;color:#facc15}
        html[data-theme="light"] .avatar{background:#171717;color:#facc15}
        html[data-theme="light"] .sidebar{background:var(--card);}
        /* Light blue & white — cool grays */
        html[data-theme="light_blue"]{--bg:#f8fafc;--card:#ffffff;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--primary:#2563eb;--btn-bg:#1e293b;--btn-hover:#38bdf8}
        html[data-theme="light_blue"] #accountDropdownBtn{background:#ffffff!important;}
        html[data-theme="light_blue"] .brand:before{background:#2563eb;color:#ffffff;}
        html[data-theme="light_blue"] .avatar{background:#1e293b;color:#e0f2fe;}
        html[data-theme="light_blue"] .sidebar{background:var(--card);}
        /* Night — blue accents */
        html[data-theme="night_blue"]{--bg:#070b14;--card:#0f172a;--text:#e2e8f0;--muted:#94a3b8;--border:#1e293b;--primary:#3b82f6;--btn-bg:#2563eb;--btn-hover:#fcd34d}
        html[data-theme="night_blue"] #accountDropdownBtn{background:color-mix(in srgb,var(--card) 88%,transparent)!important;border-color:var(--border);}
        html[data-theme="night_blue"] .brand:before{background:#1d4ed8;color:#f8fafc;}
        html[data-theme="night_blue"] .avatar{background:#1e293b;color:#bae6fd;}
        html[data-theme="night_blue"] .sidebar{background:var(--card);}
        html[data-theme="ocean"]{--bg:#082f49;--card:#0c4a6e;--text:#e0f2fe;--muted:#bae6fd;--border:#0369a1;--primary:#06b6d4;--btn-bg:#0891b2;--btn-hover:#facc15}
        #accountDropdownBtn{background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 24%,var(--card)),var(--card));}
        html[data-theme="light"] #accountDropdownBtn{background:#ffffff!important;}
        body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,sans-serif}
        .layout{min-height:100vh}
        .sidebar{
            width:260px;
            background:linear-gradient(180deg,color-mix(in srgb,var(--card) 94%,#000),var(--card));
            border-right:1px solid var(--border);
            box-shadow:8px 0 24px rgba(0,0,0,.12);
            padding:24px 18px;
            position:fixed;
            left:0;
            top:0;
            bottom:0;
            z-index:30;
            overflow:auto;
        }
        .sidebar--employee-portal{
            background:linear-gradient(180deg,color-mix(in srgb,var(--primary) 10%,var(--card)),var(--card));
            border-right:1px solid color-mix(in srgb,var(--primary) 24%,var(--border));
        }
        .sidebar--employee-portal .brand:before{content:"HR";}
        .brand{font-weight:800;font-size:19px;letter-spacing:.2px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
        .brand:before{content:"SB";width:28px;height:28px;display:grid;place-items:center;border-radius:8px;background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 45%,#fff));color:#fff;font-size:11px;font-weight:800}
        .brand.brand--logo{display:block;margin-bottom:18px;text-decoration:none;line-height:0}
        .brand.brand--logo:before{display:none;content:none}
        .brand.brand--logo img{display:block;width:100%;max-width:224px;height:auto;max-height:52px;object-fit:contain;object-position:left center}
        .menu-section{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin:3px 2px 1px}
        .menu{display:flex;flex-direction:column;gap:2px}
        .menu a{display:flex;align-items:center;gap:7px;padding:6px 8px;border:1px solid transparent;border-radius:8px;text-decoration:none;color:var(--text);font-weight:500;font-size:12px;transition:all .2s ease}
        .menu a i{width:13px;text-align:center;color:var(--muted);font-size:11px}
        .menu a.active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent)}
        .menu a:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:transparent;font-weight:700}
        .menu a.active i,.menu a:hover i{color:var(--primary)}
        @keyframes menu-loan-due-sheen{
            0%,100%{border-color:color-mix(in srgb,#f97316 38%,var(--border));background:color-mix(in srgb,#f97316 10%,transparent);color:color-mix(in srgb,var(--text) 88%,#fef3c7);}
            50%{border-color:color-mix(in srgb,#fb923c 72%,var(--border));background:color-mix(in srgb,#ea580c 18%,transparent);color:color-mix(in srgb,#ffedd5 35%,var(--text));}
        }
        @keyframes menu-loan-due-icon{
            0%,100%{color:#f97316!important;transform:scale(1);}
            50%{color:#fde68a!important;transform:scale(1.06);}
        }
        .menu a.menu-loan-mgmt--due{font-weight:650;animation:menu-loan-due-sheen 2.35s ease-in-out infinite;}
        .menu a.menu-loan-mgmt--due i{animation:menu-loan-due-icon 1.9s ease-in-out infinite;}
        .menu a.menu-loan-mgmt--due.active{animation:menu-loan-due-sheen 2.35s ease-in-out infinite;border-color:color-mix(in srgb,#f97316 55%,var(--primary));}
        @keyframes menu-rental-due-sheen{
            0%,100%{border-color:color-mix(in srgb,#ef4444 42%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);color:color-mix(in srgb,var(--text) 88%,#fecaca);}
            50%{border-color:color-mix(in srgb,#f87171 72%,var(--border));background:color-mix(in srgb,#dc2626 20%,transparent);color:color-mix(in srgb,#fecaca 40%,var(--text));}
        }
        @keyframes menu-rental-due-icon{
            0%,100%{color:#f87171!important;transform:scale(1);}
            50%{color:#fecaca!important;transform:scale(1.06);}
        }
        .menu a.menu-rentals--due{font-weight:650;animation:menu-rental-due-sheen 2.35s ease-in-out infinite;}
        .menu a.menu-rentals--due i{animation:menu-rental-due-icon 1.9s ease-in-out infinite;}
        .menu a.menu-rentals--due.active{animation:menu-rental-due-sheen 2.35s ease-in-out infinite;border-color:color-mix(in srgb,#ef4444 62%,var(--primary));}

        @keyframes menu-payroll-due-sheen{
            0%,100%{border-color:color-mix(in srgb,#ef4444 42%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);color:color-mix(in srgb,var(--text) 88%,#fecaca);}
            50%{border-color:color-mix(in srgb,#f87171 72%,var(--border));background:color-mix(in srgb,#dc2626 20%,transparent);color:color-mix(in srgb,#fecaca 40%,var(--text));}
        }
        @keyframes menu-payroll-due-icon{
            0%,100%{color:#f87171!important;transform:scale(1);}
            50%{color:#fecaca!important;transform:scale(1.06);}
        }
        .menu a.menu-payroll--due{font-weight:650;animation:menu-payroll-due-sheen 2.35s ease-in-out infinite;}
        .menu a.menu-payroll--due i{animation:menu-payroll-due-icon 1.9s ease-in-out infinite;}
        .menu a.menu-payroll--due.active{animation:menu-payroll-due-sheen 2.35s ease-in-out infinite;border-color:color-mix(in srgb,#ef4444 62%,var(--primary));}
        .menu a.menu-payroll-cycles--due{font-weight:650;animation:menu-payroll-due-sheen 2.35s ease-in-out infinite;}
        .menu a.menu-payroll-cycles--due i{animation:menu-payroll-due-icon 1.9s ease-in-out infinite;}
        .menu a.menu-payroll-cycles--due.active{animation:menu-payroll-due-sheen 2.35s ease-in-out infinite;border-color:color-mix(in srgb,#ef4444 62%,var(--primary));}

        @keyframes menu-loan-due-dot{
            from{opacity:.72;transform:scale(1);}
            to{opacity:1;transform:scale(1.18);}
        }
        .menu-loan-mgmt__pulse{
            flex-shrink:0;margin-left:auto;width:8px;height:8px;border-radius:50%;
            background:linear-gradient(135deg,#f97316,#ef4444);
            box-shadow:0 0 0 2px color-mix(in srgb,#f97316 28%,transparent);
            animation:menu-loan-due-dot 1.2s ease-in-out infinite alternate;
        }
        .menu-rentals__pulse{
            flex-shrink:0;margin-left:auto;width:8px;height:8px;border-radius:50%;
            background:linear-gradient(135deg,#ef4444,#b91c1c);
            box-shadow:0 0 0 2px color-mix(in srgb,#ef4444 32%,transparent);
            animation:menu-rental-due-dot 1.2s ease-in-out infinite alternate;
        }
        @keyframes menu-rental-due-dot{
            from{opacity:.72;transform:scale(1);}
            to{opacity:1;transform:scale(1.18);}
        }
        @media (prefers-reduced-motion:reduce){
            .menu a.menu-loan-mgmt--due,.menu a.menu-loan-mgmt--due i{animation:none;}
            .menu a.menu-loan-mgmt--due{border-color:color-mix(in srgb,#f97316 50%,var(--border));background:color-mix(in srgb,#f97316 12%,transparent);}
            .menu a.menu-loan-mgmt--due i{color:#fb923c!important;}
            .menu-loan-mgmt__pulse{animation:none;}
            .menu a.menu-rentals--due,.menu a.menu-rentals--due i{animation:none;}
            .menu a.menu-rentals--due{border-color:color-mix(in srgb,#ef4444 55%,var(--border));background:color-mix(in srgb,#ef4444 14%,transparent);}
            .menu a.menu-rentals--due i{color:#f87171!important;}
            .menu-rentals__pulse{animation:none;}
            .menu a.menu-payroll--due,.menu a.menu-payroll--due i{animation:none;}
            .menu a.menu-payroll--due{border-color:color-mix(in srgb,#ef4444 55%,var(--border));background:color-mix(in srgb,#ef4444 14%,transparent);}
            .menu a.menu-payroll--due i{color:#f87171!important;}
            .menu a.menu-payroll-cycles--due,.menu a.menu-payroll-cycles--due i{animation:none;}
            .menu a.menu-payroll-cycles--due{border-color:color-mix(in srgb,#ef4444 55%,var(--border));background:color-mix(in srgb,#ef4444 14%,transparent);}
            .menu a.menu-payroll-cycles--due i{color:#f87171!important;}
        }
        .menu-group-title{display:flex;align-items:center;gap:7px;padding:6px 8px;border:1px solid transparent;border-radius:8px;color:var(--text);font-size:11px;font-weight:600;background:transparent}
        a.menu-group-title{text-decoration:none;transition:border-color .2s ease,background .2s ease}
        a.menu-group-title:hover,a.menu-group-title.active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent)}
        a.menu-group-title i{color:var(--muted);font-size:11px}
        a.menu-group-title:hover i,a.menu-group-title.active i{color:var(--primary)}
        .submenu{display:flex;flex-direction:column;gap:1px;margin-left:10px;padding-left:7px;border-left:1px dashed color-mix(in srgb,var(--primary) 35%,var(--border))}
        .submenu a{padding:5px 8px;font-size:11px}
        /* Payroll hub: extra indent under main Payroll link */
        .menu-payroll-nested{display:flex;flex-direction:column;gap:2px}
        .menu-payroll-nested__sub{
            display:flex;flex-direction:column;gap:1px;margin:2px 0 4px 4px;padding:4px 0 6px 12px;
            border-left:1px dashed color-mix(in srgb,var(--primary) 28%,var(--border));
        }
        .menu-payroll-nested__sub a{
            display:flex;align-items:center;gap:8px;padding:5px 8px 5px 6px;font-size:11.5px;border-radius:8px;text-decoration:none;color:inherit;
        }
        .menu-payroll-nested__sub a i{width:15px;text-align:center;font-size:11px;opacity:.88;color:var(--muted)}
        .menu-payroll-nested__sub a:hover i,.menu-payroll-nested__sub a.active i{color:var(--primary)}
        .content{padding:0;margin-left:297px;min-height:100vh;border-left:1px solid var(--border)}
        .content--minimal{margin-left:0;border-left:none;max-width:none;width:100%}
        .content--pos-only .content-inner{padding:8px 10px 12px;max-width:100%}
        .content--pos-only{min-height:100vh}
        body.pos-walking-active{overflow:hidden;height:100%}
        body.pos-walking-active .layout,body.pos-walking-active .content,body.pos-walking-active .content-inner{height:100vh;max-height:100vh;overflow:hidden}
        body.pos-walking-active .content-inner{padding:0!important;max-width:100%}
        body.pos-walking-active .pos-online__top,body.pos-walking-active .pos-page__top{position:fixed;top:0;left:0;right:0;z-index:300;margin:0;border-radius:0;border-left:0;border-right:0;border-top:0;box-shadow:0 4px 20px rgba(0,0,0,.18)}
        body.pos-walking-active{--pos-walking-cart-w:min(320px,30vw);--pos-walking-sale-w:min(400px,34vw);}
        body.pos-walking-active .pos-online__scroll,body.pos-walking-active .pos-page__scroll{margin-top:var(--pos-walking-top-h,52px);height:calc(100vh - var(--pos-walking-top-h,52px));max-height:calc(100vh - var(--pos-walking-top-h,52px));overflow:hidden;box-sizing:border-box;display:flex;flex-direction:column;}
        body.pos-walking-active .pos-online__sale-body,body.pos-walking-active .pos-register__sale-body{flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;}
        body.pos-walking-active .pos-online__sale-panel .pos-online__cart-list,body.pos-walking-active .pos-register__sale-panel .pos-cart-list{flex:1;min-height:60px;max-height:none;}
        body.pos-walking-active .pos-online__body{flex:1;min-height:0;}
        body.pos-walking-active .pos-online__cats-bar,body.pos-walking-active .pos-register__browse{flex-shrink:0;background:color-mix(in srgb,var(--card) 96%,transparent);border-bottom:1px solid var(--border);}
        body.pos-walking-active .pos-online__catalog-main{flex:1;min-height:0;min-width:0;display:flex;flex-direction:column;}
        body.pos-walking-active .pos-online__grid-wrap,body.pos-walking-active .pos-register__catalog .pos-panel__body{flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;}
        body.pos-walking-active .pos-register__catalog .pos-products{max-height:none;}
        body.pos-walking-active .pos-online__checkout-body,body.pos-walking-active .pos-fixed-cart > .pos-panel__body{flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;padding:0;}
        body.pos-walking-active .pos-layout{flex:1;min-height:0;}
        body.pos-walking-active .pos-page__scroll .muted,body.pos-walking-active .pos-page__scroll > .pos-banner{display:none;}
        body.pos-walking-active .pos-online--walking,body.pos-walking-active .pos-page--walking{height:100vh;max-height:100vh;overflow:hidden;margin:0;width:100%;max-width:100%}
        body.pos-walking-active .pos-page--walking > .pcat-page-card{height:100%;padding:0!important;border:none;border-radius:0;background:transparent;box-shadow:none}
        .navbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 28px;border-bottom:1px solid var(--border);background:var(--card);position:sticky;top:0;z-index:20}
        .navtitle{font-weight:700}
        .navmeta{color:var(--muted);font-size:14px}
        .nav-right{display:flex;align-items:center;gap:10px}
        .navchip{display:inline-block;border:1px solid var(--border);border-radius:999px;padding:5px 10px;color:var(--muted);font-size:13px}
        .user-dropdown{position:relative}
        .user-trigger{display:flex;align-items:center;gap:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);padding:7px 10px;border-radius:12px;cursor:pointer}
        a.user-trigger{text-decoration:none;box-sizing:border-box}
        a.user-trigger.nav-business-profile{padding:4px 8px;gap:6px;border-radius:8px;font-size:12px;font-weight:600}
        a.user-trigger.nav-business-profile i{font-size:11px;width:12px;text-align:center}
        a.user-trigger.nav-business-profile--active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 14%,transparent)}
        .avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 35%,#fff));font-weight:700;color:#fff}
        .user-menu{position:absolute;right:0;top:calc(100% + 8px);min-width:280px;background:color-mix(in srgb,var(--card) 94%,transparent);border:1px solid var(--border);border-radius:14px;padding:12px;display:none;box-shadow:0 18px 36px rgba(0,0,0,.28);backdrop-filter:blur(10px)}
        .user-menu.open{display:block}
        .menu-head{padding:8px 10px;border-bottom:1px solid var(--border);margin-bottom:8px}
        .menu-name{font-weight:600}
        .menu-email{font-size:13px;color:var(--muted)}
        .menu-row{display:flex;justify-content:space-between;gap:12px;padding:8px 10px;font-size:14px}
        .pkg-badge{font-size:12px;border:1px solid var(--border);border-radius:999px;padding:3px 8px;color:var(--muted)}
        .dropdown-action-btn{
            width:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            box-sizing:border-box;
            border-radius:10px;
            padding:10px 12px;
            font-size:13px;
            font-weight:600;
            line-height:1.2;
            background:var(--btn-bg);
            color:#fff !important;
            text-decoration:none;
            border:1px solid color-mix(in srgb,var(--btn-bg) 72%,var(--border));
            white-space:nowrap;
        }
        .dropdown-action-btn:hover{
            background:var(--btn-hover);
            color:#111827 !important;
        }
        .dropdown-select{
            width:100%;
            box-sizing:border-box;
            border:1px solid var(--border);
            background:color-mix(in srgb,var(--card) 90%,transparent);
            color:var(--text);
            border-radius:10px;
            padding:9px 10px;
            font-size:13px;
            outline:none;
        }
        .dropdown-select:focus{border-color:var(--primary)}
        .nav-portal-employer-form{margin:0;display:flex;align-items:center}
        .nav-portal-employer-select{max-width:min(260px,42vw);min-width:120px;width:auto}
        .theme-switch{display:flex;justify-content:space-between;align-items:center;padding:8px 10px}
        .switch{position:relative;width:46px;height:26px}
        .switch input{opacity:0;width:0;height:0}
        .slider{position:absolute;inset:0;cursor:pointer;background:#475569;border-radius:999px;transition:.2s}
        .slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}
        .switch input:checked + .slider{background:#22c55e}
        .switch input:checked + .slider:before{transform:translateX(20px)}
        .content-inner{padding:28px}
        /* Full-viewport workspace (e.g. AI chat) inside main chrome */
        .content.content--chat-workspace{display:flex;flex-direction:column;box-sizing:border-box;height:100vh;height:100dvh;overflow:hidden}
        .content-inner--chat-workspace{flex:1;display:flex;flex-direction:column;min-height:0;padding:0!important}
        .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;max-width:920px}
        .muted{color:var(--muted)}
        .chip{display:inline-block;border:1px solid var(--border);padding:6px 12px;border-radius:999px;margin:8px 8px 0 0}
        button,.linkbtn{border:0;border-radius:10px;padding:10px 14px;background:var(--btn-bg);color:#fff;cursor:pointer;text-decoration:none;display:inline-block;transition:all .2s ease}
        button:hover,.linkbtn:hover{background:var(--btn-hover);color:var(--btn-hover-fg);transform:translateY(-1px)}
        .navbar-portal-meta{font-size:13px;color:var(--muted);font-weight:600;max-width:min(100%,42ch);line-height:1.35}
        /* ── Mobile sidebar overlay ──────────────────────────────── */
        @media (max-width:900px){
            .sidebar{position:fixed;transform:translateX(-100%);transition:transform .26s cubic-bezier(.4,0,.2,1)!important;z-index:31;width:260px!important;padding:24px 18px!important;overflow:auto;}
            .sidebar.sidebar--mobile-open{transform:translateX(0);}
            .content{margin-left:0!important;border-left:0;transition:none!important;}
            .sidebar-toggle-btn{display:none!important;}
            .navbar-hamburger{display:flex!important;}
        }
        /* ── Sidebar mobile backdrop ─────────────────────────────── */
        .sidebar-mobile-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.48);backdrop-filter:blur(3px);z-index:30;}
        .sidebar-mobile-backdrop.is-open{display:block;}
        /* ── Sidebar collapse (desktop) ──────────────────────────── */
        @media (min-width:901px){
            .sidebar{transition:width .22s cubic-bezier(.4,0,.2,1),padding .22s cubic-bezier(.4,0,.2,1);}
            .content{transition:margin-left .22s cubic-bezier(.4,0,.2,1);}
        }
        .sidebar--collapsed{width:58px;padding:20px 8px;overflow:hidden;}
        .sidebar--collapsed .brand{display:none;}
        .sidebar--collapsed .menu-section{display:none;}
        .sidebar--collapsed .menu a span,
        .sidebar--collapsed .menu-group-title span,
        .sidebar--collapsed .submenu,
        .sidebar--collapsed .menu-payroll-nested__sub,
        .sidebar--collapsed .menu-loan-mgmt__pulse,
        .sidebar--collapsed .menu-rentals__pulse{display:none;}
        .sidebar--collapsed .menu a{justify-content:center;padding:8px 6px;}
        .sidebar--collapsed .menu a i{width:auto;margin:0;font-size:14px;}
        .sidebar--collapsed .menu-group-title{justify-content:center;padding:8px 6px;}
        .sidebar--collapsed .menu-group-title i{width:auto;margin:0;font-size:14px;}
        .sidebar--collapsed .menu-payroll-nested{gap:0;}
        .content--sidebar-collapsed{margin-left:80px;}
        /* ── Sidebar toggle & hamburger ──────────────────────────── */
        .sidebar-toggle-btn{
            display:flex;align-items:center;justify-content:center;
            width:28px;height:28px;border-radius:7px;
            border:1px solid var(--border);background:transparent;color:var(--muted);
            cursor:pointer;font-size:11px;flex-shrink:0;padding:0;
            transition:color .15s,border-color .15s;
        }
        .sidebar-toggle-btn:hover{color:var(--primary);border-color:color-mix(in srgb,var(--primary) 55%,var(--border));}
        .navbar-hamburger{
            display:none;align-items:center;justify-content:center;
            width:34px;height:34px;border-radius:9px;
            border:1px solid var(--border);background:transparent;color:var(--text);
            cursor:pointer;font-size:14px;padding:0;flex-shrink:0;
        }
        .navbar-hamburger:hover{border-color:var(--primary);color:var(--primary);}
        /* ── Sidebar search ─────────────────────────────────────────── */
        .sidebar-search{position:relative;margin-bottom:12px;flex-shrink:0;}
        .sidebar-search__input{width:100%;box-sizing:border-box;padding:7px 10px 7px 30px;font-size:12px;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 86%,transparent);color:var(--text);outline:none;transition:border-color .15s,background .15s;}
        .sidebar-search__input::placeholder{color:var(--muted);}
        .sidebar-search__input:focus{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));background:color-mix(in srgb,var(--card) 94%,transparent);}
        .sidebar-search__icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:10px;pointer-events:none;}
        .sidebar-search__clear{position:absolute;right:7px;top:50%;transform:translateY(-50%);width:16px;height:16px;border-radius:50%;border:none;background:color-mix(in srgb,var(--muted) 28%,transparent);color:var(--muted);cursor:pointer;display:none;align-items:center;justify-content:center;font-size:9px;padding:0;line-height:1;}
        .sidebar-search__clear.visible{display:flex;}
        .sidebar-search__clear:hover{background:color-mix(in srgb,var(--muted) 46%,transparent);}
        .sidebar-suggestions{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 14px 30px rgba(0,0,0,.26);overflow:hidden;z-index:50;display:none;max-height:260px;overflow-y:auto;}
        .sidebar-suggestions.is-open{display:block;}
        .sidebar-suggestion{display:flex;align-items:center;gap:8px;padding:7px 10px;font-size:12px;font-weight:500;color:var(--text);text-decoration:none;cursor:pointer;transition:background .1s;}
        .sidebar-suggestion:hover,.sidebar-suggestion.is-active{background:color-mix(in srgb,var(--primary) 13%,transparent);}
        .sidebar-suggestion i{width:13px;text-align:center;font-size:11px;color:var(--muted);flex-shrink:0;}
        .sidebar-suggestion:hover i,.sidebar-suggestion.is-active i{color:var(--primary);}
        .sidebar-suggestion__label{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .sidebar-suggestion__section{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);flex-shrink:0;opacity:.7;}
        .sidebar-suggestions__empty{padding:10px 12px;font-size:12px;color:var(--muted);text-align:center;}
        .sidebar--collapsed .sidebar-search{display:none;}
        /* ── Collapsible sidebar groups ──────────────────────────── */
        div.menu-group-title{cursor:pointer;user-select:none;}
        .menu-group-chevron{margin-left:auto;font-size:9px;color:var(--muted);flex-shrink:0;transition:transform .2s ease;pointer-events:none;}
        div.menu-group-title.group--collapsed .menu-group-chevron{transform:rotate(-90deg);}
        .submenu{overflow:hidden;transition:max-height .28s cubic-bezier(.4,0,.2,1);}
        .sidebar--collapsed .submenu{overflow:visible!important;max-height:none!important;transition:none!important;}
    </style>
</head>
@php
    $posWalkingCustomer = (bool) session('pos_walking_customer', true);
    $posOnlyShell = ($posWalkingCustomer && request()->routeIs('pos.online', 'pos.register', 'pos.checkout'))
        || request()->routeIs('hr.portal.pos-online', 'hr.portal.pos-online.checkout');
@endphp
<body @class(['pos-walking-active' => $posOnlyShell])>
<div class="layout">
    @php
        $minimalAppShell = filter_var($minimalAppShell ?? false, FILTER_VALIDATE_BOOLEAN);
        $employeePortal = filter_var($employeePortal ?? false, FILTER_VALIDATE_BOOLEAN);
        $chatWorkspace = filter_var($chatWorkspace ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($posOnlyShell) {
            $minimalAppShell = true;
        }
        $navBusiness = \Modules\Business\Models\Business::currentForNavbar(auth()->user());
        $navBusinesses = \Modules\Business\Models\Business::allForNavbar(auth()->user());

        // Load feature flags first — used to gate sidebar sections below.
        $businessFeatures = $navBusiness
            ? (function () use ($navBusiness) {
                $saved = (array) ($navBusiness->getSetting('business.features', []) ?: []);
                $defaults = array_fill_keys(array_keys(config('features.list', [])), true);
                return !empty($saved) ? array_merge($defaults, array_map('boolval', $saved)) : $defaults;
            })()
            : [];
        $featureOn = fn (string $key) => (bool) ($businessFeatures[$key] ?? true);

        $billFeatureOn = $navBusiness && $featureOn('bill_management');
        $showSidebarFinanceOverviewLink = $navBusiness && $billFeatureOn && Route::has('account.finance.index');
        $showSidebarLoansLink = $navBusiness && $billFeatureOn && $navBusiness->loans()->exists();
        $sidebarLoanDueHighlight = $showSidebarLoansLink && $navBusiness
            ? app(\Modules\Account\Services\LoanOverviewTooltipService::class)->businessHasOverdueLoanInstallments($navBusiness)
            : false;
        $showSidebarRentalsLink = $navBusiness && $billFeatureOn && $navBusiness->rentals()->exists();
        $sidebarRentalDueHighlight = $showSidebarRentalsLink && $navBusiness
            ? app(\Modules\Account\Services\RentalService::class)->businessHasOverdueRentalPayments($navBusiness)
            : false;
        $showSidebarBillsLink = $navBusiness && $billFeatureOn && $navBusiness->bills()->exists();
        $showSidebarBudgetsLink = $navBusiness && $billFeatureOn && Route::has('budget.index');
        $showSidebarInvestmentsLink = $navBusiness && $billFeatureOn && Route::has('account.investments.index');

        // Catalog — only visible when Product Management feature is enabled.
        $productFeatureOn = $navBusiness && $featureOn('product_management');
        $showSidebarProductBrandsLink = $navBusiness && Route::has('product.brands.index') && $productFeatureOn;
        $showSidebarProductCategoriesLink = $navBusiness && Route::has('product.categories.index') && $productFeatureOn;
        $showSidebarProductUnitsLink = $navBusiness && Route::has('product.units.index') && $productFeatureOn;
        $showSidebarProductsLink = $navBusiness && Route::has('product.index') && $productFeatureOn;
        $showSidebarBarcodesLink = $navBusiness && Route::has('product.barcodes.index') && $productFeatureOn;
        $showSidebarDiscountsLink = $navBusiness && Route::has('product.discounts.index') && $productFeatureOn;
        $showSidebarCampaignsLink = $navBusiness && Route::has('product.campaigns.index') && $productFeatureOn;
        $showSidebarProductSection = $showSidebarProductBrandsLink
            || $showSidebarProductCategoriesLink
            || $showSidebarProductUnitsLink
            || $showSidebarProductsLink
            || $showSidebarBarcodesLink
            || $showSidebarDiscountsLink
            || $showSidebarCampaignsLink;

        // Stock Management — only visible when Stock Management feature is enabled.
        $stockFeatureOn = $navBusiness && $featureOn('stock_management');
        $showSidebarPurchasesLink = $navBusiness && Route::has('purchase.index') && $stockFeatureOn;
        $showSidebarGrnLink = $navBusiness && Route::has('purchase.grn.index') && $stockFeatureOn;
        $showSidebarSuppliersLink = $navBusiness && Route::has('purchase.suppliers.index') && $stockFeatureOn;
        $showSidebarChequesLink = $navBusiness && Route::has('purchase.cheques.index') && $stockFeatureOn;
        $showSidebarStockAuditLink = $navBusiness && Route::has('pos.stock-audits.index') && $stockFeatureOn;
        $showSidebarPurchaseSection = $showSidebarPurchasesLink
            || $showSidebarGrnLink
            || $showSidebarSuppliersLink
            || $showSidebarChequesLink
            || $showSidebarStockAuditLink;

        // POS — only visible when Point of Sale feature is enabled.
        $posFeatureOn = $navBusiness && $featureOn('point_of_sale');
        $showSidebarPosRegisterLink = $navBusiness && Route::has('pos.online') && $posFeatureOn;
        $showSidebarPosSalesLink = $navBusiness && Route::has('pos.sales.index') && $posFeatureOn;
        $showSidebarPosEodLink = $navBusiness && Route::has('pos.end-of-day') && $posFeatureOn;
        $showSidebarPosCustomersLink = $navBusiness && Route::has('pos.customers.index') && $posFeatureOn;
        $showSidebarPosReturnsLink = $navBusiness && Route::has('pos.returns.index') && $posFeatureOn;
        // Sales Quotations and Invoices — only visible when Sales Management feature is enabled.
        $salesFeatureOn = $navBusiness && $featureOn('sales_management');
        $showSidebarQuotationsLink = $navBusiness && Route::has('sales.quotations.index') && $salesFeatureOn;
        $showSidebarInvoicesLink   = $navBusiness && Route::has('sales.invoices.index') && $salesFeatureOn;
        $showSidebarSalesOrdersLink = $navBusiness && Route::has('sales.orders.index') && $salesFeatureOn;

        // Hub link shows whenever the Sales section is visible.
        $showSidebarPosSection = $showSidebarPosRegisterLink || $showSidebarPosSalesLink
            || $showSidebarQuotationsLink || $showSidebarInvoicesLink || $showSidebarSalesOrdersLink;
        $showSidebarPosHubLink = $navBusiness && Route::has('pos.index') && $showSidebarPosSection;

        $showSidebarCrmLink = $navBusiness && Route::has('crm.projects.index') && $featureOn('crm');
        $showSidebarBrandMgmtSection = $navBusiness && Route::has('pos.brand-mgmt.brands.index') && $featureOn('event_management');
        $showSidebarProjectManageLink = $navBusiness && Route::has('pm.projects.index') && $featureOn('project_management');
        $mailFeatureOn = $navBusiness && $featureOn('mail');
        $showSidebarMailLink = $navBusiness && Route::has('mail.inbox.index') && $mailFeatureOn;
        $sidebarMailUnreadCount = $showSidebarMailLink
            ? \Modules\Mail\Models\MailMessage::where('business_id', $navBusiness->id)->where('direction', 'inbound')->where('is_read', false)->count()
            : 0;

        $showSidebarFilesLink = $navBusiness && (
            $navBusiness->fileManagerFiles()->exists() || $navBusiness->fileManagerFolders()->exists()
        );
        $showSidebarAutomationLink = $navBusiness && Route::has('automations.index') && $featureOn('automation_editor');
        $showSidebarDeveloperToolsLink = $navBusiness && Route::has('developers.index') && $featureOn('developers');
        $showSidebarDesignStudioLink = $navBusiness && Route::has('designstudio.index') && $featureOn('social_media_campaign');
        $showSidebarServiceLink = $navBusiness && Route::has('service.catalog.index') && $featureOn('service_management');
        $showSidebarServicePosLink = $navBusiness && Route::has('service.pos.index') && $featureOn('service_management');
        $showSidebarRestaurantLink = $navBusiness && Route::has('restaurant.orders.index') && $featureOn('restaurant');
        $showSidebarDocumentationLink = $navBusiness
            && Route::has('documentation.documents.index')
            && \Modules\Documentation\Models\Document::where('business_id', $navBusiness->id)->exists();
        $showSidebarPropertiesLink = $navBusiness
            ? \Modules\Account\Models\Property::query()->where('business_id', $navBusiness->id)->exists()
            : false;
        $showSidebarModificationsLink = $navBusiness
            && Route::has('modification.index')
            && $navBusiness->modifications()->exists();
        $sidebarBillDueHighlight = $showSidebarBillsLink && $navBusiness
            ? app(\Modules\Account\Services\BillService::class)->businessHasOverdueBillPayments($navBusiness)
            : false;
        $showSidebarFinanceSection = $showSidebarFinanceOverviewLink
            || $showSidebarBillsLink
            || $showSidebarLoansLink
            || $showSidebarRentalsLink
            || $showSidebarPropertiesLink
            || $showSidebarModificationsLink
            || $showSidebarBudgetsLink
            || $showSidebarInvestmentsLink;
        $hrFeatureOn = $navBusiness && $featureOn('human_resources');
        $hrPayrollOptedIn = $navBusiness
            ? (bool) get_settings('hr.payroll.opted_in', false, $navBusiness)
            : false;
        $sidebarPayrollOverdueHighlight = false;
        $sidebarPayrollCyclesOverdueHighlight = false;
        if ($navBusiness && $hrPayrollOptedIn) {
            $hrSummary = app(\Modules\HRManagement\Services\HrHubSummaryService::class)->forBusiness($navBusiness);
            $pvoAside = $hrSummary['previous_month_payroll_overdue'] ?? [];
            $sidebarPayrollOverdueHighlight = is_array($pvoAside) && (($pvoAside['overdue'] ?? false) === true);
            $sidebarPayrollCyclesOverdueHighlight = $sidebarPayrollOverdueHighlight;
        }
        $accounts = $navBusiness
            ? \Modules\Account\Models\Account::with(['bankType', 'bank', 'warehouse'])
                ->where('user_id', auth()->id())
                ->where('business_id', $navBusiness->id)
                ->latest()
                ->get()
            : collect();
        $selectedAccountId = (int) session('selected_account_id');
        $assignedAccount = $accounts->firstWhere('id', $selectedAccountId) ?: $accounts->first();
        if ($assignedAccount && $selectedAccountId !== (int) $assignedAccount->id) {
            session(['selected_account_id' => $assignedAccount->id]);
        }
        if (!$assignedAccount) {
            session()->forget('selected_account_id');
        }
        $showSidebarSettingsSection = $navBusiness && $assignedAccount;
        if ($employeePortal && isset($portalEmployerBusiness) && $portalEmployerBusiness) {
            $navBusiness = $portalEmployerBusiness;
            $navBusinesses = collect([$portalEmployerBusiness]);
            $accounts = collect();
            $assignedAccount = null;
            $showSidebarSettingsSection = false;
            $showSidebarFinanceOverviewLink = false;
            $showSidebarFinanceSection = false;
            $showSidebarLoansLink = false;
            $showSidebarRentalsLink = false;
            $showSidebarBillsLink = false;
            $showSidebarBudgetsLink = false;
            $showSidebarInvestmentsLink = false;
            $showSidebarProductBrandsLink = false;
            $showSidebarProductCategoriesLink = false;
            $showSidebarProductUnitsLink = false;
            $showSidebarProductsLink = false;
            $showSidebarProductSection = false;
            $stockFeatureOn = false;
            $showSidebarPurchasesLink = false;
            $showSidebarGrnLink = false;
            $showSidebarSuppliersLink = false;
            $showSidebarChequesLink = false;
            $showSidebarStockAuditLink = false;
            $showSidebarPurchaseSection = false;
            $showSidebarPosRegisterLink = false;
            $showSidebarPosHubLink = false;
            $showSidebarPosSalesLink = false;
            $showSidebarPosEodLink = false;
            $showSidebarPosCustomersLink = false;
            $showSidebarPosReturnsLink = false;
            $showSidebarPosSection = false;
            $showSidebarQuotationsLink = false;
            $showSidebarCrmLink = false;
            $showSidebarBrandMgmtSection = false;
            $showSidebarProjectManageLink = false;
            $showSidebarMailLink = false;
            $showSidebarFilesLink = false;
            $showSidebarPropertiesLink = false;
            $showSidebarModificationsLink = false;
            $showSidebarAutomationLink = false;
            $showSidebarDeveloperToolsLink = false;
            $showSidebarDesignStudioLink = false;
            $showSidebarRestaurantLink = false;
            $showSidebarDocumentationLink = false;
            $sidebarLoanDueHighlight = false;
            $sidebarRentalDueHighlight = false;
            $sidebarBillDueHighlight = false;
            $hrFeatureOn = false;
            $sidebarPayrollOverdueHighlight = false;
            $sidebarPayrollCyclesOverdueHighlight = false;
        }
    @endphp
    <div id="sidebarMobileBackdrop" class="sidebar-mobile-backdrop" aria-hidden="true"></div>
    @unless($minimalAppShell)
    <aside id="appSidebar" class="sidebar{{ $employeePortal ? ' sidebar--employee-portal' : '' }}">
        @if($employeePortal)
            <div class="brand">{{ __('HR portal') }}</div>
            <nav class="menu" aria-label="{{ __('Employee HR portal navigation') }}">
                <div class="menu-section">{{ __('Self-service') }}</div>
                <a href="{{ route('hr.portal.dashboard') }}" class="{{ request()->routeIs('hr.portal.dashboard') ? 'active' : '' }}"><i class="fa fa-house" aria-hidden="true"></i><span>{{ __('Home') }}</span></a>
                <a href="{{ route('hr.portal.profile') }}" class="{{ request()->routeIs('hr.portal.profile') ? 'active' : '' }}"><i class="fa fa-user" aria-hidden="true"></i><span>{{ __('My profile') }}</span></a>
                <a href="{{ route('hr.portal.leaves') }}" class="{{ request()->routeIs('hr.portal.leaves') ? 'active' : '' }}"><i class="fa fa-calendar-days" aria-hidden="true"></i><span>{{ __('My leaves') }}</span></a>
                <a href="{{ route('hr.portal.complaints') }}" class="{{ request()->routeIs(['hr.portal.complaints', 'hr.portal.complaints.store']) ? 'active' : '' }}"><i class="fa fa-comments" aria-hidden="true"></i><span>{{ __('Complaints') }}</span></a>
                <a href="{{ route('hr.portal.salary') }}" class="{{ request()->routeIs('hr.portal.salary') ? 'active' : '' }}"><i class="fa fa-money-check-dollar" aria-hidden="true"></i><span>{{ __('My salary') }}</span></a>
                @if(Route::has('hr.portal.pos-online'))
                    <a href="{{ route('hr.portal.pos-online') }}" class="{{ request()->routeIs('hr.portal.pos-online', 'hr.portal.pos-online.checkout') ? 'active' : '' }}"><i class="fa fa-store" aria-hidden="true"></i><span>{{ __('POS Online') }}</span></a>
                @endif
                @if(Route::has('dashboard') && auth()->user() && ! auth()->user()->isHrPortalOnlyUser())
                    <div class="menu-section">{{ __('More') }}</div>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa fa-briefcase" aria-hidden="true"></i><span>{{ __('Workspace') }}</span></a>
                @endif
            </nav>
        @else
        <a href="{{ route('dashboard') }}" class="brand brand--logo" aria-label="Zeebroo">
            <img src="{{ asset('logo.png') }}" alt="Zeebroo" width="224" height="75">
        </a>
        <div class="sidebar-search" id="sidebarSearchWrap">
            <i class="fa fa-magnifying-glass sidebar-search__icon" aria-hidden="true"></i>
            <input type="text" class="sidebar-search__input" id="sidebarSearchInput"
                placeholder="Quick search…" autocomplete="off" spellcheck="false"
                aria-label="Search navigation" aria-autocomplete="list"
                aria-owns="sidebarSuggestions" aria-expanded="false">
            <button type="button" class="sidebar-search__clear" id="sidebarSearchClear" aria-label="Clear search" tabindex="-1">
                <i class="fa fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="sidebar-suggestions" id="sidebarSuggestions" role="listbox"></div>
        </div>
        <nav class="menu">
            @if(auth()->user()?->hasRole('admin'))
                <div class="menu-section">Administration</div>
                <a href="{{ route('admin.panel') }}" class="{{ request()->routeIs('admin.panel') ? 'active' : '' }}"><i class="fa fa-gauge-high"></i><span>Dashboard</span></a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="fa fa-users-gear"></i><span>User Management</span></a>
                <a href="{{ route('admin.releases.index') }}" class="{{ request()->routeIs('admin.releases.*') ? 'active' : '' }}"><i class="fa fa-rocket"></i><span>Release Management</span></a>
                <a href="{{ route('admin.packages.index') }}" class="{{ request()->routeIs('admin.packages.*') ? 'active' : '' }}"><i class="fa fa-box-open"></i><span>Package Management</span></a>
                <a href="{{ route('admin.industries.index') }}" class="{{ request()->routeIs('admin.industries.*') ? 'active' : '' }}"><i class="fa fa-industry"></i><span>Industry Management</span></a>
                <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'active' : '' }}"><i class="fa fa-bug"></i><span>Error Logs</span></a>
            @else
            <div class="menu-section">Main</div>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa fa-gauge-high"></i><span>Overview</span></a>
            <a href="{{ route('aibot.index') }}" class="{{ request()->routeIs('aibot.*') ? 'active' : '' }}"><i class="fa fa-robot"></i><span>AI Agent</span></a>
            @if($showSidebarFinanceSection)
                <div class="menu-group-title">
                    <i class="fa fa-sack-dollar"></i><span>Finance</span>
                </div>
                <div class="submenu" aria-label="Finance">
                    @if($showSidebarFinanceOverviewLink)
                        <a href="{{ route('account.finance.index') }}" class="{{ request()->routeIs('account.finance.*') ? 'active' : '' }}"><i class="fa fa-diagram-project"></i><span>Overview</span></a>
                    @endif
                    @if($showSidebarBillsLink)
                        <a href="{{ route('account.bills.index') }}" @class([
                            'active' => request()->routeIs('account.bills.*'),
                            'menu-rentals--due' => $sidebarBillDueHighlight,
                        ]) @if($sidebarBillDueHighlight) title="At least one bill has a due date on or before today without a ledger payment logged for that date." @endif>
                            <i class="fa fa-file-invoice-dollar"></i><span>Bills</span>
                            @if($sidebarBillDueHighlight)
                                <span class="menu-rentals__pulse" aria-hidden="true"></span>
                            @endif
                        </a>
                    @endif
                    @if($showSidebarLoansLink)
                        <a href="{{ route('account.loans.index') }}" @class([
                            'menu-loan-mgmt',
                            'active' => request()->routeIs('account.loans.*'),
                            'menu-loan-mgmt--due' => $sidebarLoanDueHighlight,
                        ]) @if($sidebarLoanDueHighlight) title="At least one loan has a due date in the past without a ledger installment yet." @endif>
                            <i class="fa fa-hand-holding-dollar" aria-hidden="true"></i><span>Loans</span>
                            @if($sidebarLoanDueHighlight)
                                <span class="menu-loan-mgmt__pulse" aria-hidden="true"></span>
                            @endif
                        </a>
                    @endif
                    @if($showSidebarRentalsLink)
                        <a href="{{ route('account.rentals.index') }}" @class([
                            'active' => request()->routeIs('account.rentals.*'),
                            'menu-rentals--due' => $sidebarRentalDueHighlight,
                        ]) @if($sidebarRentalDueHighlight) title="At least one rental has a billing date on or before today without a ledger payment logged for that date." @endif>
                            <i class="fa fa-house"></i><span>Rentals</span>
                            @if($sidebarRentalDueHighlight)
                                <span class="menu-rentals__pulse" aria-hidden="true"></span>
                            @endif
                        </a>
                    @endif
                    @if($showSidebarPropertiesLink && Route::has('account.properties.index'))
                        <a href="{{ route('account.properties.index') }}" class="{{ request()->routeIs('account.properties.*') ? 'active' : '' }}"><i class="fa fa-building"></i><span>Properties</span></a>
                    @endif
                    @if($showSidebarModificationsLink)
                        <a href="{{ route('modification.index') }}" class="{{ request()->routeIs('modification.*') ? 'active' : '' }}"><i class="fa fa-screwdriver-wrench"></i><span>Modifications</span></a>
                    @endif
                    @if($showSidebarInvestmentsLink)
                        <a href="{{ route('account.investments.index') }}" class="{{ request()->routeIs('account.investments.*') ? 'active' : '' }}"><i class="fa fa-chart-line"></i><span>Investments</span></a>
                    @endif
                    @if($showSidebarBudgetsLink)
                        <a href="{{ route('budget.index') }}" class="{{ request()->routeIs('budget.*') ? 'active' : '' }}"><i class="fa fa-calculator"></i><span>Budget</span></a>
                    @endif
                </div>
            @endif
            @if($showSidebarProductSection)
                <div class="menu-group-title">
                    <i class="fa fa-boxes-stacked"></i><span>Products Catalog</span>
                </div>
                <div class="submenu" aria-label="Products Catalog">
                    @if($showSidebarProductBrandsLink)
                        <a href="{{ route('product.brands.index') }}" class="{{ request()->routeIs('product.brands.*') ? 'active' : '' }}"><i class="fa fa-tag"></i><span>Brands</span></a>
                    @endif
                    @if($showSidebarProductCategoriesLink)
                        <a href="{{ route('product.categories.index') }}" class="{{ request()->routeIs('product.categories.*') ? 'active' : '' }}"><i class="fa fa-folder-tree"></i><span>Categories</span></a>
                    @endif
                    @if($showSidebarProductUnitsLink)
                        <a href="{{ route('product.units.index') }}" class="{{ request()->routeIs('product.units.*') ? 'active' : '' }}"><i class="fa fa-ruler"></i><span>Units</span></a>
                    @endif
                    @if($showSidebarProductsLink)
                        <a href="{{ route('product.index') }}" @class([
                            'active' => request()->routeIs('product.index', 'product.store', 'product.show', 'product.edit', 'product.update', 'product.destroy', 'product.sku.*', 'product.images.*'),
                        ])><i class="fa fa-box"></i><span>Products</span></a>
                    @endif
                    @if($showSidebarDiscountsLink)
                        <a href="{{ route('product.discounts.index') }}" class="{{ request()->routeIs('product.discounts.*') ? 'active' : '' }}"><i class="fa fa-percent"></i><span>Discounts</span></a>
                    @endif
                    @if($showSidebarCampaignsLink)
                        <a href="{{ route('product.campaigns.index') }}" class="{{ request()->routeIs('product.campaigns.*') ? 'active' : '' }}"><i class="fa fa-bullhorn"></i><span>Campaigns</span></a>
                    @endif
                    @if($showSidebarBarcodesLink)
                        <a href="{{ route('product.barcodes.index') }}" class="{{ request()->routeIs('product.barcodes.*') ? 'active' : '' }}"><i class="fa fa-barcode"></i><span>Barcodes</span></a>
                    @endif
                </div>
            @endif
            @if($showSidebarPurchaseSection)
                <div class="menu-group-title">
                    <i class="fa fa-warehouse"></i><span>Stock management</span>
                </div>
                <div class="submenu" aria-label="Stock management">
                    @if($showSidebarPurchasesLink)
                        <a href="{{ route('purchase.index') }}" @class([
                            'active' => request()->routeIs('purchase.index', 'purchase.store', 'purchase.show', 'purchase.edit', 'purchase.update', 'purchase.place-order', 'purchase.receive', 'purchase.cancel', 'purchase.destroy'),
                        ])><i class="fa fa-file-invoice"></i><span>Purchase orders</span></a>
                    @endif
                    @if($showSidebarGrnLink)
                        <a href="{{ route('purchase.grn.index') }}" class="{{ request()->routeIs('purchase.grn.*') ? 'active' : '' }}"><i class="fa fa-truck-ramp-box"></i><span>Goods receive</span></a>
                    @endif
                    @if($showSidebarSuppliersLink)
                        <a href="{{ route('purchase.suppliers.index') }}" class="{{ request()->routeIs('purchase.suppliers.*') ? 'active' : '' }}"><i class="fa fa-truck-field"></i><span>Suppliers</span></a>
                    @endif
                    @if($showSidebarChequesLink)
                        <a href="{{ route('purchase.cheques.index') }}" class="{{ request()->routeIs('purchase.cheques.*') ? 'active' : '' }}"><i class="fa fa-money-check"></i><span>Cheques</span></a>
                    @endif
                    @if($showSidebarStockAuditLink)
                        <a href="{{ route('pos.stock-audits.index') }}" @class(['active' => request()->routeIs('pos.stock-audits.*')])><i class="fa fa-clipboard-check"></i><span>Stock audit</span></a>
                    @endif
                </div>
            @endif
            @if($showSidebarPosSection)
                <div class="menu-group-title">
                    <i class="fa fa-cash-register"></i><span>Sales</span>
                </div>
                <div class="submenu" aria-label="Point of sale">
                    @if($showSidebarPosHubLink)
                        <a href="{{ route('pos.index') }}" @class([
                            'active' => request()->routeIs('pos.index'),
                        ])><i class="fa fa-gauge-high"></i><span>Sales hub</span></a>
                    @endif
                    @if($showSidebarPosRegisterLink)
                        <a href="{{ route('pos.online') }}" @class([
                            'active' => request()->routeIs('pos.online', 'pos.checkout'),
                        ])><i class="fa fa-store"></i><span>Online POS</span></a>
                    @endif
                    @if($showSidebarPosSalesLink)
                        <a href="{{ route('pos.sales.index') }}" @class([
                            'active' => request()->routeIs('pos.sales.*'),
                        ])><i class="fa fa-receipt"></i><span>Sales history</span></a>
                    @endif
                    @if($showSidebarPosEodLink)
                        <a href="{{ route('pos.end-of-day') }}" @class([
                            'active' => request()->routeIs('pos.end-of-day*'),
                        ])><i class="fa fa-building-columns"></i><span>End of day</span></a>
                    @endif
                    @if($showSidebarPosCustomersLink)
                        <a href="{{ route('pos.customers.index') }}" @class([
                            'active' => request()->routeIs('pos.customers.*'),
                        ])><i class="fa fa-users"></i><span>Customers</span></a>
                    @endif
                    @if($showSidebarPosReturnsLink)
                        <a href="{{ route('pos.returns.index') }}" @class([
                            'active' => request()->routeIs('pos.returns.index'),
                        ])><i class="fa fa-rotate-left"></i><span>Return items</span></a>
                        <div class="submenu" aria-label="Return items">
                            <a href="{{ route('pos.returns.create') }}" @class([
                                'active' => request()->routeIs('pos.returns.create') && request()->query('mode') !== 'open',
                            ])><i class="fa fa-receipt"></i><span>With sale reference</span></a>
                            <a href="{{ route('pos.returns.create', ['mode' => 'open']) }}" @class([
                                'active' => request()->routeIs('pos.returns.create') && request()->query('mode') === 'open',
                            ])><i class="fa fa-box-open"></i><span>Without sale reference</span></a>
                        </div>
                    @endif
                    @if($showSidebarQuotationsLink)
                        <a href="{{ route('sales.quotations.index') }}" @class(['active' => request()->routeIs('sales.quotations.*')])>
                            <i class="fa fa-file-lines"></i><span>Quotations</span>
                        </a>
                    @endif
                    @if($showSidebarInvoicesLink)
                        <a href="{{ route('sales.invoices.index') }}" @class(['active' => request()->routeIs('sales.invoices.*')])>
                            <i class="fa fa-file-invoice"></i><span>Invoices</span>
                        </a>
                    @endif
                    @if($showSidebarSalesOrdersLink)
                        <a href="{{ route('sales.orders.index') }}" @class(['active' => request()->routeIs('sales.orders.*')])>
                            <i class="fa fa-cart-shopping"></i><span>Orders</span>
                        </a>
                    @endif
                </div>
            @endif

            @if($showSidebarCrmLink)
                <div class="menu-group-title">
                    <i class="fa fa-handshake"></i><span>CRM</span>
                </div>
                <div class="submenu" aria-label="CRM">
                    <a href="{{ route('crm.projects.index') }}" @class(['active' => request()->routeIs('crm.projects.*') || request()->routeIs('crm.leads.*')])>
                        <i class="fa fa-diagram-project"></i><span>Projects</span>
                    </a>
                    <a href="{{ route('crm.contacts.index') }}" @class(['active' => request()->routeIs('crm.contacts.*')])>
                        <i class="fa fa-address-book"></i><span>Contacts</span>
                    </a>
                    <a href="{{ route('crm.tasks.index') }}" @class(['active' => request()->routeIs('crm.tasks.*')])>
                        <i class="fa fa-list-check"></i><span>Tasks</span>
                    </a>
                </div>
            @endif

            @if($showSidebarBrandMgmtSection)
                <div class="menu-group-title">
                    <i class="fa fa-bullhorn"></i><span>Event Management</span>
                </div>
                <div class="submenu" aria-label="Event Management">
                    <a href="{{ route('pos.brand-mgmt.brands.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.brands.*')])>
                        <i class="fa fa-tag"></i><span>Brands</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.reporters.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.reporters.*')])>
                        <i class="fa fa-user-tie"></i><span>Reporters</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.officers.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.officers.*')])>
                        <i class="fa fa-user-shield"></i><span>Officers</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.coordinators.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.coordinators.*')])>
                        <i class="fa fa-people-arrows"></i><span>Coordinators</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.promoters.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.promoters.*')])>
                        <i class="fa fa-user-group"></i><span>Promoters</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.promoter-positions.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.promoter-positions.*')])>
                        <i class="fa fa-list"></i><span>Promoter Positions</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.jobs.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.jobs.*')])>
                        <i class="fa fa-briefcase"></i><span>Jobs</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.agencies.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.agencies.*')])>
                        <i class="fa fa-building"></i><span>Agencies</span>
                    </a>
                    <a href="{{ route('pos.brand-mgmt.salary-sheets.index') }}" @class(['active' => request()->routeIs('pos.brand-mgmt.salary-sheets.*')])>
                        <i class="fa fa-money-check-dollar"></i><span>Salary Sheets</span>
                    </a>
                </div>
            @endif

            @if($showSidebarProjectManageLink)
                <div class="menu-group-title">
                    <i class="fa fa-folder-open"></i><span>Projects</span>
                </div>
                <div class="submenu" aria-label="Project Management">
                    <a href="{{ route('pm.projects.index') }}" @class(['active' => request()->routeIs('pm.projects.*') || request()->routeIs('pm.tasks.*')])>
                        <i class="fa fa-diagram-project"></i><span>All Projects</span>
                    </a>
                    <a href="{{ route('pm.my-tasks') }}" @class(['active' => request()->routeIs('pm.my-tasks')])>
                        <i class="fa fa-list-check"></i><span>My Tasks</span>
                    </a>
                </div>
            @endif

            @if($showSidebarMailLink)
                <div class="menu-group-title">
                    <i class="fa fa-envelope"></i><span>Mail</span>
                    @if($sidebarMailUnreadCount)
                        <span class="pcat-badge pcat-badge--on" style="margin-left:6px;">{{ $sidebarMailUnreadCount }}</span>
                    @endif
                </div>
                <div class="submenu" aria-label="Mail">
                    <a href="{{ route('mail.inbox.index', ['box' => 'inbox']) }}" @class(['active' => request()->routeIs('mail.inbox.index') && request()->query('box', 'inbox') !== 'sent'])>
                        <i class="fa fa-inbox"></i><span>Inbox</span>
                    </a>
                    <a href="{{ route('mail.inbox.index', ['box' => 'sent']) }}" @class(['active' => request()->routeIs('mail.inbox.index') && request()->query('box') === 'sent'])>
                        <i class="fa fa-paper-plane"></i><span>Sent</span>
                    </a>
                    <a href="{{ route('mail.templates.index') }}" @class(['active' => request()->routeIs('mail.templates.*')])>
                        <i class="fa fa-file-lines"></i><span>Mail Templates</span>
                    </a>
                    <a href="{{ route('mail.filters.index') }}" @class(['active' => request()->routeIs('mail.filters.*')])>
                        <i class="fa fa-filter"></i><span>Filters</span>
                    </a>
                    <a href="{{ route('mail.scheduled.index') }}" @class(['active' => request()->routeIs('mail.scheduled.*')])>
                        <i class="fa fa-clock"></i><span>Schedules</span>
                    </a>
                </div>
            @endif

            @if($showSidebarFilesLink && Route::has('filemanager.index'))
                <a href="{{ route('filemanager.index') }}" class="{{ request()->routeIs('filemanager.*') ? 'active' : '' }}"><i class="fa fa-folder-open"></i><span>Files</span></a>
            @endif
            @if($showSidebarAutomationLink)
                <a href="{{ route('automations.index') }}" class="{{ request()->routeIs('automations.*') ? 'active' : '' }}"><i class="fa fa-bolt"></i><span>Automations</span></a>
            @endif
            @if($showSidebarDesignStudioLink)
                <a href="{{ route('designstudio.index') }}" class="{{ request()->routeIs('designstudio.*') ? 'active' : '' }}"><i class="fa fa-palette"></i><span>Design Studio</span></a>
                <div class="submenu">
                    @if(Route::has('designstudio.social-media.index'))
                        <a href="{{ route('designstudio.social-media.index') }}" @class(['active' => request()->routeIs('designstudio.social-media.*')])>
                            <i class="fa fa-share-nodes"></i><span>Social Media</span>
                        </a>
                    @endif
                    @if(Route::has('designstudio.letterhead.index'))
                        <a href="{{ route('designstudio.letterhead.index') }}" @class(['active' => request()->routeIs('designstudio.letterhead.index')])>
                            <i class="fa fa-file-lines"></i><span>Letterhead</span>
                        </a>
                    @endif
                    @if(Route::has('designstudio.company-profile.index'))
                        <a href="{{ route('designstudio.company-profile.index') }}" @class(['active' => request()->routeIs('designstudio.company-profile.index')])>
                            <i class="fa fa-building"></i><span>Company Profile</span>
                        </a>
                    @endif
                    @if(Route::has('designstudio.proposals.index'))
                        <a href="{{ route('designstudio.proposals.index') }}" @class(['active' => request()->routeIs('designstudio.proposals.*')])>
                            <i class="fa fa-file-invoice"></i><span>Proposals</span>
                        </a>
                    @endif
                    @if(Route::has('designstudio.type.index'))
                        <a href="{{ route('designstudio.type.index', 'business-card') }}" @class(['active' => request()->routeIs('designstudio.type.index') && request()->route('type') === 'business-card'])>
                            <i class="fa fa-id-card"></i><span>Business Card</span>
                        </a>
                        <a href="{{ route('designstudio.type.index', 'custom') }}" @class(['active' => request()->routeIs('designstudio.type.index') && request()->route('type') === 'custom'])>
                            <i class="fa fa-paintbrush"></i><span>Custom Design</span>
                        </a>
                        <a href="{{ route('designstudio.type.index', 'sales-campaign') }}" @class(['active' => request()->routeIs('designstudio.type.index') && request()->route('type') === 'sales-campaign'])>
                            <i class="fa fa-bullhorn"></i><span>Sales Campaign</span>
                        </a>
                        <a href="{{ route('designstudio.type.index', 'hire-designer') }}" @class(['active' => request()->routeIs('designstudio.type.index') && request()->route('type') === 'hire-designer'])>
                            <i class="fa fa-user-tie"></i><span>Hire a Designer</span>
                        </a>
                    @endif
                </div>
            @endif
            @if($showSidebarServiceLink)
                <a href="{{ route('service.catalog.index') }}" class="{{ request()->routeIs('service.*') ? 'active' : '' }}"><i class="fa fa-screwdriver-wrench"></i><span>Service Catalog</span></a>
                <div class="submenu">
                    @if($showSidebarServicePosLink)
                        <a href="{{ route('service.pos.index') }}" @class(['active' => request()->routeIs('service.pos.*')])>
                            <i class="fa fa-cash-register"></i><span>Service POS</span>
                        </a>
                    @endif
                    <a href="{{ route('service.catalog.index') }}" @class(['active' => request()->routeIs('service.catalog.*')])>
                        <i class="fa fa-list-check"></i><span>Services</span>
                    </a>
                    <a href="{{ route('service.categories.index') }}" @class(['active' => request()->routeIs('service.categories.*')])>
                        <i class="fa fa-folder-tree"></i><span>Categories</span>
                    </a>
                    <a href="{{ route('service.requests.index') }}" @class(['active' => request()->routeIs('service.requests.*')])>
                        <i class="fa fa-inbox"></i><span>Requests</span>
                    </a>
                </div>
            @endif
            @if($showSidebarRestaurantLink)
                <a href="{{ route('restaurant.orders.index') }}" class="{{ request()->routeIs('restaurant.*') ? 'active' : '' }}"><i class="fa fa-utensils"></i><span>Restaurant</span></a>
                <div class="submenu">
                    <a href="{{ route('restaurant.orders.create') }}" @class(['active' => request()->routeIs('restaurant.orders.create')])>
                        <i class="fa fa-cash-register"></i><span>Restaurant POS</span>
                    </a>
                    <a href="{{ route('restaurant.orders.index') }}" @class(['active' => request()->routeIs('restaurant.orders.index')])>
                        <i class="fa fa-receipt"></i><span>Orders</span>
                    </a>
                    @if(Route::has('restaurant.kitchen'))
                        <a href="{{ route('restaurant.kitchen') }}" @class(['active' => request()->routeIs('restaurant.kitchen')])>
                            <i class="fa fa-kitchen-set"></i><span>Kitchen Display</span>
                        </a>
                    @endif
                    <a href="{{ route('restaurant.tables.index') }}" @class(['active' => request()->routeIs('restaurant.tables.*')])>
                        <i class="fa fa-chair"></i><span>Tables</span>
                    </a>
                    <a href="{{ route('restaurant.reservations.index') }}" @class(['active' => request()->routeIs('restaurant.reservations.*')])>
                        <i class="fa fa-calendar-check"></i><span>Reservations</span>
                    </a>
                    <a href="{{ route('restaurant.menu.items.index') }}" @class(['active' => request()->routeIs('restaurant.menu.*')])>
                        <i class="fa fa-utensils"></i><span>Menu</span>
                    </a>
                    <a href="{{ route('restaurant.ingredients.index') }}" @class(['active' => request()->routeIs('restaurant.ingredients.index') || request()->routeIs('restaurant.ingredients.transactions')])>
                        <i class="fa fa-flask"></i><span>Ingredients</span>
                    </a>
                    <a href="{{ route('restaurant.ingredients.purchases.index') }}" @class(['active' => request()->routeIs('restaurant.ingredients.purchases.*') || request()->routeIs('restaurant.ingredients.grn.*')])>
                        <i class="fa fa-shopping-cart"></i><span>Purchases</span>
                    </a>
                </div>
            @endif
            @if($showSidebarDocumentationLink)
                <a href="{{ route('documentation.documents.index') }}" class="{{ request()->routeIs('documentation.*') ? 'active' : '' }}"><i class="fa fa-book-open"></i><span>Documentation</span></a>
            @endif
            @if($navBusiness && $hrFeatureOn)
                <div class="menu-group-title">
                    <i class="fa fa-users-gear"></i><span>HR</span>
                </div>
                <div class="submenu">
                    <a href="{{ route('hr.index') }}" @class([
                        'active' => request()->routeIs('hr.index'),
                        'menu-payroll--due' => $sidebarPayrollOverdueHighlight,
                    ])>
                        <i class="fa fa-table-list"></i><span>HR hub</span>
                    </a>
                    <a href="{{ route('hr.employees.index') }}" class="{{ request()->routeIs('hr.employees.*') ? 'active' : '' }}"><i class="fa fa-user-group"></i><span>Employees</span></a>
                    @if(Route::has('hr.attendance.index'))
                        <a href="{{ route('hr.attendance.index') }}" class="{{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}"><i class="fa fa-calendar-check"></i><span>Attendance</span></a>
                    @endif
                    @if($hrPayrollOptedIn)
                    <div class="menu-payroll-nested">
                        <a href="{{ route('hr.payroll.index') }}" @class([
                            'active' => request()->routeIs('hr.payroll.*'),
                            'menu-payroll--due' => $sidebarPayrollOverdueHighlight,
                        ])>
                            <i class="fa fa-money-check-dollar"></i><span>{{ __('Payroll') }}</span>
                        </a>
                        <div class="menu-payroll-nested__sub" role="group" aria-label="{{ __('Payroll shortcuts') }}">
                            <a href="{{ route('hr.payroll.regional-template') }}" class="{{ request()->routeIs('hr.payroll.regional-template') ? 'active' : '' }}"><i class="fa fa-globe" aria-hidden="true"></i><span>{{ __('Regional template') }}</span></a>
                            <a href="{{ route('hr.payroll.rule-sets.index') }}" class="{{ request()->routeIs('hr.payroll.rule-sets.*') ? 'active' : '' }}"><i class="fa fa-sliders" aria-hidden="true"></i><span>{{ __('Rule sets') }}</span></a>
                            <a href="{{ route('hr.payroll.index') }}#phi-cycles-heading" @class([
                                'active' => request()->routeIs('hr.payroll.cycles.*') || request()->routeIs('hr.payroll.index'),
                                'menu-payroll-cycles--due' => $sidebarPayrollCyclesOverdueHighlight,
                            ])>
                                <i class="fa fa-calendar-week" aria-hidden="true"></i><span>{{ __('Payroll cycles') }}</span>
                            </a>
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('hr.departments.index') }}" class="{{ request()->routeIs('hr.departments.*') ? 'active' : '' }}"><i class="fa fa-folder-tree"></i><span>Departments</span></a>
                    <a href="{{ route('hr.job-titles.index') }}" class="{{ request()->routeIs('hr.job-titles.*') ? 'active' : '' }}"><i class="fa fa-id-badge"></i><span>Designations</span></a>
                </div>
            @endif
            @if($navBusiness)
                <a href="{{ route('transactions.index') }}" class="{{ request()->routeIs('transactions.*') ? 'active' : '' }}"><i class="fa fa-arrow-right-arrow-left"></i><span>Transactions</span></a>
            @endif
            @if($navBusiness && $navBusiness->multiWarehouseBranchEnabled())
                <a href="{{ route('business.branches.index') }}" class="{{ request()->routeIs('business.branches.*') ? 'active' : '' }}"><i class="fa fa-code-branch"></i><span>Branches</span></a>
            @endif
            @if($navBusiness && (int) $navBusiness->user_id === (int) auth()->id())
                <a href="{{ route('business.users.index') }}" class="{{ request()->routeIs('business.users.*') ? 'active' : '' }}"><i class="fa fa-users"></i><span>User Management</span></a>
            @endif
            @if($showSidebarSettingsSection)
                <div class="menu-section">Configuration</div>
                <div class="menu-group-title">
                    <i class="fa fa-sliders"></i><span>Settings</span>
                </div>
                <div class="submenu">
                    <a href="{{ route('settings.business') }}" class="{{ request()->routeIs('settings.business') ? 'active' : '' }}"><i class="fa fa-briefcase"></i><span>Business Settings</span></a>
                    <a href="{{ route('settings.user') }}" class="{{ request()->routeIs('settings.user') ? 'active' : '' }}"><i class="fa fa-user-gear"></i><span>User Settings</span></a>
                    @if(Route::has('mail.settings.edit'))
                        <a href="{{ route('mail.settings.edit') }}" class="{{ request()->routeIs('mail.settings.*') ? 'active' : '' }}"><i class="fa fa-envelope"></i><span>Mail Settings</span></a>
                    @endif
                    @if(Route::has('app-connection.index'))
                        <a href="{{ route('app-connection.index') }}" class="{{ request()->routeIs('app-connection.*') ? 'active' : '' }}"><i class="fa fa-plug"></i><span>App connections</span></a>
                    @endif
                    @if(Route::has('data-vault.settings'))
                        <a href="{{ route('data-vault.settings') }}" class="{{ request()->routeIs('data-vault.settings') ? 'active' : '' }}"><i class="fa fa-shield-halved"></i><span>Data Vault</span></a>
                    @endif
                    @if($showSidebarDeveloperToolsLink)
                        <a href="{{ route('developers.index') }}" class="{{ request()->routeIs('developers.*') ? 'active' : '' }}"><i class="fa fa-code"></i><span>Developer Tools</span></a>
                    @endif
                </div>
            @endif
            @endif
        </nav>
        @endif
    </aside>
    @endunless
    <main class="content{{ $minimalAppShell ? ' content--minimal' : '' }}{{ $posOnlyShell ? ' content--pos-only' : '' }}{{ $chatWorkspace ? ' content--chat-workspace' : '' }}">
        @unless($posOnlyShell || ($hideNavbar ?? false))
        <div class="navbar">
            <div style="display:flex;align-items:center;gap:10px;">
                @unless($minimalAppShell)
                <button type="button" class="navbar-hamburger" id="sidebarHamburgerBtn" aria-label="{{ __('Open menu') }}" aria-expanded="false" aria-controls="appSidebar"><i class="fa fa-bars" aria-hidden="true"></i></button>
                <button type="button" class="sidebar-toggle-btn" id="sidebarDesktopToggle" title="{{ __('Collapse sidebar') }}" aria-label="{{ __('Collapse sidebar') }}"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                @endunless
                <div>
                <div class="navtitle">{{ $heading ?? 'Overview' }}</div>
                @if($employeePortal && isset($portalEmployee))
                    <div class="navmeta navbar-portal-meta">{{ $portalEmployee->full_name }} · {{ $portalEmployee->employee_id }}</div>
                @else
                    <div class="navmeta">{{ __('Welcome, :name', ['name' => auth()->user()->name ?? __('User')]) }}</div>
                @endif
                </div>
            </div>
            <div class="nav-right">
                @if($employeePortal)
                    @if(isset($portalEmployeeChoices) && $portalEmployeeChoices->count() > 1 && isset($portalEmployee))
                        <form method="post" action="{{ route('hr.portal.switch-employer') }}" class="nav-portal-employer-form">
                            @csrf
                            <label for="portalEmployerSelect" class="muted" style="font-size:11px;margin-right:8px;text-transform:uppercase;letter-spacing:.06em;">{{ __('Employer') }}</label>
                            <select name="employee_id" id="portalEmployerSelect" class="dropdown-select nav-portal-employer-select" aria-label="{{ __('Switch employer') }}" onchange="this.form.submit()">
                                @foreach($portalEmployeeChoices as $empChoice)
                                    <option value="{{ $empChoice->id }}" {{ (int) $portalEmployee->id === (int) $empChoice->id ? 'selected' : '' }}>
                                        {{ $empChoice->business?->name ?? __('Employer') }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <div class="navchip" title="{{ __('Employer') }}">{{ $portalEmployerBusiness?->name ?? __('Employer') }}</div>
                    @endif
                    @if(Route::has('dashboard') && auth()->user() && ! auth()->user()->isHrPortalOnlyUser())
                        <a href="{{ route('dashboard') }}" class="user-trigger" style="font-size:12px;font-weight:650;padding:6px 10px;">
                            <i class="fa fa-briefcase" aria-hidden="true"></i><span>{{ __('Workspace') }}</span>
                        </a>
                    @endif
                    <div class="user-dropdown">
                        <button type="button" class="user-trigger" id="userDropdownBtn">
                            <span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                            <span>{{ auth()->user()->name ?? 'User' }}</span>
                            <i class="fa fa-chevron-down"></i>
                        </button>
                        <div class="user-menu" id="userDropdownMenu">
                            <div class="menu-head">
                                <div class="menu-name">{{ auth()->user()->name ?? 'User' }}</div>
                                <div class="menu-email">{{ auth()->user()->email ?? '' }}</div>
                            </div>
                            <form method="post" action="{{ route('logout') }}" style="margin-top:6px;">
                                @csrf
                                <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;">
                                    <i class="fa fa-right-from-bracket"></i><span>{{ __('Logout') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                @if(!$posOnlyShell && request()->routeIs('pos.online', 'pos.register'))
                    <button type="button" class="user-trigger" data-pos-settings-open title="POS settings" aria-label="POS settings">
                        <i class="fa fa-gear" aria-hidden="true"></i>
                    </button>
                @endif
                <div class="navchip">{{ now()->format('d M Y') }}</div>
                @if($navBusiness && !auth()->user()?->hasRole('admin'))
                    <a href="{{ route('business.profile') }}" class="user-trigger nav-business-profile @if(request()->routeIs('business.profile')) nav-business-profile--active @endif" title="Business profile">
                        <i class="fa fa-id-card"></i>
                        <span>Business profile</span>
                    </a>
                @endif
                @unless(auth()->user()?->hasRole('admin'))
                <div class="user-dropdown">
                    <button type="button" class="user-trigger" id="businessDropdownBtn">
                        <i class="fa fa-briefcase"></i>
                        <span>{{ $navBusiness?->name ?? 'Your Business' }}</span>
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <div class="user-menu" id="businessDropdownMenu">
                        <div class="menu-head">
                            <div class="menu-name">{{ $navBusiness?->name ?? 'No Business Yet' }}</div>
                            <div class="menu-email">{{ $navBusiness?->category ?? 'Complete onboarding in Overview' }}</div>
                        </div>
                        @php
                            $navCurrentUserRole = null;
                            if ($navBusiness) {
                                if ((int) $navBusiness->user_id === (int) auth()->id()) {
                                    $navCurrentUserRole = 'owner';
                                } else {
                                    $navMember = $navBusiness->members()->where('user_id', auth()->id())->where('status', 'active')->first();
                                    $navCurrentUserRole = $navMember?->role ?? null;
                                }
                            }
                        @endphp
                        @if($navBusinesses->count() > 1)
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">Switch business</div>
                                <form method="post" action="{{ route('business.select') }}">
                                    @csrf
                                    <select name="business_id" class="dropdown-select" onchange="this.form.submit()">
                                        @foreach($navBusinesses as $businessOption)
                                            @php
                                                $isOwner = (int) $businessOption->user_id === (int) auth()->id();
                                                $roleLabel = $isOwner ? 'Owner' : ($businessOption->members()->where('user_id', auth()->id())->value('role') ?? '');
                                                $roleLabel = $roleLabel ? ' (' . ucfirst($roleLabel) . ')' : '';
                                            @endphp
                                            <option value="{{ $businessOption->id }}" {{ (int) ($navBusiness?->id ?? 0) === (int) $businessOption->id ? 'selected' : '' }}>
                                                {{ $businessOption->name }}{{ $roleLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        @endif
                        @if($navBusiness)
                            <div class="menu-row">
                                <span><i class="fa fa-layer-group" style="margin-right:6px;"></i>Category</span>
                                <span class="pkg-badge">{{ $navBusiness->category }}</span>
                            </div>
                            @if($navCurrentUserRole)
                            <div class="menu-row">
                                <span><i class="fa fa-id-badge" style="margin-right:6px;"></i>Your role</span>
                                <span class="pkg-badge" style="
                                    background:{{ match($navCurrentUserRole) {
                                        'owner'   => 'color-mix(in srgb,#f59e0b 15%,transparent)',
                                        'admin'   => 'color-mix(in srgb,#6366f1 15%,transparent)',
                                        'manager' => 'color-mix(in srgb,#0ea5e9 15%,transparent)',
                                        default   => 'color-mix(in srgb,#64748b 15%,transparent)',
                                    } }};
                                    color:{{ match($navCurrentUserRole) {
                                        'owner'   => '#d97706',
                                        'admin'   => '#6366f1',
                                        'manager' => '#0ea5e9',
                                        default   => '#64748b',
                                    } }};">
                                    {{ ucfirst($navCurrentUserRole) }}
                                </span>
                            </div>
                            @endif
                            @if((int) $navBusiness->user_id === (int) auth()->id())
                            <div class="menu-row">
                                <a href="{{ route('business.users.index') }}" style="display:flex;align-items:center;gap:7px;color:var(--text);text-decoration:none;font-size:13px;font-weight:600;">
                                    <i class="fa fa-users" style="color:var(--primary);"></i> Manage Users
                                </a>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="user-dropdown">
                    <button type="button" class="user-trigger" id="accountDropdownBtn">
                        <i class="fa fa-building-columns"></i>
                        <span>Account</span>
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <div class="user-menu" id="accountDropdownMenu" style="min-width:310px;">
                        <div class="menu-head">
                            <div class="menu-name">
                                <i class="fa fa-wallet" style="margin-right:6px;color:var(--primary);"></i>
                                {{ $assignedAccount?->account_name ?? 'No Assigned Account' }}
                            </div>
                            <div class="menu-email">
                                {{ $assignedAccount?->bankType?->name ?? 'Complete account onboarding in Overview' }}
                            </div>
                        </div>
                        @if($accounts->count() > 1)
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">Selected Account</div>
                                <form method="post" action="{{ route('account.select') }}">
                                    @csrf
                                    <select name="account_id" class="dropdown-select" onchange="this.form.submit()">
                                        @foreach($accounts as $accountOption)
                                            <option value="{{ $accountOption->id }}" {{ (int) $assignedAccount?->id === (int) $accountOption->id ? 'selected' : '' }}>
                                                {{ $accountOption->account_name }} - {{ $accountOption->bankType?->name ?? 'Type' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        @endif
                        @if($assignedAccount)
                            <div class="menu-row">
                                <span><i class="fa fa-building" style="margin-right:6px;"></i>Bank</span>
                                <span class="pkg-badge">{{ $assignedAccount->bank?->name ?? $assignedAccount->bank_name }}</span>
                            </div>
                            <div class="menu-row">
                                <span><i class="fa fa-hashtag" style="margin-right:6px;"></i>Account No</span>
                                <span>{{ $assignedAccount->bank_account_number }}</span>
                            </div>
                            <div class="menu-row">
                                <span><i class="fa fa-code-branch" style="margin-right:6px;"></i>Branch</span>
                                <span>{{ $assignedAccount->branch }}</span>
                            </div>
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">Current Balance</div>
                                <div style="font-size:20px;font-weight:700;color:var(--primary);">
                                    {{ number_format((float) $assignedAccount->current_balance, 2) }}
                                </div>
                            </div>
                            <div class="menu-row" style="display:block;padding-top:4px;">
                                <a href="{{ route('account.onboarding') }}" class="dropdown-action-btn">
                                    <i class="fa fa-pen-to-square" style="margin-right:6px;"></i>Open Account Onboarding
                                </a>
                            </div>
                        @else
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:13px;color:var(--muted);line-height:1.5;">
                                    No account is assigned to this business yet. Complete the account onboarding to see details here.
                                </div>
                            </div>
                            <div class="menu-row" style="display:block;padding-top:4px;">
                                <a href="{{ route('account.onboarding') }}" class="dropdown-action-btn">
                                    <i class="fa fa-plus-circle" style="margin-right:6px;"></i>Start Account Onboarding
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                @endunless
                @if(auth()->user()?->hasRole('admin'))
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="user-trigger" style="gap:8px;">
                            <i class="fa fa-right-from-bracket"></i><span>Logout</span>
                        </button>
                    </form>
                @else
                <div class="user-dropdown">
                    <button type="button" class="user-trigger" id="userDropdownBtn">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                        <span>{{ auth()->user()->name ?? 'User' }}</span>
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <div class="user-menu" id="userDropdownMenu">
                        <div class="menu-head">
                            <div class="menu-name">{{ auth()->user()->name ?? 'User' }}</div>
                            <div class="menu-email">{{ auth()->user()->email ?? '' }}</div>
                        </div>
                        <div class="menu-row">
                            <span><i class="fa fa-box" style="margin-right:6px;"></i>Purchased Package</span>
                            <span class="pkg-badge">{{ $navBusiness?->package?->name ?? 'Free Trial' }}</span>
                        </div>
                        @if(auth()->check())
                            <div class="menu-row" style="display:block;">
                                <form method="post" action="{{ route('settings.store') }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="scope" value="user"/>
                                    <input type="hidden" name="key" value="ui.theme"/>
                                    <label for="zeebrooNavThemeSel" style="font-size:12px;color:var(--muted);display:block;margin-bottom:8px;"><i class="fa fa-palette" style="margin-right:6px;"></i>Color theme</label>
                                    <select name="value" id="zeebrooNavThemeSel" class="dropdown-select" onchange="this.form.submit()" style="width:100%;">
                                        <option value="night" @selected($__ui_theme === 'night')>Night — violet</option>
                                        <option value="light" @selected($__ui_theme === 'light')>Light — amber &amp; black</option>
                                        <option value="light_blue" @selected($__ui_theme === 'light_blue')>Light — blue &amp; white</option>
                                        <option value="night_blue" @selected($__ui_theme === 'night_blue')>Night — blue accents</option>
                                        <option value="ocean" @selected($__ui_theme === 'ocean')>Ocean — teal</option>
                                    </select>
                                    <noscript><button type="submit" class="linkbtn" style="margin-top:8px;width:100%;">Save theme</button></noscript>
                                </form>
                            </div>
                        @endif
                        @if($navBusiness)
                        <div class="menu-row" style="display:block;padding-top:2px;padding-bottom:2px;">
                            <a href="{{ route('business.map') }}" class="dropdown-action-btn">
                                <i class="fa fa-sitemap" style="margin-right:6px;"></i>Business Map
                            </a>
                        </div>
                        <div class="menu-row" style="display:block;padding-top:2px;padding-bottom:2px;">
                            <button type="button" id="openFeaturesModalBtn" style="width:100%;display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:10px;border:1px solid var(--border);background:color-mix(in srgb,var(--primary) 8%,transparent);color:var(--text);cursor:pointer;font-size:13px;font-weight:600;text-align:left;">
                                <i class="fa fa-sliders" style="color:var(--primary);width:14px;text-align:center;"></i>
                                <span>Manage Features</span>
                            </button>
                        </div>
                        @endif
                        <form method="post" action="{{ route('logout') }}" style="margin-top:6px;">
                            @csrf
                            <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;">
                                <i class="fa fa-right-from-bracket"></i><span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
                @endif
                @endif
            </div>
        </div>
        @endunless
        <div class="content-inner{{ $chatWorkspace ? ' content-inner--chat-workspace' : '' }}">
            @yield('content')
        </div>
    </main>
</div>
<script>
    const root = document.documentElement;
    const serverTheme = @json($__ui_theme);
    root.setAttribute('data-theme', serverTheme);
    try {
        localStorage.setItem('ui_theme', serverTheme);
    } catch (e) {}
    const dropdownBtn = document.getElementById('userDropdownBtn');
    const dropdownMenu = document.getElementById('userDropdownMenu');
    const businessDropdownBtn = document.getElementById('businessDropdownBtn');
    const businessDropdownMenu = document.getElementById('businessDropdownMenu');
    const accountDropdownBtn = document.getElementById('accountDropdownBtn');
    const accountDropdownMenu = document.getElementById('accountDropdownMenu');
    if (dropdownBtn && dropdownMenu) {
        dropdownBtn.addEventListener('click', () => dropdownMenu.classList.toggle('open'));
    }
    if (businessDropdownBtn && businessDropdownMenu) {
        businessDropdownBtn.addEventListener('click', () => businessDropdownMenu.classList.toggle('open'));
    }
    if (accountDropdownBtn && accountDropdownMenu) {
        accountDropdownBtn.addEventListener('click', () => accountDropdownMenu.classList.toggle('open'));
    }
    document.addEventListener('click', (event) => {
        if (dropdownBtn && dropdownMenu && !dropdownBtn.contains(event.target) && !dropdownMenu.contains(event.target)) {
            dropdownMenu.classList.remove('open');
        }
        if (businessDropdownBtn && businessDropdownMenu && !businessDropdownBtn.contains(event.target) && !businessDropdownMenu.contains(event.target)) {
            businessDropdownMenu.classList.remove('open');
        }
        if (accountDropdownBtn && accountDropdownMenu && !accountDropdownBtn.contains(event.target) && !accountDropdownMenu.contains(event.target)) {
            accountDropdownMenu.classList.remove('open');
        }
    });

    // ── Sidebar search ───────────────────────────────────────────────
    (function () {
        const searchInput = document.getElementById('sidebarSearchInput');
        const clearBtn    = document.getElementById('sidebarSearchClear');
        const suggestions = document.getElementById('sidebarSuggestions');
        if (!searchInput || !suggestions) return;

        // Collect every rendered sidebar nav link
        const sidebar = document.getElementById('appSidebar');
        if (!sidebar) return;

        const navItems = [];
        sidebar.querySelectorAll('nav.menu a[href]').forEach(function (a) {
            const label = a.querySelector('span')?.textContent?.trim() || a.textContent.trim();
            if (!label) return;

            // Determine parent section label for the hint chip
            let section = '';
            const parent = a.closest('.submenu, .menu-payroll-nested__sub');
            if (parent) {
                const groupTitle = parent.previousElementSibling;
                if (groupTitle) section = groupTitle.querySelector('span')?.textContent?.trim() || '';
            }

            // Get the icon class string
            const iconEl = a.querySelector('i');
            const iconClass = iconEl ? iconEl.className.replace('aria-hidden', '').trim() : 'fa fa-link';

            navItems.push({ label, href: a.href, iconClass, section });
        });

        let activeIdx = -1;
        let currentItems = [];

        function escH(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function closeSuggestions() {
            suggestions.classList.remove('is-open');
            suggestions.innerHTML = '';
            searchInput.setAttribute('aria-expanded', 'false');
            activeIdx = -1;
            currentItems = [];
        }

        function renderSuggestions(items) {
            activeIdx = -1;
            currentItems = items;
            if (items.length === 0) {
                suggestions.innerHTML = '<div class="sidebar-suggestions__empty">No results</div>';
                suggestions.classList.add('is-open');
                searchInput.setAttribute('aria-expanded', 'true');
                return;
            }
            suggestions.innerHTML = items.slice(0, 10).map(function (item, i) {
                return '<a href="' + escH(item.href) + '" class="sidebar-suggestion" role="option" data-idx="' + i + '">' +
                    '<i class="' + escH(item.iconClass) + '" aria-hidden="true"></i>' +
                    '<span class="sidebar-suggestion__label">' + escH(item.label) + '</span>' +
                    (item.section ? '<span class="sidebar-suggestion__section">' + escH(item.section) + '</span>' : '') +
                '</a>';
            }).join('');
            suggestions.classList.add('is-open');
            searchInput.setAttribute('aria-expanded', 'true');
        }

        function setActive(idx) {
            const all = suggestions.querySelectorAll('.sidebar-suggestion');
            all.forEach(function (el) { el.classList.remove('is-active'); });
            activeIdx = Math.max(-1, Math.min(all.length - 1, idx));
            if (activeIdx >= 0 && all[activeIdx]) {
                all[activeIdx].classList.add('is-active');
                all[activeIdx].scrollIntoView({ block: 'nearest' });
            }
        }

        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            clearBtn.classList.toggle('visible', q.length > 0);
            if (!q) { closeSuggestions(); return; }
            const filtered = navItems.filter(function (item) {
                return item.label.toLowerCase().includes(q) || item.section.toLowerCase().includes(q);
            });
            renderSuggestions(filtered);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (!suggestions.classList.contains('is-open')) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIdx + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIdx - 1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                const all = suggestions.querySelectorAll('.sidebar-suggestion');
                if (activeIdx >= 0 && all[activeIdx]) { all[activeIdx].click(); }
                else if (currentItems.length > 0) { window.location.href = currentItems[0].href; }
            }
            else if (e.key === 'Escape') { closeSuggestions(); searchInput.value = ''; clearBtn.classList.remove('visible'); }
        });

        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            clearBtn.classList.remove('visible');
            closeSuggestions();
            searchInput.focus();
        });

        suggestions.addEventListener('mousedown', function (e) {
            // prevent blur on input when clicking a suggestion
            e.preventDefault();
        });

        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('sidebarSearchWrap');
            if (wrap && !wrap.contains(e.target)) closeSuggestions();
        });
    })();
</script>
@if($navBusiness)
@php
    $bfmCategories = [
        ['key' => 'all',           'name' => 'All Features',    'icon' => 'fa-grip'],
        ['key' => 'sales',         'name' => 'Sales & Checkout','icon' => 'fa-cash-register'],
        ['key' => 'inventory',     'name' => 'Inventory',       'icon' => 'fa-boxes-stacked'],
        ['key' => 'finance',       'name' => 'Finance',         'icon' => 'fa-building-columns'],
        ['key' => 'hr',            'name' => 'HR & Staff',      'icon' => 'fa-users'],
        ['key' => 'marketing',     'name' => 'Marketing',       'icon' => 'fa-bullhorn'],
        ['key' => 'communication', 'name' => 'Communication',   'icon' => 'fa-envelope'],
        ['key' => 'developer',     'name' => 'Developer Tools', 'icon' => 'fa-code'],
        ['key' => 'productivity',  'name' => 'Productivity',    'icon' => 'fa-diagram-project'],
    ];

    // Feature keys the assigned package (plus any per-business admin overrides) actually
    // permits — everything else renders locked as "Not in your package" regardless of the
    // business's own saved on/off toggle. Mirrors the pos-desktop Feature Management modal,
    // which reads the same Business::effectiveFeatureKeys() via the POS settings API.
    $bfmAllowedKeys = $navBusiness->effectiveFeatureKeys();

    $bfmDefs = [
        ['key' => 'account_management', 'category' => 'finance', 'name' => 'Account Management', 'img' => asset('features/account-management.png'), 'color' => '#4e8ef7', 'desc' => 'Financial accounts & bank management', 'locked' => true, 'long' => [
            'Track ledgers, bank accounts, and financial movements across your business from one place. Every sale, bill, and payroll run posts here automatically, so your books stay current without manual entry.',
            'This module powers the numbers behind every other feature, so it is always included and cannot be turned off.',
        ]],
        ['key' => 'point_of_sale', 'category' => 'sales', 'name' => 'Point of Sale', 'img' => asset('features/point-of-sale.png'), 'color' => '#4caf7d', 'desc' => 'Cashier checkout interface & quick sales', 'long' => [
            'A fast, touch-friendly checkout screen for ringing up sales, applying discounts, and taking payments. Barcode scanning, quick-add favorites, and split payments are all one tap away.',
            'Every sale reconciles straight into Account Management and Stock Management, so your numbers and inventory stay in sync.',
            'Needs Stock Management and Product Management enabled. Point of Sale and Restaurant cover the same checkout role, so only one of the two can be enabled at a time.',
        ]],
        ['key' => 'sales_management', 'category' => 'sales', 'name' => 'Sales Management', 'img' => asset('features/sales-management.png'), 'color' => '#f59e0b', 'desc' => 'Invoices, quotations, sales orders & returns', 'long' => [
            'Create invoices, quotations, and sales orders, and track every transaction — from POS sales to manually raised invoices — in one place.',
            'Run end-of-day settlement, process returns, and manage sales customers here, whether or not the Point of Sale checkout screen is enabled.',
        ]],
        ['key' => 'product_management', 'category' => 'inventory', 'name' => 'Product Management', 'img' => asset('features/product-management.png'), 'color' => '#0ea5e9', 'desc' => 'Product catalog, categories & brands', 'long' => [
            'Organize your entire product catalog — categories, brands, and variants — from a single screen. Bulk import, bulk price updates, and barcode printing make catalog maintenance fast even for large inventories.',
            'Changes here show up instantly at checkout, so pricing and descriptions never drift out of sync.',
        ]],
        ['key' => 'stock_management', 'category' => 'inventory', 'name' => 'Stock Management', 'img' => asset('features/stock-management.png'), 'color' => '#64748b', 'desc' => 'Stock audits & inventory adjustments', 'long' => [
            'Keep inventory counts accurate with stock audits, manual adjustments, and layer-level tracking across branches and warehouses.',
            'Low-stock alerts and audit history give you a clear trail of what changed, when, and who made the change.',
        ]],
        ['key' => 'bill_management', 'category' => 'finance', 'name' => 'Bill Management', 'img' => asset('features/bill-management.png'), 'color' => '#9c6ef7', 'desc' => 'Bills, loans & expense tracking', 'long' => [
            'Record bills, loans, and recurring expenses, and track what is due, paid, and overdue at a glance.',
            'Everything posts to your accounts automatically, so your expense picture in Account Management is always up to date.',
        ]],
        ['key' => 'human_resources', 'category' => 'hr', 'name' => 'Human Resources', 'img' => asset('features/human-resource-management.png'), 'color' => '#f7a54e', 'desc' => 'Employees, departments & payroll', 'long' => [
            'Manage employees, departments, and attendance, and run payroll without leaving the app.',
            'Employee records, attendance logs, and payroll history stay linked for quick lookups.',
        ]],
        ['key' => 'service_management', 'category' => 'sales', 'name' => 'Services', 'img' => asset('features/service.png'), 'color' => '#f0a030', 'desc' => 'Service-bound products & job management', 'long' => [
            'Sell service-bound products — repairs, installations, consultations — and schedule the jobs that come with them.',
            'Works alongside Point of Sale, so a service can be sold at the counter just like a physical product.',
        ]],
        ['key' => 'social_media_campaign', 'category' => 'marketing', 'name' => 'Social Media Campaign', 'img' => asset('features/social-media-campaign.png'), 'color' => '#e040fb', 'desc' => 'Design studio & marketing assets', 'long' => [
            'A built-in design studio for creating social posts, flyers, and other marketing assets — no separate design software required.',
            'Start from a template or a blank canvas, drop in your logo and product photos, and export the result ready to post or print.',
        ]],
        ['key' => 'restaurant', 'category' => 'sales', 'name' => 'Restaurant', 'img' => asset('features/restaurant.jpeg'), 'color' => '#f97316', 'desc' => 'Restaurant POS, orders, menu & kitchen', 'long' => [
            'A dedicated restaurant workflow with table management, order tickets, a live kitchen display, and menu management for dine-in and takeaway service.',
            'Point of Sale and Restaurant cover the same checkout role, so only one of the two can be enabled at a time.',
        ]],
        ['key' => 'mail', 'category' => 'communication', 'name' => 'Mail', 'img' => asset('features/mail.png'), 'color' => '#06b6d4', 'desc' => 'Business inbox, templates & scheduled sending', 'long' => [
            'A shared business inbox with reusable templates and scheduled sending, so your team can email customers without leaving the app.',
            'Templates keep tone and formatting consistent, and scheduled sends let you queue a campaign or reminder ahead of time.',
        ]],
        ['key' => 'crm', 'category' => 'marketing', 'name' => 'CRM', 'img' => asset('features/crm.jpeg'), 'color' => '#7c3aed', 'desc' => 'Leads pipeline, contacts & follow-up tasks', 'long' => [
            'Track leads through a sales pipeline, from first contact to closed deal, and manage all your contacts in one address book.',
            'Follow-up tasks and reminders make sure a promising lead never goes quiet.',
        ]],
        ['key' => 'developers', 'category' => 'developer', 'name' => 'Developer Tools', 'img' => asset('features/developer.png'), 'color' => '#0f766e', 'desc' => 'API keys & webhooks for third-party integrations', 'long' => [
            'Generate API keys and configure webhooks to connect your business data to third-party tools — accounting software, dashboards, automation platforms, and more.',
            'Scoped keys can be revoked individually, and webhook delivery logs make it easy to debug an integration.',
        ]],
        ['key' => 'automation_editor', 'category' => 'developer', 'name' => 'Automation Editor', 'img' => asset('features/automation-flow.png'), 'color' => '#f59e0b', 'desc' => 'Visual workflow builder — triggers, conditions & actions', 'long' => [
            'Build no-code automations with a visual, drag-and-drop editor — trigger actions based on events and conditions across your business data.',
            'Combine triggers, conditions, and actions into a workflow once, then let it run in the background.',
        ]],
        ['key' => 'project_management', 'category' => 'productivity', 'name' => 'Project Management', 'img' => asset('features/project-management.jpeg'), 'color' => '#0284c7', 'desc' => 'Projects, tasks, milestones & kanban boards', 'long' => [
            'Plan and track projects with tasks, milestones, and kanban boards built for small teams.',
            'Assign tasks, set due dates, and watch a project move across the board from "To Do" through "In Progress" to "Done".',
        ]],
        ['key' => 'event_management', 'category' => 'marketing', 'name' => 'Event Management', 'img' => asset('features/event-management.png'), 'color' => '#0ea5e9', 'desc' => 'Brands, jobs, reporters, officers & salary sheets', 'long' => [
            'Run an event or advertising agency workflow — manage client brands, the jobs booked for them, and the staff assigned to cover each one.',
            "Salary sheets tie the work back to payroll, so agency-specific staffing and pay don't have to be shoehorned into a generic HR setup.",
        ]],
    ];
@endphp
<style>
.bfm-overlay{position:fixed;inset:0;z-index:400;display:none;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}
.bfm-overlay.bfm-open{display:flex;}
.bfm-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.48);backdrop-filter:blur(3px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .bfm-backdrop{background:rgba(15,23,42,.35);}
.bfm-shell{position:relative;z-index:1;width:100%;max-width:1180px;height:min(88vh,860px);background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 24px 56px rgba(0,0,0,.32);display:flex;flex-direction:column;overflow:hidden;}
.bfm-head{padding:18px 20px 14px;border-bottom:1px solid var(--border);flex-shrink:0;position:relative;}
.bfm-title{margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--text);display:flex;align-items:center;gap:8px;}
.bfm-title i{color:var(--primary);}
.bfm-sub{margin:0;font-size:13px;color:var(--muted);}
.bfm-close{position:absolute;top:14px;right:16px;width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:grid;place-items:center;font-size:16px;line-height:1;padding:0;}
.bfm-close:hover{border-color:var(--primary);color:var(--text);}
.bfm-body-wrap{flex:1;display:flex;flex-direction:row;min-height:0;overflow:hidden;}
.bfm-sidebar{width:200px;flex-shrink:0;border-right:1px solid var(--border);padding:14px 10px;overflow-y:auto;}
.bfm-sidebar-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:4px 10px 8px;}
.bfm-cat-list{display:flex;flex-direction:column;gap:2px;}
.bfm-cat-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text);user-select:none;}
.bfm-cat-item i{width:16px;text-align:center;color:var(--muted);font-size:12px;}
.bfm-cat-item:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
.bfm-cat-item.active{background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.bfm-cat-item.active i{color:var(--primary);}
.bfm-cat-item-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.bfm-cat-count{font-size:11px;font-weight:700;color:var(--muted);background:color-mix(in srgb,var(--muted) 14%,transparent);border-radius:999px;padding:1px 7px;}
.bfm-cat-item.active .bfm-cat-count{color:var(--primary);background:color-mix(in srgb,var(--primary) 16%,transparent);}
.bfm-main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden;}
.bfm-toolbar{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;flex-shrink:0;}
.bfm-search-wrap{position:relative;flex:1;min-width:180px;max-width:320px;}
.bfm-search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;}
.bfm-search-wrap input{width:100%;padding:8px 10px 8px 30px;border-radius:9px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;}
.bfm-search-wrap input:focus{outline:none;border-color:var(--primary);}
.bfm-toolbar-hint{font-size:12px;color:var(--muted);flex:2;min-width:160px;}
.bfm-sort-wrap{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);white-space:nowrap;}
.bfm-sort-wrap select{padding:6px 8px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:12.5px;}
.bfm-body{padding:16px 20px 20px;overflow-y:auto;flex:1;min-height:0;}
.bfm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;}
.bfm-empty{padding:40px 0;text-align:center;color:var(--muted);font-size:13px;}
.bfm-card{border:1px solid var(--border);border-radius:16px;background:var(--card);display:flex;flex-direction:column;cursor:pointer;transition:all .15s ease;padding:14px;}
.bfm-card:hover{transform:translateY(-2px);border-color:var(--primary);box-shadow:0 8px 20px color-mix(in srgb,var(--primary) 14%,transparent);}
.bfm-card-banner{position:relative;width:100%;aspect-ratio:1.3/1;flex-shrink:0;border-radius:12px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:color-mix(in srgb,var(--muted) 10%,var(--card));}
.bfm-card-banner-img{width:100%;height:100%;object-fit:cover;pointer-events:none;}
.bfm-card-banner-badge{position:absolute;top:8px;right:8px;}
.bfm-card-body{display:flex;flex-direction:column;gap:6px;flex:1;}
.bfm-card-name{font-size:13.5px;font-weight:700;color:var(--text);}
.bfm-card-desc{font-size:12px;color:var(--muted);line-height:1.4;flex:1;}
.bfm-card-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;}
.bfm-feat-badge{font-size:10px;font-weight:700;padding:3px 9px;border-radius:7px;pointer-events:none;letter-spacing:.02em;background:color-mix(in srgb,var(--card) 88%,transparent);backdrop-filter:blur(2px);border:1px solid var(--border);color:var(--muted);}
.bfm-feat-badge.bfm-badge-required{color:var(--muted);}
.bfm-feat-badge.bfm-badge-disallowed{color:#fff;border-color:rgba(0,0,0,.15);background:#b91c1c;backdrop-filter:none;display:inline-flex;align-items:center;gap:5px;box-shadow:0 1px 4px rgba(0,0,0,.25);}
.bfm-card-disallowed{opacity:.6;filter:grayscale(.5);cursor:default;}
.bfm-card-disallowed:hover{transform:none;box-shadow:none;border-color:var(--border);}
.bfm-card-disallowed .bfm-card-banner{filter:grayscale(.3);}
.bfm-install-btn{border:1px solid transparent;background:var(--primary);color:#fff;font-size:11.5px;font-weight:700;padding:7px 14px;border-radius:8px;cursor:pointer;white-space:nowrap;}
.bfm-install-btn:hover{filter:brightness(1.08);}
.bfm-install-btn.bfm-installed{background:transparent;border-color:var(--border);color:var(--muted);}
.bfm-readmore-btn{background:none;border:none;color:var(--primary);font-size:11.5px;font-weight:700;cursor:pointer;padding:6px 2px;}
.bfm-required-label{font-size:11.5px;font-weight:700;color:var(--muted);display:flex;align-items:center;gap:5px;}
.bfm-disallowed-label{font-size:11.5px;font-weight:700;color:#dc2626;display:flex;align-items:center;gap:5px;}
.bfm-detail-view{flex:1;display:none;flex-direction:column;min-height:0;overflow-y:auto;padding:18px 24px 24px;}
.bfm-detail-back{align-self:flex-start;background:none;border:none;color:var(--primary);font-size:13px;font-weight:700;cursor:pointer;padding:6px 0;margin-bottom:14px;}
.bfm-detail-head{display:flex;align-items:center;gap:16px;margin-bottom:16px;}
.bfm-detail-icon{width:88px;height:68px;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.bfm-detail-icon img{width:100%;height:100%;object-fit:cover;}
.bfm-detail-name{font-size:19px;font-weight:800;color:var(--text);margin:0 0 4px;}
.bfm-detail-desc{font-size:13px;color:var(--muted);margin:0;}
.bfm-detail-body p{font-size:13px;line-height:1.6;color:var(--text);margin:0 0 12px;}
.bfm-detail-foot{display:flex;align-items:center;gap:10px;margin-top:8px;}
.bfm-dep-hint{font-size:10.5px;font-weight:600;color:#b45309;margin-top:2px;}
html[data-theme="light"] .bfm-dep-hint,html[data-theme="light_blue"] .bfm-dep-hint{color:#92400e;}
.bfm-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px;justify-content:flex-end;flex-shrink:0;}
.bfm-cancel{background:transparent;border:1px solid var(--border);color:var(--text);padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;}
.bfm-cancel:hover{border-color:var(--primary);}
.bfm-save{padding:9px 20px;font-size:13px;font-weight:700;}
.bfm-status{font-size:12px;font-weight:600;margin-right:auto;display:none;}
.bfm-status.bfm-ok{color:#16a34a;display:block;}
.bfm-status.bfm-err{color:#dc2626;display:block;}
@media (max-width:760px){.bfm-sidebar{display:none;}.bfm-toolbar-hint{display:none;}}
</style>
<div id="bizFeaturesModal" class="bfm-overlay" role="dialog" aria-modal="true" aria-labelledby="bfm-title" aria-hidden="true">
    <div class="bfm-backdrop" id="bfmBackdrop"></div>
    <div class="bfm-shell">
        <div class="bfm-head">
            <h2 class="bfm-title" id="bfm-title"><i class="fa fa-sliders"></i> Business Features</h2>
            <p class="bfm-sub">Enable or disable features for <strong>{{ $navBusiness->name }}</strong>. Changes are saved immediately.</p>
            <button type="button" class="bfm-close" id="bfmCloseBtn" aria-label="Close modal" @if(session('open_features_modal')) style="display:none;" @endif>&times;</button>
        </div>
        <div id="bfmGridView" class="bfm-body-wrap">
            <aside class="bfm-sidebar">
                <div class="bfm-sidebar-title">Categories</div>
                <nav id="bfmCategories" class="bfm-cat-list"></nav>
            </aside>
            <div class="bfm-main">
                <div class="bfm-toolbar">
                    <div class="bfm-search-wrap">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" id="bfmSearch" placeholder="Search features…">
                    </div>
                    <span class="bfm-toolbar-hint">Enable or disable features for this business. Changes apply immediately.</span>
                    <div class="bfm-sort-wrap">
                        <label for="bfmSort">Sort by</label>
                        <select id="bfmSort">
                            <option value="relevant">Most relevant</option>
                            <option value="name">Name (A–Z)</option>
                            <option value="installed">Installed first</option>
                        </select>
                    </div>
                </div>
                <div class="bfm-body">
                    <div class="bfm-grid" id="bfmGrid"></div>
                </div>
            </div>
        </div>
        <div id="bfmDetailView" class="bfm-detail-view">
            <button type="button" class="bfm-detail-back" id="bfmDetailBack"><i class="fa fa-arrow-left"></i> Back to all features</button>
            <div id="bfmDetailBody"></div>
        </div>
        <div class="bfm-foot">
            <span class="bfm-status" id="bfmStatus"></span>
            <button type="button" class="bfm-cancel" id="bfmCancelBtn">{{ session('open_features_modal') ? 'Skip for now' : 'Cancel' }}</button>
            <button type="button" class="linkbtn bfm-save" id="bfmSaveBtn">Save changes</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal   = document.getElementById('bizFeaturesModal');
    var openBtn = document.getElementById('openFeaturesModalBtn');
    var closeBtn= document.getElementById('bfmCloseBtn');
    var cancelBtn=document.getElementById('bfmCancelBtn');
    var saveBtn = document.getElementById('bfmSaveBtn');
    var backdrop= document.getElementById('bfmBackdrop');
    var status  = document.getElementById('bfmStatus');
    var gridView  = document.getElementById('bfmGridView');
    var detailView= document.getElementById('bfmDetailView');
    if (!modal || !openBtn) return;

    var BFM_DEFS = @json($bfmDefs);
    var BFM_CATEGORIES = @json($bfmCategories);
    var BFM_SAVED = @json($businessFeatures);
    var BFM_ALLOWED = @json($bfmAllowedKeys);
    var BFM_MUTUAL_EXCL = { restaurant: 'point_of_sale', point_of_sale: 'restaurant' };
    var BFM_POS_DEPS = ['stock_management', 'product_management'];

    var bfmState = {};
    BFM_DEFS.forEach(function (f) {
        bfmState[f.key] = f.locked ? true : (BFM_SAVED.hasOwnProperty(f.key) ? !!BFM_SAVED[f.key] : true);
    });
    var bfmDirty = false;

    function fmIsAllowed(key) { return BFM_ALLOWED.indexOf(key) !== -1; }

    function fmFeaturesPayload() {
        var features = {};
        BFM_DEFS.forEach(function (f) { features[f.key] = fmIsOn(f.key) ? 1 : 0; });
        features['account_management'] = 1; // always required
        return features;
    }

    function fmPersist(onDone) {
        fetch('{{ route('business.features.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ features: fmFeaturesPayload() }),
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.ok) throw new Error(data.error || 'Save failed.');
            bfmDirty = true;
            if (onDone) onDone(true);
        })
        .catch(function(err) {
            showToast(err.message || 'Could not save changes.', 'error');
            if (onDone) onDone(false);
        });
    }

    var _fmSearch = '';
    var _fmCategory = 'all';
    var _fmSort = 'relevant';
    var _fmDetailKey = null;

    function fmIsOn(key) { return !!bfmState[key]; }

    function fmEsc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmDefsInCategory(cat) {
        return cat === 'all' ? BFM_DEFS : BFM_DEFS.filter(function (f) { return f.category === cat; });
    }

    function fmPosDepsOk() {
        return BFM_POS_DEPS.every(function (f) { return fmIsOn(f); });
    }

    function renderCategories() {
        var nav = document.getElementById('bfmCategories');
        nav.innerHTML = BFM_CATEGORIES.map(function (c) {
            var count = fmDefsInCategory(c.key).length;
            var active = c.key === _fmCategory ? ' active' : '';
            return '<div class="bfm-cat-item' + active + '" data-cat="' + c.key + '">' +
                '<i class="fa ' + c.icon + '"></i>' +
                '<span class="bfm-cat-item-name">' + fmEsc(c.name) + '</span>' +
                '<span class="bfm-cat-count">' + count + '</span>' +
            '</div>';
        }).join('');
        nav.querySelectorAll('[data-cat]').forEach(function (el) {
            el.addEventListener('click', function () {
                _fmCategory = el.dataset.cat;
                renderCategories();
                renderGrid();
            });
        });
    }

    function renderGrid() {
        var grid = document.getElementById('bfmGrid');
        var term = _fmSearch.trim().toLowerCase();
        var defs = fmDefsInCategory(_fmCategory);
        if (term) {
            defs = defs.filter(function (f) {
                return f.name.toLowerCase().indexOf(term) !== -1 || f.desc.toLowerCase().indexOf(term) !== -1;
            });
        }
        defs = defs.slice();
        if (_fmSort === 'name') {
            defs.sort(function (a, b) { return a.name.localeCompare(b.name); });
        } else if (_fmSort === 'installed') {
            defs.sort(function (a, b) { return (fmIsOn(b.key) ? 1 : 0) - (fmIsOn(a.key) ? 1 : 0); });
        }

        if (!defs.length) {
            grid.innerHTML = '<div class="bfm-empty">' + (term ? ('No features match "' + fmEsc(_fmSearch) + '".') : 'No features in this category.') + '</div>';
            return;
        }

        grid.innerHTML = defs.map(function (f) {
            var isOn = fmIsOn(f.key);
            var allowed = f.locked || fmIsAllowed(f.key);
            var badge = !allowed
                ? '<span class="bfm-feat-badge bfm-badge-disallowed"><i class="fa fa-lock"></i> Not in your package</span>'
                : f.locked
                    ? '<span class="bfm-feat-badge bfm-badge-required">Always On</span>'
                    : '<span class="bfm-feat-badge">Free</span>';
            var footer = !allowed
                ? '<span class="bfm-disallowed-label"><i class="fa fa-lock"></i> Contact admin to enable</span>'
                : f.locked
                    ? '<span class="bfm-required-label"><i class="fa fa-check"></i> Included</span>'
                    : ('<button type="button" class="bfm-install-btn' + (isOn ? ' bfm-installed' : '') + '" data-toggle="' + f.key + '">' +
                        (isOn ? '<i class="fa fa-check"></i> Installed' : '<i class="fa fa-download"></i> Install') + '</button>');
            var depHint = (allowed && f.key === 'point_of_sale' && !fmPosDepsOk())
                ? '<div class="bfm-dep-hint">Needs Stock + Product</div>' : '';
            return '<div class="bfm-card' + (allowed ? '' : ' bfm-card-disallowed') + '" data-card="' + f.key + '">' +
                '<div class="bfm-card-banner">' +
                    '<img src="' + f.img + '" alt="' + fmEsc(f.name) + '" class="bfm-card-banner-img">' +
                    '<div class="bfm-card-banner-badge">' + badge + '</div>' +
                '</div>' +
                '<div class="bfm-card-body">' +
                    '<div class="bfm-card-name">' + fmEsc(f.name) + '</div>' +
                    '<div class="bfm-card-desc">' + fmEsc(f.desc) + '</div>' +
                    depHint +
                    '<div class="bfm-card-footer">' + footer + '<button type="button" class="bfm-readmore-btn" data-detail="' + f.key + '">Read more</button></div>' +
                '</div>' +
            '</div>';
        }).join('');

        grid.querySelectorAll('[data-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) { e.stopPropagation(); fmToggle(btn.dataset.toggle); });
        });
        grid.querySelectorAll('[data-detail]').forEach(function (el) {
            el.addEventListener('click', function (e) { e.stopPropagation(); openDetail(el.dataset.detail); });
        });
        grid.querySelectorAll('.bfm-card').forEach(function (card) {
            card.addEventListener('click', function () { openDetail(card.dataset.card); });
        });
    }

    function fmToggle(key) {
        var def = BFM_DEFS.find(function (f) { return f.key === key; });
        if (!def || def.locked) return;
        if (!fmIsAllowed(key)) {
            showToast('This feature is not included in your package. Contact an admin to enable it.', 'warning');
            return;
        }
        var turningOn = !fmIsOn(key);

        if (turningOn && key === 'point_of_sale' && !fmPosDepsOk()) {
            showToast('Point of Sale requires Stock Management and Product Management to be enabled first.', 'warning');
            return;
        }

        var prevState = Object.assign({}, bfmState);

        bfmState[key] = turningOn;

        var rival = BFM_MUTUAL_EXCL[key];
        if (rival && turningOn && bfmState[rival]) {
            bfmState[rival] = false;
            showToast('Point of Sale and Restaurant cover the same checkout role, so the other one was turned off.', 'warning');
        }

        if (!turningOn && BFM_POS_DEPS.indexOf(key) !== -1 && bfmState['point_of_sale']) {
            bfmState['point_of_sale'] = false;
            showToast('Point of Sale was disabled because it requires ' +
                (key === 'stock_management' ? 'Stock Management' : 'Product Management') + '.', 'warning');
        }

        renderGrid();
        if (_fmDetailKey) renderDetail(_fmDetailKey);
        syncSidebarFromModal();

        fmPersist(function (ok) {
            if (!ok) {
                bfmState = prevState;
                renderGrid();
                if (_fmDetailKey) renderDetail(_fmDetailKey);
                syncSidebarFromModal();
            }
        });
    }

    function openDetail(key) {
        _fmDetailKey = key;
        renderDetail(key);
        gridView.style.display = 'none';
        detailView.style.display = 'flex';
    }

    function renderDetail(key) {
        var f = BFM_DEFS.find(function (d) { return d.key === key; });
        if (!f) return;
        var isOn = fmIsOn(f.key);
        var allowed = f.locked || fmIsAllowed(f.key);
        var badge = !allowed
            ? '<span class="bfm-feat-badge bfm-badge-disallowed"><i class="fa fa-lock"></i> Not in your package</span>'
            : f.locked
                ? '<span class="bfm-feat-badge bfm-badge-required">Always On</span>'
                : '<span class="bfm-feat-badge">Free</span>';
        var actionHtml = !allowed
            ? '<span class="bfm-disallowed-label"><i class="fa fa-lock"></i> Contact admin to enable this feature</span>'
            : f.locked
                ? '<span class="bfm-required-label"><i class="fa fa-check"></i> Always on for every business</span>'
                : ('<button type="button" class="bfm-install-btn' + (isOn ? ' bfm-installed' : '') + '" id="bfmDetailToggleBtn">' +
                    (isOn ? '<i class="fa fa-check"></i> Installed — click to uninstall' : '<i class="fa fa-download"></i> Install this feature') + '</button>');
        var paras = f.long.map(function (p) { return '<p>' + fmEsc(p) + '</p>'; }).join('');
        document.getElementById('bfmDetailBody').innerHTML =
            '<div class="bfm-detail-head">' +
                '<div class="bfm-detail-icon" style="background:' + f.color + '22"><img src="' + f.img + '" alt="' + fmEsc(f.name) + '"></div>' +
                '<div><h3 class="bfm-detail-name">' + fmEsc(f.name) + '</h3><p class="bfm-detail-desc">' + fmEsc(f.desc) + '</p></div>' +
                '<div style="margin-left:auto">' + badge + '</div>' +
            '</div>' +
            paras +
            '<div class="bfm-detail-foot">' + actionHtml + '</div>';

        var btn = document.getElementById('bfmDetailToggleBtn');
        if (btn) btn.addEventListener('click', function () { fmToggle(f.key); });
    }

    document.getElementById('bfmDetailBack').addEventListener('click', function () {
        detailView.style.display = 'none';
        gridView.style.display = 'flex';
        _fmDetailKey = null;
    });

    function syncSidebarFromModal() {
        var posPreview = document.getElementById('sidebar-pos-wizard-preview');
        if (posPreview) posPreview.style.display = fmIsOn('point_of_sale') ? 'block' : 'none';
    }

    function openModal() {
        modal.classList.add('bfm-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        status.className = 'bfm-status';
        status.textContent = '';
        var userMenu = document.getElementById('userDropdownMenu');
        if (userMenu) userMenu.classList.remove('open');
        document.getElementById('bfmSearch').value = '';
        document.getElementById('bfmSort').value = 'relevant';
        _fmSearch = ''; _fmCategory = 'all'; _fmSort = 'relevant'; _fmDetailKey = null;
        detailView.style.display = 'none';
        gridView.style.display = 'flex';
        renderCategories();
        renderGrid();
    }

    function closeModal() {
        if (bfmDirty) {
            window.location.reload();
            return;
        }
        modal.classList.remove('bfm-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (openBtn) openBtn.focus();
    }

    var bfmOnboarding = @json((bool) session('open_features_modal'));

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function() { if (!bfmOnboarding) closeModal(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('bfm-open') && !bfmOnboarding) closeModal();
    });

    @if(session('open_features_modal'))
    setTimeout(openModal, 0);
    @endif

    document.getElementById('bfmSort').addEventListener('change', function (e) {
        _fmSort = e.target.value;
        renderGrid();
    });
    document.getElementById('bfmSearch').addEventListener('input', function (e) {
        _fmSearch = e.target.value;
        renderGrid();
    });

    saveBtn.addEventListener('click', function() {
        var features = {};
        BFM_DEFS.forEach(function (f) { features[f.key] = fmIsOn(f.key) ? 1 : 0; });
        features['account_management'] = 1; // always required

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';
        status.className = 'bfm-status';
        status.textContent = '';

        fetch('{{ route('business.features.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ features: features }),
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.ok) {
                status.className = 'bfm-status bfm-ok';
                status.textContent = 'Saved successfully.';
                bfmOnboarding = false;
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                throw new Error(data.error || 'Save failed.');
            }
        })
        .catch(function(err) {
            status.className = 'bfm-status bfm-err';
            status.textContent = err.message || 'Something went wrong.';
        })
        .finally(function() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save changes';
        });
    });
})();
</script>
@endif

{{-- Global toast notifications --}}
<div id="app-toast-container" aria-live="assertive" aria-atomic="false"></div>
<style>
#app-toast-container{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column-reverse;gap:10px;pointer-events:none;}
.app-toast{
    pointer-events:auto;display:flex;align-items:flex-start;gap:10px;
    padding:13px 16px;border-radius:12px;font-size:13px;font-weight:600;line-height:1.45;
    max-width:360px;min-width:220px;
    box-shadow:0 6px 28px rgba(0,0,0,.28);border:1px solid transparent;
    animation:appToastIn .22s cubic-bezier(.34,1.28,.64,1) forwards;
    cursor:pointer;
}
.app-toast i{flex-shrink:0;margin-top:2px;font-size:13px;}
.app-toast--error{background:color-mix(in srgb,#ef4444 13%,var(--card));color:color-mix(in srgb,#ef4444 85%,var(--text));border-color:color-mix(in srgb,#ef4444 38%,var(--border));}
.app-toast--warning{background:color-mix(in srgb,#f59e0b 13%,var(--card));color:color-mix(in srgb,#b45309 90%,var(--text));border-color:color-mix(in srgb,#f59e0b 40%,var(--border));}
.app-toast--success{background:color-mix(in srgb,#22c55e 13%,var(--card));color:color-mix(in srgb,#16a34a 90%,var(--text));border-color:color-mix(in srgb,#22c55e 38%,var(--border));}
html[data-theme="light"] .app-toast--error,html[data-theme="light_blue"] .app-toast--error{background:#fef2f2;color:#b91c1c;border-color:#fca5a5;}
html[data-theme="light"] .app-toast--warning,html[data-theme="light_blue"] .app-toast--warning{background:#fffbeb;color:#92400e;border-color:#fcd34d;}
html[data-theme="light"] .app-toast--success,html[data-theme="light_blue"] .app-toast--success{background:#f0fdf4;color:#15803d;border-color:#86efac;}
.app-toast--closing{animation:appToastOut .18s ease forwards;}
@keyframes appToastIn{from{opacity:0;transform:translateY(14px) scale(.96);}to{opacity:1;transform:translateY(0) scale(1);}}
@keyframes appToastOut{from{opacity:1;transform:translateY(0) scale(1);}to{opacity:0;transform:translateY(10px) scale(.96);}}
@media(max-width:480px){#app-toast-container{bottom:16px;right:12px;left:12px;}.app-toast{max-width:100%;}}
</style>
<script>
window.showToast = (function() {
    var icons = {error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', success: 'fa-circle-check'};
    return function(msg, type) {
        type = type || 'error';
        var container = document.getElementById('app-toast-container');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'app-toast app-toast--' + type;
        toast.setAttribute('role', 'alert');
        var icon = document.createElement('i');
        icon.className = 'fa ' + (icons[type] || icons.error);
        icon.setAttribute('aria-hidden', 'true');
        var text = document.createElement('span');
        text.textContent = msg;
        toast.appendChild(icon);
        toast.appendChild(text);
        container.appendChild(toast);
        function dismiss() {
            if (toast.classList.contains('app-toast--closing')) return;
            toast.classList.add('app-toast--closing');
            setTimeout(function() { toast.parentNode && toast.parentNode.removeChild(toast); }, 200);
        }
        toast.addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);
    };
})();
</script>
<script>
(function () {
    var sidebar    = document.getElementById('appSidebar');
    var content    = document.querySelector('.content');
    var toggleBtn  = document.getElementById('sidebarDesktopToggle');
    var hamburger  = document.getElementById('sidebarHamburgerBtn');
    var backdrop   = document.getElementById('sidebarMobileBackdrop');
    if (!sidebar) return;

    /* ── Desktop collapse ─────────────────────────────────────────── */
    var collapsed  = localStorage.getItem('sb_collapsed') === '1';
    function applyCollapsed(c, skipStorage) {
        sidebar.classList.toggle('sidebar--collapsed', c);
        if (content) content.classList.toggle('content--sidebar-collapsed', c);
        var chevronClass = c ? 'fa fa-chevron-right' : 'fa fa-chevron-left';
        var label = c ? 'Expand sidebar' : 'Collapse sidebar';
        if (toggleBtn) {
            var icon = toggleBtn.querySelector('i');
            if (icon) icon.className = chevronClass;
            toggleBtn.title = label;
            toggleBtn.setAttribute('aria-label', label);
        }
        if (!skipStorage) localStorage.setItem('sb_collapsed', c ? '1' : '0');
    }
    applyCollapsed(collapsed, true);

    function doToggle() { collapsed = !collapsed; applyCollapsed(collapsed, false); }
    if (toggleBtn) toggleBtn.addEventListener('click', doToggle);

    /* ── Mobile open / close ──────────────────────────────────────── */
    function openMobile() {
        sidebar.classList.add('sidebar--mobile-open');
        if (backdrop) { backdrop.classList.add('is-open'); backdrop.setAttribute('aria-hidden', 'false'); }
        if (hamburger) hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
    function closeMobile() {
        sidebar.classList.remove('sidebar--mobile-open');
        if (backdrop) { backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden', 'true'); }
        if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }
    if (hamburger) hamburger.addEventListener('click', openMobile);
    if (backdrop)  backdrop.addEventListener('click', closeMobile);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--mobile-open')) closeMobile();
    });
})();
</script>
<script>
(function () {
    var nav = document.querySelector('#appSidebar nav.menu');
    if (!nav) return;

    // Build list of all collapsible groups
    var groups = [];
    nav.querySelectorAll('div.menu-group-title').forEach(function (title) {
        var sub = title.nextElementSibling;
        if (!sub || !sub.classList.contains('submenu')) return;
        var chev = document.createElement('i');
        chev.className = 'fa fa-chevron-down menu-group-chevron';
        chev.setAttribute('aria-hidden', 'true');
        title.appendChild(chev);
        title.setAttribute('role', 'button');
        title.setAttribute('tabindex', '0');
        groups.push({ title: title, sub: sub });
    });

    var STORE_KEY = 'sb_open_group';
    // Restore which group was open — default: none (all collapsed)
    var openLabel = localStorage.getItem(STORE_KEY) || '';

    function collapse(g, animate) {
        if (animate) {
            g.sub.style.maxHeight = g.sub.scrollHeight + 'px';
            requestAnimationFrame(function () { g.sub.style.maxHeight = '0'; });
        } else {
            g.sub.style.maxHeight = '0';
        }
        g.title.classList.add('group--collapsed');
        g.title.setAttribute('aria-expanded', 'false');
    }

    function expand(g, animate) {
        if (animate) {
            g.sub.style.maxHeight = g.sub.scrollHeight + 'px';
            g.sub.addEventListener('transitionend', function h(e) {
                if (e.propertyName !== 'max-height') return;
                g.sub.removeEventListener('transitionend', h);
                if (!g.title.classList.contains('group--collapsed')) g.sub.style.maxHeight = 'none';
            });
        } else {
            g.sub.style.maxHeight = 'none';
        }
        g.title.classList.remove('group--collapsed');
        g.title.setAttribute('aria-expanded', 'true');
    }

    function labelOf(g) {
        return (g.title.querySelector('span') || g.title).textContent.trim();
    }

    // Apply initial state — all collapsed except the saved open group
    groups.forEach(function (g) {
        if (openLabel && labelOf(g) === openLabel) {
            expand(g, false);
        } else {
            collapse(g, false);
        }
    });

    // Click handler — accordion: open clicked, close all others
    groups.forEach(function (g) {
        function toggle() {
            var isCollapsed = g.title.classList.contains('group--collapsed');
            if (isCollapsed) {
                // Close all others first
                groups.forEach(function (other) {
                    if (other !== g) collapse(other, true);
                });
                expand(g, true);
                localStorage.setItem(STORE_KEY, labelOf(g));
            } else {
                collapse(g, true);
                localStorage.setItem(STORE_KEY, '');
            }
        }
        g.title.addEventListener('click', toggle);
        g.title.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); }
        });
    });

    // Auto-open the group containing the active link on page load
    if (!openLabel) {
        groups.forEach(function (g) {
            if (g.sub.querySelector('a.active')) {
                expand(g, false);
                localStorage.setItem(STORE_KEY, labelOf(g));
            }
        });
    }
})();
</script>
</body>
</html>
