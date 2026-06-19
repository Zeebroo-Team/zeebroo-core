@extends('theme::layouts.app', ['title' => 'Edit Reservation', 'heading' => 'Restaurant'])

@section('content')
<div style="max-width:640px;">

  @include('restaurant::partials.nav')

  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert" style="margin-bottom:14px;">{{ $errors->first() }}</div>
  @endif

  {{-- Page header --}}
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="{{ route('restaurant.reservations.index') }}"
       style="padding:7px 10px;border-radius:8px;border:1px solid var(--border);color:var(--muted);
              text-decoration:none;font-size:13px;display:flex;align-items:center;gap:5px;flex-shrink:0;">
      <i class="fa fa-arrow-left"></i>
    </a>
    <div style="flex:1;min-width:0;">
      <h2 style="margin:0 0 2px;font-size:16px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        {{ $reservation->customer_name }}
      </h2>
      <p style="margin:0;font-size:12px;color:var(--muted);">
        <i class="fa fa-calendar" style="margin-right:4px;opacity:.7;"></i>
        {{ $reservation->reserved_at->format('l, d F Y \a\t g:i A') }}
        &nbsp;&middot;&nbsp;
        <i class="fa fa-users" style="margin-right:3px;opacity:.7;"></i>
        {{ $reservation->party_size }} {{ $reservation->party_size === 1 ? 'guest' : 'guests' }}
        @if($reservation->table)
          &nbsp;&middot;&nbsp;
          <i class="fa fa-chair" style="margin-right:3px;opacity:.7;"></i>
          {{ $reservation->table->name }}
        @endif
      </p>
    </div>
    <span style="padding:5px 13px;border-radius:999px;font-size:12px;font-weight:800;flex-shrink:0;
                 background:color-mix(in srgb,{{ $reservation->statusColor() }} 14%,transparent);
                 color:{{ $reservation->statusColor() }};">
      {{ ucfirst($reservation->status) }}
    </span>
  </div>

  {{-- Form card --}}
  <form method="POST" action="{{ route('restaurant.reservations.update', $reservation) }}">
    @csrf @method('PUT')

    <div style="background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;">

      {{-- Section header --}}
      <div style="padding:14px 20px;border-bottom:1px solid var(--border);
                  background:color-mix(in srgb,var(--primary) 3%,var(--bg));
                  display:flex;align-items:center;gap:9px;">
        <div style="width:30px;height:30px;border-radius:8px;
                    background:color-mix(in srgb,var(--primary) 12%,transparent);
                    display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:12px;">
          <i class="fa fa-pen-to-square"></i>
        </div>
        <span style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Reservation Details</span>
      </div>

      {{-- Fields --}}
      <div style="padding:20px;">
        @include('restaurant::reservations.partials.form', ['resv' => $reservation, 'tables' => $tables])
      </div>

      {{-- Actions --}}
      <div style="padding:14px 20px;border-top:1px solid var(--border);
                  display:flex;align-items:center;justify-content:space-between;gap:10px;
                  background:color-mix(in srgb,var(--bg) 80%,transparent);">
        <a href="{{ route('restaurant.reservations.index') }}"
           style="padding:9px 18px;border-radius:9px;border:1px solid var(--border);
                  font-size:13px;text-decoration:none;color:var(--text);font-weight:600;">
          Cancel
        </a>
        <button type="submit" class="linkbtn"
                style="padding:9px 24px;font-size:13px;font-weight:700;border-radius:9px;
                       display:flex;align-items:center;gap:6px;">
          <i class="fa fa-floppy-disk" style="font-size:11px;"></i> Save Changes
        </button>
      </div>

    </div>
  </form>

</div>
@endsection
