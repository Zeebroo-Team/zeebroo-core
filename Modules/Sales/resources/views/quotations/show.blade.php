@extends('theme::layouts.app', ['title' => 'Quotation', 'heading' => 'Quotation'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.qt-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid var(--border);}
.qt-status--draft    {opacity:.8;}
.qt-status--sent     {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.qt-status--accepted {border-color:color-mix(in srgb,#10b981 45%,var(--border));background:color-mix(in srgb,#10b981 12%,transparent);}
.qt-status--rejected {border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);opacity:.8;}
.qt-status--expired  {border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Header --}}
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                {{ $quotation->quote_date->format('M j, Y') }}
                @if($quotation->expiry_date)
                    &middot; Expires {{ $quotation->expiry_date->format('M j, Y') }}
                    @if($quotation->isExpired())
                        <span style="color:#f59e0b;font-weight:700;">(expired)</span>
                    @endif
                @endif
            </p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">
                {{ $quotation->quote_number ?? 'Quotation' }}
                @if($quotation->customer)
                    <span class="muted" style="font-weight:600;font-size:14px;">&middot; {{ $quotation->customer->name }}</span>
                @endif
            </h2>
            @if($quotation->reference)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Ref: {{ $quotation->reference }}</p>
            @endif
            <span class="qt-status qt-status--{{ $quotation->status }}" style="margin-top:8px;display:inline-block;">
                {{ $quotation->statusLabel() }}
            </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            <a href="{{ route('sales.quotations.print', $quotation) }}" target="_blank"
               class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-print"></i> Print
            </a>

            @if($quotation->isEditable())
                <a href="{{ route('sales.quotations.edit', $quotation) }}"
                   class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                    Edit
                </a>
            @endif

            @if($quotation->status === \Modules\Sales\Models\Quotation::STATUS_DRAFT)
                <form method="POST" action="{{ route('sales.quotations.mark-sent', $quotation) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#3b82f6 12%,transparent);border:1px solid color-mix(in srgb,#3b82f6 45%,var(--border));color:var(--text);">
                        <i class="fa fa-paper-plane"></i> Mark sent
                    </button>
                </form>
            @endif

            @if(in_array($quotation->status, [\Modules\Sales\Models\Quotation::STATUS_DRAFT, \Modules\Sales\Models\Quotation::STATUS_SENT]))
                <form method="POST" action="{{ route('sales.quotations.mark-accepted', $quotation) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 45%,var(--border));color:var(--text);">
                        <i class="fa fa-check"></i> Accept
                    </button>
                </form>
                <form method="POST" action="{{ route('sales.quotations.mark-rejected', $quotation) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Mark this quotation as rejected?')"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));color:#ef4444;">
                        <i class="fa fa-xmark"></i> Reject
                    </button>
                </form>
            @endif

            @if($quotation->status !== \Modules\Sales\Models\Quotation::STATUS_ACCEPTED)
                <form method="POST" action="{{ route('sales.quotations.destroy', $quotation) }}" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this quotation? This cannot be undone.')"
                            class="pcat-btn-del" style="padding:8px 12px;">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Customer info --}}
    @if($quotation->customer)
    <div style="border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:20px;background:color-mix(in srgb,var(--card) 97%,transparent);">
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Customer</p>
            <p style="margin:0;font-weight:700;font-size:13px;color:var(--text);">{{ $quotation->customer->name }}</p>
        </div>
        @if($quotation->customer->email)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Email</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $quotation->customer->email }}</p>
        </div>
        @endif
        @if($quotation->customer->phone)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Phone</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $quotation->customer->phone }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Line items --}}
    <div class="pcat-table-wrap" style="margin-bottom:16px;">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th>Item</th>
                    <th style="width:10%;text-align:right;">Qty</th>
                    <th style="width:16%;text-align:right;">Unit price{{ $currency ? ' ('.$currency.')' : '' }}</th>
                    <th style="width:16%;text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $item)
                    @php
                        $isService     = $item->service_item_id && $item->serviceItem;
                        $boundProducts = $isService ? $item->serviceItem->products : collect();
                        $label         = $item->serviceItem?->name ?? $item->product?->name ?? ($item->description ?: '—');
                        $sublabel      = (!$item->serviceItem && $item->product && $item->description && $item->description !== $item->product->name)
                                       ? $item->description : null;
                    @endphp
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td>
                            <strong style="color:var(--text);">{{ $label }}</strong>
                            @if($isService)
                                <span style="display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;background:color-mix(in srgb,#8b5cf6 13%,transparent);color:#8b5cf6;margin-left:6px;vertical-align:middle;">Service</span>
                            @endif
                            @if($sublabel)
                                <div class="muted" style="font-size:11px;">{{ $sublabel }}</div>
                            @endif
                        </td>
                        <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align:right;font-weight:700;color:var(--text);">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                    @foreach($boundProducts as $bp)
                        <tr style="background:color-mix(in srgb,var(--card) 60%,transparent);">
                            <td></td>
                            <td style="padding-left:28px;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <i class="fa fa-angle-right" style="color:var(--muted);font-size:11px;"></i>
                                    <span style="font-size:12px;color:var(--text);">{{ $bp->name }}</span>
                                    @if($bp->sku)
                                        <span class="muted" style="font-size:11px;">({{ $bp->sku }})</span>
                                    @endif
                                </div>
                            </td>
                            <td style="text-align:right;font-size:12px;color:var(--muted);">
                                {{ rtrim(rtrim(number_format((float)$bp->pivot->qty, 3), '0'), '.') }}
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <div style="min-width:260px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Subtotal</span>
                <span>{{ number_format($quotation->subtotal, 2) }}</span>
            </div>
            @if($quotation->discount_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Discount</span>
                <span style="color:#ef4444;">− {{ number_format($quotation->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($quotation->tax_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Tax</span>
                <span>+ {{ number_format($quotation->tax_amount, 2) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:15px;font-weight:800;">
                <span style="color:var(--text);">Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <span style="color:var(--text);">{{ number_format($quotation->total, 2) }}</span>
            </div>
        </div>
    </div>

    @if($quotation->notes)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:12px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Notes</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $quotation->notes }}</p>
    </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('sales.quotations.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All quotations
    </a>
</div>
@endsection
