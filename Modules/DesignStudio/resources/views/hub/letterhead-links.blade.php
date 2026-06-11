@extends('theme::layouts.app', ['title' => __('Letterhead Connections'), 'heading' => __('Letterhead Connections')])

@section('content')
<style>
    /* ── page shell ───────────────────────────────────────────── */
    .lhl-page{max-width:1100px;margin:0 auto;display:grid;gap:14px}

    .lhl-header{
        border:1px solid color-mix(in srgb,var(--border)90%,transparent);
        border-radius:14px;background:var(--card);padding:16px 18px;
        display:flex;justify-content:space-between;align-items:flex-start;
        flex-wrap:wrap;gap:12px;
    }
    .lhl-header__title{margin:0;font-size:1.06rem;font-weight:800;letter-spacing:-.02em;color:var(--text);display:flex;align-items:center;gap:9px}
    .lhl-header__sub{margin:4px 0 0;font-size:12px;color:var(--muted);max-width:580px}
    .lhl-header__actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .lhl-btn{
        display:inline-flex;align-items:center;gap:7px;padding:8px 14px;
        border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;
        cursor:pointer;border:1px solid;transition:background .15s;
    }
    .lhl-btn--primary{
        border-color:color-mix(in srgb,var(--primary)42%,var(--border));
        background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);
    }
    .lhl-btn--primary:hover{background:color-mix(in srgb,var(--primary)20%,transparent);color:var(--text)}
    .lhl-btn--muted{
        border-color:color-mix(in srgb,var(--border)88%,transparent);
        background:color-mix(in srgb,var(--card)96%,transparent);color:var(--text);
    }
    .lhl-btn--muted:hover{background:color-mix(in srgb,var(--primary)8%,transparent)}

    /* ── graph card ───────────────────────────────────────────── */
    .lhl-graph-card{
        border:1px solid color-mix(in srgb,var(--border)90%,transparent);
        border-radius:14px;background:var(--card);overflow:hidden;
    }
    .lhl-legend{
        display:flex;flex-wrap:wrap;gap:16px;align-items:center;
        padding:10px 18px;font-size:11px;color:var(--muted);
        border-bottom:1px solid color-mix(in srgb,var(--border)80%,transparent);
        background:color-mix(in srgb,var(--card)97%,transparent);
    }
    .lhl-legend-item{display:flex;align-items:center;gap:6px}
    .lhl-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .lhl-legend-line{width:24px;height:2.5px;flex-shrink:0;border-radius:2px}
    .lhl-legend-dash{
        width:24px;height:0;flex-shrink:0;
        border-top:2.5px dashed;opacity:.45;
    }
    .lhl-hint{
        font-size:11px;color:var(--muted);padding:9px 18px;
        border-top:1px solid color-mix(in srgb,var(--border)80%,transparent);
        background:color-mix(in srgb,var(--card)97%,transparent);
        display:flex;align-items:center;gap:7px;
    }

    /* ── node info panel (below diagram) ─────────────────────── */
    .lhl-nodes-grid{
        display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;
    }
    .lhl-node-info{
        border:1px solid color-mix(in srgb,var(--border)84%,transparent);
        border-radius:12px;padding:12px 14px;background:var(--card);
        display:flex;align-items:flex-start;gap:11px;
    }
    .lhl-node-info__badge{
        width:34px;height:34px;border-radius:8px;flex-shrink:0;
        display:flex;align-items:center;justify-content:center;
        font-size:13px;font-weight:900;color:#fff;
    }
    .lhl-node-info__label{font-size:12.5px;font-weight:750;color:var(--text);margin:0 0 2px}
    .lhl-node-info__sub{font-size:10.5px;color:var(--muted);margin:0}
    .lhl-node-info__status{
        display:inline-flex;align-items:center;gap:4px;
        font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;
        margin-top:5px;border:1px solid;
    }

    /* ── save toast ───────────────────────────────────────────── */
    .lhl-toast{
        position:fixed;bottom:24px;right:24px;z-index:9999;
        background:#10b981;color:#fff;padding:8px 16px;border-radius:9px;
        font-size:12px;font-weight:700;
        box-shadow:0 4px 14px rgba(16,185,129,.35);
        opacity:0;transform:translateY(10px);
        transition:opacity .25s,transform .25s;pointer-events:none;
    }
    .lhl-toast.show{opacity:1;transform:translateY(0)}
    .lhl-toast--err{background:#ef4444;box-shadow:0 4px 14px rgba(239,68,68,.35)}
</style>

<div class="lhl-page">

    {{-- ── Header ── --}}
    <header class="lhl-header">
        <div>
            <h1 class="lhl-header__title">
                <i class="fa fa-diagram-project" style="color:color-mix(in srgb,var(--primary)68%,var(--muted));" aria-hidden="true"></i>
                {{ __('Letterhead Connections') }}
            </h1>
            <p class="lhl-header__sub">{{ __('Choose which document print outputs display your letterhead design. Toggle a node to enable or disable it. Drag nodes to rearrange.') }}</p>
        </div>
        <div class="lhl-header__actions">
            @if($letterhead)
                <a href="{{ route('designstudio.editor.edit', $letterhead) }}" class="lhl-btn lhl-btn--primary">
                    <i class="fa fa-pen" aria-hidden="true"></i>{{ __('Edit Letterhead') }}
                </a>
            @else
                <a href="{{ route('designstudio.index') }}" class="lhl-btn lhl-btn--primary">
                    <i class="fa fa-plus" aria-hidden="true"></i>{{ __('Create Letterhead') }}
                </a>
            @endif
            <a href="{{ route('designstudio.index') }}" class="lhl-btn lhl-btn--muted">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Design Studio') }}
            </a>
        </div>
    </header>

    {{-- ── Diagram card ── --}}
    <div class="lhl-graph-card">
        <div class="lhl-legend">
            <span class="lhl-legend-item">
                <span class="lhl-legend-dot" style="background:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,.22);"></span>
                {{ __('Connected — letterhead appears on print') }}
            </span>
            <span class="lhl-legend-item">
                <span class="lhl-legend-dot" style="background:#64748b;"></span>
                {{ __('Disconnected — plain print') }}
            </span>
            <span class="lhl-legend-item" style="margin-left:auto;">
                <i class="fa fa-hand-pointer" style="font-size:10px;opacity:.6;" aria-hidden="true"></i>
                {{ __('Click node or midpoint to toggle · Drag to rearrange') }}
            </span>
        </div>

        <svg id="lhlGraph" style="display:block;width:100%;"></svg>

        <div class="lhl-hint">
            <i class="fa fa-circle-info" style="font-size:12px;opacity:.5;" aria-hidden="true"></i>
            {{ __('Changes save automatically. When a link is enabled, the document\'s print view will render the letterhead header and footer.') }}
        </div>
    </div>

    {{-- ── Node info cards ── --}}
    <div class="lhl-nodes-grid" id="lhlNodeCards">
        {{-- populated by JS --}}
    </div>

</div>

<div id="lhlToast" class="lhl-toast"><i class="fa fa-check" style="margin-right:5px;"></i>{{ __('Saved') }}</div>

<style>
    /* flowing-dash animation on enabled links */
    @keyframes lhl-flow { to { stroke-dashoffset: -24; } }
    .lhl-flow-path { animation: lhl-flow 1.4s linear infinite; }
    /* pulse ring on center node */
    @keyframes lhl-pulse {
        0%   { r: 52px; opacity: .22; }
        100% { r: 72px; opacity: 0;   }
    }
    .lhl-pulse-ring { animation: lhl-pulse 2s ease-out infinite; }
</style>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
'use strict';

/* ── Config ────────────────────────────────────────────────────── */
const CSRF     = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
const SAVE_URL = '{{ route("designstudio.letterhead.links.toggle") }}';
let   enabled  = @json($enabled);

const DOC_NODES = [
    { id: 'po',              label: 'Purchase Orders',  sub: 'PO Invoice Print',  abbr: 'PO', color: '#3b82f6', angle: -90  },
    { id: 'grn',             label: 'Goods Received',   sub: 'GRN Print',         abbr: 'GR', color: '#8b5cf6', angle: -30  },
    { id: 'hr_salary_sheet', label: 'Salary Sheet',     sub: 'Monthly Payroll',   abbr: 'SS', color: '#10b981', angle:  90  },
    { id: 'hr_payslip',      label: 'HR Payslips',      sub: 'Employee Payslip',  abbr: 'PS', color: '#f59e0b', angle: 162  },
    { id: 'sales_quotation', label: 'Sales Quotation',  sub: 'Quote Print',       abbr: 'QT', color: '#ec4899', angle: -150 },
];

/* ── Dimensions ─────────────────────────────────────────────────── */
const svgEl  = document.getElementById('lhlGraph');
const W      = svgEl.parentElement.clientWidth || 800;
const H      = 500;
const cx = W / 2, cy = H / 2;
const RADIUS = Math.min(W / 2 - 110, 195);
const NW = 154, NH = 78, CW = 150, CH = 72;

/* ── Simulation nodes ───────────────────────────────────────────── */
const simNodes = [
    { id: 'letterhead', x: cx, y: cy, fx: cx, fy: cy },
    ...DOC_NODES.map(n => {
        const r = n.angle * Math.PI / 180;
        return { id: n.id, x: cx + RADIUS * Math.cos(r), y: cy + RADIUS * Math.sin(r) };
    }),
];
const nodeById = Object.fromEntries(simNodes.map(n => [n.id, n]));

const simLinks = DOC_NODES.map(n => ({ source: 'letterhead', target: n.id }));

/* ── Force simulation ───────────────────────────────────────────── */
const sim = d3.forceSimulation(simNodes)
    .force('link',    d3.forceLink(simLinks).id(d => d.id).distance(RADIUS).strength(0.38))
    .force('radial',  d3.forceRadial(RADIUS, cx, cy).strength(0.14))
    .force('collide', d3.forceCollide(Math.max(NW, NH) / 2 + 14).strength(0.8))
    .alphaDecay(0.020)
    .velocityDecay(0.48)
    .on('tick', onTick);

/* ── SVG setup ──────────────────────────────────────────────────── */
const svg = d3.select('#lhlGraph')
    .attr('width', W).attr('height', H)
    .attr('viewBox', `0 0 ${W} ${H}`);

const defs = svg.append('defs');

/* dot-grid background */
defs.append('pattern')
    .attr('id', 'lhl-dots').attr('width', 24).attr('height', 24)
    .attr('patternUnits', 'userSpaceOnUse')
    .append('circle').attr('cx', 1).attr('cy', 1).attr('r', 1)
    .attr('fill', 'rgba(148,163,184,0.13)');

svg.append('rect').attr('width', W).attr('height', H).attr('fill', 'url(#lhl-dots)');

/* per-node arrowhead markers (enabled + disabled) */
DOC_NODES.forEach(n => {
    [
        { id: `arr-${n.id}`,     fill: n.color, w: 8, h: 8, op: 1.0 },
        { id: `arr-${n.id}-off`, fill: '#4b5563', w: 6, h: 6, op: 0.55 },
    ].forEach(cfg => {
        defs.append('marker')
            .attr('id', cfg.id)
            .attr('viewBox', '0 -5 10 10')
            .attr('refX', 9).attr('refY', 0)
            .attr('markerWidth', cfg.w).attr('markerHeight', cfg.h)
            .attr('orient', 'auto')
            .append('path')
            .attr('d', 'M 0 -4 L 10 0 L 0 4 Z')
            .attr('fill', cfg.fill)
            .attr('opacity', cfg.op);
    });
});

/* soft radial gradient around center */
const rg = defs.append('radialGradient').attr('id', 'lhl-center-glow')
    .attr('cx', '50%').attr('cy', '50%').attr('r', '50%');
rg.append('stop').attr('offset', '0%').attr('stop-color', '#6366f1').attr('stop-opacity', 0.18);
rg.append('stop').attr('offset', '100%').attr('stop-color', '#6366f1').attr('stop-opacity', 0);
svg.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 80)
    .attr('fill', 'url(#lhl-center-glow)').attr('pointer-events', 'none');

/* ── Layers ─────────────────────────────────────────────────────── */
const linkLayer = svg.append('g');
const flowLayer = svg.append('g');
const midLayer  = svg.append('g');
const nodeLayer = svg.append('g');

/* ── Link base paths ────────────────────────────────────────────── */
const linkPaths = linkLayer.selectAll('path.lhl-base')
    .data(DOC_NODES).enter().append('path')
    .attr('class', 'lhl-base')
    .attr('fill', 'none')
    .attr('stroke-linecap', 'round')
    .attr('stroke-width', 2.5)
    .call(applyLinkStyle);

/* animated flow-dash overlay on enabled links */
const flowPaths = flowLayer.selectAll('path.lhl-flow-path')
    .data(DOC_NODES).enter().append('path')
    .attr('class', 'lhl-flow-path')
    .attr('fill', 'none')
    .attr('stroke-linecap', 'round')
    .attr('stroke-width', 3)
    .attr('stroke-dasharray', '7 17')
    .attr('stroke-dashoffset', 0)
    .attr('pointer-events', 'none')
    .call(applyFlowStyle);

/* ── Midpoint toggle bubbles ────────────────────────────────────── */
const midSels = midLayer.selectAll('g.lhl-mid')
    .data(DOC_NODES).enter()
    .append('g').attr('class', 'lhl-mid')
    .style('cursor', 'pointer')
    .on('click', (_, d) => doToggle(d.id));

midSels.append('circle').attr('r', 14).attr('stroke-width', 2.5).call(applyMidCircle);
midSels.append('text')
    .attr('text-anchor', 'middle').attr('dy', '0.38em')
    .attr('fill', '#fff').attr('font-size', '13px').attr('font-weight', '900')
    .attr('pointer-events', 'none').call(applyMidText);

/* ── Center node ────────────────────────────────────────────────── */
const centerG = nodeLayer.append('g')
    .attr('class', 'lhl-center')
    .attr('transform', `translate(${cx},${cy})`);

/* pulsing ring (CSS animated) */
centerG.append('circle').attr('r', 52).attr('fill', 'none')
    .attr('stroke', '#6366f1').attr('stroke-width', 1.5)
    .attr('class', 'lhl-pulse-ring');

centerG.append('rect')
    .attr('x', -CW/2).attr('y', -CH/2).attr('width', CW).attr('height', CH).attr('rx', 14)
    .attr('fill', 'color-mix(in srgb,#6366f1 13%,var(--card,#f8fafc))')
    .attr('stroke', '#6366f1').attr('stroke-width', 2.5)
    .style('filter', 'drop-shadow(0 6px 18px rgba(99,102,241,0.32))');
centerG.append('rect')
    .attr('x', -CW/2 + 3).attr('y', -CH/2).attr('width', CW - 6).attr('height', 4).attr('rx', 2)
    .attr('fill', '#6366f1');
centerG.append('circle').attr('cx', 0).attr('cy', -CH/2 + 17).attr('r', 13)
    .attr('fill', 'rgba(99,102,241,0.20)');
centerG.append('text').attr('text-anchor', 'middle').attr('y', -CH/2 + 21)
    .attr('fill', '#6366f1').attr('font-size', '9px').attr('font-weight', '900')
    .attr('pointer-events', 'none').text('LH');
centerG.append('text').attr('text-anchor', 'middle').attr('y', -CH/2 + 40)
    .attr('fill', 'var(--text,#1e293b)').attr('font-size', '13.5px').attr('font-weight', '800')
    .attr('pointer-events', 'none').text('Letterhead');
centerG.append('text').attr('text-anchor', 'middle').attr('y', -CH/2 + 54)
    .attr('fill', 'var(--muted,#64748b)').attr('font-size', '9.5px')
    .attr('pointer-events', 'none').text('Design Studio');

/* ── Doc nodes ──────────────────────────────────────────────────── */
const dragBehavior = d3.drag()
    .subject(function(_, d) { return nodeById[d.id]; })
    .on('start', function(event, d) {
        if (!event.active) sim.alphaTarget(0.38).restart();
        const n = nodeById[d.id];
        n.fx = n.x; n.fy = n.y;
    })
    .on('drag', function(event, d) {
        const n = nodeById[d.id];
        n.fx = Math.max(NW / 2 + 2, Math.min(W - NW / 2 - 2, event.x));
        n.fy = Math.max(NH / 2 + 2, Math.min(H - NH / 2 - 2, event.y));
    })
    .on('end', function(event, d) {
        if (!event.active) sim.alphaTarget(0.08);
        const n = nodeById[d.id];
        n.fx = null; n.fy = null;  /* release: elastic spring kicks in */
        setTimeout(() => { if (!event.active) sim.alphaTarget(0); }, 900);
    });

let _clickTarget = null;
const docGs = nodeLayer.selectAll('g.lhl-doc')
    .data(DOC_NODES).enter()
    .append('g').attr('class', 'lhl-doc')
    .style('cursor', 'grab')
    .call(dragBehavior)
    .on('mousedown', (_, d) => { _clickTarget = d.id; })
    .on('click', function(event, d) {
        if (event.defaultPrevented) return;  /* was a drag */
        if (_clickTarget === d.id) doToggle(d.id);
        _clickTarget = null;
    });

/* card rect */
docGs.append('rect')
    .attr('x', -NW/2).attr('y', -NH/2).attr('width', NW).attr('height', NH).attr('rx', 13)
    .attr('stroke-width', 2).call(applyNodeRect);
/* top stripe */
docGs.append('rect')
    .attr('x', -NW/2 + 3).attr('y', -NH/2).attr('width', NW - 6).attr('height', 4).attr('rx', 2)
    .attr('fill', d => d.color);
/* abbr circle */
docGs.append('circle').attr('cx', 0).attr('cy', -NH/2 + 17).attr('r', 13)
    .attr('pointer-events', 'none').call(applyAbbrCircle);
docGs.append('text').attr('text-anchor', 'middle').attr('y', -NH/2 + 21)
    .attr('font-size', '9px').attr('font-weight', '900')
    .attr('pointer-events', 'none').call(applyAbbrText);
/* label */
docGs.append('text').attr('text-anchor', 'middle').attr('y', -NH/2 + 40)
    .attr('fill', 'var(--text,#1e293b)').attr('font-size', '11.5px').attr('font-weight', '800')
    .attr('pointer-events', 'none').text(d => d.label);
/* subtitle */
docGs.append('text').attr('text-anchor', 'middle').attr('y', -NH/2 + 54)
    .attr('fill', 'var(--muted,#64748b)').attr('font-size', '9px')
    .attr('pointer-events', 'none').text(d => d.sub);
/* status dot (top-right corner) */
docGs.append('circle')
    .attr('cx', NW/2 - 11).attr('cy', -NH/2 + 12).attr('r', 5)
    .attr('pointer-events', 'none').call(applyStatusDot);

/* ── Tick ───────────────────────────────────────────────────────── */
function onTick() {
    /* clamp doc nodes to canvas */
    simNodes.forEach(n => {
        if (n.id === 'letterhead') return;
        n.x = Math.max(NW / 2 + 2, Math.min(W - NW / 2 - 2, n.x));
        n.y = Math.max(NH / 2 + 2, Math.min(H - NH / 2 - 2, n.y));
    });

    linkPaths.attr('d', d => edgePath(d));
    flowPaths.attr('d', d => edgePath(d));
    midSels.attr('transform', d => {
        const s = nodeById['letterhead'], t = nodeById[d.id];
        return `translate(${(s.x + t.x) / 2},${(s.y + t.y) / 2})`;
    });
    docGs.attr('transform', d => `translate(${nodeById[d.id].x},${nodeById[d.id].y})`);
}

/* compute a path between the EDGE of source rect and EDGE of target rect */
function edgePath(d) {
    const s = nodeById['letterhead'];
    const t = nodeById[d.id];
    const dx = t.x - s.x, dy = t.y - s.y;
    const len = Math.hypot(dx, dy) || 1;
    const ux = dx / len, uy = dy / len;
    /* exit letterhead rect */
    const ts = Math.min((CW / 2) / Math.abs(ux || 1e-9), (CH / 2) / Math.abs(uy || 1e-9));
    const x1 = s.x + ux * (ts + 3), y1 = s.y + uy * (ts + 3);
    /* enter doc rect (leave room for arrowhead ~11px) */
    const tt = Math.min((NW / 2) / Math.abs(ux || 1e-9), (NH / 2) / Math.abs(uy || 1e-9));
    const x2 = t.x - ux * (tt + 11), y2 = t.y - uy * (tt + 11);
    return `M ${x1} ${y1} L ${x2} ${y2}`;
}

/* ── Style helpers ──────────────────────────────────────────────── */
function isEnabled(id) { return enabled.includes(id); }

function applyLinkStyle(sel) {
    sel.attr('stroke',       d => isEnabled(d.id) ? d.color : '#4b5563')
       .attr('stroke-dasharray', d => isEnabled(d.id) ? null : '8,6')
       .attr('opacity',      d => isEnabled(d.id) ? 0.75 : 0.28)
       .attr('marker-end',   d => isEnabled(d.id)
            ? `url(#arr-${d.id})` : `url(#arr-${d.id}-off)`);
}
function applyFlowStyle(sel) {
    sel.attr('stroke',  d => d.color)
       .attr('opacity', d => isEnabled(d.id) ? 0.55 : 0);
}
function applyMidCircle(sel) {
    sel.attr('fill',   d => isEnabled(d.id) ? d.color : '#374151')
       .attr('stroke', d => isEnabled(d.id) ? 'rgba(255,255,255,0.28)' : '#4b5563');
}
function applyMidText(sel) { sel.text(d => isEnabled(d.id) ? '✓' : '×'); }
function applyNodeRect(sel) {
    sel.attr('fill',   d => isEnabled(d.id) ? 'var(--card,#fff)' : 'color-mix(in srgb,var(--card,#fff) 78%,#1e293b)')
       .attr('stroke', d => isEnabled(d.id) ? d.color : '#374151')
       .style('filter',d => isEnabled(d.id) ? `drop-shadow(0 5px 14px ${d.color}45)` : 'none');
}
function applyAbbrCircle(sel) {
    sel.attr('fill', d => isEnabled(d.id) ? d.color + '2a' : 'rgba(100,116,139,0.14)');
}
function applyAbbrText(sel) {
    sel.attr('fill', d => isEnabled(d.id) ? d.color : '#64748b').text(d => d.abbr);
}
function applyStatusDot(sel) {
    sel.attr('fill', d => isEnabled(d.id) ? '#10b981' : '#4b5563');
}

/* ── Toggle ─────────────────────────────────────────────────────── */
function doToggle(id) {
    const idx = enabled.indexOf(id);
    if (idx >= 0) enabled.splice(idx, 1); else enabled.push(id);

    const t = d3.transition().duration(240).ease(d3.easeCubicOut);
    linkPaths.transition(t).call(applyLinkStyle);
    flowPaths.transition(t).call(applyFlowStyle);
    midSels.select('circle').transition(t).call(applyMidCircle);
    midSels.select('text').call(applyMidText);
    docGs.select('rect').transition(t).call(applyNodeRect);
    docGs.select('circle:nth-of-type(1)').call(applyAbbrCircle);
    docGs.select('text:nth-of-type(1)').call(applyAbbrText);
    docGs.select('circle:nth-of-type(2)').transition(t).call(applyStatusDot);

    /* bounce the toggled node */
    const n = nodeById[id];
    n.vx += (Math.random() - 0.5) * 3;
    n.vy += (Math.random() - 0.5) * 3;
    sim.alpha(0.22).restart();

    buildInfoCards();
    saveState();
}

/* ── Info cards ─────────────────────────────────────────────────── */
function buildInfoCards() {
    document.getElementById('lhlNodeCards').innerHTML = DOC_NODES.map(n => {
        const on = isEnabled(n.id);
        const sc = on ? '#10b981' : '#64748b';
        const sb = on ? 'rgba(16,185,129,.11)' : 'rgba(100,116,139,.09)';
        const se = on ? 'rgba(16,185,129,.32)' : 'rgba(100,116,139,.28)';
        return `<div class="lhl-node-info">
            <div class="lhl-node-info__badge" style="background:${n.color}20;color:${n.color};">${n.abbr}</div>
            <div style="min-width:0;">
                <p class="lhl-node-info__label">${n.label}</p>
                <p class="lhl-node-info__sub">${n.sub}</p>
                <span class="lhl-node-info__status" style="color:${sc};background:${sb};border-color:${se};">
                    ${on ? '&#10003;&nbsp;Enabled' : '&times;&nbsp;Disabled'}
                </span>
            </div>
        </div>`;
    }).join('');
}
buildInfoCards();

/* ── Save ───────────────────────────────────────────────────────── */
let _st = null;
function saveState() {
    clearTimeout(_st);
    _st = setTimeout(() => {
        fetch(SAVE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ enabled }),
        })
        .then(r => r.json()).then(() => showToast('ok')).catch(() => showToast('err'));
    }, 380);
}
function showToast(type) {
    const t = document.getElementById('lhlToast');
    t.className = 'lhl-toast' + (type === 'err' ? ' lhl-toast--err' : '');
    t.innerHTML = type === 'err'
        ? '<i class="fa fa-triangle-exclamation" style="margin-right:5px;"></i>Save failed'
        : '<i class="fa fa-check" style="margin-right:5px;"></i>Saved';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}

})();
</script>
@endsection
