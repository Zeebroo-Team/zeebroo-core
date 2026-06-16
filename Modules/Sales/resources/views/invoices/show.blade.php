@extends('theme::layouts.app', ['title' => 'Invoice', 'heading' => 'Invoice'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.inv-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid var(--border);}
.inv-status--draft     {opacity:.8;}
.inv-status--sent      {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.inv-status--paid      {border-color:color-mix(in srgb,#10b981 45%,var(--border));background:color-mix(in srgb,#10b981 12%,transparent);}
.inv-status--overdue   {border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 12%,transparent);}
.inv-status--cancelled {border-color:color-mix(in srgb,#94a3b8 45%,var(--border));background:color-mix(in srgb,#94a3b8 12%,transparent);opacity:.8;}
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
                Issued {{ $invoice->issue_date->format('M j, Y') }}
                @if($invoice->due_date)
                    &middot; Due {{ $invoice->due_date->format('M j, Y') }}
                    @if($invoice->isPaymentDue())
                        <span style="color:#ef4444;font-weight:700;">(payment overdue)</span>
                    @endif
                @endif
            </p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">
                {{ $invoice->invoice_number ?? 'Invoice' }}
                @if($invoice->customer)
                    <span class="muted" style="font-weight:600;font-size:14px;">&middot; {{ $invoice->customer->name }}</span>
                @endif
            </h2>
            @if($invoice->reference)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Ref: {{ $invoice->reference }}</p>
            @endif
            <span class="inv-status inv-status--{{ $invoice->status }}" style="margin-top:8px;display:inline-block;">
                {{ $invoice->statusLabel() }}
            </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank"
               class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-print"></i> Print
            </a>

            @if($invoice->isEditable())
                <a href="{{ route('sales.invoices.edit', $invoice) }}"
                   class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                    Edit
                </a>
            @endif

            @if($invoice->status === \Modules\Sales\Models\Invoice::STATUS_DRAFT)
                <form method="POST" action="{{ route('sales.invoices.mark-sent', $invoice) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#3b82f6 12%,transparent);border:1px solid color-mix(in srgb,#3b82f6 45%,var(--border));color:var(--text);">
                        <i class="fa fa-paper-plane"></i> Mark sent
                    </button>
                </form>
            @endif

            @if(in_array($invoice->status, [\Modules\Sales\Models\Invoice::STATUS_DRAFT, \Modules\Sales\Models\Invoice::STATUS_SENT, \Modules\Sales\Models\Invoice::STATUS_OVERDUE]))
                <form method="POST" action="{{ route('sales.invoices.mark-paid', $invoice) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 45%,var(--border));color:var(--text);">
                        <i class="fa fa-circle-check"></i> Mark paid
                    </button>
                </form>
            @endif

            @if(in_array($invoice->status, [\Modules\Sales\Models\Invoice::STATUS_DRAFT, \Modules\Sales\Models\Invoice::STATUS_SENT]))
                <form method="POST" action="{{ route('sales.invoices.mark-overdue', $invoice) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Mark this invoice as overdue?')"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#ef4444 12%,transparent);border:1px solid color-mix(in srgb,#ef4444 45%,var(--border));color:#ef4444;">
                        <i class="fa fa-clock"></i> Mark overdue
                    </button>
                </form>
            @endif

            @if($invoice->status !== \Modules\Sales\Models\Invoice::STATUS_PAID && $invoice->status !== \Modules\Sales\Models\Invoice::STATUS_CANCELLED)
                <form method="POST" action="{{ route('sales.invoices.cancel', $invoice) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Cancel this invoice?')"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);">
                        Cancel
                    </button>
                </form>
            @endif

            @if($invoice->status !== \Modules\Sales\Models\Invoice::STATUS_PAID)
                <form method="POST" action="{{ route('sales.invoices.destroy', $invoice) }}" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this invoice? This cannot be undone.')"
                            class="pcat-btn-del" style="padding:8px 12px;">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Customer info --}}
    @if($invoice->customer)
    <div style="border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:20px;background:color-mix(in srgb,var(--card) 97%,transparent);">
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Customer</p>
            <p style="margin:0;font-weight:700;font-size:13px;color:var(--text);">{{ $invoice->customer->name }}</p>
        </div>
        @if($invoice->customer->email)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Email</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $invoice->customer->email }}</p>
        </div>
        @endif
        @if($invoice->customer->phone)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Phone</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $invoice->customer->phone }}</p>
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
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td>
                            <strong style="color:var(--text);">
                                {{ $item->product?->name ?? ($item->description ?: '—') }}
                            </strong>
                            @if($item->product && $item->description && $item->description !== $item->product->name)
                                <div class="muted" style="font-size:11px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align:right;font-weight:700;color:var(--text);">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <div style="min-width:260px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Subtotal</span>
                <span>{{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @if($invoice->discount_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Discount</span>
                <span style="color:#ef4444;">− {{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($invoice->tax_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Tax</span>
                <span>+ {{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:15px;font-weight:800;">
                <span style="color:var(--text);">Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <span style="color:var(--text);">{{ number_format($invoice->total, 2) }}</span>
            </div>
        </div>
    </div>

    @if($invoice->notes)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:12px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Notes</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $invoice->notes }}</p>
    </div>
    @endif

    {{-- Public share --}}
    <div style="border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-top:8px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <p style="margin:0 0 2px;font-size:13px;font-weight:700;color:var(--text);">
                    <i class="fa fa-link" style="margin-right:6px;opacity:.7;"></i>Public sharing
                </p>
                <p class="muted" style="margin:0;font-size:12px;">
                    @if($invoice->isPublic())
                        Anyone with the link can view this invoice without logging in.
                    @else
                        Enable to generate a shareable link anyone can view.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('sales.invoices.toggle-share', $invoice) }}" style="flex-shrink:0;">
                @csrf
                <button type="submit" class="linkbtn"
                        style="padding:7px 14px;font-size:12px;border:1px solid var(--border);background:transparent;color:var(--text);display:inline-flex;align-items:center;gap:6px;">
                    @if($invoice->isPublic())
                        <i class="fa fa-eye-slash"></i> Disable link
                    @else
                        <i class="fa fa-share-nodes"></i> Enable sharing
                    @endif
                </button>
            </form>
        </div>

        @if($invoice->isPublic())
        <div style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input id="inv-share-url" type="text" readonly value="{{ $invoice->shareUrl() }}"
                   style="flex:1;min-width:200px;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:12px;font-family:monospace;outline:none;">
            <button type="button" onclick="copyInvShareUrl()"
                    class="linkbtn" style="padding:8px 12px;font-size:12px;border:1px solid var(--border);background:transparent;color:var(--text);display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                <i class="fa fa-copy" id="inv-copy-icon"></i>
                <span id="inv-copy-label">Copy link</span>
            </button>
            <a href="{{ $invoice->shareUrl() }}" target="_blank"
               class="linkbtn" style="padding:8px 12px;font-size:12px;border:1px solid var(--border);background:transparent;color:var(--text);display:inline-flex;align-items:center;gap:6px;text-decoration:none;white-space:nowrap;">
                <i class="fa fa-arrow-up-right-from-square"></i> Preview
            </a>
        </div>
        @endif
    </div>
</div>

@once
<script>
function copyInvShareUrl() {
    var url = document.getElementById('inv-share-url');
    navigator.clipboard.writeText(url.value).then(function () {
        document.getElementById('inv-copy-icon').className  = 'fa fa-check';
        document.getElementById('inv-copy-label').textContent = 'Copied!';
        setTimeout(function () {
            document.getElementById('inv-copy-icon').className  = 'fa fa-copy';
            document.getElementById('inv-copy-label').textContent = 'Copy link';
        }, 2000);
    });
}
</script>
@endonce

<div style="margin-top:14px;">
    <a href="{{ route('sales.invoices.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All invoices
    </a>
</div>
@endsection
