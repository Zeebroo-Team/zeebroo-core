@extends('theme::layouts.app', ['title' => $title, 'heading' => $heading])

@section('content')
@php
    $mapJson = json_encode([
        'business' => ['name' => $business->name, 'id' => $business->id],
        'bills'    => $bills instanceof \Illuminate\Support\Collection ? $bills->values()->all() : (is_array($bills) ? $bills : []),
        'loans'    => $loans->map(fn ($l) => ['id' => $l->id, 'name' => (string) $l->name, 'amount' => (float) $l->borrowed_amount])->values()->all(),
        'rentals'  => $rentals->map(fn ($r) => ['id' => $r->id, 'type' => (string) ($r->property_type ?? ''), 'purpose' => (string) ($r->purpose ?? ''), 'amount' => (float) $r->recurring_cost])->values()->all(),
        'mods'     => $modifications->map(fn ($m) => ['id' => $m->id, 'name' => (string) $m->name, 'amount' => (float) ($m->estimated_cost ?? 0)])->values()->all(),
        'employees'=> $employees->map(fn ($e) => ['id' => $e->id, 'name' => (string) $e->full_name, 'etype' => (string) ($e->employment_type ?? '')])->values()->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
@endphp
<style>
.bmap-wrap{position:relative;background:var(--card,#fff);border-radius:12px;border:1px solid var(--border,#e2e8f0);overflow:hidden;height:calc(100vh - 170px);min-height:500px;display:flex;flex-direction:column;}
.bmap-bar{display:flex;align-items:center;gap:8px;padding:9px 14px;border-bottom:1px solid var(--border,#e2e8f0);flex-wrap:wrap;row-gap:6px;}
.bmap-bar-title{font-weight:600;font-size:13px;color:var(--text,#1e293b);flex:1;min-width:120px;}
.bmap-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:1px solid var(--border,#e2e8f0);background:var(--card,#fff);color:var(--text,#334155);border-radius:6px;font-size:12px;cursor:pointer;}
.bmap-btn:hover{background:var(--muted,#f1f5f9);}
.bmap-legend{display:flex;align-items:center;gap:10px;font-size:11px;color:var(--text-muted,#64748b);flex-wrap:wrap;}
.bmap-dot{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:2px;vertical-align:middle;}
.bmap-svg{flex:1;width:100%;display:block;cursor:grab;background:radial-gradient(ellipse 90% 80% at 50% 50%,#f8fafc 0%,#f1f5f9 100%);}
.bmap-svg:active{cursor:grabbing;}
</style>

<div class="bmap-wrap">
  <div class="bmap-bar">
    <span class="bmap-bar-title"><i class="fa fa-sitemap"></i>&nbsp;{{ $business->name }}</span>
    <div class="bmap-legend">
      <span><span class="bmap-dot" style="background:#3b82f6"></span>POS</span>
      <span><span class="bmap-dot" style="background:#10b981"></span>Inventory</span>
      <span><span class="bmap-dot" style="background:#f59e0b"></span>Expenses</span>
      <span><span class="bmap-dot" style="background:#14b8a6"></span>Income</span>
      <span><span class="bmap-dot" style="background:#8b5cf6"></span>Customers</span>
      <span><span class="bmap-dot" style="background:#6366f1"></span>Reports</span>
      <span><span class="bmap-dot" style="background:#ec4899"></span>HR</span>
      <span><span class="bmap-dot" style="background:#ef4444"></span>Overdue</span>
    </div>
    <button class="bmap-btn" id="bmapFit" title="{{ __('Fit to view') }}"><i class="fa fa-expand"></i></button>
    <button class="bmap-btn" id="bmapZoomIn"  title="{{ __('Zoom in') }}"><i class="fa fa-plus"></i></button>
    <button class="bmap-btn" id="bmapZoomOut" title="{{ __('Zoom out') }}"><i class="fa fa-minus"></i></button>
  </div>
  <svg id="bmapSvg" class="bmap-svg">
    <defs>
      <filter id="bshadow" x="-30%" y="-30%" width="160%" height="160%">
        <feDropShadow dx="0" dy="1.5" stdDeviation="2.5" flood-color="rgba(0,0,0,0.10)"/>
      </filter>
    </defs>
  </svg>
</div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
(function () {
  'use strict';

  var RAW = {!! $mapJson !!};

  /* ── helpers ─────────────────────────────────────────────────────────── */
  function trunc(s, n) {
    s = String(s || '');
    return s.length > n ? s.slice(0, n - 1) + '…' : s;
  }
  function fmt(n) {
    if (n == null) return '';
    return Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 });
  }

  /* ── build static + dynamic tree ─────────────────────────────────────── */
  function buildTree() {
    var bills = (RAW.bills     || []).slice(0, 18);
    var loans = (RAW.loans     || []).slice(0, 12);
    var rents = (RAW.rentals   || []).slice(0, 12);
    var mods  = (RAW.mods      || []).slice(0, 12);
    var emps  = (RAW.employees || []).slice(0, 18);

    return {
      id:'center', label: trunc(RAW.business.name, 20), type:'center', color:'#6366f1', icon:'',
      children: [
        { id:'pos', label:'POS', type:'cat', color:'#3b82f6', icon:'',
          children: [
            { id:'pos-sales', label:'Sales',     type:'subcat', color:'#3b82f6', icon:'', children:[] },
            { id:'pos-pay',   label:'Payments',  type:'subcat', color:'#3b82f6', icon:'', children:[] },
            { id:'pos-disc',  label:'Discounts', type:'subcat', color:'#3b82f6', icon:'', children:[] },
          ]
        },
        { id:'inv', label:'Inventory', type:'cat', color:'#10b981', icon:'',
          children: [
            { id:'inv-prod',  label:'Products',  type:'subcat', color:'#10b981', icon:'', children:[] },
            { id:'inv-stock', label:'Stock',     type:'subcat', color:'#10b981', icon:'', children:[] },
            { id:'inv-sup',   label:'Suppliers', type:'subcat', color:'#10b981', icon:'', children:[] },
          ]
        },
        { id:'exp', label:'Expenses', type:'cat', color:'#f59e0b', icon:'',
          children: [
            { id:'exp-bills', label:'Bills'+(bills.length?' ('+bills.length+')':''), type:'subcat', color:'#f59e0b', icon:'',
              children: bills.map(function(b) {
                return { id:'bill-'+b.id, label:trunc(b.name,17), type:'data',
                  color:b.overdue?'#ef4444':'#10b981', icon:'', overdue:b.overdue, amount:b.amount };
              })
            },
            { id:'exp-loans', label:'Loans'+(loans.length?' ('+loans.length+')':''), type:'subcat', color:'#f59e0b', icon:'',
              children: loans.map(function(l) {
                return { id:'loan-'+l.id, label:trunc(l.name,17), type:'data',
                  color:'#6366f1', icon:'', amount:l.amount };
              })
            },
            { id:'exp-rent', label:'Rentals'+(rents.length?' ('+rents.length+')':''), type:'subcat', color:'#f59e0b', icon:'',
              children: rents.map(function(r) {
                var lbl = trunc((r.type||'Property')+(r.purpose?' – '+r.purpose:''),17);
                return { id:'rent-'+r.id, label:lbl, type:'data',
                  color:'#8b5cf6', icon:'', amount:r.amount };
              })
            },
            { id:'exp-mods', label:'Modifications'+(mods.length?' ('+mods.length+')':''), type:'subcat', color:'#f59e0b', icon:'',
              children: mods.map(function(m) {
                return { id:'mod-'+m.id, label:trunc(m.name,17), type:'data',
                  color:'#64748b', icon:'', amount:m.amount };
              })
            },
          ]
        },
        { id:'inc', label:'Income', type:'cat', color:'#14b8a6', icon:'',
          children: [
            { id:'inc-rev', label:'Revenue',  type:'subcat', color:'#14b8a6', icon:'', children:[] },
            { id:'inc-inv', label:'Invoices', type:'subcat', color:'#14b8a6', icon:'', children:[] },
          ]
        },
        { id:'cust', label:'Customers', type:'cat', color:'#8b5cf6', icon:'',
          children: [
            { id:'cust-cnt', label:'Contacts', type:'subcat', color:'#8b5cf6', icon:'', children:[] },
            { id:'cust-ord', label:'Orders',   type:'subcat', color:'#8b5cf6', icon:'', children:[] },
          ]
        },
        { id:'rpt', label:'Reports', type:'cat', color:'#6366f1', icon:'',
          children: [
            { id:'rpt-anal', label:'Analytics', type:'subcat', color:'#6366f1', icon:'', children:[] },
            { id:'rpt-exp',  label:'Export',    type:'subcat', color:'#6366f1', icon:'', children:[] },
          ]
        },
        { id:'hr', label:'HR', type:'cat', color:'#ec4899', icon:'',
          children: [
            { id:'hr-emp', label:'Employees'+(emps.length?' ('+emps.length+')':''), type:'subcat', color:'#ec4899', icon:'',
              children: emps.map(function(e) {
                return { id:'emp-'+e.id, label:trunc(e.name,17), type:'data',
                  color:'#ec4899', icon:'', etype:e.etype };
              })
            },
            { id:'hr-pay',   label:'Payroll', type:'subcat', color:'#ec4899', icon:'', children:[] },
            { id:'hr-leave', label:'Leave',   type:'subcat', color:'#ec4899', icon:'', children:[] },
          ]
        },
      ]
    };
  }

  /* ── radial layout ───────────────────────────────────────────────────── */
  var R = { center:0, cat:240, subcat:450, data:650 };

  function countLeaves(n) {
    var ch = n.children || [];
    if (!ch.length) return 1;
    return ch.reduce(function(s, c) { return s + countLeaves(c); }, 0);
  }

  function layoutNode(node, depth, a0, a1) {
    var mid = (a0 + a1) / 2;
    node.x    = depth === 0 ? 0 : Math.cos(mid) * R[node.type];
    node.y    = depth === 0 ? 0 : Math.sin(mid) * R[node.type];
    node.midA = mid;
    node.depth = depth;
    var ch = node.children || [];
    if (!ch.length) return;
    var total = ch.reduce(function(s, c) { return s + countLeaves(c); }, 0) || 1;
    var MIN_RAD = 0.12; // minimum radians per child to prevent tight clustering
    var needed = ch.length * MIN_RAD;
    var available = a1 - a0;
    var span = Math.max(available, needed);
    var cursor = mid - span / 2;
    ch.forEach(function(c) {
      var frac = countLeaves(c) / total;
      var cSpan = span * frac;
      layoutNode(c, depth + 1, cursor, cursor + cSpan);
      cursor += cSpan;
    });
  }

  /* ── flatten tree ────────────────────────────────────────────────────── */
  function flatten(root) {
    var nodes = [], links = [];
    function walk(n, p) {
      nodes.push(n);
      if (p) links.push({ s: p, t: n });
      (n.children || []).forEach(function(c) { walk(c, n); });
    }
    walk(root, null);
    return { nodes: nodes, links: links };
  }

  /* ── node dimensions ─────────────────────────────────────────────────── */
  var NW  = { center:180, cat:132, subcat:118, data:106 };
  var NH  = { center:46,  cat:38,  subcat:32,  data:26  };
  var NRX = { center:23,  cat:8,   subcat:7,   data:5   };
  var IW  = { center:36,  cat:30,  subcat:24,  data:20  };

  /* ── render ──────────────────────────────────────────────────────────── */
  var svgSel = d3.select('#bmapSvg');
  var rootG  = svgSel.append('g').attr('id', 'bmapRoot');

  var zoomBeh = d3.zoom()
    .scaleExtent([0.08, 5])
    .on('zoom', function(ev) { rootG.attr('transform', ev.transform); });

  svgSel.call(zoomBeh).on('dblclick.zoom', null);

  var tree = buildTree();
  layoutNode(tree, 0, -Math.PI, Math.PI);
  var flat = flatten(tree);

  /* links */
  rootG.selectAll('.bml')
    .data(flat.links)
    .enter().append('line')
    .attr('class', 'bml')
    .attr('x1', function(d) { return d.s.x; })
    .attr('y1', function(d) { return d.s.y; })
    .attr('x2', function(d) { return d.t.x; })
    .attr('y2', function(d) { return d.t.y; })
    .attr('stroke', function(d) { return d.t.color || '#94a3b8'; })
    .attr('stroke-width', function(d) { return d.t.type==='cat'?2.5:d.t.type==='subcat'?1.8:1.2; })
    .attr('stroke-opacity', 0.42)
    .attr('stroke-dasharray', function(d) { return d.t.type==='data'?'5,4':'7,3'; });

  /* node groups */
  var ng = rootG.selectAll('.bmn')
    .data(flat.nodes)
    .enter().append('g')
    .attr('class', 'bmn')
    .attr('transform', function(d) { return 'translate('+d.x+','+d.y+')'; })
    .style('cursor', 'pointer');

  /* background rect */
  ng.append('rect')
    .attr('x',      function(d) { return -NW[d.type]/2; })
    .attr('y',      function(d) { return -NH[d.type]/2; })
    .attr('width',  function(d) { return NW[d.type]; })
    .attr('height', function(d) { return NH[d.type]; })
    .attr('rx',     function(d) { return NRX[d.type]; })
    .attr('fill',   function(d) { return d.type==='center' ? d.color : '#ffffff'; })
    .attr('stroke', function(d) { return d.type==='center' ? 'none' : (d.color||'#94a3b8'); })
    .attr('stroke-width', function(d) { return d.type==='center'?0:1.5; })
    .attr('filter', 'url(#bshadow)');

  /* colored icon strip (all types, left edge) */
  ng.filter(function(d) { return d.type !== 'center'; })
    .append('rect')
    .attr('x',      function(d) { return -NW[d.type]/2; })
    .attr('y',      function(d) { return -NH[d.type]/2; })
    .attr('width',  function(d) { return IW[d.type]; })
    .attr('height', function(d) { return NH[d.type]; })
    .attr('rx',     function(d) { return NRX[d.type]; })
    .attr('fill',   function(d) { return d.color || '#6366f1'; })
    .attr('opacity', 0.9);

  /* icon glyph — center node gets its own x calculation */
  ng.append('text')
    .attr('x', function(d) {
      return d.type === 'center'
        ? -NW.center/2 + IW.center/2
        : -NW[d.type]/2 + IW[d.type]/2;
    })
    .attr('y', 0)
    .attr('text-anchor', 'middle')
    .attr('dominant-baseline', 'central')
    .attr('fill', 'white')
    .style('font-family', 'FontAwesome, "Font Awesome 5 Free"')
    .style('font-weight', '900')
    .style('font-size', function(d) {
      return (d.type==='center'?14:d.type==='cat'?13:d.type==='subcat'?11:9)+'px';
    })
    .text(function(d) { return d.icon || ''; });

  /* label text */
  ng.append('text')
    .attr('x', function(d) { return -NW[d.type]/2 + IW[d.type] + 6; })
    .attr('y', 0)
    .attr('dominant-baseline', 'central')
    .attr('fill', function(d) { return d.type==='center' ? '#ffffff' : '#1e293b'; })
    .style('font-size', function(d) {
      return (d.type==='center'?13:d.type==='cat'?11.5:d.type==='subcat'?10.5:9.5)+'px';
    })
    .style('font-weight', function(d) { return (d.type==='center'||d.type==='cat') ? '600' : '400'; })
    .text(function(d) { return d.label; });

  /* overdue red dot (top-right corner of data bill nodes) */
  ng.filter(function(d) { return d.overdue; })
    .append('circle')
    .attr('cx', function(d) { return  NW[d.type]/2 - 5; })
    .attr('cy', function(d) { return -NH[d.type]/2 + 5; })
    .attr('r', 4)
    .attr('fill', '#ef4444');

  /* ── tooltip ─────────────────────────────────────────────────────────── */
  var tip = d3.select('body').append('div')
    .style('position', 'fixed')
    .style('background', '#1e293b')
    .style('color', '#f8fafc')
    .style('padding', '7px 11px')
    .style('border-radius', '7px')
    .style('font-size', '12px')
    .style('pointer-events', 'none')
    .style('opacity', '0')
    .style('z-index', '9999')
    .style('max-width', '230px')
    .style('line-height', '1.5')
    .style('box-shadow', '0 4px 12px rgba(0,0,0,.25)');

  ng.on('mouseover', function(ev, d) {
    var html = '<strong>'+d.label+'</strong>';
    if (d.amount != null && d.amount > 0) html += '<br>Amount: ' + fmt(d.amount);
    if (d.overdue)  html += '<br><span style="color:#f87171">Overdue</span>';
    if (d.etype)    html += '<br>Type: ' + d.etype;
    if (d.type === 'center') html = '<strong>' + RAW.business.name + '</strong>';
    tip.html(html)
      .style('opacity', '1')
      .style('left', (ev.clientX + 14) + 'px')
      .style('top',  (ev.clientY - 10) + 'px');
  })
  .on('mousemove', function(ev) {
    tip.style('left', (ev.clientX + 14) + 'px')
       .style('top',  (ev.clientY - 10) + 'px');
  })
  .on('mouseleave', function() {
    tip.style('opacity', '0');
  });

  /* ── fit to view ─────────────────────────────────────────────────────── */
  function fitView() {
    var svgEl = document.getElementById('bmapSvg');
    var W = svgEl.clientWidth  || 900;
    var H = svgEl.clientHeight || 600;
    try {
      var bb = document.getElementById('bmapRoot').getBBox();
      if (bb.width < 1) return;
      var pad = 80;
      var sc  = Math.min(0.95, Math.min(W / (bb.width + pad), H / (bb.height + pad)));
      var tx  = W / 2 - sc * (bb.x + bb.width  / 2);
      var ty  = H / 2 - sc * (bb.y + bb.height / 2);
      svgSel.call(zoomBeh.transform, d3.zoomIdentity.translate(tx, ty).scale(sc));
    } catch (e) { /* getBBox can fail in hidden elements */ }
  }

  setTimeout(fitView, 150);

  var btnFit  = document.getElementById('bmapFit');
  var btnZI   = document.getElementById('bmapZoomIn');
  var btnZO   = document.getElementById('bmapZoomOut');

  btnFit && btnFit.addEventListener('click', fitView);
  btnZI  && btnZI.addEventListener('click',  function() { svgSel.transition().duration(300).call(zoomBeh.scaleBy, 1.5); });
  btnZO  && btnZO.addEventListener('click',  function() { svgSel.transition().duration(300).call(zoomBeh.scaleBy, 0.67); });

})();
</script>
@endsection
