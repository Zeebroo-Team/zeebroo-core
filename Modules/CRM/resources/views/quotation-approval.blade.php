{{-- The page an approval email's link opens (QuotationApprovalLinkController): the brief (QuotationApprovalBrief) and the head's decision. --}}
@php
    $submitted = $submission?->created_at;
    $expires   = $q->expiry_date;
    $daysLeft  = $expires ? (int) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null;
    $hasTax    = (float) $q->tax_amount > 0 || $q->items->contains(fn ($i) => (float) $i->tax_rate > 0);
    $hasLineDisc = (float) $q->items->sum('discount_amount') > 0;
    $address   = collect([$branch?->address, $branch?->phone, $branch?->email])->filter()->implode(' · ');
    $myRole    = collect($chain['heads'])->firstWhere('you', true)['role'] ?? 'Approver';
    [$statusLabel, $statusClass] = match (true) {
        $done === 'approve'            => ['Approved', 'ok'],
        $done === 'reject'             => ['Rejected', 'bad'],
        $q->status === 'pending_approval' => ['Awaiting decision', 'wait'],
        $q->status === 'approved', $q->status === 'sent' => [ucfirst($q->status), 'ok'],
        $q->status === 'rejected'      => ['Rejected', 'bad'],
        $q->status === 'returned'      => ['Returned for changes', 'warn'],
        default                        => [ucfirst(str_replace('_', ' ', (string) $q->status)), 'muted'],
    };
    $n = 0;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Approve {{ $q->quote_number }} · {{ $business?->name ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <style>
        :root{--ink:#0f172a;--text:#1e293b;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc;--bg:#eef1f5;--accent:#1e3a8a;--accent-soft:#eff6ff;
              --ok:#047857;--ok-bg:#ecfdf5;--bad:#b91c1c;--bad-bg:#fef2f2;--warn:#b45309;--warn-bg:#fffbeb;--wait:#6d28d9;--wait-bg:#f5f3ff}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);font-family:Inter,'Segoe UI',system-ui,sans-serif;color:var(--text);font-size:14px;line-height:1.55;-webkit-font-smoothing:antialiased}
        a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}

        .topbar{background:#fff;border-bottom:1px solid var(--line);border-top:4px solid var(--accent)}
        .topbar-in{max-width:1180px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;gap:16px;justify-content:space-between;flex-wrap:wrap}
        .brand{display:flex;align-items:center;gap:14px;min-width:0}
        .brand img{max-height:40px;max-width:130px;display:block}
        .brand-name{font-size:16px;font-weight:700;color:var(--ink)}
        .brand-addr{font-size:12px;color:var(--muted)}
        .secure{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:6px 12px}
        .secure i{color:var(--ok)}

        .wrap{max-width:1180px;margin:0 auto;padding:28px 24px 48px}
        .head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}
        .eyebrow{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
        h1{margin:4px 0 2px;font-size:26px;font-weight:700;color:var(--ink);letter-spacing:-.01em}
        h1 small{font-size:15px;font-weight:600;color:var(--muted)}
        .head-sub{color:var(--muted);font-size:14px}
        .head-sub strong{color:var(--ink)}
        .badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;border-radius:999px;padding:5px 12px;text-transform:uppercase;letter-spacing:.04em}
        .badge.ok{color:var(--ok);background:var(--ok-bg)}.badge.bad{color:var(--bad);background:var(--bad-bg)}
        .badge.warn{color:var(--warn);background:var(--warn-bg)}.badge.wait{color:var(--wait);background:var(--wait-bg)}.badge.muted{color:var(--muted);background:var(--soft)}

        html,body{max-width:100%;overflow-x:hidden}
        .grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:22px;align-items:start}
        .grid > *{min-width:0}
        .card{background:#fff;border:1px solid var(--line);border-radius:10px;margin-bottom:18px;overflow:hidden}
        .card-h{display:flex;align-items:center;gap:10px;padding:14px 20px;border-bottom:1px solid var(--line);font-size:12.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink)}
        .num{width:22px;height:22px;border-radius:6px;background:var(--accent);color:#fff;font-size:11px;display:inline-flex;align-items:center;justify-content:center}
        .card-b{padding:18px 20px}

        .kpis{display:grid;grid-template-columns:repeat(4,1fr)}
        .kpi{padding:16px 20px;border-left:1px solid var(--line)}.kpi:first-child{border-left:0}
        .kpi-l{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .kpi-v{font-size:17px;font-weight:700;color:var(--ink);margin-top:3px}
        .kpi-v.red{color:var(--bad)}.kpi-s{font-size:12px;color:var(--muted)}

        .callout{display:flex;gap:12px;padding:13px 16px;border-radius:8px;margin-bottom:18px;font-size:13.5px;border-left:4px solid}
        .callout i{margin-top:3px}
        .callout.bad{background:var(--bad-bg);border-color:#dc2626;color:#7f1d1d}
        .callout.note{background:#fff;border:1px solid var(--line);border-left:4px solid var(--accent)}
        .callout .lbl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:2px}

        .person{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;justify-content:space-between}
        .avatar{width:44px;height:44px;border-radius:50%;background:var(--accent-soft);color:var(--accent);font-weight:700;display:flex;align-items:center;justify-content:center;flex:none}
        .p-name{font-size:15px;font-weight:700;color:var(--ink)}
        .p-meta{font-size:12.5px;color:var(--muted)}
        .p-contact{font-size:13px;line-height:1.8}
        .p-contact i{width:16px;color:var(--muted)}

        .org{display:flex;gap:28px;margin-bottom:14px;flex-wrap:wrap}
        .org div span{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .org div strong{font-size:14px;color:var(--ink)}

        table{width:100%;border-collapse:collapse}
        .tbl{border:1px solid var(--line);border-radius:8px;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .tbl a,.p-contact a,.facts strong,.brand-addr,.head-sub{overflow-wrap:anywhere}
        .tbl th{background:var(--soft);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);text-align:left;padding:10px 12px;border-bottom:1px solid var(--line)}
        .tbl td{padding:11px 12px;border-bottom:1px solid var(--line);vertical-align:top}
        .tbl tr:last-child td{border-bottom:0}
        .tbl tr.you td{background:var(--accent-soft)}
        .r{text-align:right!important;white-space:nowrap}
        .sub{font-size:12px;color:var(--muted)}
        .pill{display:inline-block;font-size:10.5px;font-weight:700;border-radius:4px;padding:2px 7px;letter-spacing:.03em}
        .pill.you{background:var(--accent);color:#fff}
        .pill.sent{background:var(--ok-bg);color:var(--ok)}

        .dl-group{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--accent);margin:16px 0 6px}
        .dl-group:first-child{margin-top:0}
        .dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:24px;border-top:1px solid var(--line)}
        .dl.wide{grid-template-columns:1fr}
        .dl > div{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13.5px}
        .dl dt{flex:0 0 120px;color:var(--muted);font-size:12.5px}
        .dl dd{margin:0;color:var(--ink);min-width:0;overflow-wrap:anywhere}
        .dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}

        .meta-row{display:flex;gap:28px;flex-wrap:wrap;margin-bottom:16px}
        .meta-row span{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .meta-row strong{font-size:13.5px;color:var(--ink)}
        .totals{margin:14px 0 0 auto;max-width:320px}
        .totals div{display:flex;justify-content:space-between;padding:5px 2px;font-size:13.5px;color:var(--muted)}
        .totals .grand{margin-top:6px;padding:11px 14px;background:var(--accent);color:#fff;border-radius:6px;font-weight:700;font-size:16px}
        .limit{font-size:12px;text-align:right;margin-top:6px}
        .terms{margin-top:18px;border:1px solid var(--line);border-radius:8px;padding:12px 14px;font-size:13px;color:#334155;background:var(--soft)}

        .timeline{list-style:none;margin:0;padding:0}
        .timeline li{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9}
        .timeline li:last-child{border-bottom:0}
        .timeline .when{flex:0 0 150px;font-size:12px;color:var(--muted)}

        .side{position:sticky;top:20px}
        .decide .total{padding:20px;border-bottom:1px solid var(--line);background:var(--soft)}
        .decide .total .v{font-size:28px;font-weight:700;color:var(--ink);letter-spacing:-.01em}
        .facts{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
        .facts span{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700}
        .facts strong{font-size:13px;color:var(--ink)}
        form{padding:20px}
        label{display:block;font-size:13px;font-weight:600;color:var(--ink);margin-bottom:6px}
        label small{font-weight:400;color:var(--muted)}
        textarea{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px 12px;font:inherit;font-size:14px;resize:vertical;min-height:110px;color:var(--ink)}
        textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(30,58,138,.12)}
        textarea.invalid{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
        .err{color:var(--bad);font-size:12.5px;margin-top:6px}
        .btns{display:grid;gap:10px;margin-top:14px}
        .btn{display:flex;align-items:center;justify-content:center;gap:8px;border-radius:8px;padding:12px 16px;font:inherit;font-size:14.5px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:background .15s,border-color .15s}
        .btn-approve{background:#047857;color:#fff}.btn-approve:hover{background:#065f46}
        .btn-reject{background:#fff;color:var(--bad);border-color:#fca5a5}.btn-reject:hover{background:var(--bad-bg);border-color:#f87171}
        .btn:disabled{opacity:.6;cursor:wait}
        .as{font-size:12px;color:var(--muted);margin-top:12px;line-height:1.5}
        .state{padding:28px 20px;text-align:center}
        .state i{font-size:38px;margin-bottom:10px}
        .state h3{margin:0 0 6px;font-size:17px;color:var(--ink)}
        .state p{margin:0;color:var(--muted);font-size:13.5px}
        .state.ok i{color:var(--ok)}.state.bad i{color:var(--bad)}.state.info i{color:var(--muted)}

        .foot{max-width:1180px;margin:0 auto;padding:0 24px 32px;font-size:12px;color:var(--muted)}

        /* Phone: a bar pinned to the bottom jumps to the decision, which follows the details */
        .mbar{display:none}
        @media (max-width:960px){
            .grid{grid-template-columns:minmax(0,1fr)}
            .side{position:static}
        }
        @media (max-width:640px){
            body{font-size:13.5px}
            .wrap{padding:16px 12px 96px}
            .topbar-in{padding:10px 12px;gap:8px}
            .brand img{max-height:32px;max-width:90px}
            .brand-name{font-size:15px}
            .secure{font-size:11px;padding:4px 10px;max-width:100%}
            .head{margin-bottom:14px;align-items:flex-start}
            h1{font-size:21px}
            .head-sub{font-size:13px}
            .card{margin-bottom:12px;border-radius:8px}
            .card-h{padding:12px 14px}
            .card-b{padding:14px}
            .kpis{grid-template-columns:1fr 1fr}
            .kpi{padding:12px 14px}
            .kpi:nth-child(3){border-left:0}
            .kpi:nth-child(n+3){border-top:1px solid var(--line)}
            .kpi-v{font-size:15px}
            .callout{padding:11px 12px;margin-bottom:12px}
            .person{flex-direction:column;gap:10px}
            .org{gap:18px}
            .dl{grid-template-columns:minmax(0,1fr)}
            .dl > div{flex-direction:column;gap:1px;padding:7px 0}
            .dl dt{flex:none;font-size:11.5px}
            .meta-row{gap:12px 18px}
            .tbl th,.tbl td{padding:9px 10px}
            .tbl table.items{min-width:480px}
            .totals{max-width:none}
            .timeline li{flex-direction:column;gap:2px}
            .timeline .when{flex:none}
            .decide .total{padding:16px}
            .decide .total .v{font-size:24px}
            form{padding:16px}
            .btn{padding:14px 16px;font-size:15px}
            .foot{padding:0 12px 96px;text-align:center}
            .mbar{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:20;align-items:center;justify-content:space-between;gap:12px;
                  padding:10px 14px calc(10px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid var(--line);box-shadow:0 -6px 20px rgba(15,23,42,.08)}
            .mbar .l{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700}
            .mbar .v{font-size:17px;font-weight:700;color:var(--ink)}
            .mbar a{background:var(--accent);color:#fff;border-radius:8px;padding:11px 18px;font-weight:600;font-size:14px;text-decoration:none;white-space:nowrap}
        }
    </style>
</head>
<body>

<header class="topbar"><div class="topbar-in">
    <div class="brand">
        @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $business?->name }}">@endif
        <div>
            <div class="brand-name">{{ $business?->name }}</div>
            @if($address)<div class="brand-addr">{{ $address }}</div>@endif
        </div>
    </div>
    <div class="secure"><i class="fa fa-lock"></i> Secure approval link · {{ $approver->name }}</div>
</div></header>

<main class="wrap">
    <div class="head">
        <div>
            <div class="eyebrow">Quotation approval</div>
            <h1>{{ $q->quote_number }}@if($q->version > 1) <small>v{{ (int) $q->version }}</small>@endif</h1>
            <div class="head-sub">For <strong>{{ $leadName }}</strong>@if($preparedBy) · prepared by <strong>{{ $preparedBy['name'] }}</strong>@endif @if($submitted) · {{ $submitted->format('d M Y, g:i A') }}@endif</div>
        </div>
        <span class="badge {{ $statusClass }}"><i class="fa fa-circle" style="font-size:7px"></i> {{ $statusLabel }}</span>
    </div>

    <div class="grid">
        <div>
            <div class="card"><div class="kpis">
                <div class="kpi"><div class="kpi-l">Total</div><div class="kpi-v">{{ $money($q->total) }}</div><div class="kpi-s">{{ $q->items->count() }} line{{ $q->items->count() === 1 ? '' : 's' }}</div></div>
                <div class="kpi"><div class="kpi-l">Discount</div>
                    <div class="kpi-v {{ $discount['over_limit'] ? 'red' : '' }}">{{ (float) $q->discount_amount > 0 ? $num($discount['percent']) . '%' : 'None' }}</div>
                    <div class="kpi-s">@if($discount['limit'] !== null)Limit {{ $num($discount['limit']) }}%@else No limit set @endif</div></div>
                <div class="kpi"><div class="kpi-l">Valid until</div>
                    <div class="kpi-v {{ $daysLeft !== null && $daysLeft < 0 ? 'red' : '' }}">{{ $expires?->format('d M Y') ?? '—' }}</div>
                    <div class="kpi-s">@if($daysLeft !== null){{ $daysLeft >= 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : 'Expired' }}@endif</div></div>
                <div class="kpi"><div class="kpi-l">Department</div><div class="kpi-v" style="font-size:15px">{{ $chain['department'] ?? '—' }}</div><div class="kpi-s">{{ $chain['division'] ?? '' }}</div></div>
            </div></div>

            @if($discount['over_limit'])
                <div class="callout bad"><i class="fa fa-triangle-exclamation"></i>
                    <div><strong>Discount exceeds the department limit.</strong> This quotation gives {{ $num($discount['percent']) }}% ({{ $money($q->discount_amount) }}) off; the limit is {{ $num($discount['limit']) }}%.</div></div>
            @endif

            @if($submission?->comment)
                <div class="callout note"><i class="fa fa-quote-left" style="color:var(--accent)"></i>
                    <div><div class="lbl">Note from {{ $preparedBy['name'] ?? 'the submitter' }}</div>{!! nl2br(e($submission->comment)) !!}</div></div>
            @endif

            {{-- Prepared by --}}
            <section class="card">
                <div class="card-h"><span class="num">{{ ++$n }}</span> Prepared by</div>
                <div class="card-b">
                    @if($preparedBy)
                        <div class="person">
                            <div style="display:flex;gap:14px">
                                <div class="avatar">{{ mb_strtoupper(mb_substr($preparedBy['name'], 0, 1)) }}</div>
                                <div>
                                    <div class="p-name">{{ $preparedBy['name'] }}</div>
                                    <div class="p-meta">{{ collect([$preparedBy['title'], $preparedBy['employee_no'] ? 'Emp. No. ' . $preparedBy['employee_no'] : null])->filter()->implode(' · ') ?: 'Staff member' }}</div>
                                    <div class="p-meta">{{ collect([$chain['department'], $chain['division']])->filter()->implode(' › ') }}</div>
                                </div>
                            </div>
                            <div class="p-contact">
                                @if($preparedBy['email'])<div><i class="fa fa-envelope"></i> <a href="mailto:{{ $preparedBy['email'] }}">{{ $preparedBy['email'] }}</a></div>@endif
                                @if($preparedBy['phone'])<div><i class="fa fa-phone"></i> <a href="tel:{{ $preparedBy['phone'] }}">{{ $preparedBy['phone'] }}</a></div>@endif
                                @if($submitted)<div><i class="fa fa-clock"></i> Submitted {{ $submitted->format('d M Y, g:i A') }}</div>@endif
                            </div>
                        </div>
                    @else
                        <span class="sub">Not recorded.</span>
                    @endif
                </div>
            </section>

            {{-- Department & approvers --}}
            <section class="card">
                <div class="card-h"><span class="num">{{ ++$n }}</span> Department &amp; approvers</div>
                <div class="card-b">
                    <div class="org">
                        <div><span>Department</span><strong>{{ $chain['department'] ?? 'Not set' }}</strong></div>
                        <div><span>Division</span><strong>{{ $chain['division'] ?? '—' }}</strong></div>
                    </div>
                    <div class="tbl tbl-scroll"><table>
                        <thead><tr><th>Role</th><th>Name</th><th>Contact</th></tr></thead>
                        <tbody>
                        @forelse($chain['heads'] as $h)
                            @php $p = $h['person']; @endphp
                            <tr class="{{ $h['you'] ? 'you' : '' }}">
                                <td class="sub" style="white-space:nowrap">{{ $h['role'] }}</td>
                                <td>
                                    @if($p)<strong>{{ $p['name'] }}</strong> @if($h['you'])<span class="pill you">YOU</span>@endif
                                        @if($p['title'])<div class="sub">{{ $p['title'] }}</div>@endif
                                    @else<span class="sub">Not assigned</span>@endif
                                </td>
                                <td style="font-size:13px">
                                    @if($p['email'] ?? null)<a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>@endif
                                    @if($p['phone'] ?? null)<div class="sub">{{ $p['phone'] }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="sub">No department heads on file.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    <div class="sub" style="margin-top:8px">Approval by any one head who received the request is final.</div>
                </div>
            </section>

            {{-- Lead --}}
            <section class="card">
                <div class="card-h"><span class="num">{{ ++$n }}</span> Lead details</div>
                <div class="card-b">
                    @foreach($leadInfo as $group => $fields)
                        <div class="dl-group">{{ $group }}</div>
                        <dl class="dl {{ $group === 'Interest' ? 'wide' : '' }}" style="margin:0">
                            @foreach($fields as $k => $v)
                                <div><dt>{{ $k }}</dt><dd>
                                    @if($k === 'Email')<a href="mailto:{{ $v }}">{{ $v }}</a>
                                    @elseif($k === 'Phone')<a href="tel:{{ $v }}">{{ $v }}</a>
                                    @elseif($k === 'Stage')<span class="dot" style="background:{{ $lead->stageColor() }}"></span>{{ $v }}
                                    @else{!! nl2br(e($v)) !!}@endif
                                </dd></div>
                            @endforeach
                        </dl>
                    @endforeach
                </div>
            </section>

            {{-- Quotation --}}
            <section class="card">
                <div class="card-h"><span class="num">{{ ++$n }}</span> Quotation</div>
                <div class="card-b">
                    <div class="meta-row">
                        @foreach(array_filter([
                            'Number'      => $q->quote_number . ($q->version > 1 ? ' (v' . (int) $q->version . ')' : ''),
                            'Quote date'  => $q->quote_date?->format('d M Y'),
                            'Valid until' => $expires?->format('d M Y'),
                            'Reference'   => $q->reference,
                            'Revises'     => $q->parent ? $q->parent->quote_number . ' v' . (int) $q->parent->version . ' · ' . $money($q->parent->total) : null,
                        ]) as $k => $v)
                            <div><span>{{ $k }}</span><strong>{{ $v }}</strong></div>
                        @endforeach
                    </div>

                    <div class="tbl tbl-scroll"><table class="items">
                        <thead><tr>
                            <th style="width:32px">#</th><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th>
                            @if($hasLineDisc)<th class="r">Disc.</th>@endif
                            @if($hasTax)<th class="r">Tax</th>@endif
                            <th class="r">Amount</th>
                        </tr></thead>
                        <tbody>
                        @foreach($q->items as $i)
                            <tr>
                                <td class="sub">{{ $loop->iteration }}</td>
                                <td>{{ $i->description ?: $i->product?->name ?: $i->serviceItem?->name ?: '—' }}
                                    @if($i->product?->sku)<div class="sub">SKU {{ $i->product->sku }}</div>@endif</td>
                                <td class="r">{{ $num($i->quantity, 3) }}</td>
                                <td class="r">{{ $money($i->unit_price) }}</td>
                                @if($hasLineDisc)<td class="r" style="color:var(--bad)">{{ (float) $i->discount_amount > 0 ? '−' . $money($i->discount_amount) : '—' }}</td>@endif
                                @if($hasTax)<td class="r">{{ (float) $i->tax_rate > 0 ? $num($i->tax_rate) . '%' : '—' }}</td>@endif
                                <td class="r" style="font-weight:600;color:var(--ink)">{{ $money($i->line_total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>

                    <div class="totals">
                        <div><span>Subtotal</span><span>{{ $money($q->subtotal) }}</span></div>
                        @if((float) $q->discount_amount > 0)<div><span>Discount ({{ $num($discount['percent']) }}%)</span><span style="color:var(--bad)">−{{ $money($q->discount_amount) }}</span></div>@endif
                        @if((float) $q->tax_amount > 0)<div><span>Tax</span><span>+{{ $money($q->tax_amount) }}</span></div>@endif
                        <div class="grand"><span>Total</span><span>{{ $money($q->total) }}</span></div>
                        @if($discount['limit'] !== null)
                            <div class="limit" style="display:block;color:{{ $discount['over_limit'] ? 'var(--bad)' : 'var(--ok)' }}">
                                <i class="fa {{ $discount['over_limit'] ? 'fa-triangle-exclamation' : 'fa-circle-check' }}"></i>
                                {{ $discount['over_limit'] ? 'Over' : 'Within' }} the {{ $num($discount['limit']) }}% discount limit
                            </div>
                        @endif
                    </div>

                    @if($q->notes)
                        <div class="terms"><div class="dl-group" style="margin:0 0 4px">Notes &amp; terms</div>{!! nl2br(e($q->notes)) !!}</div>
                    @endif
                </div>
            </section>

            @if($history !== [])
                <section class="card">
                    <div class="card-h"><span class="num">{{ ++$n }}</span> Approval history</div>
                    <div class="card-b" style="padding-top:6px;padding-bottom:6px">
                        <ul class="timeline">
                            @foreach($history as $h)
                                <li>
                                    <div class="when">{{ $h['at']?->format('d M Y, g:i A') }}</div>
                                    <div>
                                        <span class="dot" style="background:{{ $h['color'] }}"></span><strong>{{ $h['label'] }}</strong>
                                        <span class="sub"> · v{{ $h['version'] }}@if($h['by']) · {{ $h['by'] }}@endif</span>
                                        @if($h['comment'])<div style="color:#334155;font-style:italic;margin-top:2px">“{{ $h['comment'] }}”</div>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        {{-- Decision --}}
        <aside class="side" id="decide">
            <div class="card decide">
                <div class="total">
                    <div class="eyebrow">Quotation total</div>
                    <div class="v">{{ $money($q->total) }}</div>
                    <div class="facts">
                        <div><span>Customer</span><strong>{{ $lead->name }}</strong></div>
                        <div><span>Discount</span><strong style="{{ $discount['over_limit'] ? 'color:var(--bad)' : '' }}">{{ (float) $q->discount_amount > 0 ? $num($discount['percent']) . '%' : 'None' }}</strong></div>
                    </div>
                </div>

                @if($done)
                    <div class="state {{ $done === 'approve' ? 'ok' : 'bad' }}">
                        <i class="fa {{ $done === 'approve' ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                        <h3>Quotation {{ $done === 'approve' ? 'approved' : 'rejected' }}</h3>
                        <p>Thank you, {{ $approver->name }}. {{ $preparedBy['name'] ?? 'The team' }} has been notified{{ $done === 'approve' ? ' and can now send it to the customer' : '' }}.</p>
                    </div>
                @elseif($blocker)
                    <div class="state info">
                        <i class="fa fa-circle-info"></i>
                        <h3>No action needed</h3>
                        <p>{{ $blocker }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ $actionUrl }}" id="decide-form" novalidate>
                        @csrf
                        <label for="comment">Comment <small>— optional to approve, required to reject</small></label>
                        <textarea id="comment" name="comment" maxlength="2000" placeholder="Add a comment for {{ $preparedBy['name'] ?? 'the team' }}…" class="{{ $errors->has('comment') ? 'invalid' : '' }}">{{ old('comment') }}</textarea>
                        <div class="err" id="comment-err" @unless($errors->any()) hidden @endunless>{{ $errors->first() }}</div>
                        <div class="btns">
                            <button type="submit" name="decision" value="approve" class="btn btn-approve"><i class="fa fa-check"></i> Approve quotation</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-reject"><i class="fa fa-xmark"></i> Reject</button>
                        </div>
                        <div class="as"><i class="fa fa-user-shield"></i> Deciding as <strong>{{ $approver->name }}</strong> ({{ $myRole }}). The first head to decide settles it.</div>
                    </form>
                @endif
            </div>
        </aside>
    </div>
</main>

@if(! $done && ! $blocker)
    <div class="mbar">
        <div><div class="l">Total</div><div class="v">{{ $money($q->total) }}</div></div>
        <a href="#decide">Approve or reject <i class="fa fa-arrow-down"></i></a>
    </div>
@endif

<div class="foot">
    <i class="fa fa-lock"></i> Confidential — internal pricing for {{ $business?->name ?? 'the company' }}. Please do not share this page or its link.
</div>

<script>
// The phone's bottom bar steps aside once the decision panel is on screen
(function () {
    var bar = document.querySelector('.mbar'), panel = document.getElementById('decide');
    if (!bar || !panel || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (e) { bar.style.display = e[0].isIntersecting ? 'none' : ''; }).observe(panel);
})();
(function () {
    var form = document.getElementById('decide-form');
    if (!form) return;
    var comment = document.getElementById('comment'), err = document.getElementById('comment-err');
    form.addEventListener('submit', function (e) {
        var decision = e.submitter ? e.submitter.value : null;
        if (decision === 'reject' && !comment.value.trim()) {
            e.preventDefault();
            err.textContent = 'Say why the quotation is rejected.';
            err.hidden = false;
            comment.classList.add('invalid');
            comment.focus();
            return;
        }
        if (decision === 'reject' && !confirm('Reject this quotation?')) { e.preventDefault(); return; }
        // Keep the clicked button's value once the buttons are disabled
        if (!decision) return;
        var keep = document.createElement('input');
        keep.type = 'hidden'; keep.name = 'decision'; keep.value = decision;
        form.appendChild(keep);
        form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    });
    comment.addEventListener('input', function () { comment.classList.remove('invalid'); err.hidden = true; });
})();
</script>
</body>
</html>
