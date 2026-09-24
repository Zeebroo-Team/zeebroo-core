@extends('theme::layouts.app', ['title' => 'Stock Transfer', 'heading' => 'Stock Transfer'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.st-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;}
.st-badge--in_transit{border:1px solid color-mix(in srgb,#3b82f6 42%,var(--border));background:color-mix(in srgb,#3b82f6 11%,transparent);color:color-mix(in srgb,#2563eb 80%,var(--text));}
.st-badge--completed{border:1px solid color-mix(in srgb,#10b981 42%,var(--border));background:color-mix(in srgb,#10b981 11%,transparent);color:color-mix(in srgb,#059669 80%,var(--text));}
.st-badge--cancelled{border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:color-mix(in srgb,#ef4444 11%,transparent);color:color-mix(in srgb,#dc2626 80%,var(--text));}
.st-route{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;color:var(--text);margin-top:8px;}
.st-route i{color:var(--muted);font-size:12px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div style="flex:1;min-width:220px;">
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                @if($transfer->transferredBy)Transferred by {{ $transfer->transferredBy->name }}@endif
                @if($transfer->transferred_at) &middot; {{ $transfer->transferred_at->format('M j, Y g:i A') }}@endif
            </p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">{{ $transfer->transfer_number }}</h2>
            <div class="st-route">
                <span>{{ $transfer->fromBranch?->name ?? '—' }}</span>
                <i class="fa fa-arrow-right-long"></i>
                <span>{{ $transfer->toBranch?->name ?? '—' }}</span>
            </div>
            @if($transfer->notes)
                <p class="muted" style="margin:6px 0 0;font-size:12px;">{{ $transfer->notes }}</p>
            @endif
            <div style="margin-top:10px;">
                <span class="st-badge st-badge--{{ $transfer->status }}">{{ $transfer->statusLabel() }}</span>
                @if($transfer->isCompleted() && $transfer->receivedBy)
                    <span class="muted" style="font-size:12px;margin-left:8px;">Received by {{ $transfer->receivedBy->name }} on {{ $transfer->received_at->format('M j, Y g:i A') }}</span>
                @endif
                @if($transfer->isCancelled() && $transfer->cancelledBy)
                    <span class="muted" style="font-size:12px;margin-left:8px;">Cancelled by {{ $transfer->cancelledBy->name }} on {{ $transfer->cancelled_at->format('M j, Y g:i A') }}</span>
                @endif
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            <a href="{{ route('pos.stock-transfers.index') }}"
               class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-arrow-left"></i> All transfers
            </a>

            @if($transfer->isInTransit())
                <form method="POST" action="{{ route('pos.stock-transfers.receive', $transfer) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Mark this transfer as received? This will add the stock to the destination branch.')"
                            class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fa fa-check"></i> Mark received
                    </button>
                </form>
                <form method="POST" action="{{ route('pos.stock-transfers.cancel', $transfer) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Cancel this transfer? Stock will be returned to the source branch.')"
                            class="pcat-btn-del" style="padding:8px 14px;">
                        <i class="fa fa-ban"></i> Cancel
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Unit</th>
                    <th>Quantity</th>
                    <th>Unit cost</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfer->lines as $line)
                    <tr>
                        <td style="font-weight:600;">{{ $line->product_name }}</td>
                        <td class="muted">{{ $line->sku ?: '—' }}</td>
                        <td class="muted">{{ $line->unit ?: '—' }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                        <td class="muted">{{ number_format((float) $line->unit_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="padding:16px;font-size:13px;">No lines.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
