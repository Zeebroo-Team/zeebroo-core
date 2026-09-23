@extends('theme::layouts.app', ['title' => 'Finance Overview', 'heading' => 'Finance Overview'])

@section('content')
<div class="fin-page">
    <style>
        .fin-page{max-width:none;width:100%;margin:0;box-sizing:border-box;}
        .fin-hero{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:flex-start;padding:0 0 12px;margin-bottom:2px;border-bottom:1px solid var(--border);}
        .fin-hero__badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--primary);padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
        .fin-hero__actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
        .fin-btn--ghost{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:9px;font-size:12px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 92%,transparent);color:var(--text);text-decoration:none;transition:border-color .18s ease,background .18s ease;}
        .fin-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 8%,transparent);}
        .fin-body{padding:14px 0 0;}
        .fin-empty{text-align:center;padding:22px 16px;color:var(--muted);border:1px dashed color-mix(in srgb,var(--primary) 26%,var(--border));border-radius:11px;background:color-mix(in srgb,var(--primary) 5%,transparent);}
        .fin-empty__ico{width:44px;height:44px;margin:0 auto 10px;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 22%,transparent),color-mix(in srgb,var(--primary) 7%,transparent));color:var(--primary);font-size:18px;}
        .fin-empty h2{margin:0;font-size:15px;font-weight:700;color:var(--text);}
        .fin-empty p{margin:7px auto 0;max-width:40ch;color:var(--muted);font-size:13px;line-height:1.45;}
        .fin-snapshot{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:8px 12px;padding:11px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border));background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 12%,transparent) 0%,color-mix(in srgb,var(--card) 94%,transparent) 48%);margin-bottom:16px;}
        .fin-snap-stat__lbl{font-size:9px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);font-weight:700;}
        .fin-snap-stat__val{font-size:15px;font-weight:800;letter-spacing:-.03em;line-height:1.15;color:var(--text);}

        .mm-canvas{position:relative;display:flex;align-items:stretch;gap:18px;padding:20px 8px;overflow-x:auto;}
        .mm-svg{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;}
        .mm-left-nodes,.mm-right-nodes{display:flex;flex-direction:column;gap:10px;min-width:220px;justify-content:center;z-index:1;}
        .mm-mid-col,.mm-center-col{display:flex;align-items:center;justify-content:center;z-index:1;}
        .mm-node{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:11px;padding:9px 12px;background:var(--card);box-shadow:0 8px 24px -20px rgba(0,0,0,.4);font-size:12px;text-decoration:none;color:inherit;transition:border-color .16s ease,transform .16s ease;}
        a.mm-node:hover{border-color:color-mix(in srgb,var(--primary) 55%,var(--border));transform:translateY(-1px);}
        .mm-node i{font-size:14px;flex-shrink:0;}
        .mm-node-bill i{color:#4e8ef7;} .mm-node-loan i{color:#7c3aed;} .mm-node-rental i{color:#ea580c;}
        .mm-node-alert{border-color:color-mix(in srgb,#ef4444 55%,var(--border));}
        .mm-node-dim{opacity:.72;cursor:default;}
        .mm-node-inc{flex-direction:row-reverse;text-align:right;}
        .mm-node-body{min-width:0;}
        .mm-node-label{font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;}
        .mm-node-meta{font-size:10.5px;color:var(--muted);margin-top:1px;}
        .mm-overdue-badge{color:#ef4444;font-weight:700;}
        .mm-empty-col{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:var(--muted);font-size:11px;min-width:200px;}
        .mm-hub{border-radius:14px;padding:14px 18px;text-align:center;color:#fff;min-width:130px;box-shadow:0 14px 30px -16px rgba(0,0,0,.5);}
        .mm-hub-exp{background:linear-gradient(135deg,#ef4444,#b91c1c);}
        .mm-hub-inc{background:linear-gradient(135deg,#22c55e,#15803d);}
        .mm-hub-label{font-weight:800;font-size:14px;}
        .mm-hub-sub{font-size:11px;opacity:.9;margin-top:2px;}
        .mm-center-hub{background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 55%,#0f172a));border-radius:16px;padding:18px 22px;text-align:center;color:#fff;min-width:150px;box-shadow:0 16px 34px -16px rgba(0,0,0,.55);}
        .mm-center-name{font-weight:800;font-size:15px;}
        .mm-loading{display:flex;align-items:center;justify-content:center;gap:8px;padding:40px 0;color:var(--muted);font-size:13px;}
        @media (max-width:900px){.mm-canvas{flex-direction:column;} .mm-left-nodes,.mm-right-nodes{min-width:0;}}
    </style>

    <header class="fin-hero">
        <div>
            <span class="fin-hero__badge"><i class="fa fa-diagram-project"></i> Finance Overview</span>
        </div>
        <div class="fin-hero__actions">
            <a class="fin-btn--ghost" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i> Dashboard</a>
        </div>
    </header>

    <div class="fin-body">
        @if(!$business)
            <div class="fin-empty">
                <div class="fin-empty__ico"><i class="fa fa-briefcase"></i></div>
                <h2>No business selected</h2>
                <p>Create or select a business from the navbar to see its finance overview.</p>
            </div>
        @else
            <div class="fin-snapshot" aria-label="Finance summary">
                <div><span class="fin-snap-stat__lbl">Bills</span><br><span class="fin-snap-stat__val">{{ $summary['bills_count'] }}</span></div>
                <div><span class="fin-snap-stat__lbl">Loans</span><br><span class="fin-snap-stat__val">{{ $summary['loans_count'] }}</span></div>
                <div><span class="fin-snap-stat__lbl">Rentals</span><br><span class="fin-snap-stat__val">{{ $summary['rentals_count'] }}</span></div>
                <div><span class="fin-snap-stat__lbl">Approx. monthly outflow</span><br><span class="fin-snap-stat__val">@if($currency){{ $currency }} @endif{{ $summary['total_monthly_fmt'] }}</span></div>
            </div>

            @php($expNodes = $billNodes->concat($loanNodes)->concat($rentalNodes))
            <div class="mm-canvas" id="mm-canvas">
                <svg class="mm-svg" id="mm-svg" aria-hidden="true"></svg>

                <div class="mm-left-nodes" id="mm-left-nodes">
                    @if($expNodes->isEmpty())
                        <div class="mm-empty-col"><i class="fa fa-inbox"></i><span>No expenses recorded</span></div>
                    @else
                        @foreach($billNodes as $n)
                            <a class="mm-node mm-node-bill{{ $n['overdue'] ? ' mm-node-alert' : '' }}" href="{{ $n['route'] }}">
                                <i class="fa fa-file-invoice-dollar"></i>
                                <div class="mm-node-body">
                                    <div class="mm-node-label">{{ $n['label'] }}</div>
                                    <div class="mm-node-meta">{{ $n['meta'] }}@if($n['overdue']) <span class="mm-overdue-badge">· Overdue</span>@endif</div>
                                </div>
                            </a>
                        @endforeach
                        @foreach($loanNodes as $n)
                            <a class="mm-node mm-node-loan" href="{{ $n['route'] }}">
                                <i class="fa fa-hand-holding-dollar"></i>
                                <div class="mm-node-body">
                                    <div class="mm-node-label">{{ $n['label'] }}</div>
                                    <div class="mm-node-meta">{{ $n['meta'] }}</div>
                                </div>
                            </a>
                        @endforeach
                        @foreach($rentalNodes as $n)
                            <a class="mm-node mm-node-rental{{ $n['overdue'] ? ' mm-node-alert' : '' }}" href="{{ $n['route'] }}">
                                <i class="fa fa-house"></i>
                                <div class="mm-node-body">
                                    <div class="mm-node-label">{{ $n['label'] }}</div>
                                    <div class="mm-node-meta">{{ $n['meta'] }}@if($n['overdue']) <span class="mm-overdue-badge">· Overdue</span>@endif</div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>

                <div class="mm-mid-col">
                    <div class="mm-hub mm-hub-exp" id="mm-hub-exp">
                        <div class="mm-hub-label">Expenses</div>
                        <div class="mm-hub-sub">@if($currency){{ $currency }} @endif{{ $summary['total_monthly_fmt'] }} / mo</div>
                    </div>
                </div>

                <div class="mm-center-col">
                    <div class="mm-center-hub" id="mm-center-hub">
                        <div class="mm-center-name">{{ $business->name }}</div>
                    </div>
                </div>

                <div class="mm-mid-col">
                    <div class="mm-hub mm-hub-inc" id="mm-hub-inc">
                        <div class="mm-hub-label">Income</div>
                        <div class="mm-hub-sub">Web panel</div>
                    </div>
                </div>

                <div class="mm-right-nodes" id="mm-right-nodes">
                    @foreach($incomeNodes as $n)
                        @if($n['route'])
                            <a class="mm-node mm-node-inc" href="{{ $n['route'] }}">
                                <i class="fa {{ $n['icon'] }}"></i>
                                <div class="mm-node-body">
                                    <div class="mm-node-label">{{ $n['label'] }}</div>
                                    <div class="mm-node-meta">{{ $n['meta'] }}</div>
                                </div>
                            </a>
                        @else
                            <div class="mm-node mm-node-inc mm-node-dim">
                                <i class="fa {{ $n['icon'] }}"></i>
                                <div class="mm-node-body">
                                    <div class="mm-node-label">{{ $n['label'] }}</div>
                                    <div class="mm-node-meta">{{ $n['meta'] }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@if($business)
<script>
(function () {
    function drawMindMapConnectors() {
        var canvas = document.getElementById('mm-canvas');
        var svg = document.getElementById('mm-svg');
        if (!canvas || !svg) return;

        var cr = canvas.getBoundingClientRect();
        svg.setAttribute('viewBox', '0 0 ' + canvas.offsetWidth + ' ' + canvas.offsetHeight);

        function pt(el, side) {
            var r = el.getBoundingClientRect();
            return { x: (side === 'left' ? r.left : r.right) - cr.left, y: r.top + r.height / 2 - cr.top };
        }
        function curve(p1, p2, color, arrowId) {
            var cx = (p1.x + p2.x) / 2;
            return '<path d="M' + p1.x.toFixed(1) + ',' + p1.y.toFixed(1) + ' C' + cx.toFixed(1) + ',' + p1.y.toFixed(1) + ' ' + cx.toFixed(1) + ',' + p2.y.toFixed(1) + ' ' + p2.x.toFixed(1) + ',' + p2.y.toFixed(1) + '" stroke="' + color + '" stroke-width="1.8" fill="none" opacity="0.7" marker-end="url(#' + arrowId + ')"/>';
        }

        var hubExp = document.getElementById('mm-hub-exp');
        var hubInc = document.getElementById('mm-hub-inc');
        var center = document.getElementById('mm-center-hub');
        if (!hubExp || !hubInc || !center) return;

        var TYPE_COLOR = { bill: '#4e8ef7', loan: '#7c3aed', rental: '#ea580c' };
        var INC = '#22c55e', HUB = '#64748b';
        var paths = [];
        var usedMarkers = {};
        function arrowId(color) { return 'mm-arr-' + color.replace('#', ''); }
        function coloredCurve(p1, p2, color) {
            usedMarkers[color] = true;
            return curve(p1, p2, color, arrowId(color));
        }

        paths.push(coloredCurve(pt(center, 'left'), pt(hubExp, 'right'), HUB));
        paths.push(coloredCurve(pt(center, 'right'), pt(hubInc, 'left'), HUB));

        document.querySelectorAll('#mm-left-nodes .mm-node').forEach(function (node) {
            var type = null;
            node.classList.forEach(function (c) {
                if (c.indexOf('mm-node-') === 0 && c !== 'mm-node-alert') type = c.replace('mm-node-', '');
            });
            var color = TYPE_COLOR[type] || '#ef4444';
            paths.push(coloredCurve(pt(node, 'right'), pt(hubExp, 'left'), color));
        });

        document.querySelectorAll('#mm-right-nodes .mm-node').forEach(function (node) {
            paths.push(coloredCurve(pt(hubInc, 'right'), pt(node, 'left'), INC));
        });

        var markerDefs = Object.keys(usedMarkers).map(function (c) {
            return '<marker id="' + arrowId(c) + '" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0,8 3,0 6" fill="' + c + '"/></marker>';
        }).join('');

        svg.innerHTML = '<defs>' + markerDefs + '</defs>' + paths.join('\n');
    }

    requestAnimationFrame(function () { setTimeout(drawMindMapConnectors, 30); });
    window.addEventListener('resize', drawMindMapConnectors);
})();
</script>
@endif
@endsection
