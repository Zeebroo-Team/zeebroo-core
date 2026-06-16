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
                $defaults = ['account_management' => true, 'bill_management' => true, 'human_resources' => true, 'point_of_sale' => true, 'product_management' => true, 'social_media_campaign' => true, 'stock_management' => true];
                return !empty($saved) ? array_merge($defaults, array_map('boolval', $saved)) : $defaults;
            })()
            : [];
        $featureOn = fn (string $key) => (bool) ($businessFeatures[$key] ?? true);

        $showSidebarLoansLink = $navBusiness && $navBusiness->loans()->exists();
        $sidebarLoanDueHighlight = $showSidebarLoansLink && $navBusiness
            ? app(\Modules\Account\Services\LoanOverviewTooltipService::class)->businessHasOverdueLoanInstallments($navBusiness)
            : false;
        $showSidebarRentalsLink = $navBusiness && $navBusiness->rentals()->exists();
        $sidebarRentalDueHighlight = $showSidebarRentalsLink && $navBusiness
            ? app(\Modules\Account\Services\RentalService::class)->businessHasOverdueRentalPayments($navBusiness)
            : false;
        $showSidebarBillsLink = $navBusiness && $navBusiness->bills()->exists();

        // Catalog — always visible when Product Management feature is enabled.
        $productFeatureOn = $navBusiness && $featureOn('product_management');
        $showSidebarProductBrandsLink = $navBusiness && Route::has('product.brands.index')
            && ($productFeatureOn || $navBusiness->productBrands()->exists());
        $showSidebarProductCategoriesLink = $navBusiness && Route::has('product.categories.index')
            && ($productFeatureOn || $navBusiness->productCategories()->exists());
        $showSidebarProductUnitsLink = $navBusiness && Route::has('product.units.index')
            && ($productFeatureOn || $navBusiness->productUnits()->exists());
        $showSidebarProductsLink = $navBusiness && Route::has('product.index')
            && ($productFeatureOn || $navBusiness->products()->exists());
        $showSidebarBarcodesLink = $navBusiness && Route::has('product.barcodes.index')
            && ($productFeatureOn || $navBusiness->productBarcodeSheets()->exists());
        $showSidebarDiscountsLink = $navBusiness && Route::has('product.discounts.index')
            && ($productFeatureOn || $navBusiness->productDiscounts()->exists());
        $showSidebarProductSection = $showSidebarProductBrandsLink
            || $showSidebarProductCategoriesLink
            || $showSidebarProductUnitsLink
            || $showSidebarProductsLink
            || $showSidebarBarcodesLink
            || $showSidebarDiscountsLink;

        // Stock Management — always visible when Stock Management feature is enabled.
        $stockFeatureOn = $navBusiness && $featureOn('stock_management');
        $showSidebarPurchasesLink = $navBusiness && Route::has('purchase.index')
            && ($stockFeatureOn || $navBusiness->purchases()->exists());
        $showSidebarGrnLink = $navBusiness && Route::has('purchase.grn.index')
            && ($stockFeatureOn || $navBusiness->goodsReceiveNotes()->exists());
        $showSidebarSuppliersLink = $navBusiness && Route::has('purchase.suppliers.index')
            && ($stockFeatureOn || $navBusiness->suppliers()->exists());
        $showSidebarChequesLink = $navBusiness && Route::has('purchase.cheques.index')
            && ($stockFeatureOn || $navBusiness->chequePayments()->exists());
        $showSidebarStockAuditLink = $navBusiness && Route::has('pos.stock-audits.index')
            && ($stockFeatureOn || \Modules\Pos\Models\StockAudit::query()->where('business_id', $navBusiness->id)->exists());
        $showSidebarPurchaseSection = $showSidebarPurchasesLink
            || $showSidebarGrnLink
            || $showSidebarSuppliersLink
            || $showSidebarChequesLink
            || $showSidebarStockAuditLink;

        // POS — always visible when Point of Sale feature is enabled.
        $posFeatureOn = $navBusiness && $featureOn('point_of_sale');
        $showSidebarPosRegisterLink = $navBusiness && Route::has('pos.online')
            && ($posFeatureOn || $navBusiness->products()->where('is_active', true)->where('is_bundle', false)->exists());
        $showSidebarPosSalesLink = $navBusiness && Route::has('pos.sales.index')
            && ($posFeatureOn || $navBusiness->sales()->exists());
        $showSidebarPosEodLink = $navBusiness && Route::has('pos.end-of-day')
            && ($posFeatureOn || $navBusiness->sales()->where('is_settled', false)->exists());
        $showSidebarPosCustomersLink = $navBusiness && Route::has('pos.customers.index')
            && ($posFeatureOn || \Modules\Pos\Models\Customer::query()->where('business_id', $navBusiness->id)->exists());
        $showSidebarPosReturnsLink = $navBusiness && Route::has('pos.returns.index')
            && ($posFeatureOn || \Modules\Pos\Models\SaleReturn::query()->where('business_id', $navBusiness->id)->exists());
        // Sales Quotations and Invoices (always show when module is active)
        $showSidebarQuotationsLink = $navBusiness && Route::has('sales.quotations.index');
        $showSidebarInvoicesLink   = $navBusiness && Route::has('sales.invoices.index');

        // Hub link shows whenever the Sales section is visible (feature on, or data-driven links are showing).
        $showSidebarPosSection = $showSidebarPosRegisterLink || $showSidebarPosSalesLink
            || $showSidebarQuotationsLink || $showSidebarInvoicesLink || ($navBusiness && $posFeatureOn);
        $showSidebarPosHubLink = $navBusiness && Route::has('pos.index') && $showSidebarPosSection;

        $showSidebarFilesLink = $navBusiness && (
            $navBusiness->fileManagerFiles()->exists() || $navBusiness->fileManagerFolders()->exists()
        );
        $showSidebarDesignStudioLink = $navBusiness && Route::has('designstudio.index');
        $showSidebarServiceLink = $navBusiness && Route::has('service.catalog.index');
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
            $showSidebarLoansLink = false;
            $showSidebarRentalsLink = false;
            $showSidebarBillsLink = false;
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
            $showSidebarFilesLink = false;
            $showSidebarPropertiesLink = false;
            $showSidebarModificationsLink = false;
            $showSidebarDesignStudioLink = false;
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
            <div class="menu-section">Main</div>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa fa-gauge-high"></i><span>Overview</span></a>
            <a href="{{ route('aibot.index') }}" class="{{ request()->routeIs('aibot.*') ? 'active' : '' }}"><i class="fa fa-robot"></i><span>AI Agent</span></a>
            @if($showSidebarModificationsLink)
                <a href="{{ route('modification.index') }}" class="{{ request()->routeIs('modification.*') ? 'active' : '' }}"><i class="fa fa-screwdriver-wrench"></i><span>Modification</span></a>
            @endif
            @if($showSidebarPropertiesLink && Route::has('account.properties.index'))
                <a href="{{ route('account.properties.index') }}" class="{{ request()->routeIs('account.properties.*') ? 'active' : '' }}"><i class="fa fa-building"></i><span>Property</span></a>
            @endif
            @if($showSidebarLoansLink)
                <a href="{{ route('account.loans.index') }}" @class([
                    'menu-loan-mgmt',
                    'active' => request()->routeIs('account.loans.*'),
                    'menu-loan-mgmt--due' => $sidebarLoanDueHighlight,
                ]) @if($sidebarLoanDueHighlight) title="At least one loan has a due date in the past without a ledger installment yet." @endif>
                    <i class="fa fa-hand-holding-dollar" aria-hidden="true"></i><span>Loan management</span>
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
            @if($showSidebarProductSection)
                <div class="menu-group-title">
                    <i class="fa fa-boxes-stacked"></i><span>Catalog</span>
                </div>
                <div class="submenu" aria-label="Catalog">
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
                </div>
            @endif

            @if($showSidebarFilesLink && Route::has('filemanager.index'))
                <a href="{{ route('filemanager.index') }}" class="{{ request()->routeIs('filemanager.*') ? 'active' : '' }}"><i class="fa fa-folder-open"></i><span>Files</span></a>
            @endif
            @if($showSidebarDesignStudioLink)
                <a href="{{ route('designstudio.index') }}" class="{{ request()->routeIs('designstudio.*') ? 'active' : '' }}"><i class="fa fa-palette"></i><span>Design Studio</span></a>
                <div class="submenu">
                    @if(Route::has('designstudio.social-media.index'))
                        <a href="{{ route('designstudio.social-media.index') }}" @class(['active' => request()->routeIs('designstudio.social-media.*')])>
                            <i class="fa fa-share-nodes"></i><span>Social Media</span>
                        </a>
                    @endif
                </div>
            @endif
            @if($showSidebarServiceLink)
                <a href="{{ route('service.catalog.index') }}" class="{{ request()->routeIs('service.*') ? 'active' : '' }}"><i class="fa fa-screwdriver-wrench"></i><span>Services</span></a>
                <div class="submenu">
                    <a href="{{ route('service.catalog.index') }}" @class(['active' => request()->routeIs('service.catalog.*')])>
                        <i class="fa fa-list-check"></i><span>Catalog</span>
                    </a>
                    <a href="{{ route('service.requests.index') }}" @class(['active' => request()->routeIs('service.requests.*')])>
                        <i class="fa fa-inbox"></i><span>Requests</span>
                    </a>
                </div>
            @endif
            @if($showSidebarDocumentationLink)
                <a href="{{ route('documentation.documents.index') }}" class="{{ request()->routeIs('documentation.*') ? 'active' : '' }}"><i class="fa fa-book-open"></i><span>Documentation</span></a>
            @endif
            @if($navBusiness && ($hrFeatureOn || $hrPayrollOptedIn))
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
            @if($showSidebarSettingsSection)
                <div class="menu-section">Configuration</div>
                <div class="menu-group-title">
                    <i class="fa fa-sliders"></i><span>Settings</span>
                </div>
                <div class="submenu">
                    <a href="{{ route('settings.business') }}" class="{{ request()->routeIs('settings.business') ? 'active' : '' }}"><i class="fa fa-briefcase"></i><span>Business Settings</span></a>
                    <a href="{{ route('settings.user') }}" class="{{ request()->routeIs('settings.user') ? 'active' : '' }}"><i class="fa fa-user-gear"></i><span>User Settings</span></a>
                    @if(Route::has('app-connection.index'))
                        <a href="{{ route('app-connection.index') }}" class="{{ request()->routeIs('app-connection.*') ? 'active' : '' }}"><i class="fa fa-plug"></i><span>App connections</span></a>
                    @endif
                </div>
            @endif
            @if(auth()->user()?->hasRole('admin'))
                <a href="{{ route('admin.panel') }}" class="{{ request()->routeIs('admin.panel') ? 'active' : '' }}"><i class="fa fa-user-shield"></i><span>Admin Panel</span></a>
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
                @if($navBusiness)
                    <a href="{{ route('business.profile') }}" class="user-trigger nav-business-profile @if(request()->routeIs('business.profile')) nav-business-profile--active @endif" title="Business profile">
                        <i class="fa fa-id-card"></i>
                        <span>Business profile</span>
                    </a>
                @endif
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
                        @if($navBusinesses->count() > 1)
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">Selected business</div>
                                <form method="post" action="{{ route('business.select') }}">
                                    @csrf
                                    <select name="business_id" class="dropdown-select" onchange="this.form.submit()">
                                        @foreach($navBusinesses as $businessOption)
                                            <option value="{{ $businessOption->id }}" {{ (int) ($navBusiness?->id ?? 0) === (int) $businessOption->id ? 'selected' : '' }}>
                                                {{ $businessOption->name }}
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
                            <div class="menu-row" style="display:block;">
                                <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">About Business</div>
                                <div style="font-size:13px;line-height:1.4;">{{ $navBusiness->description ?: 'No description added yet.' }}</div>
                            </div>
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
                            <span class="pkg-badge">Free Trial</span>
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
<style>
.bfm-overlay{position:fixed;inset:0;z-index:400;display:none;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}
.bfm-overlay.bfm-open{display:flex;}
.bfm-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.48);backdrop-filter:blur(3px);}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .bfm-backdrop{background:rgba(15,23,42,.35);}
.bfm-card{position:relative;z-index:1;width:100%;max-width:680px;background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 24px 56px rgba(0,0,0,.32);display:flex;flex-direction:column;max-height:min(90vh,700px);overflow:hidden;}
.bfm-head{padding:20px 20px 14px;border-bottom:1px solid var(--border);flex-shrink:0;position:relative;}
.bfm-title{margin:0 0 4px;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--text);}
.bfm-sub{margin:0;font-size:13px;color:var(--muted);}
.bfm-close{position:absolute;top:16px;right:16px;width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;display:grid;place-items:center;font-size:16px;line-height:1;padding:0;}
.bfm-close:hover{border-color:var(--primary);color:var(--text);}
.bfm-body{padding:18px 20px;overflow-y:auto;flex:1;min-height:0;}
.bfm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;}
.bfm-card-item{border:2px solid var(--border);border-radius:13px;padding:14px 12px;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;transition:all .18s ease;user-select:none;background:var(--card);position:relative;}
.bfm-card-item:hover{transform:translateY(-2px);border-color:var(--primary);box-shadow:0 6px 16px color-mix(in srgb,var(--primary) 16%,transparent);}
.bfm-card-item.bfm-enabled{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,var(--card));}
.bfm-card-item.bfm-disabled{opacity:.52;filter:grayscale(.8);border-color:color-mix(in srgb,var(--border) 80%,transparent);}
.bfm-feat-img{width:52px;height:52px;object-fit:contain;pointer-events:none;}
.bfm-feat-name{font-size:12.5px;font-weight:700;color:var(--text);text-align:center;line-height:1.25;pointer-events:none;}
.bfm-feat-badge{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:2px 8px;border-radius:999px;pointer-events:none;}
.bfm-feat-badge.bfm-badge-on{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.bfm-feat-badge.bfm-badge-off{background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);}
.bfm-feat-badge.bfm-badge-required{background:color-mix(in srgb,#6366f1 14%,transparent);color:#4f46e5;}
html[data-theme="light"] .bfm-feat-badge.bfm-badge-on,html[data-theme="light_blue"] .bfm-feat-badge.bfm-badge-on{background:#dcfce7;color:#15803d;}
html[data-theme="light"] .bfm-feat-badge.bfm-badge-off,html[data-theme="light_blue"] .bfm-feat-badge.bfm-badge-off{background:#f3f4f6;color:#6b7280;}
html[data-theme="light"] .bfm-feat-badge.bfm-badge-required,html[data-theme="light_blue"] .bfm-feat-badge.bfm-badge-required{background:#ede9fe;color:#4338ca;}
.bfm-card-item.bfm-required{cursor:default;}
.bfm-card-item.bfm-required:hover{border-color:var(--border);transform:none;}
.bfm-card-item.bfm-dep-blocked{opacity:.45;filter:grayscale(.6);border-style:dashed;}
.bfm-card-item.bfm-dep-blocked:hover{border-color:color-mix(in srgb,#f59e0b 55%,var(--border));transform:none;}
.bfm-dep-hint{font-size:10px;font-weight:600;color:#b45309;margin-top:3px;text-align:center;pointer-events:none;}
html[data-theme="light"] .bfm-dep-hint,html[data-theme="light_blue"] .bfm-dep-hint{color:#92400e;}
.bfm-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px;justify-content:flex-end;flex-shrink:0;}
.bfm-cancel{background:transparent;border:1px solid var(--border);color:var(--text);padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;}
.bfm-cancel:hover{border-color:var(--primary);}
.bfm-save{padding:9px 20px;font-size:13px;font-weight:700;}
.bfm-status{font-size:12px;font-weight:600;margin-right:auto;display:none;}
.bfm-status.bfm-ok{color:#16a34a;display:block;}
.bfm-status.bfm-err{color:#dc2626;display:block;}
</style>
<div id="bizFeaturesModal" class="bfm-overlay" role="dialog" aria-modal="true" aria-labelledby="bfm-title" aria-hidden="true">
    <div class="bfm-backdrop" id="bfmBackdrop"></div>
    <div class="bfm-card">
        <div class="bfm-head">
            <h2 class="bfm-title" id="bfm-title">Business Features</h2>
            <p class="bfm-sub">Enable or disable features for <strong>{{ $navBusiness->name }}</strong>. Changes are saved immediately.</p>
            <button type="button" class="bfm-close" id="bfmCloseBtn" aria-label="Close modal" @if(session('open_features_modal')) style="display:none;" @endif>&times;</button>
        </div>
        <div class="bfm-body">
            <div class="bfm-grid" id="bfmGrid">
                @php
                    $bfmItems = [
                        ['key' => 'account_management',   'label' => 'Account Management',   'img' => 'features/account-management.png'],
                        ['key' => 'bill_management',      'label' => 'Bill Management',       'img' => 'features/bill-management.png'],
                        ['key' => 'human_resources',      'label' => 'Human Resources',       'img' => 'features/human-resource-management.png'],
                        ['key' => 'point_of_sale',        'label' => 'Point of Sale',         'img' => 'features/point-of-sale.png'],
                        ['key' => 'product_management',   'label' => 'Product Management',    'img' => 'features/product-management.svg'],
                        ['key' => 'social_media_campaign','label' => 'Social Media Campaign', 'img' => 'features/social-media-campaign.png'],
                        ['key' => 'stock_management',     'label' => 'Stock Management',      'img' => 'features/stock-management.png'],
                    ];
                @endphp
                @foreach($bfmItems as $bfmItem)
                    @php
                        $bfmRequired       = $bfmItem['key'] === 'account_management';
                        $bfmOn = $bfmRequired ? true : ($businessFeatures[$bfmItem['key']] ?? true);
                    @endphp
                    @if($bfmRequired)
                    <div class="bfm-card-item bfm-enabled bfm-required"
                         data-feature="{{ $bfmItem['key'] }}"
                         role="checkbox"
                         aria-checked="true"
                         aria-disabled="true"
                         tabindex="0"
                         title="Account Management is always required and cannot be disabled."
                         onkeydown="">
                        <img src="{{ asset($bfmItem['img']) }}" class="bfm-feat-img" alt="{{ $bfmItem['label'] }}">
                        <div class="bfm-feat-name">{{ $bfmItem['label'] }}</div>
                        <span class="bfm-feat-badge bfm-badge-required">Required</span>
                    </div>
                    @else
                    <div class="bfm-card-item {{ $bfmOn ? 'bfm-enabled' : 'bfm-disabled' }}"
                         data-feature="{{ $bfmItem['key'] }}"
                         role="checkbox"
                         aria-checked="{{ $bfmOn ? 'true' : 'false' }}"
                         tabindex="0"
                         onclick="bfmToggle(this)"
                         onkeydown="if(event.key===' '||event.key==='Enter'){event.preventDefault();bfmToggle(this);}">
                        <img src="{{ asset($bfmItem['img']) }}" class="bfm-feat-img" alt="{{ $bfmItem['label'] }}">
                        <div class="bfm-feat-name">{{ $bfmItem['label'] }}</div>
                        <span class="bfm-feat-badge {{ $bfmOn ? 'bfm-badge-on' : 'bfm-badge-off' }}">{{ $bfmOn ? 'Enabled' : 'Disabled' }}</span>
                        @if($bfmItem['key'] === 'point_of_sale')
                            <span class="bfm-dep-hint" id="bfmPosDepHint" style="display:none;">Needs Stock + Product</span>
                        @endif
                    </div>
                    @endif
                @endforeach
            </div>
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
    if (!modal || !openBtn) return;

    function openModal() {
        modal.classList.add('bfm-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        status.className = 'bfm-status';
        status.textContent = '';
        var userMenu = document.getElementById('userDropdownMenu');
        if (userMenu) userMenu.classList.remove('open');
        bfmUpdateDepStates();
        // Focus first card
        var first = modal.querySelector('.bfm-card-item');
        if (first) setTimeout(function(){ first.focus(); }, 60);
    }

    function closeModal() {
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

    // POS dependencies: both must be enabled to allow POS
    var BFM_POS_DEPS = ['stock_management', 'product_management'];

    function bfmIsEnabled(feature) {
        var c = modal.querySelector('[data-feature="' + feature + '"]');
        return c && c.classList.contains('bfm-enabled');
    }

    function bfmSetCard(feature, enable) {
        var c = modal.querySelector('[data-feature="' + feature + '"]');
        if (!c || c.classList.contains('bfm-required')) return;
        var b = c.querySelector('.bfm-feat-badge');
        if (enable) {
            c.classList.replace('bfm-disabled', 'bfm-enabled');
            c.setAttribute('aria-checked', 'true');
            if (b) { b.textContent = 'Enabled'; b.className = 'bfm-feat-badge bfm-badge-on'; }
        } else {
            c.classList.replace('bfm-enabled', 'bfm-disabled');
            c.setAttribute('aria-checked', 'false');
            if (b) { b.textContent = 'Disabled'; b.className = 'bfm-feat-badge bfm-badge-off'; }
        }
    }

    function bfmPosDepsOk() {
        return BFM_POS_DEPS.every(function(f) { return bfmIsEnabled(f); });
    }

    function bfmUpdateDepStates() {
        var posCard = modal.querySelector('[data-feature="point_of_sale"]');
        var hint    = document.getElementById('bfmPosDepHint');
        if (!posCard) return;
        var depsOk = bfmPosDepsOk();
        if (depsOk) {
            posCard.classList.remove('bfm-dep-blocked');
            if (hint) hint.style.display = 'none';
        } else {
            posCard.classList.add('bfm-dep-blocked');
            if (hint) hint.style.display = 'block';
        }
    }

    window.bfmToggle = function(card) {
        if (card.classList.contains('bfm-required')) return;
        var feature = card.dataset.feature;
        var isOn    = card.classList.contains('bfm-enabled');
        var badge   = card.querySelector('.bfm-feat-badge');

        // Block enabling POS when dependencies are not met
        if (!isOn && feature === 'point_of_sale' && !bfmPosDepsOk()) {
            showToast('Point of Sale requires Stock Management and Product Management to be enabled first.', 'warning');
            return;
        }

        if (isOn) {
            card.classList.replace('bfm-enabled', 'bfm-disabled');
            card.setAttribute('aria-checked', 'false');
            if (badge) { badge.textContent = 'Disabled'; badge.className = 'bfm-feat-badge bfm-badge-off'; }
        } else {
            card.classList.replace('bfm-disabled', 'bfm-enabled');
            card.setAttribute('aria-checked', 'true');
            if (badge) { badge.textContent = 'Enabled'; badge.className = 'bfm-feat-badge bfm-badge-on'; }
        }

        // Auto-disable POS if one of its dependencies is being turned off
        if (isOn && BFM_POS_DEPS.indexOf(feature) !== -1 && bfmIsEnabled('point_of_sale')) {
            bfmSetCard('point_of_sale', false);
            showToast('Point of Sale was disabled because it requires ' +
                (feature === 'stock_management' ? 'Stock Management' : 'Product Management') + '.', 'warning');
        }

        bfmUpdateDepStates();
        syncSidebarFromModal();
    };

    function syncSidebarFromModal() {
        var posPreview = document.getElementById('sidebar-pos-wizard-preview');
        if (!posPreview) return;
        var posCard = modal.querySelector('[data-feature="point_of_sale"]');
        if (!posCard) return;
        posPreview.style.display = posCard.classList.contains('bfm-enabled') ? 'block' : 'none';
    }

    saveBtn.addEventListener('click', function() {
        var cards = modal.querySelectorAll('.bfm-card-item[data-feature]');
        var features = {};
        cards.forEach(function(card) {
            features[card.dataset.feature] = card.classList.contains('bfm-enabled') ? 1 : 0;
        });
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
                setTimeout(closeModal, 900);
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
