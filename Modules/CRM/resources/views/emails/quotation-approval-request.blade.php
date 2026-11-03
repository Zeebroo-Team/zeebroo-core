{{-- The quotation approval request to a head (QuotationApprovalRequestMail). Inline styles and tables only — mail clients drop <style>. --}}
@php
    $ink     = '#0f172a';
    $muted   = '#64748b';
    $line    = '#e2e8f0';
    $accent  = '#1e3a8a';
    $font    = "font-family:'Segoe UI',Helvetica,Arial,sans-serif;";
    $th      = 'padding:9px 10px;border-bottom:1px solid ' . $line . ';font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:' . $muted . ';font-weight:700;background:#f8fafc;';
    $td      = 'padding:10px;border-bottom:1px solid ' . $line . ';vertical-align:top;font-size:13px;';
    $kLabel  = 'padding:6px 12px 6px 0;color:' . $muted . ';font-size:12px;width:34%;vertical-align:top;';
    $kValue  = 'padding:6px 0;font-size:13px;color:' . $ink . ';vertical-align:top;';
    $section = fn (string $n, string $title) => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;"><tr>'
        . '<td style="width:28px;vertical-align:middle;"><div style="width:22px;height:22px;line-height:22px;border-radius:6px;background:' . $accent . ';color:#fff;font-size:11px;font-weight:700;text-align:center;">' . $n . '</div></td>'
        . '<td style="vertical-align:middle;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:' . $ink . ';">' . e($title) . '</td>'
        . '<td style="vertical-align:middle;padding-left:12px;"><div style="height:1px;background:' . $line . ';"></div></td></tr></table>';

    $submitter = $q->submittedBy;
    $submitted = $submission->created_at;
    $expires   = $q->expiry_date;
    $daysLeft  = $expires ? (int) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null;
    $hasTax    = (float) $q->tax_amount > 0 || $q->items->contains(fn ($i) => (float) $i->tax_rate > 0);
    $hasLineDisc = (float) $q->items->sum('discount_amount') > 0;
    $address   = collect([$branch?->address, $branch?->phone, $branch?->email])->filter()->implode('  ·  ');
    $n = 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Approval required: {{ $q->quote_number }}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f5;{{ $font }}color:{{ $ink }};">
<div style="display:none;max-height:0;overflow:hidden;">{{ $preparedBy['name'] ?? 'A team member' }} requests your approval for quotation {{ $q->quote_number }} — {{ $money($q->total) }} for {{ $leadName }}.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;background:#ffffff;border:1px solid #dbe1ea;border-radius:6px;overflow:hidden;">

    {{-- Letterhead --}}
    <tr><td style="height:5px;background:{{ $accent }};font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td style="padding:22px 32px 18px;border-bottom:1px solid {{ $line }};">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            <td style="vertical-align:middle;">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                    @if($logoUrl)<td style="padding-right:14px;vertical-align:middle;"><img src="{{ $logoUrl }}" alt="{{ $business?->name }}" height="44" style="display:block;max-height:44px;max-width:140px;border:0;"></td>@endif
                    <td style="vertical-align:middle;">
                        <div style="font-size:18px;font-weight:700;color:{{ $ink }};">{{ $business?->name }}</div>
                        @if($address)<div style="font-size:11.5px;color:{{ $muted }};margin-top:2px;">{{ $address }}</div>@endif
                    </td>
                </tr></table>
            </td>
            <td style="vertical-align:middle;text-align:right;white-space:nowrap;">
                <div style="font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:{{ $muted }};font-weight:700;">Internal · Approval request</div>
                <div style="font-size:16px;font-weight:700;color:{{ $accent }};margin-top:3px;">{{ $q->quote_number }}@if($q->version > 1) <span style="font-weight:600;color:{{ $muted }};">v{{ (int) $q->version }}</span>@endif</div>
                <div style="font-size:11.5px;color:{{ $muted }};">{{ $submitted?->format('d M Y, g:i A') }}</div>
            </td>
        </tr></table>
    </td></tr>

    {{-- Summary strip --}}
    <tr><td style="padding:0;background:#f8fafc;border-bottom:1px solid {{ $line }};">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            @foreach([
                ['Quotation total', $money($q->total), $ink],
                ['Discount', (float) $q->discount_amount > 0 ? $num($discount['percent']) . '%' . ($discount['limit'] !== null ? ' / ' . $num($discount['limit']) . '% limit' : '') : 'None', $discount['over_limit'] ? '#b91c1c' : $ink],
                ['Valid until', $expires ? $expires->format('d M Y') : '—', $daysLeft !== null && $daysLeft < 0 ? '#b91c1c' : $ink],
                ['Status', 'Awaiting approval', '#7c3aed'],
            ] as [$k, $v, $c])
                <td width="25%" style="padding:14px 16px;{{ $loop->first ? 'padding-left:32px;' : 'border-left:1px solid ' . $line . ';' }}vertical-align:top;">
                    <div style="font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:{{ $muted }};font-weight:700;">{{ $k }}</div>
                    <div style="font-size:{{ $loop->first ? '18px' : '14px' }};font-weight:700;color:{{ $c }};margin-top:3px;">{{ $v }}</div>
                </td>
            @endforeach
        </tr></table>
    </td></tr>

    {{-- Request --}}
    <tr><td style="padding:26px 32px 4px;font-size:14px;line-height:1.65;">
        <p style="margin:0 0 12px;">Dear {{ $approver['name'] }},</p>
        <p style="margin:0 0 16px;">
            <strong>{{ $preparedBy['name'] ?? 'A team member' }}</strong>@if($preparedBy['title'] ?? null), {{ $preparedBy['title'] }},@endif
            has submitted quotation <strong>{{ $q->quote_number }}</strong>@if($q->version > 1) (version {{ (int) $q->version }})@endif
            for <strong>{{ $leadName }}</strong> totalling <strong>{{ $money($q->total) }}</strong>.
            As <strong>{{ $approver['role'] }}</strong>, your approval is required before it can be sent to the customer.
        </p>

        @if($discount['over_limit'])
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;"><tr>
                <td style="width:4px;background:#dc2626;"></td>
                <td style="background:#fef2f2;padding:11px 14px;font-size:13px;color:#7f1d1d;">
                    <strong>Discount exceeds the department limit.</strong>
                    This quotation gives {{ $num($discount['percent']) }}% ({{ $money($q->discount_amount) }}) off; the {{ $chain['department'] ?? 'department' }} limit is {{ $num($discount['limit']) }}%.
                </td>
            </tr></table>
        @endif

        @if($submission->comment)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;"><tr>
                <td style="width:4px;background:{{ $accent }};"></td>
                <td style="background:#f1f5f9;padding:11px 14px;">
                    <div style="font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:{{ $muted }};font-weight:700;margin-bottom:3px;">Note from {{ $preparedBy['name'] ?? 'the submitter' }}</div>
                    <div style="font-size:13.5px;font-style:italic;">“{!! nl2br(e($submission->comment)) !!}”</div>
                </td>
            </tr></table>
        @endif
    </td></tr>

    {{-- 1. Prepared by --}}
    <tr><td style="padding:16px 32px 6px;">
        {!! $section(++$n, 'Prepared by') !!}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $line }};border-radius:6px;"><tr>
            <td style="padding:14px 16px;vertical-align:top;width:50%;">
                <div style="font-size:15px;font-weight:700;">{{ $preparedBy['name'] ?? '—' }}</div>
                <div style="font-size:12.5px;color:{{ $muted }};margin-top:1px;">
                    {{ collect([$preparedBy['title'] ?? null, ($preparedBy['employee_no'] ?? null) ? 'Emp. No. ' . $preparedBy['employee_no'] : null])->filter()->implode(' · ') ?: 'Staff member' }}
                </div>
                <div style="font-size:12.5px;color:{{ $muted }};margin-top:1px;">{{ collect([$chain['department'], $chain['division']])->filter()->implode(' › ') }}</div>
            </td>
            <td style="padding:14px 16px;vertical-align:top;font-size:12.5px;line-height:1.7;">
                @if($preparedBy['email'] ?? null)<div><span style="color:{{ $muted }};">Email&nbsp;</span> <a href="mailto:{{ $preparedBy['email'] }}" style="color:{{ $accent }};text-decoration:none;">{{ $preparedBy['email'] }}</a></div>@endif
                @if($preparedBy['phone'] ?? null)<div><span style="color:{{ $muted }};">Phone&nbsp;</span> <a href="tel:{{ $preparedBy['phone'] }}" style="color:{{ $accent }};text-decoration:none;">{{ $preparedBy['phone'] }}</a></div>@endif
                <div><span style="color:{{ $muted }};">Submitted&nbsp;</span> {{ $submitted?->format('d M Y, g:i A') }}</div>
            </td>
        </tr></table>
    </td></tr>

    {{-- 2. Department & approvers --}}
    <tr><td style="padding:18px 32px 6px;">
        {!! $section(++$n, 'Department & approvers') !!}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;"><tr>
            <td style="{{ $kLabel }}width:18%;">Department</td><td style="{{ $kValue }}font-weight:600;">{{ $chain['department'] ?? 'Not set' }}</td>
            <td style="{{ $kLabel }}width:14%;">Division</td><td style="{{ $kValue }}font-weight:600;">{{ $chain['division'] ?? '—' }}</td>
        </tr></table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid {{ $line }};">
            <tr>
                <th style="{{ $th }}text-align:left;">Role</th>
                <th style="{{ $th }}text-align:left;">Name</th>
                <th style="{{ $th }}text-align:left;">Contact</th>
                <th style="{{ $th }}text-align:right;">Request</th>
            </tr>
            @forelse($chain['heads'] as $h)
                @php $p = $h['person']; @endphp
                <tr style="{{ $h['you'] ? 'background:#eff6ff;' : '' }}">
                    <td style="{{ $td }}color:{{ $muted }};white-space:nowrap;">{{ $h['role'] }}</td>
                    <td style="{{ $td }}">
                        @if($p)<strong>{{ $p['name'] }}</strong>@if($h['you']) <span style="font-size:11px;color:{{ $accent }};font-weight:700;">(you)</span>@endif
                            @if($p['title'])<div style="font-size:11.5px;color:{{ $muted }};">{{ $p['title'] }}</div>@endif
                        @else<span style="color:{{ $muted }};">Not assigned</span>@endif
                    </td>
                    <td style="{{ $td }}font-size:12px;line-height:1.6;">
                        @if($p['email'] ?? null)<a href="mailto:{{ $p['email'] }}" style="color:{{ $accent }};text-decoration:none;">{{ $p['email'] }}</a>@endif
                        @if($p['phone'] ?? null)<div style="color:{{ $muted }};">{{ $p['phone'] }}</div>@endif
                        @if($p && ! ($p['email'] ?? null) && ! ($p['phone'] ?? null))<span style="color:{{ $muted }};">—</span>@endif
                    </td>
                    <td style="{{ $td }}text-align:right;white-space:nowrap;">
                        @if($h['emailed'])<span style="font-size:11px;font-weight:700;color:#047857;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:4px;padding:2px 7px;">SENT</span>
                        @else<span style="font-size:11px;font-weight:700;color:{{ $muted }};background:#f1f5f9;border:1px solid {{ $line }};border-radius:4px;padding:2px 7px;">NOT SENT</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="{{ $td }}color:{{ $muted }};">No department heads on file.</td></tr>
            @endforelse
        </table>
        <div style="font-size:11.5px;color:{{ $muted }};margin-top:6px;">Approval by any one head who received this request is final.</div>
    </td></tr>

    {{-- 3. Lead --}}
    <tr><td style="padding:18px 32px 6px;">
        {!! $section(++$n, 'Lead details') !!}
        @foreach($leadInfo as $group => $fields)
            <div style="font-size:11px;font-weight:700;color:{{ $accent }};letter-spacing:.05em;text-transform:uppercase;margin:{{ $loop->first ? '0' : '12px' }} 0 4px;">{{ $group }}</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid {{ $line }};">
                @foreach($fields as $k => $v)
                    <tr>
                        <td style="{{ $kLabel }}border-bottom:1px solid #f1f5f9;">{{ $k }}</td>
                        <td style="{{ $kValue }}border-bottom:1px solid #f1f5f9;">
                            @if($k === 'Email')<a href="mailto:{{ $v }}" style="color:{{ $accent }};text-decoration:none;">{{ $v }}</a>
                            @elseif($k === 'Phone')<a href="tel:{{ $v }}" style="color:{{ $accent }};text-decoration:none;">{{ $v }}</a>
                            @elseif($k === 'Stage')<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $lead->stageColor() }};margin-right:6px;"></span>{{ $v }}
                            @else{!! nl2br(e($v)) !!}@endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endforeach
    </td></tr>

    {{-- 4. Quotation --}}
    <tr><td style="padding:18px 32px 6px;">
        {!! $section(++$n, 'Quotation') !!}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;"><tr>
            @foreach(array_filter([
                'Number'     => $q->quote_number . ($q->version > 1 ? ' (v' . (int) $q->version . ')' : ''),
                'Quote date' => $q->quote_date?->format('d M Y'),
                'Valid until'=> $expires ? $expires->format('d M Y') . ($daysLeft !== null ? ($daysLeft >= 0 ? ' · ' . $daysLeft . 'd left' : ' · expired') : '') : null,
                'Reference'  => $q->reference,
                'Revises'    => $q->parent ? $q->parent->quote_number . ' v' . (int) $q->parent->version . ' · ' . $money($q->parent->total) : null,
            ]) as $k => $v)
                <td style="vertical-align:top;padding:0 14px 0 0;">
                    <div style="font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:{{ $muted }};font-weight:700;">{{ $k }}</div>
                    <div style="font-size:13px;font-weight:600;margin-top:2px;">{{ $v }}</div>
                </td>
            @endforeach
        </tr></table>

        <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:{{ $accent }};letter-spacing:.05em;text-transform:uppercase;">Items</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid {{ $line }};">
            <tr>
                <th style="{{ $th }}text-align:left;width:24px;">#</th>
                <th style="{{ $th }}text-align:left;">Description</th>
                <th style="{{ $th }}text-align:right;">Qty</th>
                <th style="{{ $th }}text-align:right;">Unit price</th>
                @if($hasLineDisc)<th style="{{ $th }}text-align:right;">Disc.</th>@endif
                @if($hasTax)<th style="{{ $th }}text-align:right;">Tax</th>@endif
                <th style="{{ $th }}text-align:right;">Amount</th>
            </tr>
            @foreach($q->items as $i)
                <tr style="{{ $loop->even ? 'background:#fcfdfe;' : '' }}">
                    <td style="{{ $td }}color:{{ $muted }};">{{ $loop->iteration }}</td>
                    <td style="{{ $td }}">
                        {{ $i->description ?: $i->product?->name ?: $i->serviceItem?->name ?: '—' }}
                        @if($i->product?->sku)<div style="font-size:11px;color:{{ $muted }};">SKU {{ $i->product->sku }}</div>@endif
                    </td>
                    <td style="{{ $td }}text-align:right;white-space:nowrap;">{{ $num($i->quantity, 3) }}</td>
                    <td style="{{ $td }}text-align:right;white-space:nowrap;">{{ $money($i->unit_price) }}</td>
                    @if($hasLineDisc)<td style="{{ $td }}text-align:right;white-space:nowrap;color:#b91c1c;">{{ (float) $i->discount_amount > 0 ? '−' . $money($i->discount_amount) : '—' }}</td>@endif
                    @if($hasTax)<td style="{{ $td }}text-align:right;white-space:nowrap;">{{ (float) $i->tax_rate > 0 ? $num($i->tax_rate) . '%' : '—' }}</td>@endif
                    <td style="{{ $td }}text-align:right;white-space:nowrap;font-weight:600;">{{ $money($i->line_total) }}</td>
                </tr>
            @endforeach
        </table>

        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 0 auto;font-size:13px;min-width:290px;">
            <tr><td style="padding:4px 12px;color:{{ $muted }};">Subtotal</td><td style="padding:4px 0;text-align:right;">{{ $money($q->subtotal) }}</td></tr>
            @if((float) $q->discount_amount > 0)
                <tr><td style="padding:4px 12px;color:{{ $muted }};">Discount ({{ $num($discount['percent']) }}%)</td><td style="padding:4px 0;text-align:right;color:#b91c1c;">−{{ $money($q->discount_amount) }}</td></tr>
            @endif
            @if((float) $q->tax_amount > 0)
                <tr><td style="padding:4px 12px;color:{{ $muted }};">Tax</td><td style="padding:4px 0;text-align:right;">+{{ $money($q->tax_amount) }}</td></tr>
            @endif
            <tr><td style="padding:10px 12px;background:{{ $accent }};color:#fff;font-weight:700;">Total</td><td style="padding:10px 12px;background:{{ $accent }};color:#fff;text-align:right;font-weight:700;font-size:16px;">{{ $money($q->total) }}</td></tr>
            @if($discount['limit'] !== null)
                <tr><td colspan="2" style="padding:7px 0 0;text-align:right;font-size:11.5px;color:{{ $discount['over_limit'] ? '#b91c1c' : '#047857' }};">
                    {{ $discount['over_limit'] ? 'Over' : 'Within' }} the department's {{ $num($discount['limit']) }}% discount limit
                </td></tr>
            @endif
        </table>

        @if($q->notes)
            <p style="margin:16px 0 6px;font-size:11px;font-weight:700;color:{{ $accent }};letter-spacing:.05em;text-transform:uppercase;">Notes &amp; terms</p>
            <div style="border:1px solid {{ $line }};border-radius:6px;padding:11px 14px;font-size:12.5px;line-height:1.6;color:#334155;">{!! nl2br(e($q->notes)) !!}</div>
        @endif
    </td></tr>

    @if($history !== [])
        {{-- 5. History --}}
        <tr><td style="padding:18px 32px 6px;">
            {!! $section(++$n, 'Approval history') !!}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid {{ $line }};">
                @foreach($history as $h)
                    <tr>
                        <td style="{{ $td }}width:120px;white-space:nowrap;color:{{ $muted }};font-size:12px;">{{ $h['at']?->format('d M Y, g:i A') }}</td>
                        <td style="{{ $td }}">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $h['color'] }};margin-right:6px;"></span><strong>{{ $h['label'] }}</strong>
                            <span style="color:{{ $muted }};"> · v{{ $h['version'] }}@if($h['by']) · {{ $h['by'] }}@endif</span>
                            @if($h['comment'])<div style="color:#334155;margin-top:3px;font-style:italic;">“{{ $h['comment'] }}”</div>@endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </td></tr>
    @endif

    {{-- Decision --}}
    <tr><td style="padding:24px 32px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid {{ $line }};border-radius:6px;"><tr>
            <td style="padding:20px 22px;text-align:center;">
                <div style="font-size:15px;font-weight:700;margin-bottom:4px;">Your decision is required</div>
                <div style="font-size:12.5px;color:{{ $muted }};margin-bottom:16px;">Review the quotation and approve or reject it — no sign-in needed. A reason is required to reject.</div>
                <a href="{{ $reviewUrl }}" style="display:inline-block;background:{{ $accent }};color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 34px;border-radius:5px;">Review &amp; approve or reject</a>
                @if($hasPdf)<div style="font-size:12px;color:{{ $muted }};margin-top:12px;">The customer-ready PDF is attached ({{ $q->quote_number }}.pdf).</div>@endif
            </td>
        </tr></table>
    </td></tr>

    {{-- Footer --}}
    <tr><td style="padding:14px 32px 26px;font-size:11.5px;color:{{ $muted }};line-height:1.65;">
        @if($others !== [])
            Also sent to {{ collect($others)->map(fn ($o) => $o['name'] . ' (' . $o['role'] . ')')->implode(', ') }}. The first decision is final.<br>
        @endif
        This link is personal to you and stays valid for {{ $linkDays }} days, or until the quotation is decided or resubmitted. You can also approve it in the CRM.<br>
        If the button does not work, copy this address into your browser:<br>
        <a href="{{ $reviewUrl }}" style="color:{{ $accent }};word-break:break-all;">{{ $reviewUrl }}</a>
        <div style="border-top:1px solid {{ $line }};margin-top:14px;padding-top:12px;">
            <strong style="color:#334155;">Confidential.</strong> This message contains internal pricing for {{ $business?->name ?? 'the company' }} and is intended only for the addressee. Please do not forward it to the customer.
        </div>
    </td></tr>

</table>
<div style="font-size:11px;color:#94a3b8;margin-top:12px;">{{ $business?->name }} · Sent automatically by the CRM</div>
</td></tr>
</table>
</body>
</html>
