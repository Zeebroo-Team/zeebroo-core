@php
    $summary = $summary ?? [];
@endphp
<dl class="supplier-show-overview-grid">
    <div>
        <dt>Contact</dt>
        <dd>{{ $supplier->contact_name ?: '—' }}</dd>
    </div>
    <div>
        <dt>Email</dt>
        <dd>{{ $supplier->email ?: '—' }}</dd>
    </div>
    <div>
        <dt>Phone</dt>
        <dd>{{ $supplier->phone ?: '—' }}</dd>
    </div>
    <div>
        <dt>Status</dt>
        <dd>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</dd>
    </div>
    <div>
        <dt>PO total @if(filled($currency))({{ $currency }})@endif</dt>
        <dd>{{ number_format((float) ($summary['purchases_total'] ?? 0), 2) }}</dd>
    </div>
    <div>
        <dt>Received total @if(filled($currency))({{ $currency }})@endif</dt>
        <dd>{{ number_format((float) ($summary['grns_total'] ?? 0), 2) }}</dd>
    </div>
    <div>
        <dt>Cash paid @if(filled($currency))({{ $currency }})@endif</dt>
        <dd>{{ number_format((float) ($summary['cash_paid_total'] ?? 0), 2) }}</dd>
    </div>
    <div>
        <dt>Credit outstanding @if(filled($currency))({{ $currency }})@endif</dt>
        <dd>{{ number_format((float) ($summary['credit_outstanding'] ?? 0), 2) }}</dd>
    </div>
</dl>
@if($supplier->notes)
    <div style="padding:12px 14px;border:1px solid var(--border);border-radius:10px;">
        <p class="muted" style="margin:0 0 6px;font-size:11px;font-weight:700;">Notes</p>
        <p style="margin:0;font-size:13px;line-height:1.5;color:var(--text);white-space:pre-wrap;">{{ $supplier->notes }}</p>
    </div>
@endif
