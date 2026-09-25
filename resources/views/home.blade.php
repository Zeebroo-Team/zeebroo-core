@extends('theme::layouts.app', ['title' => 'Home', 'heading' => 'Home'])

@section('content')
@php
    $navBusiness = $business;
    $businessFeatures = $navBusiness
        ? (function () use ($navBusiness) {
            $saved = (array) ($navBusiness->getSetting('business.features', []) ?: []);
            $defaults = array_fill_keys(array_keys(config('features.list', [])), true);
            return !empty($saved) ? array_merge($defaults, array_map('boolval', $saved)) : $defaults;
        })()
        : [];
    $featureOn = fn (string $key) => (bool) ($businessFeatures[$key] ?? true);
    $currency = trim((string) get_settings('business.currency', '', $navBusiness));
@endphp

<style>
    .home-topbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px;}
    .home-greeting{font-weight:800;font-size:15px;}
    .home-topbar-sep{color:var(--muted);}
    .home-date{color:var(--muted);font-size:13px;}
    .home-kpi-pills{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto;}
    .kpi-pill{display:flex;align-items:center;gap:6px;border:1px solid var(--border);border-radius:999px;padding:6px 12px;font-size:12.5px;background:var(--card);}
    .kpi-pill strong{font-size:13px;}
    .home-subnav{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--border);margin-bottom:16px;padding-bottom:8px;}
    .home-tab-btn{border:1px solid transparent;background:transparent;color:var(--muted);padding:7px 12px;border-radius:9px;font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;}
    .home-tab-btn:hover{background:color-mix(in srgb,var(--border) 40%,transparent);}
    .home-tab-btn.active{background:var(--primary);color:#fff;}
    .home-body{display:flex;gap:18px;align-items:flex-start;}
    .home-main{flex:1;min-width:0;}
    .home-right-panel{width:280px;flex-shrink:0;display:flex;flex-direction:column;gap:14px;}
    .home-view{display:none;flex-direction:column;gap:20px;}
    .home-view.active{display:flex;}
    .hog-hero{display:flex;gap:14px;align-items:center;border:1px solid var(--border);border-radius:16px;padding:18px;background:color-mix(in srgb,var(--primary) 8%,var(--card));}
    .hog-hero-icon{width:44px;height:44px;border-radius:12px;background:var(--primary);color:#fff;display:grid;place-items:center;font-size:18px;flex-shrink:0;}
    .hog-hero-title{font-weight:800;font-size:16px;}
    .hog-hero-sub{color:var(--muted);font-size:12.5px;margin-top:4px;}
    .hog-section-head{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
    .hog-section-icon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;color:#fff;flex-shrink:0;}
    .hog-icon-blue{background:#2563eb;} .hog-icon-amber{background:#d97706;} .hog-icon-rose{background:#e11d48;}
    .hog-icon-purple{background:#7c3aed;} .hog-icon-teal{background:#0d9488;} .hog-icon-indigo{background:#4f46e5;} .hog-icon-green{background:#16a34a;}
    .hog-section-title{font-weight:800;font-size:14px;}
    .hog-section-desc{color:var(--muted);font-size:12px;}
    .hog-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;}
    .hog-card{border:1px solid var(--border);border-radius:12px;padding:12px;text-align:left;background:var(--card);color:inherit;text-decoration:none;display:block;cursor:pointer;transition:border-color .15s ease;}
    .hog-card:hover{border-color:var(--primary);}
    .hog-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
    .hog-card-icon{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;color:#fff;font-size:12px;}
    .hog-card-stat{font-weight:800;font-size:13px;}
    .hog-card-name{font-weight:700;font-size:13px;}
    .hog-card-desc{color:var(--muted);font-size:11.5px;margin-top:3px;}
    .hrp-section{border:1px solid var(--border);border-radius:14px;padding:12px;background:var(--card);}
    .hrp-section-title{font-weight:700;font-size:12.5px;margin-bottom:10px;}
    .hrp-stat-row{display:flex;justify-content:space-between;gap:6px;}
    .hrp-stat{text-align:center;flex:1;}
    .hrp-stat-val{font-weight:800;font-size:15px;}
    .hrp-stat-lbl{color:var(--muted);font-size:10.5px;margin-top:2px;}
    .hrp-green{color:#16a34a;} .hrp-amber{color:#d97706;}
    .hrp-bill-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-top:1px solid var(--border);font-size:12px;}
    .hrp-bill-row:first-child{border-top:none;}
    .hrp-bill-overdue{color:#e11d48;font-weight:700;}
    .hrp-actions{display:flex;flex-direction:column;gap:6px;}
    .hrp-action-btn{border:1px solid var(--border);background:transparent;color:var(--text);padding:8px 10px;border-radius:9px;font-size:12px;font-weight:600;text-align:left;display:flex;align-items:center;gap:8px;text-decoration:none;cursor:pointer;}
    .hrp-action-btn:hover{border-color:var(--primary);}
    .home-stat-card{border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--card);}
    .home-stat-card .v{font-weight:800;font-size:17px;}
    .home-stat-card .l{color:var(--muted);font-size:11.5px;margin-top:2px;}
    .home-mini-table{width:100%;border-collapse:collapse;font-size:12.5px;}
    .home-mini-table th{text-align:left;color:var(--muted);font-weight:600;font-size:11px;padding:6px 8px;border-bottom:1px solid var(--border);}
    .home-mini-table td{padding:7px 8px;border-bottom:1px solid var(--border);}
    .home-panel-foot{margin-top:4px;}
    .home-linkbtn{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--primary);text-decoration:none;}
    .home-empty{color:var(--muted);font-size:12px;padding:10px 0;}

    /* ── Business Flow diagram (positioned canvas, mirrors the Electron app's node chart) ── */
    .flow-canvas-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--card) 97%,transparent);padding:6px;max-height:640px;cursor:grab;touch-action:none;}
    .flow-canvas-wrap.flow-dragging{cursor:grabbing;user-select:none;}
    .flow-canvas{position:relative;}
    .flow-svg{position:absolute;left:0;top:0;pointer-events:none;}
    .flow-n{position:absolute;box-sizing:border-box;display:flex;align-items:center;justify-content:center;white-space:nowrap;font-family:inherit;}
    .flow-n--root{border-radius:22px;background:var(--primary);color:#fff;font-weight:800;font-size:12.5px;gap:6px;box-shadow:0 6px 16px color-mix(in srgb,var(--primary) 45%,transparent);}
    .flow-n--hub{border-radius:19px;color:#fff;font-weight:700;font-size:11.5px;gap:6px;}
    .flow-n--hub.k-expense{background:#dc2626;}
    .flow-n--hub.k-income{background:#16a34a;}
    .flow-n--hub.k-bills{background:#ef4444;}
    .flow-n--hub.k-loans{background:#7c3aed;}
    .flow-n--hub.k-rentals{background:#ea580c;}
    .flow-n--hub.k-assets{background:#d97706;}
    .flow-badge{background:rgba(255,255,255,.3);border-radius:9px;padding:0 5px;font-size:9.5px;font-weight:800;line-height:15px;}
    .flow-n--leaf{border-radius:16px;font-weight:700;font-size:10.5px;padding:0 10px;overflow:hidden;text-overflow:ellipsis;border:1.5px solid;}
    .flow-n--leaf.k-decor{background:color-mix(in srgb,#f43f5e 12%,var(--card));border-color:color-mix(in srgb,#f43f5e 45%,var(--border));color:#e11d48;}
    .flow-n--leaf.k-income{background:color-mix(in srgb,#16a34a 10%,var(--card));border-color:color-mix(in srgb,#16a34a 40%,var(--border));color:#16a34a;}
    .flow-n--item{flex-direction:column;align-items:flex-start;justify-content:center;gap:3px;padding:6px 10px;border-radius:10px;border:1.5px solid var(--border);background:var(--card);text-align:left;white-space:normal;}
    .flow-n--item .fi-top{display:flex;align-items:center;gap:5px;width:100%;justify-content:space-between;}
    .flow-n--item .fi-name{font-weight:700;font-size:11px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:118px;}
    .flow-n--item .fi-meta{font-size:9.5px;color:var(--muted);}
    .flow-n--item.k-bill{border-color:#f87171;}
    .flow-n--item.k-loan{border-color:#a78bfa;}
    .flow-n--item.k-rental{border-color:#fb923c;}
    .flow-n--item.k-property{border-color:#fbbf24;}
    .flow-n--item.overdue{border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,var(--card));}
    .flow-overdue-tag{color:#ef4444;font-weight:800;font-size:8.5px;text-transform:uppercase;letter-spacing:.02em;flex-shrink:0;}
    .flow-n--more{border-radius:10px;border:1.5px dashed var(--border);color:var(--muted);font-size:10px;font-style:italic;}
    a.flow-n{text-decoration:none;color:inherit;cursor:pointer;transition:transform .12s ease,box-shadow .12s ease,filter .12s ease;}
    a.flow-n:hover{filter:brightness(1.08);transform:translateY(-1px) scale(1.035);box-shadow:0 8px 18px rgba(0,0,0,.2);z-index:5;}
    a.flow-n--item:hover,a.flow-n--leaf:hover{border-color:var(--primary);}

    /* ── Zoom controls (mirror the Electron chart's pane controls) ── */
    .flow-viewport{position:relative;}
    .flow-canvas-scaler{transform-origin:0 0;}
    .flow-controls{position:absolute;left:10px;bottom:10px;z-index:20;display:flex;flex-direction:column;background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.16);}
    .flow-ctrl-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:none;border-bottom:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-size:12.5px;}
    .flow-ctrl-btn:last-child{border-bottom:none;}
    .flow-ctrl-btn:hover{background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
</style>

@php
    $iconColorClass = [
        'blue' => 'hog-icon-blue', 'amber' => 'hog-icon-amber', 'rose' => 'hog-icon-rose',
        'purple' => 'hog-icon-purple', 'teal' => 'hog-icon-teal', 'indigo' => 'hog-icon-indigo', 'green' => 'hog-icon-green',
    ];
@endphp

<div class="home-topbar">
    <span class="home-greeting">{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}</span>
    <span class="home-topbar-sep">&middot;</span>
    <span class="home-date">{{ now()->format('l, F j, Y') }}</span>
    <div class="home-kpi-pills">
        <div class="kpi-pill"><i class="fa fa-cart-shopping"></i><strong>{{ $kpis['sales'] }}</strong><span>sales</span></div>
        <div class="kpi-pill"><i class="fa fa-dollar-sign"></i><strong>{{ number_format($kpis['revenue'], 2) }}</strong><span>revenue</span></div>
        <div class="kpi-pill"><i class="fa fa-boxes-stacked"></i><strong>{{ $kpis['products'] }}</strong><span>products</span></div>
        <div class="kpi-pill"><i class="fa fa-users"></i><strong>{{ $kpis['customers'] }}</strong><span>customers</span></div>
    </div>
</div>

<div class="home-subnav">
    <button type="button" class="home-tab-btn active" data-home-view="overview"><i class="fa fa-compass"></i> Overview</button>
    <button type="button" class="home-tab-btn" data-home-view="flow"><i class="fa fa-diagram-project"></i> Business Flow</button>
    <button type="button" class="home-tab-btn" data-home-view="today"><i class="fa fa-sun"></i> Today</button>
    <button type="button" class="home-tab-btn" data-home-view="activity"><i class="fa fa-clock-rotate-left"></i> Recent Activity</button>
    <button type="button" class="home-tab-btn" data-home-view="analytics"><i class="fa fa-chart-line"></i> Analytics</button>
    <button type="button" class="home-tab-btn" data-home-view="expenses"><i class="fa fa-file-invoice-dollar"></i> Expenses</button>
    <button type="button" class="home-tab-btn" data-home-view="profit"><i class="fa fa-sack-dollar"></i> Profit</button>
    <button type="button" class="home-tab-btn" data-home-view="payroll"><i class="fa fa-user-tie"></i> Payroll</button>
    <button type="button" class="home-tab-btn" data-home-view="orders"><i class="fa fa-receipt"></i> Orders</button>
    <button type="button" class="home-tab-btn" data-home-view="crm"><i class="fa fa-handshake"></i> CRM</button>
</div>

<div class="home-body">
    <div class="home-main">

        {{-- ── Overview ── --}}
        <div id="home-view-overview" class="home-view active">
            <div class="hog-hero">
                <div class="hog-hero-icon"><i class="fa fa-compass"></i></div>
                <div>
                    <div class="hog-hero-title">Welcome to Zeebroo</div>
                    <div class="hog-hero-sub">Your all-in-one system for sales, inventory, finance, HR and services — everything your business needs in one place.</div>
                </div>
            </div>

            @foreach($overview as $section)
                @if($section['key'] === null || $featureOn($section['key']))
                    <div class="hog-section-block">
                        <div class="hog-section-head">
                            <div class="hog-section-icon {{ $iconColorClass[$section['color']] ?? 'hog-icon-blue' }}"><i class="fa {{ $section['icon'] }}"></i></div>
                            <div>
                                <div class="hog-section-title">{{ $section['title'] }}</div>
                                <div class="hog-section-desc">{{ $section['desc'] }}</div>
                            </div>
                        </div>
                        <div class="hog-grid">
                            @foreach($section['cards'] as $card)
                                @php
                                    $href = Route::has($card['route']) ? route($card['route']) : '#';
                                    $isHomeSelf = $card['route'] === 'home.index';
                                @endphp
                                <a href="{{ $isHomeSelf ? '#' : $href }}" @if($isHomeSelf) data-home-jump="{{ collect(['Today\'s Summary'=>'today','Recent Activity'=>'activity','Profit Report'=>'profit','Analytics'=>'analytics'])[$card['label']] ?? 'overview' }}" @endif class="hog-card">
                                    <div class="hog-card-top">
                                        <div class="hog-card-icon {{ $iconColorClass[$section['color']] ?? 'hog-icon-blue' }}"><i class="fa {{ $card['icon'] }}"></i></div>
                                        @if(isset($card['stat']))
                                            <span class="hog-card-stat">{{ $card['stat'] }}</span>
                                        @endif
                                    </div>
                                    <div class="hog-card-name">{{ $card['label'] }}</div>
                                    <div class="hog-card-desc">{{ $card['desc'] }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- ── Business Flow ── --}}
        <div id="home-view-flow" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-green"><i class="fa fa-diagram-project"></i></div>
                <div><div class="hog-section-title">Business Flow</div><div class="hog-section-desc">How money moves through {{ $navBusiness->name }} this month</div></div>
            </div>

            <div class="flow-viewport">
                <div class="flow-canvas-wrap" id="flow-wrap">
                    <div class="flow-canvas-scaler" id="flow-scaler">
                        <div class="flow-canvas" id="flow-canvas" data-base-w="{{ $flowChart['width'] }}" data-base-h="{{ $flowChart['height'] }}" style="width:{{ $flowChart['width'] }}px;height:{{ $flowChart['height'] }}px;">
                            <svg class="flow-svg" width="{{ $flowChart['width'] }}" height="{{ $flowChart['height'] }}">
                                <defs>
                                    @foreach($flowChart['palette'] as $key => $hex)
                                        <marker id="flow-arrow-{{ $key }}" markerWidth="9" markerHeight="7" refX="8" refY="3.5" orient="auto">
                                            <polygon points="0 0, 9 3.5, 0 7" fill="{{ $hex }}" />
                                        </marker>
                                    @endforeach
                                </defs>
                                @foreach($flowChart['edges'] as $edge)
                                    <path d="{{ $edge['d'] }}" fill="none" stroke="{{ $edge['color'] }}"
                                          stroke-width="{{ $edge['dashed'] ? 1.6 : 2 }}"
                                          @if($edge['dashed']) stroke-dasharray="5 4" @endif
                                          opacity="{{ $edge['dashed'] ? 0.85 : 0.9 }}"
                                          marker-end="url(#flow-arrow-{{ $edge['key'] }})" />
                                @endforeach
                            </svg>

                            @foreach($flowChart['nodes'] as $node)
                                @php
                                    $style = "left:{$node['x']}px;top:{$node['y']}px;width:{$node['w']}px;height:{$node['h']}px;";
                                    $tag = !empty($node['href']) ? 'a' : 'div';
                                    $hrefAttr = !empty($node['href']) ? 'href="'.e($node['href']).'"' : '';
                                @endphp

                                @if($node['kind'] === 'root')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--root" style="{{ $style }}"><i class="fa fa-building"></i> {{ $node['label'] }}</{{ $tag }}>

                                @elseif(str_starts_with($node['kind'], 'hub-'))
                                    @php
                                        $hubMeta = [
                                            'hub-expense' => ['k-expense', 'fa-arrow-trend-down'],
                                            'hub-income'  => ['k-income', 'fa-arrow-trend-up'],
                                            'hub-bills'   => ['k-bills', 'fa-file-invoice-dollar'],
                                            'hub-loans'   => ['k-loans', 'fa-hand-holding-dollar'],
                                            'hub-rentals' => ['k-rentals', 'fa-house'],
                                            'hub-assets'  => ['k-assets', 'fa-coins'],
                                        ][$node['kind']];
                                    @endphp
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--hub {{ $hubMeta[0] }}" style="{{ $style }}">
                                        <i class="fa {{ $hubMeta[1] }}" style="font-size:10px;"></i> {{ $node['label'] }}
                                        @if(!empty($node['badge']))<span class="flow-badge">{{ $node['badge'] }}</span>@endif
                                    </{{ $tag }}>

                                @elseif($node['kind'] === 'leaf-decor' || $node['kind'] === 'leaf-income')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--leaf {{ $node['kind'] === 'leaf-decor' ? 'k-decor' : 'k-income' }}" style="{{ $style }}">{{ $node['label'] }}</{{ $tag }}>

                                @elseif($node['kind'] === 'item-more')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--more" style="{{ $style }}">{{ $node['label'] }}</{{ $tag }}>

                                @elseif($node['kind'] === 'item-bill')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--item k-bill {{ !empty($node['overdue']) ? 'overdue' : '' }}" style="{{ $style }}">
                                        <div class="fi-top">
                                            <span class="fi-name">{{ $node['name'] }}</span>
                                            @if(!empty($node['overdue']))<span class="flow-overdue-tag">Overdue</span>@endif
                                        </div>
                                        <div class="fi-meta">{{ $node['cadence'] }} @if($node['amount'] !== null) &middot; {{ $currency }} {{ number_format($node['amount'], 2) }} @else &middot; Variable @endif</div>
                                    </{{ $tag }}>

                                @elseif($node['kind'] === 'item-loan')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--item k-loan" style="{{ $style }}">
                                        <div class="fi-top"><span class="fi-name">{{ $node['name'] }}</span></div>
                                        <div class="fi-meta">{{ $currency }} {{ number_format($node['monthly'], 2) }} / mo &middot; {{ $node['cadence'] }}</div>
                                    </{{ $tag }}>

                                @elseif($node['kind'] === 'item-rental')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--item k-rental {{ !empty($node['overdue']) ? 'overdue' : '' }}" style="{{ $style }}">
                                        <div class="fi-top">
                                            <span class="fi-name">{{ $node['name'] }}</span>
                                            @if(!empty($node['overdue']))<span class="flow-overdue-tag">Overdue</span>@endif
                                        </div>
                                        <div class="fi-meta">{{ $currency }} {{ number_format($node['monthly'], 2) }} / mo &middot; {{ $node['cadence'] }}</div>
                                    </{{ $tag }}>

                                @elseif($node['kind'] === 'item-property')
                                    <{{ $tag }} {!! $hrefAttr !!} class="flow-n flow-n--item k-property {{ (!empty($node['expired']) || !empty($node['expiring_soon'])) ? 'overdue' : '' }}" style="{{ $style }}">
                                        <div class="fi-top">
                                            <span class="fi-name">{{ $node['name'] }}</span>
                                            @if(!empty($node['expired']))<span class="flow-overdue-tag">Expired</span>@elseif(!empty($node['expiring_soon']))<span class="flow-overdue-tag">Soon</span>@endif
                                        </div>
                                        <div class="fi-meta">{{ $node['type'] }} &middot; {{ $currency }} {{ number_format($node['cost'], 2) }}</div>
                                    </{{ $tag }}>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flow-controls">
                    <button type="button" class="flow-ctrl-btn" data-flow-zoom="in" title="Zoom in"><i class="fa fa-plus"></i></button>
                    <button type="button" class="flow-ctrl-btn" data-flow-zoom="out" title="Zoom out"><i class="fa fa-minus"></i></button>
                    <button type="button" class="flow-ctrl-btn" data-flow-zoom="fit" title="Fit to screen"><i class="fa fa-expand"></i></button>
                </div>
            </div>

            @unless($featureOn('bill_management'))
                <p class="home-empty" style="margin-top:8px;">Bills, loans, rentals and assets are hidden because the Bill Management feature is off for this business.</p>
            @endunless

            <div class="hog-grid">
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($flow['income']['pos_sales_monthly'], 2) }}</div><div class="l">Income — POS sales (this month)</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($flow['expense']['bills_monthly'], 2) }}</div><div class="l">Expense — Bills (monthly)</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($flow['expense']['loans_monthly'], 2) }}</div><div class="l">Expense — Loans (monthly)</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($flow['expense']['rentals_monthly'], 2) }}</div><div class="l">Expense — Rentals (monthly)</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($flow['expense']['total_monthly'], 2) }}</div><div class="l">Total monthly expenses</div></div>
            </div>
            <div class="home-panel-foot">
                @if(Route::has('account.finance.index'))
                    <a href="{{ route('account.finance.index') }}" class="home-linkbtn">Open full Finance view <i class="fa fa-arrow-right"></i></a>
                @endif
            </div>
        </div>

        {{-- ── Today ── --}}
        <div id="home-view-today" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-blue"><i class="fa fa-sun"></i></div>
                <div><div class="hog-section-title">Today's Summary</div><div class="hog-section-desc">{{ now()->format('l, M j') }}</div></div>
            </div>
            <div class="hog-grid">
                <div class="home-stat-card"><div class="v">{{ $today['sales']['count'] }}</div><div class="l">Sales</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($today['sales']['revenue'], 2) }}</div><div class="l">Revenue</div></div>
                <div class="home-stat-card"><div class="v">{{ $today['sales']['items_sold'] }}</div><div class="l">Items sold</div></div>
                @foreach($today['sales']['by_method'] as $method => $m)
                    <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($m['total'], 2) }}</div><div class="l">{{ ucfirst($method) }} ({{ $m['count'] }})</div></div>
                @endforeach
            </div>
            <div>
                <div class="hog-section-title" style="margin-bottom:8px;">Top products today</div>
                @if(count($today['top_products']))
                    <table class="home-mini-table">
                        <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
                        <tbody>
                        @foreach($today['top_products'] as $p)
                            <tr><td>{{ $p['name'] }}</td><td>{{ $p['qty'] }}</td><td>{{ $currency }} {{ number_format($p['revenue'], 2) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="home-empty">No sales yet today.</div>
                @endif
            </div>
        </div>

        {{-- ── Recent Activity ── --}}
        <div id="home-view-activity" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-green"><i class="fa fa-clock-rotate-left"></i></div>
                <div><div class="hog-section-title">Recent Activity</div><div class="hog-section-desc">Latest transactions and changes across the business</div></div>
            </div>
            @if(count($activity))
                <table class="home-mini-table">
                    <tbody>
                    @foreach($activity as $item)
                        <tr>
                            <td style="width:26px;"><i class="fa {{ $item['icon'] }}" style="color:var(--muted);"></i></td>
                            <td>{{ $item['label'] }}</td>
                            <td style="color:var(--muted);white-space:nowrap;">{{ optional($item['timestamp'])->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="home-empty">No recent activity yet.</div>
            @endif
        </div>

        {{-- ── Analytics ── --}}
        <div id="home-view-analytics" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-green"><i class="fa fa-chart-line"></i></div>
                <div><div class="hog-section-title">Analytics</div><div class="hog-section-desc">Daily revenue — last {{ $analytics['period'] }} days</div></div>
            </div>
            @include('crm::partials.line-chart', [
                'canvasId' => 'home-analytics-chart',
                'chartLabels' => $analytics['labels'],
                'chartDatasets' => [[
                    'label' => 'Revenue',
                    'data' => $analytics['revenue'],
                    'borderColor' => '#7c3aed',
                    'backgroundColor' => 'rgba(124,58,237,.14)',
                    'tension' => 0.35,
                    'fill' => true,
                    'pointRadius' => 0,
                ]],
            ])
        </div>

        {{-- ── Expenses ── --}}
        <div id="home-view-expenses" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-rose"><i class="fa fa-file-invoice-dollar"></i></div>
                <div><div class="hog-section-title">Expenses</div><div class="hog-section-desc">Bills, loans and rentals</div></div>
            </div>
            <div class="hog-grid">
                <div class="home-stat-card">
                    <div class="v">{{ $expenses['bills']['count'] }}</div>
                    <div class="l">Bills @if($expenses['bills']['overdue']) &middot; <span style="color:#e11d48;">{{ $expenses['bills']['overdue'] }} overdue</span> @endif</div>
                    <div style="margin-top:6px;font-size:12px;">{{ $currency }} {{ number_format($expenses['bills']['monthly'], 2) }} / mo</div>
                    @if(Route::has('account.bills.index'))<a href="{{ route('account.bills.index') }}" class="home-linkbtn" style="margin-top:6px;">View all</a>@endif
                </div>
                <div class="home-stat-card">
                    <div class="v">{{ $expenses['loans']['count'] }}</div>
                    <div class="l">Loans</div>
                    <div style="margin-top:6px;font-size:12px;">{{ $currency }} {{ number_format($expenses['loans']['monthly'], 2) }} / mo</div>
                    @if(Route::has('account.loans.index'))<a href="{{ route('account.loans.index') }}" class="home-linkbtn" style="margin-top:6px;">View all</a>@endif
                </div>
                <div class="home-stat-card">
                    <div class="v">{{ $expenses['rentals']['count'] }}</div>
                    <div class="l">Rentals @if($expenses['rentals']['overdue']) &middot; <span style="color:#e11d48;">{{ $expenses['rentals']['overdue'] }} overdue</span> @endif</div>
                    <div style="margin-top:6px;font-size:12px;">{{ $currency }} {{ number_format($expenses['rentals']['monthly'], 2) }} / mo</div>
                    @if(Route::has('account.rentals.index'))<a href="{{ route('account.rentals.index') }}" class="home-linkbtn" style="margin-top:6px;">View all</a>@endif
                </div>
            </div>
        </div>

        {{-- ── Profit ── --}}
        <div id="home-view-profit" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-rose"><i class="fa fa-sack-dollar"></i></div>
                <div><div class="hog-section-title">Profit</div><div class="hog-section-desc">This month, {{ now()->format('F Y') }}</div></div>
            </div>
            <div class="hog-grid">
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($profit['revenue'], 2) }}</div><div class="l">Revenue</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($profit['cogs'], 2) }}</div><div class="l">Cost of goods sold</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($profit['gross_profit'], 2) }}</div><div class="l">Gross profit</div></div>
                <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($profit['expenses'], 2) }}</div><div class="l">Monthly expenses</div></div>
                <div class="home-stat-card"><div class="v" style="color:{{ $profit['net_profit'] >= 0 ? '#16a34a' : '#e11d48' }};">{{ $currency }} {{ number_format($profit['net_profit'], 2) }}</div><div class="l">Net profit</div></div>
            </div>
        </div>

        {{-- ── Payroll ── --}}
        <div id="home-view-payroll" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-purple"><i class="fa fa-user-tie"></i></div>
                <div><div class="hog-section-title">Payroll</div><div class="hog-section-desc">Staff and the latest payroll cycle</div></div>
            </div>
            <div class="hog-grid">
                <div class="home-stat-card"><div class="v">{{ $payroll['department_count'] ?? 0 }}</div><div class="l">Departments</div></div>
                <div class="home-stat-card"><div class="v">{{ $payroll['employee_count'] ?? 0 }}</div><div class="l">Employees</div></div>
                @if(!empty($payroll['latest_payroll_run']))
                    <div class="home-stat-card"><div class="v">{{ $currency }} {{ number_format($payroll['latest_payroll_run']['total_net'], 2) }}</div><div class="l">Latest cycle net pay ({{ $payroll['latest_payroll_run']['name'] }})</div></div>
                @endif
            </div>
            <div class="home-panel-foot">
                @if(Route::has('hr.payroll.index'))
                    <a href="{{ route('hr.payroll.index') }}" class="home-linkbtn">Open Payroll <i class="fa fa-arrow-right"></i></a>
                @endif
            </div>
        </div>

        {{-- ── Orders ── --}}
        <div id="home-view-orders" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-blue"><i class="fa fa-receipt"></i></div>
                <div><div class="hog-section-title">Orders</div><div class="hog-section-desc">Recent sales and purchase orders</div></div>
            </div>
            <div>
                <div class="hog-section-title" style="margin-bottom:8px;">Recent sales</div>
                @if($orders['sales']->count())
                    <table class="home-mini-table">
                        <thead><tr><th>Sale #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        @foreach($orders['sales'] as $s)
                            <tr><td>{{ $s->sale_number }}</td><td>{{ $currency }} {{ number_format((float) $s->total, 2) }}</td><td>{{ ucfirst($s->status) }}</td><td>{{ optional($s->sold_at)->format('M j, Y') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(Route::has('pos.sales.index'))<a href="{{ route('pos.sales.index') }}" class="home-linkbtn" style="margin-top:8px;">View all sales</a>@endif
                @else
                    <div class="home-empty">No sales yet.</div>
                @endif
            </div>
            <div>
                <div class="hog-section-title" style="margin-bottom:8px;">Recent purchase orders</div>
                @if($orders['purchases']->count())
                    <table class="home-mini-table">
                        <thead><tr><th>PO #</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($orders['purchases'] as $p)
                            <tr><td>{{ $p->po_number }}</td><td>{{ $p->supplier->name ?? '—' }}</td><td>{{ $currency }} {{ number_format((float) $p->total, 2) }}</td><td>{{ ucfirst(str_replace('_', ' ', $p->status)) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(Route::has('purchase.index'))<a href="{{ route('purchase.index') }}" class="home-linkbtn" style="margin-top:8px;">View all purchase orders</a>@endif
                @else
                    <div class="home-empty">No purchase orders yet.</div>
                @endif
            </div>
        </div>

        {{-- ── CRM ── --}}
        <div id="home-view-crm" class="home-view">
            <div class="hog-section-head">
                <div class="hog-section-icon hog-icon-indigo"><i class="fa fa-handshake"></i></div>
                <div><div class="hog-section-title">CRM</div><div class="hog-section-desc">Pipelines, leads, contacts and tasks</div></div>
            </div>
            <div class="hog-grid">
                <div class="home-stat-card"><div class="v">{{ $crm['projects_count'] }}</div><div class="l">Active pipelines</div></div>
                <div class="home-stat-card"><div class="v">{{ $crm['open_leads'] }}</div><div class="l">Open leads</div></div>
                <div class="home-stat-card"><div class="v">{{ $crm['open_tasks'] }}</div><div class="l">Open tasks</div></div>
                <div class="home-stat-card"><div class="v" style="color:{{ $crm['overdue_tasks'] ? '#e11d48' : 'inherit' }};">{{ $crm['overdue_tasks'] }}</div><div class="l">Overdue tasks</div></div>
            </div>
            <div>
                <div class="hog-section-title" style="margin-bottom:8px;">Recent contacts</div>
                @if($crm['recent_contacts']->count())
                    <table class="home-mini-table">
                        <tbody>
                        @foreach($crm['recent_contacts'] as $c)
                            <tr><td>{{ $c->name }}</td><td style="color:var(--muted);">{{ $c->created_at->diffForHumans() }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="home-empty">No contacts yet.</div>
                @endif
                @if(Route::has('crm.projects.index'))<a href="{{ route('crm.projects.index') }}" class="home-linkbtn" style="margin-top:8px;">Open CRM</a>@endif
            </div>
        </div>

    </div>

    {{-- ── Right sidebar ── --}}
    <div class="home-right-panel">
        <div class="hrp-section">
            <div class="hrp-section-title"><i class="fa fa-sun"></i> Today</div>
            <div class="hrp-stat-row">
                <div class="hrp-stat"><div class="hrp-stat-val">{{ $rightPanel['today']['sales'] }}</div><div class="hrp-stat-lbl">Sales</div></div>
                <div class="hrp-stat"><div class="hrp-stat-val hrp-green">{{ number_format($rightPanel['today']['revenue'], 0) }}</div><div class="hrp-stat-lbl">Revenue</div></div>
                <div class="hrp-stat"><div class="hrp-stat-val hrp-amber">{{ $rightPanel['today']['items_sold'] }}</div><div class="hrp-stat-lbl">Items sold</div></div>
            </div>
        </div>

        <div class="hrp-section">
            <div class="hrp-section-title"><i class="fa fa-calendar-exclamation"></i> Upcoming Bills</div>
            @if(count($rightPanel['upcoming_bills']))
                @foreach($rightPanel['upcoming_bills'] as $bill)
                    <div class="hrp-bill-row">
                        <span class="{{ $bill['overdue'] ? 'hrp-bill-overdue' : '' }}">{{ $bill['name'] }}</span>
                        <span class="{{ $bill['overdue'] ? 'hrp-bill-overdue' : '' }}" style="color:var(--muted);">{{ $bill['overdue'] ? 'Overdue' : $bill['cadence'] }}</span>
                    </div>
                @endforeach
            @else
                <div class="home-empty">No bills yet.</div>
            @endif
        </div>

        <div class="hrp-section">
            <div class="hrp-section-title"><i class="fa fa-bolt"></i> Quick Actions</div>
            <div class="hrp-actions">
                @if(Route::has('pos.online'))<a href="{{ route('pos.online') }}" class="hrp-action-btn"><i class="fa fa-cart-plus"></i> New Sale</a>@endif
                @if(Route::has('product.index'))<a href="{{ route('product.index') }}" class="hrp-action-btn"><i class="fa fa-plus"></i> Add Product</a>@endif
                @if(Route::has('account.bills.index'))<a href="{{ route('account.bills.index') }}" class="hrp-action-btn"><i class="fa fa-file-invoice-dollar"></i> New Bill</a>@endif
                @if(Route::has('purchase.index'))<a href="{{ route('purchase.index') }}" class="hrp-action-btn"><i class="fa fa-file-invoice"></i> Purchase Orders</a>@endif
                @if(Route::has('product.barcodes.index'))<a href="{{ route('product.barcodes.index') }}" class="hrp-action-btn"><i class="fa fa-barcode"></i> Print Barcodes</a>@endif
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var buttons = document.querySelectorAll('.home-tab-btn');
    var views = document.querySelectorAll('.home-view');

    function activate(name) {
        buttons.forEach(function (b) { b.classList.toggle('active', b.dataset.homeView === name); });
        views.forEach(function (v) { v.classList.toggle('active', v.id === 'home-view-' + name); });
        if (name === 'flow' && window.flowDiagramFit) { setTimeout(window.flowDiagramFit, 30); }
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () { activate(btn.dataset.homeView); });
    });

    document.querySelectorAll('[data-home-jump]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            activate(el.dataset.homeJump);
        });
    });
})();

(function () {
    var canvas = document.getElementById('flow-canvas');
    var scaler = document.getElementById('flow-scaler');
    var wrap = document.getElementById('flow-wrap');
    if (!canvas || !scaler || !wrap) return;

    var baseW = parseFloat(canvas.dataset.baseW, 10) || canvas.offsetWidth;
    var baseH = parseFloat(canvas.dataset.baseH, 10) || canvas.offsetHeight;
    var scale = 1;

    function apply() {
        canvas.style.transform = 'scale(' + scale + ')';
        scaler.style.width = (baseW * scale) + 'px';
        scaler.style.height = (baseH * scale) + 'px';
    }

    function fit() {
        var availW = wrap.clientWidth - 16;
        scale = availW > 0 ? Math.max(0.3, Math.min(1, availW / baseW)) : 1;
        apply();
        wrap.scrollLeft = 0;
        wrap.scrollTop = 0;
    }

    document.querySelectorAll('[data-flow-zoom]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.dataset.flowZoom;
            if (action === 'in') { scale = Math.min(2, scale + 0.15); apply(); }
            else if (action === 'out') { scale = Math.max(0.3, scale - 0.15); apply(); }
            else { fit(); }
        });
    });

    window.flowDiagramFit = fit;
    window.addEventListener('resize', function () {
        if (document.getElementById('home-view-flow').classList.contains('active')) fit();
    });

    // ── Click-and-drag panning (mirrors the Electron chart's draggable canvas) ──
    var dragging = false, startX = 0, startY = 0, startLeft = 0, startTop = 0;

    wrap.addEventListener('pointerdown', function (e) {
        if (e.target.closest('.flow-n') || e.target.closest('.flow-controls')) return;
        dragging = true;
        wrap.classList.add('flow-dragging');
        startX = e.clientX; startY = e.clientY;
        startLeft = wrap.scrollLeft; startTop = wrap.scrollTop;
        wrap.setPointerCapture(e.pointerId);
    });
    wrap.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        wrap.scrollLeft = startLeft - (e.clientX - startX);
        wrap.scrollTop = startTop - (e.clientY - startY);
    });
    ['pointerup', 'pointercancel'].forEach(function (evt) {
        wrap.addEventListener(evt, function () {
            dragging = false;
            wrap.classList.remove('flow-dragging');
        });
    });
})();
</script>
@endsection
