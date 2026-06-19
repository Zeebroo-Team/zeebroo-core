@extends('theme::layouts.app', ['title' => 'Tables', 'heading' => 'Restaurant'])

@section('content')
<style>
/* ── SVG table colours (adapt to theme) ───────── */
.rt-tbl  { fill: color-mix(in srgb, var(--bg) 80%, var(--border)); }
.rt-chr  { fill: color-mix(in srgb, var(--border) 180%, var(--bg)); }
.rt-lbl  { fill: var(--text); }
.rt-sub  { fill: var(--muted); }

/* ── Floor plan surface ───────────────────────── */
.rt-floor {
  background-color: color-mix(in srgb, var(--bg) 60%, #b8a88a 40%);
  background-image:
    linear-gradient(rgba(0,0,0,.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,0,0,.045) 1px, transparent 1px);
  background-size: 48px 48px;
  border-radius: 16px;
  border: 1px solid var(--border);
  padding: 32px 28px;
  min-height: 220px;
}

/* ── Table card ───────────────────────────────── */
.rt-card {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: transform .18s, filter .18s;
  user-select: none;
}
.rt-card:hover { transform: translateY(-4px); filter: drop-shadow(0 8px 18px rgba(0,0,0,.18)); }
.rt-card:active { transform: translateY(-1px); }

/* ── Status badge ─────────────────────────────── */
.rt-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  border: 1.5px solid;
}

/* ── Pulse for occupied ───────────────────────── */
@keyframes rtPulse {
  0%,100% { opacity:1; }
  50%      { opacity:.5; }
}
.rt-dot-occupied { animation: rtPulse 1.6s ease-in-out infinite; }

/* ── Legend ───────────────────────────────────── */
.rt-legend-dot {
  width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
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

    $tblStroke  = "stroke=\"$sc\" stroke-width=\"2.5\" paint-order=\"stroke\"";
    $dotClass   = $status === 'occupied' ? 'class="rt-dot-occupied"' : '';

    /* ── Round table (1–4 seats) ────────────── */
    if ($cap <= 4) {
        $vw = 110; $vh = 110;
        $cx = 55; $cy = 55;
        $tR = 30;         // table radius
        $cR = 46;         // chair orbit radius
        $cW = 19; $cH = 12; // chair size

        $n = max(2, (int)$cap);
        $chairs = '';
        for ($i = 0; $i < $n; $i++) {
            $a_deg = -90 + 360 * $i / $n;
            $a_rad = deg2rad($a_deg);
            $chx   = round($cx + $cR * cos($a_rad), 3);
            $chy   = round($cy + $cR * sin($a_rad), 3);
            $rot   = round($a_deg + 90, 3);
            // Chair centred on origin, translated & rotated into position
            $chairs .= "<rect x=\"" . (-$cW/2) . "\" y=\"" . (-$cH/2) . "\""
                     . " width=\"$cW\" height=\"$cH\" rx=\"4\""
                     . " class=\"rt-chr\""
                     . " transform=\"translate($chx,$chy) rotate($rot)\"/>";
        }

        // Status glow halo
        $glow  = "<circle cx=\"$cx\" cy=\"$cy\" r=\"$tR\" fill=\"$sc\" opacity=\".12\"/>";
        // Table surface
        $table = "<circle cx=\"$cx\" cy=\"$cy\" r=\"$tR\" class=\"rt-tbl\" $tblStroke/>";

        // Indicator dot (top-right of table)
        $dotX = round($cx + $tR * cos(deg2rad(-45)), 2);
        $dotY = round($cy + $tR * sin(deg2rad(-45)), 2);
        $dot  = "<circle cx=\"$dotX\" cy=\"$dotY\" r=\"5\" fill=\"$sc\" $dotClass/>";

        // Table name + seat count
        $fs   = strlen($name) > 6 ? 8 : (strlen($name) > 4 ? 10 : 12);
        $text = "<text x=\"$cx\" y=\"" . ($cy - 5) . "\" text-anchor=\"middle\""
              . " font-size=\"$fs\" font-weight=\"800\" class=\"rt-lbl\""
              . " font-family=\"system-ui,-apple-system,sans-serif\">"
              . htmlspecialchars($name, ENT_XML1) . "</text>"
              . "<text x=\"$cx\" y=\"" . ($cy + 9) . "\" text-anchor=\"middle\""
              . " font-size=\"8\" class=\"rt-sub\""
              . " font-family=\"system-ui,-apple-system,sans-serif\">{$cap}p</text>";

        return "<svg viewBox=\"0 0 $vw $vh\" xmlns=\"http://www.w3.org/2000/svg\""
             . " style=\"height:130px;width:auto;display:block;\">"
             . $chairs . $glow . $table . $dot . $text . "</svg>";
    }

    /* ── Rectangular table (5+ seats) ──────── */
    $topN = (int) ceil($cap / 2);
    $botN = $cap - $topN;
    // Cap visual chairs at 6 per side to avoid overflowing SVG
    $topDraw = min($topN, 6);
    $botDraw = min($botN, 6);

    $cW = 22; $cH = 13; $cRx = 4; $cGap = 6;
    $side    = max($topDraw, $botDraw);
    $tw      = $side * ($cW + $cGap) - $cGap + 28; // table width
    $th      = 52;                                   // table height
    $padX    = 10;                                   // left/right padding
    $padY    = $cH + 8;                              // top/bottom padding (room for chairs)
    $vw      = $tw + $padX * 2;
    $vh      = $th + $padY * 2;
    $tx      = $padX; $ty = $padY;                  // table origin

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

    // Status glow
    $glow  = "<rect x=\"$tx\" y=\"$ty\" width=\"$tw\" height=\"$th\" rx=\"10\" fill=\"$sc\" opacity=\".12\"/>";
    // Table surface + status stroke
    $table = "<rect x=\"$tx\" y=\"$ty\" width=\"$tw\" height=\"$th\" rx=\"10\" class=\"rt-tbl\" $tblStroke/>";

    // Dot indicator
    $dot = "<circle cx=\"" . ($tx + $tw - 9) . "\" cy=\"" . ($ty + 9) . "\" r=\"5\" fill=\"$sc\" $dotClass/>";

    // Subtle centre line on long table
    $midLine = ($tw > 150)
        ? "<line x1=\"" . ($tx + 16) . "\" y1=\"" . ($ty + $th/2) . "\""
        . "      x2=\"" . ($tx + $tw - 16) . "\" y2=\"" . ($ty + $th/2) . "\""
        . "      stroke=\"$sc\" stroke-width=\".7\" opacity=\".3\"/>"
        : '';

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
         . " style=\"height:130px;width:auto;display:block;\">"
         . $chairsTop . $chairsBot . $glow . $table . $midLine . $dot . $text . "</svg>";
};
@endphp

{{-- ═══════════════════════════════════════════
     HEADER
═══════════════════════════════════════════ --}}
@include('restaurant::partials.nav')

@if(session('status'))
  <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if($errors->any())
  <div class="pcat-banner pcat-banner--err" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
  <div>
    <h2 style="margin:0 0 2px;font-size:16px;font-weight:800;">Floor Plan</h2>
    <p style="margin:0;font-size:12px;color:var(--muted);">Click any table to edit its status or details</p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    {{-- Legend --}}
    @foreach([
      'available' => ['#22c55e', 'Available'],
      'occupied'  => ['#ef4444', 'Occupied'],
      'reserved'  => ['#f59e0b', 'Reserved'],
      'inactive'  => ['#9ca3af', 'Inactive'],
    ] as $st => [$color, $label])
      <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--muted);">
        <span class="rt-legend-dot" style="background:{{ $color }};"></span>{{ $label }}
      </div>
    @endforeach
    <button type="button" class="linkbtn"
            style="padding:8px 16px;font-size:13px;margin-left:8px;"
            onclick="document.getElementById('addTableModal').style.display='flex'">
      <i class="fa fa-plus"></i> Add table
    </button>
  </div>
</div>

{{-- ═══════════════════════════════════════════
     FLOOR PLAN
═══════════════════════════════════════════ --}}
<div class="rt-floor">
  @if($tables->isEmpty())
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                min-height:200px;gap:12px;color:var(--muted);">
      <svg viewBox="0 0 80 70" xmlns="http://www.w3.org/2000/svg" style="width:80px;opacity:.25;">
        <rect x="10" y="3" width="22" height="14" rx="3" fill="currentColor"/>
        <rect x="40" y="3" width="22" height="14" rx="3" fill="currentColor"/>
        <rect x="5" y="22" width="70" height="42" rx="8" fill="currentColor"/>
        <rect x="10" y="58" width="22" height="14" rx="3" fill="currentColor" transform="translate(0,-6)"/>
        <rect x="40" y="58" width="22" height="14" rx="3" fill="currentColor" transform="translate(0,-6)"/>
      </svg>
      <p style="margin:0;font-size:14px;">No tables yet — add your first table to set up the floor plan.</p>
      <button type="button" class="linkbtn" style="padding:8px 20px;font-size:13px;"
              onclick="document.getElementById('addTableModal').style.display='flex'">
        <i class="fa fa-plus" style="margin-right:5px;"></i>Add first table
      </button>
    </div>
  @else
    <div style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-end;">
      @foreach($tables as $table)
        <div class="rt-card"
             onclick="openEditTable({{ $table->id }}, '{{ addslashes($table->name) }}', {{ $table->capacity }}, '{{ $table->status }}', '{{ addslashes($table->notes ?? '') }}')"
             title="{{ $table->name }} — {{ $table->statusLabel() }} — {{ $table->capacity }} seats{{ $table->notes ? ' — '.$table->notes : '' }}">

          {{-- SVG table illustration --}}
          {!! $genTableSvg($table->capacity, $table->status, $table->name) !!}

          {{-- Status badge --}}
          <span class="rt-badge"
                style="color:{{ $table->statusColor() }};border-color:color-mix(in srgb,{{ $table->statusColor() }} 35%,var(--border));
                       background:color-mix(in srgb,{{ $table->statusColor() }} 10%,var(--bg));">
            <span style="width:6px;height:6px;border-radius:50%;background:{{ $table->statusColor() }};flex-shrink:0;"></span>
            {{ $table->statusLabel() }}
          </span>
        </div>
      @endforeach
    </div>

    {{-- Summary strip --}}
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid rgba(0,0,0,.08);
                display:flex;gap:20px;flex-wrap:wrap;">
      @foreach(['available'=>['#22c55e','Available'],'occupied'=>['#ef4444','Occupied'],'reserved'=>['#f59e0b','Reserved']] as $st=>[$clr,$lbl])
        @php $cnt = $tables->where('status', $st)->count(); @endphp
        @if($cnt)
          <div style="font-size:12px;color:var(--muted);">
            <strong style="color:{{ $clr }};font-size:16px;font-weight:900;">{{ $cnt }}</strong>
            {{ $lbl }}
          </div>
        @endif
      @endforeach
      <div style="font-size:12px;color:var(--muted);margin-left:auto;">
        <strong style="color:var(--text);">{{ $tables->count() }}</strong> total tables
        &nbsp;·&nbsp;
        <strong style="color:var(--text);">{{ $tables->sum('capacity') }}</strong> total seats
      </div>
    </div>
  @endif
</div>

{{-- ═══════════════════════════════════════════
     ADD TABLE MODAL
═══════════════════════════════════════════ --}}
<div id="addTableModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);
     align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);">
  <div style="background:var(--bg);border-radius:16px;padding:24px;width:100%;max-width:420px;
              box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
      <div style="width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,var(--primary) 12%,transparent);
                  color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:16px;">
        <i class="fa fa-chair"></i>
      </div>
      <h4 style="margin:0;font-size:15px;font-weight:800;">Add Table</h4>
    </div>
    <form method="POST" action="{{ route('restaurant.tables.store') }}" style="display:flex;flex-direction:column;gap:14px;">
      @csrf
      <div>
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:5px;">
          Table name / number <span style="color:#ef4444;">*</span>
        </label>
        <input type="text" name="name" required maxlength="100" placeholder="e.g. T-01, VIP Room, Bar"
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
        <button type="button"
                onclick="document.getElementById('addTableModal').style.display='none'"
                style="padding:9px 18px;border-radius:9px;border:1px solid var(--border);
                       background:transparent;color:var(--text);font-size:13px;cursor:pointer;">Cancel</button>
        <button type="submit" class="linkbtn" style="padding:9px 22px;font-size:13px;">Save table</button>
      </div>
    </form>
  </div>
</div>

{{-- ═══════════════════════════════════════════
     EDIT TABLE MODAL
═══════════════════════════════════════════ --}}
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
      <button type="button"
              onclick="document.getElementById('editTableModal').style.display='none'"
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

      {{-- Actions --}}
      <div style="display:flex;gap:8px;justify-content:space-between;padding-top:4px;">
        <form id="deleteTableForm" method="POST" action=""
              onsubmit="return confirm('Remove this table from the floor plan?')" style="display:inline;">
          @csrf @method('DELETE')
          <button type="submit"
                  style="padding:9px 14px;border-radius:9px;border:1px solid #ef4444;
                         background:transparent;color:#ef4444;font-size:13px;cursor:pointer;
                         display:flex;align-items:center;gap:6px;">
            <i class="fa fa-trash"></i> Remove
          </button>
        </form>
        <div style="display:flex;gap:8px;">
          <button type="button"
                  onclick="document.getElementById('editTableModal').style.display='none'"
                  style="padding:9px 18px;border-radius:9px;border:1px solid var(--border);
                         background:transparent;color:var(--text);font-size:13px;cursor:pointer;">Cancel</button>
          <button type="submit" form="editTableForm" class="linkbtn"
                  style="padding:9px 22px;font-size:13px;">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEditTable(id, name, cap, status, notes) {
  document.getElementById('editTableForm').action   = '/restaurant/tables/' + id;
  document.getElementById('deleteTableForm').action = '/restaurant/tables/' + id;
  document.getElementById('etName').value   = name;
  document.getElementById('etCap').value    = cap;
  document.getElementById('etStatus').value = status;
  document.getElementById('etNotes').value  = notes;
  document.getElementById('editTableModal').style.display = 'flex';
}
/* Close modals on backdrop click */
['addTableModal','editTableModal'].forEach(function(id) {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
  });
});
</script>
@endsection
