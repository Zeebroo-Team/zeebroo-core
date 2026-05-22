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
        .menu-section{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin:4px 2px 2px}
        .menu{display:flex;flex-direction:column;gap:6px}
        .menu a{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid transparent;border-radius:10px;text-decoration:none;color:var(--text);font-weight:500;font-size:13px;transition:all .2s ease}
        .menu a i{width:14px;text-align:center;color:var(--muted);font-size:12px}
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
        .menu-group-title{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:12px;font-weight:600;background:color-mix(in srgb,var(--primary) 8%,transparent)}
        .submenu{display:flex;flex-direction:column;gap:4px;margin-left:12px;padding-left:8px;border-left:1px dashed color-mix(in srgb,var(--primary) 35%,var(--border))}
        .submenu a{padding:7px 9px;font-size:12px}
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
        @media (max-width:900px){.sidebar{position:static;width:auto;height:auto;border-right:0;border-bottom:1px solid var(--border)}.content{margin-left:0;border-left:0}}
    </style>
</head>
@php
    $posWalkingCustomer = (bool) session('pos_walking_customer', true);
    $posOnlyShell = $posWalkingCustomer && request()->routeIs('pos.online', 'pos.register', 'pos.checkout');
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
        $showSidebarLoansLink = $navBusiness && $navBusiness->loans()->exists();
        $sidebarLoanDueHighlight = $showSidebarLoansLink && $navBusiness
            ? app(\Modules\Account\Services\LoanOverviewTooltipService::class)->businessHasOverdueLoanInstallments($navBusiness)
            : false;
        $showSidebarRentalsLink = $navBusiness && $navBusiness->rentals()->exists();
        $sidebarRentalDueHighlight = $showSidebarRentalsLink && $navBusiness
            ? app(\Modules\Account\Services\RentalService::class)->businessHasOverdueRentalPayments($navBusiness)
            : false;
        $showSidebarBillsLink = $navBusiness && $navBusiness->bills()->exists();
        $showSidebarProductBrandsLink = $navBusiness && Route::has('product.brands.index') && $navBusiness->productBrands()->exists();
        $showSidebarProductCategoriesLink = $navBusiness && Route::has('product.categories.index') && $navBusiness->productCategories()->exists();
        $showSidebarProductUnitsLink = $navBusiness && Route::has('product.units.index') && $navBusiness->productUnits()->exists();
        $showSidebarProductsLink = $navBusiness && Route::has('product.index') && $navBusiness->products()->exists();
        $showSidebarProductSection = $showSidebarProductBrandsLink
            || $showSidebarProductCategoriesLink
            || $showSidebarProductUnitsLink
            || $showSidebarProductsLink;
        $showSidebarPurchasesLink = $navBusiness && Route::has('purchase.index') && $navBusiness->purchases()->exists();
        $showSidebarGrnLink = $navBusiness && Route::has('purchase.grn.index') && $navBusiness->goodsReceiveNotes()->exists();
        $showSidebarSuppliersLink = $navBusiness && Route::has('purchase.suppliers.index') && $navBusiness->suppliers()->exists();
        $showSidebarChequesLink = $navBusiness && Route::has('purchase.cheques.index') && $navBusiness->chequePayments()->exists();
        $showSidebarPurchaseSection = $showSidebarPurchasesLink
            || $showSidebarGrnLink
            || $showSidebarSuppliersLink
            || $showSidebarChequesLink;
        $showSidebarPosRegisterLink = $navBusiness && Route::has('pos.online') && $navBusiness->products()->where('is_active', true)->where('is_bundle', false)->exists();
        $showSidebarPosHubLink = $navBusiness && Route::has('pos.index');
        $showSidebarPosSalesLink = $navBusiness && Route::has('pos.sales.index') && $navBusiness->sales()->exists();
        $showSidebarPosSection = $showSidebarPosHubLink || $showSidebarPosRegisterLink || $showSidebarPosSalesLink;
        $showSidebarFilesLink = $navBusiness && (
            $navBusiness->fileManagerFiles()->exists() || $navBusiness->fileManagerFolders()->exists()
        );
        $showSidebarPropertiesLink = $navBusiness
            ? \Modules\Account\Models\Property::query()->where('business_id', $navBusiness->id)->exists()
            : false;
        $showSidebarModificationsLink = $navBusiness
            && Route::has('modification.index')
            && $navBusiness->modifications()->exists();
        $sidebarBillDueHighlight = $showSidebarBillsLink && $navBusiness
            ? app(\Modules\Account\Services\BillService::class)->businessHasOverdueBillPayments($navBusiness)
            : false;
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
            $showSidebarPurchasesLink = false;
            $showSidebarGrnLink = false;
            $showSidebarSuppliersLink = false;
            $showSidebarChequesLink = false;
            $showSidebarPurchaseSection = false;
            $showSidebarPosRegisterLink = false;
            $showSidebarPosHubLink = false;
            $showSidebarPosSalesLink = false;
            $showSidebarPosSection = false;
            $showSidebarFilesLink = false;
            $showSidebarPropertiesLink = false;
            $showSidebarModificationsLink = false;
            $sidebarLoanDueHighlight = false;
            $sidebarRentalDueHighlight = false;
            $sidebarBillDueHighlight = false;
            $sidebarPayrollOverdueHighlight = false;
            $sidebarPayrollCyclesOverdueHighlight = false;
        }
    @endphp
    @unless($minimalAppShell)
    <aside class="sidebar{{ $employeePortal ? ' sidebar--employee-portal' : '' }}">
        @if($employeePortal)
            <div class="brand">{{ __('HR portal') }}</div>
            <nav class="menu" aria-label="{{ __('Employee HR portal navigation') }}">
                <div class="menu-section">{{ __('Self-service') }}</div>
                <a href="{{ route('hr.portal.dashboard') }}" class="{{ request()->routeIs('hr.portal.dashboard') ? 'active' : '' }}"><i class="fa fa-house" aria-hidden="true"></i><span>{{ __('Home') }}</span></a>
                <a href="{{ route('hr.portal.profile') }}" class="{{ request()->routeIs('hr.portal.profile') ? 'active' : '' }}"><i class="fa fa-user" aria-hidden="true"></i><span>{{ __('My profile') }}</span></a>
                <a href="{{ route('hr.portal.leaves') }}" class="{{ request()->routeIs('hr.portal.leaves') ? 'active' : '' }}"><i class="fa fa-calendar-days" aria-hidden="true"></i><span>{{ __('My leaves') }}</span></a>
                <a href="{{ route('hr.portal.complaints') }}" class="{{ request()->routeIs(['hr.portal.complaints', 'hr.portal.complaints.store']) ? 'active' : '' }}"><i class="fa fa-comments" aria-hidden="true"></i><span>{{ __('Complaints') }}</span></a>
                <a href="{{ route('hr.portal.salary') }}" class="{{ request()->routeIs('hr.portal.salary') ? 'active' : '' }}"><i class="fa fa-money-check-dollar" aria-hidden="true"></i><span>{{ __('My salary') }}</span></a>
                @if(Route::has('dashboard') && auth()->user() && ! auth()->user()->isHrPortalOnlyUser())
                    <div class="menu-section">{{ __('More') }}</div>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa fa-briefcase" aria-hidden="true"></i><span>{{ __('Workspace') }}</span></a>
                @endif
            </nav>
        @else
        <a href="{{ route('dashboard') }}" class="brand brand--logo" aria-label="Zeebroo">
            <img src="{{ asset('logo.png') }}" alt="Zeebroo" width="224" height="75">
        </a>
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
                </div>
            @endif
            @if($showSidebarPurchaseSection)
                <div class="menu-group-title">
                    <i class="fa fa-cart-shopping"></i><span>Purchase orders</span>
                </div>
                <div class="submenu" aria-label="Purchase orders">
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
                        <a href="{{ route('pos.register') }}" @class([
                            'active' => request()->routeIs('pos.register'),
                        ])><i class="fa fa-cash-register"></i><span>Register</span></a>
                    @endif
                    @if($showSidebarPosSalesLink)
                        <a href="{{ route('pos.sales.index') }}" @class([
                            'active' => request()->routeIs('pos.sales.*'),
                        ])><i class="fa fa-receipt"></i><span>Sales history</span></a>
                    @endif
                </div>
            @endif
            @if($showSidebarFilesLink && Route::has('filemanager.index'))
                <a href="{{ route('filemanager.index') }}" class="{{ request()->routeIs('filemanager.*') ? 'active' : '' }}"><i class="fa fa-folder-open"></i><span>Files</span></a>
            @endif
            @if($navBusiness && $hrPayrollOptedIn)
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
        @unless($posOnlyShell)
        <div class="navbar">
            <div>
                <div class="navtitle">{{ $heading ?? 'Overview' }}</div>
                @if($employeePortal && isset($portalEmployee))
                    <div class="navmeta navbar-portal-meta">{{ $portalEmployee->full_name }} · {{ $portalEmployee->employee_id }}</div>
                @else
                    <div class="navmeta">{{ __('Welcome, :name', ['name' => auth()->user()->name ?? __('User')]) }}</div>
                @endif
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
</script>
</body>
</html>
