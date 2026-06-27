@extends('theme::layouts.app', ['title' => 'Stock History — '.$ingredient->name, 'heading' => 'Restaurant'])

@section('content')
<style>
.tx-wrap { max-width:760px; }
.tx-card  { background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.tx-table { width:100%;border-collapse:collapse; }
.tx-table thead th { padding:10px 16px;font-size:11px;font-weight:800;color:var(--muted);
                      text-transform:uppercase;letter-spacing:.4px;
                      border-bottom:1px solid var(--border);
                      background:color-mix(in srgb,var(--border) 15%,var(--bg)); }
.tx-table tbody tr { border-bottom:1px solid var(--border); }
.tx-table tbody tr:last-child { border-bottom:none; }
.tx-table tbody td { padding:11px 16px;font-size:13px;vertical-align:middle; }
.tx-type  { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;
             font-size:11px;font-weight:800;text-transform:uppercase; }
.tx-change { font-weight:800; }
.tx-empty  { text-align:center;padding:48px 24px;color:var(--muted); }
</style>

<div class="tx-wrap">
  @include('restaurant::partials.nav')

  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
    <a href="{{ route('restaurant.ingredients.index') }}"
       style="display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:9px;
              border:1px solid var(--border);color:var(--muted);text-decoration:none;font-size:13px;">
      <i class="fa fa-arrow-left"></i>
    </a>
    <div>
      <span style="font-size:12px;color:var(--muted);">Ingredients /</span>
      <span style="font-size:12px;font-weight:700;"> {{ $ingredient->name }} — Stock History</span>
    </div>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
    <div>
      <span style="font-size:15px;font-weight:900;">{{ $ingredient->name }}</span>
      <span style="font-size:12px;color:var(--muted);margin-left:8px;">Current: <strong>{{ number_format((float)$ingredient->quantity,2) }} {{ strtoupper($ingredient->unit) }}</strong></span>
    </div>
  </div>

  <div class="tx-card">
    @if($transactions->isEmpty())
      <div class="tx-empty"><i class="fa fa-clock-rotate-left" style="font-size:32px;margin-bottom:12px;display:block;"></i>No transactions yet.</div>
    @else
      <table class="tx-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Change</th>
            <th>Balance After</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          @foreach($transactions as $tx)
          <tr>
            <td style="color:var(--muted);font-size:12px;white-space:nowrap;">{{ $tx->created_at->format('d M y, H:i') }}</td>
            <td>
              <span class="tx-type" style="background:{{ $tx->typeColor() }}22;color:{{ $tx->typeColor() }};">
                {{ $tx->typeLabel() }}
              </span>
            </td>
            <td>
              <span class="tx-change" style="color:{{ (float)$tx->quantity_change >= 0 ? '#16a34a' : '#dc2626' }}">
                {{ (float)$tx->quantity_change >= 0 ? '+' : '' }}{{ number_format((float)$tx->quantity_change, 3) }}
              </span>
            </td>
            <td>{{ number_format((float)$tx->quantity_after, 3) }} {{ strtoupper($ingredient->unit) }}</td>
            <td style="color:var(--muted);font-size:12px;">{{ $tx->notes ?: '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div style="padding:14px 16px;">
        {{ $transactions->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
