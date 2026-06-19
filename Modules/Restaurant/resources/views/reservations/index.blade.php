@extends('theme::layouts.app', ['title' => 'Reservations', 'heading' => 'Restaurant'])

@section('content')
<style>
.rv-pill        { padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid var(--border);
                  text-decoration:none;color:var(--muted);background:var(--bg);transition:all .15s;white-space:nowrap; }
.rv-pill:hover  { border-color:var(--primary);color:var(--primary); }
.rv-pill.on     { background:var(--primary);border-color:var(--primary);color:#fff; }
.rv-stat        { border-radius:12px;border:1px solid var(--border);padding:11px 15px;background:var(--bg);
                  display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--text);transition:box-shadow .15s; }
.rv-stat:hover  { box-shadow:0 3px 14px rgba(0,0,0,.08); }
.rv-stat-ico    { width:33px;height:33px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.rv-card        { background:var(--bg);border:1px solid var(--border);border-radius:12px;margin-bottom:8px;
                  display:flex;align-items:stretch;overflow:hidden;border-left-width:3px;transition:box-shadow .15s; }
.rv-card:hover  { box-shadow:0 3px 14px rgba(0,0,0,.07); }
.rv-time-col    { padding:14px 10px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
                  flex-shrink:0;width:64px;border-right:1px solid var(--border);
                  background:color-mix(in srgb,var(--bg) 70%,transparent); }
.rv-time-h      { font-size:17px;font-weight:900;line-height:1;letter-spacing:-.5px;color:var(--text); }
.rv-time-ap     { font-size:9px;font-weight:800;color:var(--muted);margin-top:1px;text-transform:uppercase;letter-spacing:.6px; }
.rv-dur         { font-size:10px;color:var(--muted);margin-top:6px;font-weight:700;
                  padding:2px 5px;border-radius:4px;background:color-mix(in srgb,var(--border) 60%,transparent); }
.rv-body        { flex:1;padding:13px 16px;min-width:0;display:flex;flex-direction:column;gap:5px; }
.rv-row1        { display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap; }
.rv-name        { font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.rv-badge       { padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;flex-shrink:0; }
.rv-meta        { display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.rv-mi          { display:flex;align-items:center;gap:4px;font-size:12px;color:var(--muted); }
.rv-mi i        { font-size:10px; }
.rv-notes       { font-size:12px;color:var(--muted);font-style:italic;padding:4px 8px;border-radius:6px;
                  background:color-mix(in srgb,var(--bg) 50%,transparent);border-left:2px solid var(--border); }
.rv-actions     { display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-top:2px; }
.rv-act         { padding:4px 11px;border-radius:7px;font-size:11px;font-weight:800;border:none;cursor:pointer;
                  display:flex;align-items:center;gap:4px;transition:opacity .15s; }
.rv-act:hover   { opacity:.82; }
.rv-ico-btn     { padding:5px 8px;border-radius:7px;font-size:12px;border:1px solid var(--border);background:transparent;
                  color:var(--muted);cursor:pointer;text-decoration:none;transition:all .15s;display:inline-flex;align-items:center; }
.rv-ico-btn:hover { border-color:var(--text);color:var(--text); }
.rv-divider     { width:1px;height:18px;background:var(--border);flex-shrink:0; }
@media(max-width:600px){
  .rv-time-col  { width:52px;padding:12px 6px; }
  .rv-time-h    { font-size:14px; }
}
</style>

<div>
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert" style="margin-bottom:12px;">{{ $errors->first() }}</div>
  @endif

  @php
    $selDate  = $date ?: date('Y-m-d');
    $prevDate = date('Y-m-d', strtotime($selDate . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($selDate . ' +1 day'));
  @endphp

  {{-- Top bar --}}
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 1px;font-size:16px;font-weight:800;">Reservations</h2>
      <p style="margin:0;font-size:12px;color:var(--muted);">Track and manage all restaurant bookings</p>
    </div>
    <button type="button"
            onclick="document.getElementById('addResvModal').style.display='flex'"
            class="linkbtn"
            style="padding:8px 16px;font-size:13px;font-weight:700;border-radius:9px;display:flex;align-items:center;gap:6px;">
      <i class="fa fa-plus"></i> New Reservation
    </button>
  </div>

  {{-- Today stat cards --}}
  @php
    $statCards = [
      'pending'   => ['label' => 'Pending Today',   'icon' => 'fa-hourglass-start', 'color' => '#f59e0b'],
      'confirmed' => ['label' => 'Confirmed Today',  'icon' => 'fa-circle-check',    'color' => '#3b82f6'],
      'seated'    => ['label' => 'Currently Seated', 'icon' => 'fa-chair',           'color' => '#8b5cf6'],
      'completed' => ['label' => 'Completed Today',  'icon' => 'fa-check-double',    'color' => '#22c55e'],
    ];
  @endphp
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px;">
    @foreach($statCards as $st => $sc)
      <a href="{{ route('restaurant.reservations.index', ['date' => date('Y-m-d'), 'status' => $st]) }}"
         class="rv-stat">
        <div class="rv-stat-ico"
             style="background:color-mix(in srgb,{{ $sc['color'] }} 14%,transparent);color:{{ $sc['color'] }};">
          <i class="fa {{ $sc['icon'] }}"></i>
        </div>
        <div>
          <div style="font-size:20px;font-weight:900;line-height:1;">{{ $statusCounts->get($st, 0) }}</div>
          <div style="font-size:10px;color:var(--muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $sc['label'] }}</div>
        </div>
      </a>
    @endforeach
  </div>

  {{-- Filter bar --}}
  <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 14px;
              display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;">

    {{-- Status pills --}}
    <span style="font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;">Status</span>
    <div style="display:flex;gap:4px;flex-wrap:wrap;">
      @foreach(['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed','seated'=>'Seated','completed'=>'Completed','cancelled'=>'Cancelled'] as $key => $lbl)
        <a href="{{ route('restaurant.reservations.index', array_merge(request()->query(), ['status' => $key])) }}"
           class="rv-pill {{ $status === $key ? 'on' : '' }}">{{ $lbl }}</a>
      @endforeach
    </div>

    <div class="rv-divider"></div>

    {{-- Date navigation --}}
    <div style="display:flex;align-items:center;gap:4px;margin-left:auto;flex-wrap:wrap;">
      <a href="{{ route('restaurant.reservations.index', array_merge(request()->query(), ['date' => $prevDate])) }}"
         title="Previous day"
         style="padding:5px 9px;border-radius:7px;border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:12px;display:flex;align-items:center;">
        <i class="fa fa-chevron-left"></i>
      </a>
      <form method="GET" action="{{ route('restaurant.reservations.index') }}" style="display:contents;">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
               style="padding:5px 9px;border-radius:7px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:12px;font-weight:700;cursor:pointer;">
      </form>
      <a href="{{ route('restaurant.reservations.index', array_merge(request()->query(), ['date' => $nextDate])) }}"
         title="Next day"
         style="padding:5px 9px;border-radius:7px;border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:12px;display:flex;align-items:center;">
        <i class="fa fa-chevron-right"></i>
      </a>
      @if($date)
        <a href="{{ route('restaurant.reservations.index', ['status' => $status]) }}"
           style="padding:5px 11px;border-radius:7px;border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap;">
          All dates
        </a>
      @else
        <a href="{{ route('restaurant.reservations.index', array_merge(request()->query(), ['date' => date('Y-m-d')])) }}"
           style="padding:5px 11px;border-radius:7px;border:1px solid var(--primary);background:color-mix(in srgb,var(--primary) 8%,var(--bg));color:var(--primary);text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap;">
          Today
        </a>
      @endif
    </div>
  </div>

  {{-- Reservation cards --}}
  @php
    $quickActions = [
      'pending'   => ['label' => 'Confirm', 'target' => 'confirmed', 'color' => '#3b82f6', 'icon' => 'fa-circle-check'],
      'confirmed' => ['label' => 'Seat',    'target' => 'seated',    'color' => '#8b5cf6', 'icon' => 'fa-chair'],
      'seated'    => ['label' => 'Complete','target' => 'completed', 'color' => '#22c55e', 'icon' => 'fa-check-double'],
    ];
    $cancelable = ['pending', 'confirmed', 'seated'];
  @endphp

  @forelse($reservations as $resv)
    @php
      $sc     = $resv->statusColor();
      $qa     = $quickActions[$resv->status] ?? null;
      $canCnl = in_array($resv->status, $cancelable);
    @endphp
    <div class="rv-card" style="border-left-color:{{ $sc }};">

      {{-- Time column --}}
      <div class="rv-time-col">
        <div class="rv-time-h">{{ $resv->reserved_at->format('g:i') }}</div>
        <div class="rv-time-ap">{{ $resv->reserved_at->format('A') }}</div>
        <div class="rv-dur">{{ $resv->duration_minutes }}m</div>
        <div style="margin-top:auto;padding-top:10px;">
          <div style="width:7px;height:7px;border-radius:50%;background:{{ $sc }};margin:0 auto;"></div>
        </div>
      </div>

      {{-- Body --}}
      <div class="rv-body">

        {{-- Name + status badge --}}
        <div class="rv-row1">
          <div class="rv-name">{{ $resv->customer_name }}</div>
          <span class="rv-badge"
                style="background:color-mix(in srgb,{{ $sc }} 14%,transparent);color:{{ $sc }};">
            {{ ucfirst($resv->status) }}
          </span>
        </div>

        {{-- Meta row --}}
        <div class="rv-meta">
          <span class="rv-mi">
            <i class="fa fa-users" style="color:var(--primary);"></i>
            {{ $resv->party_size }} {{ $resv->party_size === 1 ? 'guest' : 'guests' }}
          </span>
          @if($resv->table)
            <span class="rv-mi">
              <i class="fa fa-chair" style="color:var(--primary);"></i>
              {{ $resv->table->name }}
            </span>
          @else
            <span class="rv-mi" style="opacity:.5;">
              <i class="fa fa-chair"></i> No table
            </span>
          @endif
          @if($resv->customer_phone)
            <span class="rv-mi">
              <i class="fa fa-phone"></i>
              {{ $resv->customer_phone }}
            </span>
          @endif
          @if($resv->customer_email)
            <span class="rv-mi">
              <i class="fa fa-envelope"></i>
              {{ $resv->customer_email }}
            </span>
          @endif
          <span class="rv-mi" style="margin-left:auto;">
            <i class="fa fa-calendar"></i>
            {{ $resv->reserved_at->format('d M Y') }}
          </span>
        </div>

        {{-- Notes --}}
        @if($resv->notes)
          <div class="rv-notes">
            <i class="fa fa-note-sticky" style="margin-right:4px;opacity:.55;"></i>{{ $resv->notes }}
          </div>
        @endif

        {{-- Action buttons --}}
        <div class="rv-actions">
          @if($qa)
            <form method="POST"
                  action="{{ route('restaurant.reservations.quickStatus', $resv) }}"
                  style="display:contents;">
              @csrf
              <input type="hidden" name="status" value="{{ $qa['target'] }}">
              <button type="submit" class="rv-act"
                      style="background:{{ $qa['color'] }};color:#fff;">
                <i class="fa {{ $qa['icon'] }}" style="font-size:10px;"></i>
                {{ $qa['label'] }}
              </button>
            </form>
          @endif

          @if($canCnl)
            <form method="POST"
                  action="{{ route('restaurant.reservations.quickStatus', $resv) }}"
                  onsubmit="return confirm('Cancel this reservation?')"
                  style="display:contents;">
              @csrf
              <input type="hidden" name="status" value="cancelled">
              <button type="submit" class="rv-act"
                      style="background:color-mix(in srgb,#ef4444 12%,transparent);color:#ef4444;">
                <i class="fa fa-ban" style="font-size:10px;"></i> Cancel
              </button>
            </form>
          @endif

          <div style="margin-left:auto;display:flex;gap:4px;">
            <a href="{{ route('restaurant.reservations.edit', $resv) }}"
               class="rv-ico-btn" title="Edit reservation">
              <i class="fa fa-pen"></i>
            </a>
            <form method="POST"
                  action="{{ route('restaurant.reservations.destroy', $resv) }}"
                  onsubmit="return confirm('Permanently delete this reservation?')"
                  style="display:contents;">
              @csrf @method('DELETE')
              <button type="submit" class="rv-ico-btn"
                      style="border-color:#ef4444;color:#ef4444;" title="Delete reservation">
                <i class="fa fa-trash"></i>
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  @empty
    <div style="text-align:center;padding:48px 20px;color:var(--muted);
                border:2px dashed var(--border);border-radius:14px;">
      <i class="fa fa-calendar-xmark"
         style="font-size:32px;opacity:.25;display:block;margin-bottom:10px;"></i>
      <p style="margin:0;font-size:13px;font-weight:700;">No reservations found.</p>
      <p style="margin:6px 0 0;font-size:12px;">Try a different date or status filter.</p>
    </div>
  @endforelse

  <div style="margin-top:12px;">{{ $reservations->withQueryString()->links() }}</div>
</div>

{{-- ── Add Reservation Modal ── --}}
<div id="addResvModal"
     style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);
            align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);"
     onclick="if(event.target===this) this.style.display='none'">
  <div style="background:var(--bg);border-radius:16px;width:100%;max-width:560px;max-height:90vh;
              overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25);border:1px solid var(--border);">

    {{-- Modal header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:10px;
                    background:color-mix(in srgb,var(--primary) 12%,transparent);
                    display:flex;align-items:center;justify-content:center;color:var(--primary);">
          <i class="fa fa-calendar-plus" style="font-size:15px;"></i>
        </div>
        <div>
          <h4 style="margin:0;font-size:15px;font-weight:800;">New Reservation</h4>
          <p style="margin:0;font-size:12px;color:var(--muted);">Book a table for a guest</p>
        </div>
      </div>
      <button type="button"
              onclick="document.getElementById('addResvModal').style.display='none'"
              style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px;
                     line-height:1;padding:4px 6px;border-radius:7px;transition:background .15s;"
              onmouseover="this.style.background='color-mix(in srgb,var(--border) 60%,transparent)'"
              onmouseout="this.style.background='none'">
        <i class="fa fa-xmark"></i>
      </button>
    </div>

    <div style="height:1px;background:var(--border);margin:16px 0;"></div>

    <form method="POST" action="{{ route('restaurant.reservations.store') }}"
          style="padding:0 24px 24px;">
      @csrf
      @include('restaurant::reservations.partials.form', ['resv' => null, 'tables' => $tables])
      <div style="display:flex;gap:8px;justify-content:flex-end;
                  padding-top:16px;border-top:1px solid var(--border);margin-top:16px;">
        <button type="button"
                onclick="document.getElementById('addResvModal').style.display='none'"
                style="padding:9px 20px;border-radius:9px;border:1px solid var(--border);
                       background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;">
          Cancel
        </button>
        <button type="submit" class="linkbtn"
                style="padding:9px 22px;font-size:13px;font-weight:700;border-radius:9px;
                       display:flex;align-items:center;gap:6px;">
          <i class="fa fa-check" style="font-size:11px;"></i> Create Reservation
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
