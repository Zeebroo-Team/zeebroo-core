@extends('theme::layouts.app', ['title' => 'Tables', 'heading' => 'Restaurant'])

@section('content')
<style>
/* ── SVG table colours ──────────────────────── */
.rt-tbl { fill: color-mix(in srgb, var(--bg) 80%, var(--border)); }
.rt-chr { fill: color-mix(in srgb, var(--border) 180%, var(--bg)); }
.rt-lbl { fill: var(--text); }
.rt-sub { fill: var(--muted); }

/* ── Floor canvas wrapper ───────────────────── */
.rt-floor-wrap {
  width: 100%;
  overflow-x: auto;
  border-radius: 16px;
  border: 1px solid var(--border);
}
.rt-floor {
  position: relative;
  width: 1200px;
  min-height: 560px;
  background-color: color-mix(in srgb, var(--bg) 60%, #b8a88a 40%);
  background-image:
    linear-gradient(rgba(0,0,0,.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,0,0,.04) 1px, transparent 1px);
  background-size: 48px 48px;
  padding: 20px;
  box-sizing: border-box;
}
.rt-floor.rt-floor--edit {
  border: 2.5px dashed var(--primary);
  cursor: default;
}
.rt-floor.rt-floor--edit::after {
  content: 'Drag tables to reposition';
  position: absolute;
  top: 10px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .04em;
  color: var(--primary);
  background: color-mix(in srgb, var(--primary) 10%, var(--card));
  padding: 3px 12px;
  border-radius: 999px;
  pointer-events: none;
  white-space: nowrap;
}

/* ── Table card ─────────────────────────────── */
.rt-card {
  position: absolute;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  user-select: none;
  transition: filter .18s;
}
.rt-card:not(.rt-floor--edit .rt-card):hover {
  filter: drop-shadow(0 8px 18px rgba(0,0,0,.18));
}
.rt-floor--edit .rt-card {
  cursor: grab;
}
.rt-card--dragging {
  cursor: grabbing !important;
  filter: drop-shadow(0 12px 24px rgba(0,0,0,.28));
  z-index: 100 !important;
}

/* ── Card action buttons (shown on hover, hidden in drag mode) ── */
.rt-card__acts {
  display: flex;
  gap: 4px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .15s;
}
.rt-card:hover .rt-card__acts {
  opacity: 1;
  pointer-events: auto;
}
.rt-floor--edit .rt-card__acts {
  display: none;
}
.rt-card__btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  font-size: 11px;
  font-weight: 700;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 1px 4px rgba(0,0,0,.12);
  line-height: 1.4;
}
.rt-card__btn:hover { background: color-mix(in srgb, var(--primary) 8%, var(--card)); border-color: color-mix(in srgb, var(--primary) 40%, var(--border)); }
.rt-card__btn--del { color: #ef4444; border-color: color-mix(in srgb, #ef4444 30%, var(--border)); }
.rt-card__btn--del:hover { background: color-mix(in srgb, #ef4444 8%, var(--card)); border-color: #ef4444; }

/* ── Status badge ───────────────────────────── */
.rt-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  border: 1.5px solid;
  white-space: nowrap;
}

/* ── Pulse for occupied ─────────────────────── */
@keyframes rtPulse { 0%,100%{opacity:1} 50%{opacity:.5} }
.rt-dot-occupied { animation: rtPulse 1.6s ease-in-out infinite; }

/* ── Legend dot ─────────────────────────────── */
.rt-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ── Toolbar ────────────────────────────────── */
.rt-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
}
.rt-toolbar-left { display:flex; flex-direction:column; gap:2px; }
.rt-toolbar-right { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.rt-edit-actions { display:none; align-items:center; gap:8px; }
.rt-edit-actions.rt-visible { display:flex; }

/* ── Empty state ────────────────────────────── */
.rt-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 12px;
  color: var(--muted);
}
</style>

@php
/* ════════════════════════════════════════════
   SVG TABLE GENERATOR
   ════════════════════════════════════════════ */
$genTableSvg = function(int $cap, string $status, string $name): string {

    $statusColors = [
        'available' => '#22c55e',
        'occupied'  => '#ef4444',
        'reserved'  => '#f59e0b',
        'inactive'  => '#9ca3af',
    ];
    $sc = $statusColors[$status] ?? '#9ca3af';

    $tblStroke = "stroke=\"$sc\" stroke-width=\"2.5\" paint-order=\"stroke\"";
    $dotClass  = $status === 'occupied' ? 'class="rt-dot-occupied"' : '';

    /* Round table (1–4 seats) */
    if ($cap <= 4) {
        $vw = 110; $vh = 110;
        $cx = 55; $cy = 55;
        $tR = 30; $cR = 46;
        $cW = 19; $cH = 12;
        $n = max(2, (int)$cap);
        $chairs = '';
        for ($i = 0; $i < $n; $i++) {
            $a_deg = -90 + 360 * $i / $n;
            $a_rad = deg2rad($a_deg);
            $chx = round($cx + $cR * cos($a_rad), 3);
            $chy = round($cy + $cR * sin($a_rad), 3);
            $rot = round($a_deg + 90, 3);
            $chairs .= "<rect x=\"" . (-$cW/2) . "\" y=\"" . (-$cH/2) . "\""
                     . " width=\"$cW\" height=\"$cH\" rx=\"4\""
                     . " class=\"rt-chr\""
                     . " transform=\"translate($chx,$chy) rotate($rot)\"/>";
        }
        $glow  = "<circle cx=\"$cx\" cy=\"$cy\" r=\"$tR\" fill=\"$sc\" opacity=\".12\"/>";
        $table = "<circle cx=\"$cx\" cy=\"$cy\" r=\"$tR\" class=\"rt-tbl\" $tblStroke/>";
        $dotX  = round($cx + $tR * cos(deg2rad(-45)), 2);
        $dotY  = round($cy + $tR * sin(deg2rad(-45)), 2);
        $dot   = "<circle cx=\"$dotX\" cy=\"$dotY\" r=\"5\" fill=\"$sc\" $dotClass/>";
        $fs    = strlen($name) > 6 ? 8 : (strlen($name) > 4 ? 10 : 12);
        $text  = "<text x=\"$cx\" y=\"" . ($cy - 5) . "\" text-anchor=\"middle\""
               . " font-size=\"$fs\" font-weight=\"800\" class=\"rt-lbl\""
               . " font-family=\"system-ui,-apple-system,sans-serif\">"
               . htmlspecialchars($name, ENT_XML1) . "</text>"
               . "<text x=\"$cx\" y=\"" . ($cy + 9) . "\" text-anchor=\"middle\""
               . " font-size=\"8\" class=\"rt-sub\""
               . " font-family=\"system-ui,-apple-system,sans-serif\">{$cap}p</text>";
        return "<svg viewBox=\"0 0 $vw $vh\" xmlns=\"http://www.w3.org/2000/svg\""
             . " style=\"height:110px;width:auto;display:block;pointer-events:none;\">"
             . $chairs . $glow . $table . $dot . $text . "</svg>";
    }

    /* Rectangular table (5+ seats) */
    $topN = (int) ceil($cap / 2);
    $botN = $cap - $topN;
    $topDraw = min($topN, 6);
    $botDraw = min($botN, 6);
    $cW = 22; $cH = 13; $cRx = 4; $cGap = 6;
    $side = max($topDraw, $botDraw);
    $tw   = $side * ($cW + $cGap) - $cGap + 28;
    $th   = 52;
    $padX = 10; $padY = $cH + 8;
    $vw   = $tw + $padX * 2;
    $vh   = $th + $padY * 2;
    $tx   = $padX; $ty = $padY;
    $drawRow = function(int $n, float $y) use ($tx, $tw, $cW, $cH, $cRx, $cGap): string {
        $total = $n * ($cW + $cGap) - $cGap;
        $startX = $tx + ($tw - $total) / 2;
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $chx = $startX + $i * ($cW + $cGap);
            $out .= "<rect x=\"$chx\" y=\"$y\" width=\"$cW\" height=\"$cH\" rx=\"$cRx\" class=\"rt-chr\"/>";
        }
        return $out;
    };
    $chairsTop = $drawRow($topDraw, $ty - $cH - 4);
    $chairsBot = $drawRow($botDraw, $ty + $th + 4);
    $glow  = "<rect x=\"$tx\" y=\"$ty\" width=\"$tw\" height=\"$th\" rx=\"10\" fill=\"$sc\" opacity=\".12\"/>";
    $table = "<rect x=\"$tx\" y=\"$ty\" width=\"$tw\" height=\"$th\" rx=\"10\" class=\"rt-tbl\" $tblStroke/>";
    $dot   = "<circle cx=\"" . ($tx + $tw - 9) . "\" cy=\"" . ($ty + 9) . "\" r=\"5\" fill=\"$sc\" $dotClass/>";
    $midLine = ($tw > 150)
        ? "<line x1=\"" . ($tx + 16) . "\" y1=\"" . ($ty + $th/2) . "\""
        . " x2=\"" . ($tx + $tw - 16) . "\" y2=\"" . ($ty + $th/2) . "\""
        . " stroke=\"$sc\" stroke-width=\".7\" opacity=\".3\"/>" : '';
    $tcx  = $tx + $tw / 2;
    $tcy  = $ty + $th / 2;
    $fs   = strlen($name) > 8 ? 9 : (strlen($name) > 5 ? 11 : 13);
    $text = "<text x=\"$tcx\" y=\"" . ($tcy - 5) . "\" text-anchor=\"middle\""
          . " font-size=\"$fs\" font-weight=\"800\" class=\"rt-lbl\""
          . " font-family=\"system-ui,-apple-system,sans-serif\">"
          . htmlspecialchars($name, ENT_XML1) . "</text>"
          . "<text x=\"$tcx\" y=\"" . ($tcy + 10) . "\" text-anchor=\"middle\""
          . " font-size=\"9\" class=\"rt-sub\""
          . " font-family=\"system-ui,-apple-system,sans-serif\">{$cap} seats</text>";
    return "<svg viewBox=\"0 0 $vw $vh\" xmlns=\"http://www.w3.org/2000/svg\""
         . " style=\"height:110px;width:auto;display:block;pointer-events:none;\">"
         . $chairsTop . $chairsBot . $glow . $table . $midLine . $dot . $text . "</svg>";
};

/* Auto-arrange tables that have no stored position */
$cols     = 5;
$cellW    = 200;
$cellH    = 180;
$paddingX = 30;
$paddingY = 40;
foreach ($tables as $i => $tbl) {
    if ($tbl->pos_x === null || $tbl->pos_y === null) {
        $col = $i % $cols;
        $row = (int) floor($i / $cols);
        $tbl->pos_x = $paddingX + $col * $cellW;
        $tbl->pos_y = $paddingY + $row * $cellH;
    }
}
@endphp

@include('restaurant::partials.nav')

@if(session('status'))
  <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if($errors->any())
  <div class="pcat-banner pcat-banner--err" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

{{-- ── Toolbar ── --}}
<div class="rt-toolbar">
  <div class="rt-toolbar-left">
    <h2 style="margin:0;font-size:16px;font-weight:800;">Floor Plan</h2>
    <p id="rt-subtitle" style="margin:0;font-size:12px;color:var(--muted);">Click any table to edit its details</p>
  </div>
  <div class="rt-toolbar-right">
    {{-- Legend --}}
    @foreach(['available'=>['#22c55e','Available'],'occupied'=>['#ef4444','Occupied'],'reserved'=>['#f59e0b','Reserved'],'inactive'=>['#9ca3af','Inactive']] as $st=>[$color,$label])
      <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--muted);">
        <span class="rt-legend-dot" style="background:{{ $color }};"></span>{{ $label }}
      </div>
    @endforeach

    {{-- Edit layout actions (shown when in edit mode) --}}
    <div class="rt-edit-actions" id="rt-edit-actions">
      <span id="rt-save-status" style="font-size:12px;color:var(--muted);"></span>
      <button type="button" id="rt-cancel-edit"
              style="padding:8px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">
        Cancel
      </button>
      <button type="button" id="rt-save-layout" class="linkbtn" style="padding:8px 16px;font-size:13px;">
        <i class="fa fa-floppy-disk"></i> Save layout
      </button>
    </div>

    {{-- Default actions --}}
    <div id="rt-default-actions" style="display:flex;align-items:center;gap:8px;">
      @if($tables->isNotEmpty())
        <button type="button" id="rt-edit-layout-btn"
                style="padding:8px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;display:flex;align-items:center;gap:6px;">
          <i class="fa fa-pen-ruler"></i> Edit layout
        </button>
      @endif
      <button type="button" class="linkbtn"
              style="padding:8px 16px;font-size:13px;"
              onclick="document.getElementById('addTableModal').style.display='flex'">
        <i class="fa fa-plus"></i> Add table
      </button>
    </div>
  </div>
</div>

{{-- ── Floor plan canvas ── --}}
<div class="rt-floor-wrap">
  <div class="rt-floor" id="rt-floor">
    @if($tables->isEmpty())
      <div class="rt-empty">
        <svg viewBox="0 0 80 70" xmlns="http://www.w3.org/2000/svg" style="width:80px;opacity:.25;">
          <rect x="10" y="3" width="22" height="14" rx="3" fill="currentColor"/>
          <rect x="40" y="3" width="22" height="14" rx="3" fill="currentColor"/>
          <rect x="5" y="22" width="70" height="42" rx="8" fill="currentColor"/>
        </svg>
        <p style="margin:0;font-size:14px;">No tables yet — add your first table to set up the floor plan.</p>
        <button type="button" class="linkbtn" style="padding:8px 20px;font-size:13px;"
                onclick="document.getElementById('addTableModal').style.display='flex'">
          <i class="fa fa-plus" style="margin-right:5px;"></i>Add first table
        </button>
      </div>
    @else
      @foreach($tables as $table)
        <div class="rt-card"
             data-table-id="{{ $table->id }}"
             data-table-name="{{ addslashes($table->name) }}"
             data-table-cap="{{ $table->capacity }}"
             data-table-status="{{ $table->status }}"
             data-table-notes="{{ addslashes($table->notes ?? '') }}"
             style="left:{{ $table->pos_x }}px;top:{{ $table->pos_y }}px;z-index:1;">
          {!! $genTableSvg($table->capacity, $table->status, $table->name) !!}
          <span class="rt-badge"
                style="color:{{ $table->statusColor() }};border-color:color-mix(in srgb,{{ $table->statusColor() }} 35%,var(--border));background:color-mix(in srgb,{{ $table->statusColor() }} 10%,var(--bg));">
            <span style="width:6px;height:6px;border-radius:50%;background:{{ $table->statusColor() }};flex-shrink:0;"></span>
            {{ $table->statusLabel() }}
          </span>
          {{-- Hover action buttons --}}
          <div class="rt-card__acts">
            <button type="button" class="rt-card__btn"
                    onclick="openEditTable('{{ $table->id }}','{{ addslashes($table->name) }}',{{ $table->capacity }},'{{ $table->status }}','{{ addslashes($table->notes ?? '') }}')">
              <i class="fa fa-pen"></i> Edit
            </button>
            <button type="button" class="rt-card__btn rt-card__btn--del"
                    onclick="rtDeleteTable({{ $table->id }},'{{ addslashes($table->name) }}')">
              <i class="fa fa-trash"></i>
            </button>
          </div>
        </div>
      @endforeach
    @endif
  </div>
</div>

{{-- Summary strip --}}
@if($tables->isNotEmpty())
<div style="margin-top:12px;display:flex;gap:20px;flex-wrap:wrap;padding:0 2px;">
  @foreach(['available'=>['#22c55e','Available'],'occupied'=>['#ef4444','Occupied'],'reserved'=>['#f59e0b','Reserved']] as $st=>[$clr,$lbl])
    @php $cnt = $tables->where('status', $st)->count(); @endphp
    @if($cnt)
      <div style="font-size:12px;color:var(--muted);">
        <strong style="color:{{ $clr }};font-size:16px;font-weight:900;">{{ $cnt }}</strong> {{ $lbl }}
      </div>
    @endif
  @endforeach
  <div style="font-size:12px;color:var(--muted);margin-left:auto;">
    <strong style="color:var(--text);">{{ $tables->count() }}</strong> tables
    &nbsp;·&nbsp;
    <strong style="color:var(--text);">{{ $tables->sum('capacity') }}</strong> seats
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════
     ADD TABLE MODAL
════════════════════════════════════════════ --}}
<div id="addTableModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);">
  <div style="background:var(--bg);border-radius:16px;padding:24px;width:100%;max-width:420px;
              box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,var(--primary) 12%,transparent);
                    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;">
          <i class="fa fa-chair"></i>
        </div>
        <h4 style="margin:0;font-size:15px;font-weight:800;">Add Table</h4>
      </div>
      <button type="button" onclick="document.getElementById('addTableModal').style.display='none'"
              style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;">×</button>
    </div>
    <form method="POST" action="{{ route('restaurant.tables.store') }}" style="display:flex;flex-direction:column;gap:14px;">
      @csrf
      <div>
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">
          Table name / number <span style="color:#ef4444;">*</span>
        </label>
        <input type="text" name="name" required maxlength="100" placeholder="e.g. T-01, VIP Room"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                      background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">
            Seats <span style="color:#ef4444;">*</span>
          </label>
          <input type="number" name="capacity" value="4" required min="1" max="100"
                 style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                        background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">Section</label>
          <input type="text" name="notes" maxlength="500" placeholder="e.g. Patio, Main hall"
                 style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                        background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:4px;">
        <button type="button" onclick="document.getElementById('addTableModal').style.display='none'"
                style="padding:9px 18px;border-radius:9px;border:1px solid var(--border);
                       background:transparent;color:var(--text);font-size:13px;cursor:pointer;">Cancel</button>
        <button type="submit" class="linkbtn" style="padding:9px 22px;font-size:13px;">Save table</button>
      </div>
    </form>
  </div>
</div>

{{-- ════════════════════════════════════════════
     EDIT TABLE MODAL
════════════════════════════════════════════ --}}
<div id="editTableModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);">
  <div style="background:var(--bg);border-radius:16px;padding:24px;width:100%;max-width:420px;
              box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,var(--primary) 12%,transparent);
                    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;">
          <i class="fa fa-pen-to-square"></i>
        </div>
        <h4 style="margin:0;font-size:15px;font-weight:800;">Edit Table</h4>
      </div>
      <button type="button" onclick="document.getElementById('editTableModal').style.display='none'"
              style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;">×</button>
    </div>

    <form id="editTableForm" method="POST" action="" style="display:flex;flex-direction:column;gap:14px;">
      @csrf @method('PUT')
      <div>
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">
          Name <span style="color:#ef4444;">*</span>
        </label>
        <input type="text" id="etName" name="name" required maxlength="100"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                      background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">Seats</label>
          <input type="number" id="etCap" name="capacity" required min="1" max="100"
                 style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                        background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">Status</label>
          <select id="etStatus" name="status"
                  style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                         background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
            <option value="reserved">Reserved</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div>
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">Section / Notes</label>
        <input type="text" id="etNotes" name="notes" maxlength="500" placeholder="e.g. Patio, Window seat"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--border);
                      background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
      </div>
      <div style="display:flex;gap:8px;justify-content:space-between;padding-top:4px;">
        <button type="button"
                onclick="rtDeleteFromModal()"
                style="padding:9px 14px;border-radius:9px;border:1px solid #ef4444;
                       background:transparent;color:#ef4444;font-size:13px;cursor:pointer;
                       display:flex;align-items:center;gap:6px;">
          <i class="fa fa-trash"></i> Remove
        </button>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="document.getElementById('editTableModal').style.display='none'"
                  style="padding:9px 18px;border-radius:9px;border:1px solid var(--border);
                         background:transparent;color:var(--text);font-size:13px;cursor:pointer;">Cancel</button>
          <button type="submit" form="editTableForm" class="linkbtn" style="padding:9px 22px;font-size:13px;">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Standalone delete form (not nested) --}}
<form id="rt-delete-form" method="POST" action="" style="display:none;">
  @csrf @method('DELETE')
</form>

<script>
(function () {
  var floor       = document.getElementById('rt-floor');
  var editActions = document.getElementById('rt-edit-actions');
  var defActions  = document.getElementById('rt-default-actions');
  var editBtn     = document.getElementById('rt-edit-layout-btn');
  var cancelBtn   = document.getElementById('rt-cancel-edit');
  var saveBtn     = document.getElementById('rt-save-layout');
  var saveStatus  = document.getElementById('rt-save-status');
  var subtitle    = document.getElementById('rt-subtitle');

  var editMode    = false;
  var isDragging  = false;
  var dragEl      = null;
  var dragOffX    = 0, dragOffY = 0;
  var origPositions = {};   // {id: {x,y}} snapshot for cancel
  var saveUrl     = @json(route('restaurant.tables.positions'));

  function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value || '';
  }

  /* ── Enter / leave edit mode ── */
  function enterEdit() {
    editMode = true;
    floor.classList.add('rt-floor--edit');
    editActions.classList.add('rt-visible');
    defActions.style.display = 'none';
    subtitle.textContent = 'Drag tables to reposition, then save layout';
    // snapshot current positions
    origPositions = {};
    floor.querySelectorAll('.rt-card[data-table-id]').forEach(function (el) {
      origPositions[el.dataset.tableId] = { x: parseInt(el.style.left), y: parseInt(el.style.top) };
    });
  }

  function leaveEdit() {
    editMode = false;
    floor.classList.remove('rt-floor--edit');
    editActions.classList.remove('rt-visible');
    defActions.style.display = 'flex';
    subtitle.textContent = 'Click any table to edit its details';
    setStatus('');
  }

  function setStatus(msg, isError) {
    if (!saveStatus) return;
    saveStatus.textContent = msg;
    saveStatus.style.color = isError ? '#f87171' : 'var(--muted)';
  }

  /* ── Toggle edit mode ── */
  editBtn && editBtn.addEventListener('click', enterEdit);

  cancelBtn && cancelBtn.addEventListener('click', function () {
    // restore snapshot positions
    floor.querySelectorAll('.rt-card[data-table-id]').forEach(function (el) {
      var snap = origPositions[el.dataset.tableId];
      if (snap) { el.style.left = snap.x + 'px'; el.style.top = snap.y + 'px'; }
    });
    leaveEdit();
  });

  /* ── Save layout ── */
  saveBtn && saveBtn.addEventListener('click', function () {
    var positions = [];
    floor.querySelectorAll('.rt-card[data-table-id]').forEach(function (el) {
      positions.push({
        id: parseInt(el.dataset.tableId, 10),
        x:  parseInt(el.style.left, 10) || 0,
        y:  parseInt(el.style.top,  10) || 0,
      });
    });
    setStatus('Saving…');
    saveBtn.disabled = true;
    fetch(saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ positions: positions }),
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      saveBtn.disabled = false;
      if (d && d.success) {
        setStatus('Saved!');
        setTimeout(leaveEdit, 800);
      } else {
        setStatus('Could not save.', true);
      }
    })
    .catch(function () {
      saveBtn.disabled = false;
      setStatus('Could not save.', true);
    });
  });

  /* ── Drag-and-drop (mouse) ── */
  floor.addEventListener('mousedown', function (e) {
    if (!editMode) return;
    var card = e.target.closest('.rt-card[data-table-id]');
    if (!card) return;
    e.preventDefault();
    isDragging = true;
    dragEl = card;
    var cardRect = card.getBoundingClientRect();
    dragOffX = e.clientX - cardRect.left;
    dragOffY = e.clientY - cardRect.top;
    card.classList.add('rt-card--dragging');
    card.style.zIndex = '50';
  });

  document.addEventListener('mousemove', function (e) {
    if (!isDragging || !dragEl || !floor) return;
    var floorRect = floor.getBoundingClientRect();
    var x = e.clientX - floorRect.left - dragOffX;
    var y = e.clientY - floorRect.top  - dragOffY;
    x = Math.max(0, Math.min(x, floor.offsetWidth  - dragEl.offsetWidth));
    y = Math.max(0, Math.min(y, floor.offsetHeight - dragEl.offsetHeight));
    dragEl.style.left = Math.round(x) + 'px';
    dragEl.style.top  = Math.round(y) + 'px';
  });

  document.addEventListener('mouseup', function () {
    if (dragEl) {
      dragEl.classList.remove('rt-card--dragging');
      dragEl.style.zIndex = '1';
    }
    isDragging = false;
    dragEl = null;
  });

  /* ── Drag-and-drop (touch) ── */
  floor.addEventListener('touchstart', function (e) {
    if (!editMode) return;
    var card = e.target.closest('.rt-card[data-table-id]');
    if (!card) return;
    var touch = e.touches[0];
    isDragging = true;
    dragEl = card;
    var cardRect = card.getBoundingClientRect();
    dragOffX = touch.clientX - cardRect.left;
    dragOffY = touch.clientY - cardRect.top;
    card.classList.add('rt-card--dragging');
    card.style.zIndex = '50';
  }, { passive: true });

  document.addEventListener('touchmove', function (e) {
    if (!isDragging || !dragEl || !floor) return;
    e.preventDefault();
    var touch = e.touches[0];
    var floorRect = floor.getBoundingClientRect();
    var x = touch.clientX - floorRect.left - dragOffX;
    var y = touch.clientY - floorRect.top  - dragOffY;
    x = Math.max(0, Math.min(x, floor.offsetWidth  - dragEl.offsetWidth));
    y = Math.max(0, Math.min(y, floor.offsetHeight - dragEl.offsetHeight));
    dragEl.style.left = Math.round(x) + 'px';
    dragEl.style.top  = Math.round(y) + 'px';
  }, { passive: false });

  document.addEventListener('touchend', function () {
    if (dragEl) {
      dragEl.classList.remove('rt-card--dragging');
      dragEl.style.zIndex = '1';
    }
    isDragging = false;
    dragEl = null;
  });

  /* ── Close modals on backdrop click ── */
  ['addTableModal', 'editTableModal'].forEach(function (id) {
    var el = document.getElementById(id);
    el && el.addEventListener('click', function (e) {
      if (e.target === this) this.style.display = 'none';
    });
  });
})();

/* ── Edit table modal populate ── */
function openEditTable(id, name, cap, status, notes) {
  document.getElementById('editTableForm').action = '/restaurant/tables/' + id;
  document.getElementById('editTableForm').dataset.tableId = id;
  document.getElementById('editTableForm').dataset.tableName = name;
  document.getElementById('etName').value   = name;
  document.getElementById('etCap').value    = cap;
  document.getElementById('etStatus').value = status;
  document.getElementById('etNotes').value  = notes;
  document.getElementById('editTableModal').style.display = 'flex';
  setTimeout(function () { document.getElementById('etName').focus(); }, 60);
}

/* ── Delete from edit modal ── */
function rtDeleteFromModal() {
  var form = document.getElementById('editTableForm');
  var name = form.dataset.tableName || 'this table';
  if (!confirm('Remove "' + name + '" from the floor plan?')) return;
  var del = document.getElementById('rt-delete-form');
  del.action = form.action;
  del.submit();
}

/* ── Delete from card hover button ── */
function rtDeleteTable(id, name) {
  if (!confirm('Remove "' + name + '" from the floor plan?')) return;
  var del = document.getElementById('rt-delete-form');
  del.action = '/restaurant/tables/' + id;
  del.submit();
}
</script>
@endsection
