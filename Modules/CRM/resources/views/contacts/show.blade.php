@extends('theme::layouts.app', ['title' => $customer->name, 'heading' => $customer->name])

@section('content')
@include('product::partials.catalog-hub-styles')

@php
    $s = $profile['summary'];
    $orderRoutes = ['invoice' => 'sales.invoices.show', 'order' => 'sales.orders.show', 'pos_sale' => 'pos.sales.show'];
    $fmtDate = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d M Y') : '—';
@endphp
<style>
.c360-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:16px;}
.c360-stat{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:var(--card);}
.c360-stat b{display:block;font-size:18px;font-weight:800;color:var(--text);}
.c360-stat span{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.c360-grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr));}
.c360-sec h2{font-size:14px;font-weight:800;margin:0 0 8px;display:flex;align-items:center;gap:8px;}
.c360-sec h2 small{font-weight:600;color:var(--muted);font-size:12px;}
.c360-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.c360-table th{text-align:left;padding:6px 8px;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
.c360-table td{padding:7px 8px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:top;}
.c360-wrap{border:1px solid var(--border);border-radius:10px;overflow:auto;max-height:340px;background:var(--card);}
.c360-empty{font-size:12.5px;color:var(--muted);margin:0;padding:10px 0;}
.c360-pill{display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid var(--border);}
.c360-pill--open{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
.c360-pill--overdue{border-color:#ef444466;color:#ef4444;}
.c360-tl{list-style:none;margin:0;padding:0;max-height:520px;overflow:auto;}
.c360-tl li{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);font-size:12.5px;}
.c360-tl-icon{flex:0 0 26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;}
.c360-tl-body{white-space:pre-line;color:var(--muted);margin-top:2px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div style="margin-bottom:14px;">
        <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;">{{ $customer->name }}</h2>
        <div class="muted" style="font-size:13px;">
            @if($customer->phone) {{ $customer->phone }} @endif
            @if($customer->email) · {{ $customer->email }} @endif
            @if($customer->address) · {{ $customer->address }} @endif
            · {{ ucfirst($customer->customer_type ?? 'retail') }} customer since {{ $customer->created_at?->format('M Y') }}
        </div>
        <div style="margin-top:8px;">
            <a href="{{ route('pos.customers.index', ['q' => $customer->phone ?: $customer->name]) }}" class="pcat-link">
                <i class="fa fa-pen"></i> Edit customer details
            </a>
        </div>
    </div>

    <div class="c360-stats">
        <div class="c360-stat"><b>{{ $s['open_leads'] }} / {{ $s['leads'] }}</b><span>Open leads</span></div>
        <div class="c360-stat"><b>{{ $s['open_quotations'] }} / {{ $s['quotations'] }}</b><span>Open quotations</span></div>
        <div class="c360-stat"><b>{{ $s['open_tasks'] }}</b><span>Open follow-ups @if($s['overdue_tasks'])· <span style="color:#ef4444;">{{ $s['overdue_tasks'] }} overdue</span>@endif</span></div>
        <div class="c360-stat"><b>{{ $s['orders'] }}</b><span>Invoices &amp; orders</span></div>
        <div class="c360-stat"><b>{{ number_format($s['total_sales'], 2) }}</b><span>Total sales</span></div>
    </div>

    @if($customer->notes)
        <div class="pcat-inline" style="margin-bottom:16px;">
            <h2 style="font-size:13px;">Notes</h2>
            <p class="pcat-muted" style="white-space:pre-line;">{{ $customer->notes }}</p>
        </div>
    @endif

    <div class="c360-grid">
        <section class="c360-sec">
            <h2><i class="fa fa-bullseye"></i> Leads <small>{{ count($profile['leads']) }}</small></h2>
            @if($profile['leads'] === [])
                <p class="c360-empty">No leads are linked to this customer.</p>
            @else
                <div class="c360-wrap"><table class="c360-table">
                    <thead><tr><th>Lead</th><th>Relation</th><th>Status</th><th>Value</th><th>Created</th></tr></thead>
                    <tbody>
                    @foreach($profile['leads'] as $l)
                        <tr>
                            <td><a href="{{ route('crm.leads.show', $l['id']) }}" class="pcat-link">{{ $l['name'] }}</a>@if($l['company'])<div class="muted">{{ $l['company'] }}</div>@endif</td>
                            <td>{{ $l['relation'] ?? '—' }}</td>
                            <td><span class="c360-pill {{ $l['is_open'] ? 'c360-pill--open' : '' }}">{{ $l['status_label'] ?? '—' }}</span></td>
                            <td>{{ $l['estimated_value'] !== null ? number_format($l['estimated_value'], 2) : '—' }}</td>
                            <td>{{ $fmtDate($l['created_at']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="c360-sec">
            <h2><i class="fa fa-file-invoice"></i> Quotations <small>{{ count($profile['quotations']) }} · every version</small></h2>
            @if($profile['quotations'] === [])
                <p class="c360-empty">No quotations yet.</p>
            @else
                <div class="c360-wrap"><table class="c360-table">
                    <thead><tr><th>Number</th><th>Date</th><th>Status</th><th>Items</th><th style="text-align:right;">Total</th></tr></thead>
                    <tbody>
                    @foreach($profile['quotations'] as $q)
                        <tr>
                            <td>
                                @if(Route::has('sales.quotations.show'))<a href="{{ route('sales.quotations.show', $q['id']) }}" class="pcat-link">{{ $q['number'] }}</a>@else{{ $q['number'] }}@endif
                                @if($q['reference'])<div class="muted">{{ $q['reference'] }}</div>@endif
                            </td>
                            <td>{{ $fmtDate($q['date']) }}</td>
                            <td><span class="c360-pill">{{ $q['status_label'] }}</span></td>
                            <td>{{ $q['items_count'] }}</td>
                            <td style="text-align:right;">{{ number_format($q['total'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="c360-sec">
            <h2><i class="fa fa-receipt"></i> Invoices &amp; orders <small>{{ count($profile['orders']) }}</small></h2>
            @if($profile['orders'] === [])
                <p class="c360-empty">No invoices, orders or POS sales yet.</p>
            @else
                <div class="c360-wrap"><table class="c360-table">
                    <thead><tr><th>Type</th><th>Number</th><th>Date</th><th>Status</th><th style="text-align:right;">Total</th></tr></thead>
                    <tbody>
                    @foreach($profile['orders'] as $o)
                        <tr>
                            <td>{{ $o['type_label'] }}</td>
                            <td>
                                @if(Route::has($orderRoutes[$o['type']]))<a href="{{ route($orderRoutes[$o['type']], $o['id']) }}" class="pcat-link">{{ $o['number'] }}</a>@else{{ $o['number'] }}@endif
                            </td>
                            <td>{{ $fmtDate($o['date']) }}</td>
                            <td><span class="c360-pill">{{ $o['status_label'] }}</span></td>
                            <td style="text-align:right;">{{ number_format($o['total'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="c360-sec">
            <h2><i class="fa fa-paperclip"></i> Documents <small>{{ count($profile['documents']) }}</small></h2>
            @if($profile['documents'] === [])
                <p class="c360-empty">No files on this customer's leads.</p>
            @else
                <div class="c360-wrap"><table class="c360-table">
                    <thead><tr><th>File</th><th>Lead</th><th>Size</th><th>Uploaded</th></tr></thead>
                    <tbody>
                    @foreach($profile['documents'] as $d)
                        <tr>
                            <td><a href="{{ route('crm.leads.attachments.download', [$d['lead']['id'], $d['id']]) }}" class="pcat-link"><i class="fa {{ $d['is_image'] ? 'fa-image' : 'fa-file' }}"></i> {{ $d['name'] }}</a></td>
                            <td><a href="{{ route('crm.leads.show', $d['lead']['id']) }}" class="pcat-link">{{ $d['lead']['name'] }}</a></td>
                            <td>{{ $d['size'] ?? '—' }}</td>
                            <td>{{ $d['uploaded_by']['name'] ?? '—' }}, {{ $fmtDate($d['created_at']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="c360-sec">
            <h2><i class="fa fa-list-check"></i> Follow-ups &amp; tasks <small>{{ count($profile['tasks']) }}</small></h2>
            @include('crm::partials.task-form', ['subjectType' => 'customer', 'subjectId' => $customer->id])
            @php $leadTasks = array_values(array_filter($profile['tasks'], fn ($t) => $t['lead'] !== null)); @endphp
            <div style="margin-top:12px;">
                @include('crm::partials.task-list', ['tasks' => $customer->tasks])
            </div>
            @if($leadTasks !== [])
                <h3 style="font-size:12px;font-weight:800;margin:12px 0 6px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">On the customer's leads</h3>
                <div class="c360-wrap"><table class="c360-table">
                    <thead><tr><th>Task</th><th>Lead</th><th>Due</th><th>Assigned</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($leadTasks as $t)
                        <tr>
                            <td>{{ $t['title'] }}</td>
                            <td><a href="{{ route('crm.leads.show', $t['lead']['id']) }}" class="pcat-link">{{ $t['lead']['name'] }}</a></td>
                            <td>{{ $fmtDate($t['due_at']) }}</td>
                            <td>{{ $t['assigned_to']['name'] ?? '—' }}</td>
                            <td>
                                @if($t['status'] === 'completed')<span class="c360-pill">Done</span>
                                @elseif($t['is_overdue'])<span class="c360-pill c360-pill--overdue">Overdue</span>
                                @else<span class="c360-pill c360-pill--open">Open</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>

        <section class="c360-sec">
            <h2><i class="fa fa-clock-rotate-left"></i> Timeline <small>{{ count($profile['timeline']) }}</small></h2>
            @include('crm::partials.activity-form', ['subjectType' => 'customer', 'subjectId' => $customer->id])
            @if($profile['timeline'] === [])
                <p class="c360-empty">Nothing has happened with this customer yet.</p>
            @else
                <ul class="c360-tl" style="margin-top:12px;">
                    @foreach($profile['timeline'] as $e)
                        <li>
                            <span class="c360-tl-icon" style="background:{{ $e['color'] }};"><i class="fa {{ $e['icon'] }}"></i></span>
                            <div style="min-width:0;flex:1;">
                                <div>
                                    <strong>{{ $e['user']['name'] ?? 'System' }}</strong> · {{ $e['title'] }}
                                    @if($e['lead'])<span class="muted"> · lead <a href="{{ route('crm.leads.show', $e['lead']['id']) }}" class="pcat-link">{{ $e['lead']['name'] }}</a></span>@endif
                                </div>
                                @if($e['body'])<div class="c360-tl-body">{{ \Illuminate\Support\Str::limit($e['body'], 400) }}</div>@endif
                                <div class="muted" style="font-size:11px;margin-top:2px;">{{ $e['at'] ? \Illuminate\Support\Carbon::parse($e['at'])->format('d M Y, H:i') : '' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.contacts.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All contacts
    </a>
</div>
@endsection
