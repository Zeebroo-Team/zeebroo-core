@extends('theme::layouts.app', ['title' => __('Payslip'), 'heading' => __('Payslip')])

@section('content')
    @php
        $isDownload = (bool) ($isDownload ?? false);
        $period = $cycle->year.'-'.str_pad((string) $cycle->month, 2, '0', STR_PAD_LEFT);
        $leaveContext = $leaveContext ?? [
            'approved_leave_rows' => collect(),
            'pending_count' => 0,
            'employee_leave_url' => null,
            'leave_inbox_url' => route('hr.leave-requests.index'),
        ];
        $leaveTypeLabels = [
            'annual' => __('Annual'),
            'casual' => __('Casual'),
            'sick' => __('Sick'),
            'unpaid' => __('Unpaid'),
            'other' => __('Other'),
        ];
        $reduction = is_array($item->snapshot_json['reduction'] ?? null) ? $item->snapshot_json['reduction'] : [];
        $basicMonthly = (float) ($reduction['basic_salary_monthly'] ?? ($item->employee?->basic_salary ?? 0));
        $basicEarned = (float) ($reduction['basic_salary_earned'] ?? ($item->basic_salary ?? 0));
        $basicReduction = round(max(0, $basicMonthly - $basicEarned), 2);
        $stdDays = (float) ($reduction['standard_days'] ?? 0);
        $actDays = (float) ($reduction['actual_days'] ?? 0);
        $enteredAttendance = (bool) ($reduction['entered_attendance'] ?? false);
        $joinDate = (string) ($reduction['employee_join_date'] ?? '');
        $cycleStart = (string) ($reduction['cycle_period_start'] ?? '');
        $cycleEnd = (string) ($reduction['cycle_period_end'] ?? '');
    @endphp
    <style>
        .payslip-wrap{max-width:980px;margin:0 auto}
        .payslip-sheet{border:1px solid color-mix(in srgb,var(--border)85%,transparent);border-radius:18px;background:var(--card);color:var(--text);padding:22px;box-shadow:0 12px 30px rgba(15,23,42,.08)}
        .payslip-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;padding-bottom:14px;margin-bottom:16px;border-bottom:1px dashed color-mix(in srgb,var(--border)78%,transparent)}
        .payslip-title{margin:0;font-size:1.35rem;letter-spacing:-.02em;line-height:1.2}
        .payslip-sub{margin:6px 0 0;font-size:13px;color:var(--muted)}
        .payslip-issued{font-size:12px;color:var(--muted);padding:6px 10px;border:1px solid color-mix(in srgb,var(--border)78%,transparent);border-radius:999px;background:color-mix(in srgb,var(--card)90%,transparent)}
        .payslip-back-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;border:1px solid color-mix(in srgb,var(--border)84%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--text);font-size:12px;font-weight:700;text-decoration:none}
        .payslip-back-btn:hover{background:color-mix(in srgb,var(--primary)11%,transparent);text-decoration:none}
        .payslip-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}
        .payslip-box{border:1px solid color-mix(in srgb,var(--border)84%,transparent);border-radius:12px;padding:12px;background:linear-gradient(160deg,color-mix(in srgb,var(--card)96%,transparent),color-mix(in srgb,var(--primary)7%,transparent))}
        .payslip-box small{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:800}
        .payslip-box strong{display:block;margin-top:7px;font-size:17px;line-height:1.2}
        .payslip-box p{margin:4px 0 0;font-size:12px;color:var(--muted)}
        .payslip-section{margin-top:16px;padding:12px;border:1px solid color-mix(in srgb,var(--border)82%,transparent);border-radius:12px;background:color-mix(in srgb,var(--card)98%,transparent)}
        .payslip-section__title{margin:0 0 10px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .payslip-table-wrap{overflow:auto;border:1px solid color-mix(in srgb,var(--border)82%,transparent);border-radius:10px}
        .payslip-table{width:100%;border-collapse:collapse;min-width:640px;font-size:13px}
        .payslip-table thead th{background:color-mix(in srgb,var(--card)92%,transparent);border-bottom:1px solid color-mix(in srgb,var(--border)82%,transparent);text-align:left;padding:10px;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
        .payslip-table td{padding:10px;border-bottom:1px solid color-mix(in srgb,var(--border)65%,transparent);vertical-align:middle}
        .payslip-table tr:last-child td{border-bottom:none}
        .payslip-table td:last-child,.payslip-table th:last-child{text-align:right}
        .payslip-note{margin:0;font-size:12px;line-height:1.55;color:var(--muted)}
        .payslip-links{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
        .payslip-summary{margin-top:16px;display:grid;gap:8px;justify-content:end}
        .payslip-summary__row{display:flex;justify-content:space-between;gap:26px;min-width:300px;font-size:13px}
        .payslip-summary__net{padding-top:8px;margin-top:2px;border-top:1px dashed color-mix(in srgb,var(--border)78%,transparent)}
        .payslip-summary__net strong{font-size:16px}
        .payslip-reduction{margin-top:16px;padding:12px;border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));border-radius:12px;background:color-mix(in srgb,#f59e0b 9%,transparent)}
        .payslip-reduction__title{margin:0 0 8px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}

        .payslip-print-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;border:1px solid color-mix(in srgb,#6366f1 42%,var(--border));background:color-mix(in srgb,#6366f1 10%,transparent);color:var(--text);font-size:12px;font-weight:700;cursor:pointer}
        .payslip-print-btn:hover{background:color-mix(in srgb,#6366f1 18%,transparent)}
        .payslip-print-btn:disabled{opacity:.6;cursor:not-allowed}

        .lh-print-zone{display:none}
        .payslip-print-info{display:none}

        @media print{
            /* hide chrome */
            .sidebar,.navbar,.payslip-back-btn,.payslip-issued,
            .payslip-print-btn,.payslip-top,.payslip-links,
            .content-inner > *:not(.payslip-wrap){display:none!important}

            /* reset layout */
            *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
            body,html{background:#fff!important}
            .content,.content-inner{margin:0!important;padding:0!important;max-width:none!important;background:#fff!important}

            /* sheet */
            .payslip-wrap{max-width:none!important;background:#fff!important}
            .payslip-sheet{border:none!important;box-shadow:none!important;border-radius:0!important;padding:0 16px 16px!important;background:#fff!important}

            /* meta boxes → compact row */
            .payslip-meta{
                grid-template-columns:repeat(3,1fr)!important;
                gap:0!important;
                border:1px solid #e2e8f0!important;border-radius:6px!important;
                overflow:hidden!important;
                margin-bottom:10px!important;
            }
            .payslip-box{
                border:none!important;border-right:1px solid #e2e8f0!important;
                border-radius:0!important;padding:7px 10px!important;
                background:#f8fafc!important;
            }
            .payslip-box:last-child{border-right:none!important}
            .payslip-box small{font-size:7.5px!important}
            .payslip-box strong{font-size:13px!important;margin-top:4px!important}
            .payslip-box p{font-size:9px!important}

            /* sections */
            .payslip-section{
                margin-top:8px!important;padding:8px!important;
                border:1px solid #e2e8f0!important;border-radius:6px!important;
                background:#fff!important;
            }
            .payslip-section__title{font-size:8px!important;margin-bottom:6px!important}
            .payslip-table-wrap{border:1px solid #e2e8f0!important;border-radius:4px!important;overflow:visible!important}
            .payslip-table{font-size:9.5px!important;min-width:0!important}
            .payslip-table thead th{
                background:#f1f5f9!important;color:#475569!important;
                font-size:8px!important;padding:5px 8px!important;
                border-bottom:1px solid #e2e8f0!important;
            }
            .payslip-table td{padding:5px 8px!important;border-bottom:1px solid #e2e8f0!important}
            .payslip-table tr:last-child td{border-bottom:none!important}

            /* summary */
            .payslip-summary{margin-top:8px!important}
            .payslip-summary__row{font-size:10px!important;gap:16px!important;min-width:220px!important}
            .payslip-summary__net strong{font-size:12px!important}
            .payslip-summary__net{border-top-color:#e2e8f0!important}

            /* reduction note */
            .payslip-reduction{margin-top:8px!important;padding:8px!important;border-radius:6px!important}
            .payslip-reduction__title{font-size:8px!important}
            .payslip-note{font-size:9.5px!important}

            /* letterhead + print-only info */
            .lh-print-zone{display:block!important}
            .payslip-print-info{display:block!important}
            #lhRenderCanvas{display:none!important;visibility:hidden!important}
        }
    </style>
    <div class="payslip-wrap">
    <div class="payslip-sheet">
        @include('hrmanagement::partials.print-letterhead', ['accentColor' => $accentColor ?? '#3B82F6', 'mainBranch' => $mainBranch ?? null])

        {{-- print-only document header --}}
        <div class="payslip-print-info" style="padding:10px 0 8px;border-bottom:2px solid #e2e8f0;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:15px;font-weight:800;color:#0f172a;letter-spacing:-.02em;">{{ __('Payslip') }}</div>
                    <div style="font-size:11px;color:#475569;margin-top:3px;">
                        {{ $business->name }}
                        &nbsp;·&nbsp; {{ $cycle->name }}
                        @if($cycle->period_start && $cycle->period_end)
                            &nbsp;·&nbsp; {{ $cycle->period_start->format('M j') }} – {{ $cycle->period_end->format('M j, Y') }}
                        @endif
                        &nbsp;·&nbsp; {{ ucfirst((string) $item->status) }}
                    </div>
                    <div style="font-size:11px;color:#1e293b;margin-top:5px;font-weight:700;">
                        {{ $item->employee?->full_name ?? '—' }}
                        @if($item->employee?->employee_id)
                            <span style="font-weight:400;color:#64748b;">&nbsp;·&nbsp; ID: {{ $item->employee->employee_id }}</span>
                        @endif
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:8.5px;color:#94a3b8;margin-bottom:4px;">{{ __('Printed') }}: {{ now()->format('M j, Y H:i') }}</div>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:6px 12px;text-align:center;">
                        <div style="font-size:7.5px;text-transform:uppercase;letter-spacing:.06em;color:#15803d;font-weight:800;">{{ __('Net Pay') }}</div>
                        <div style="font-size:16px;font-weight:800;color:#15803d;font-variant-numeric:tabular-nums;line-height:1.2;margin-top:2px;">{{ number_format((float) $item->net_pay, 2) }}</div>
                        <div style="font-size:7.5px;color:#16a34a;margin-top:1px;">{{ $cycle->ruleSet?->currency ?: ($business->currency ?? '') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @unless($isDownload)
            <div style="display:flex;align-items:center;gap:8px;margin:0 0 12px;flex-wrap:wrap;">
                <a href="{{ route('hr.payroll.cycles.show', $cycle) }}" class="payslip-back-btn"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Back to cycle') }}</a>
                <button id="lhPrintBtn" type="button" onclick="window.print()" class="payslip-print-btn"><i class="fa fa-print" aria-hidden="true"></i>{{ __('Print / PDF') }}</button>
                <a href="{{ route('hr.payroll.cycles.items.payslip.download', [$cycle, $item]) }}" class="payslip-back-btn" style="margin-left:auto;"><i class="fa fa-download" aria-hidden="true"></i>{{ __('Download HTML') }}</a>
            </div>
        @endunless

        <header class="payslip-top">
            <div>
                <h2 class="payslip-title">{{ __('Payslip') }}</h2>
                <p class="payslip-sub">{{ $business->name }} · {{ $cycle->name }} · {{ $period }}</p>
            </div>
            <div class="payslip-issued">{{ __('Generated at') }}: {{ now()->format('Y-m-d H:i') }}</div>
        </header>

        <div class="payslip-meta">
            <article class="payslip-box">
                <small>{{ __('Employee') }}</small>
                <strong>{{ $item->employee?->full_name ?? '—' }}</strong>
                <p>{{ $item->employee?->employee_id }}</p>
            </article>
            <article class="payslip-box">
                <small>{{ __('Status') }}</small>
                <strong>{{ ucfirst((string) $item->status) }}</strong>
                <p>{{ __('Cycle') }}: {{ $period }}</p>
            </article>
            <article class="payslip-box">
                <small>{{ __('Net pay') }}</small>
                <strong>{{ number_format((float) $item->net_pay, 2) }}</strong>
                <p>{{ __('Amount payable') }}</p>
            </article>
        </div>

        <section class="payslip-section">
            <h3 class="payslip-section__title">{{ __('Payroll components') }}</h3>
            <div class="payslip-table-wrap">
            <table class="payslip-table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Component') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($item->components as $c)
                        <tr>
                            <td>{{ $c->code }}</td>
                            <td>{{ $c->name }}</td>
                            <td>{{ ucfirst((string) $c->component_type) }}</td>
                            <td>{{ number_format((float) $c->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>

        <section class="payslip-section">
            <h3 class="payslip-section__title">{{ __('Leave · this payroll period') }}</h3>
            @if(($leaveContext['approved_leave_rows'] ?? collect())->isEmpty())
                <p class="payslip-note">{{ __('No approved leave overlaps this cycle period.') }}</p>
            @else
                <div class="payslip-table-wrap" style="margin-bottom:8px;">
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th style="text-align:left;">{{ __('Type') }}</th>
                                <th style="text-align:left;">{{ __('From') }}</th>
                                <th style="text-align:left;">{{ __('To') }}</th>
                                <th style="text-align:right;">{{ __('Days in period') }}</th>
                                <th style="text-align:left;">{{ __('Status') }}</th>
                                <th style="text-align:left;">{{ __('Request') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leaveContext['approved_leave_rows'] as $row)
                                @php
                                    $lr = $row['leave'];
                                    $daysIn = (int) ($row['days_in_period'] ?? 0);
                                    $employee = $item->employee;
                                    $leaveRowUrl = $employee
                                        ? route('hr.employees.show', $employee).'?lr='.$lr->id.'#leave'
                                        : null;
                                @endphp
                                <tr>
                                    <td>{{ $leaveTypeLabels[$lr->leave_type] ?? $lr->leave_type }}</td>
                                    <td>{{ $lr->starts_on?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $lr->ends_on?->format('Y-m-d') ?? '—' }}</td>
                                    <td>{{ $daysIn }}</td>
                                    <td>{{ __('Approved') }}</td>
                                    <td style="text-align:left !important;">
                                        @if($leaveRowUrl && ! $isDownload)
                                            <a href="{{ $leaveRowUrl }}" class="emp-docs-action">{{ __('View on profile') }}</a>
                                        @elseif($leaveRowUrl)
                                            <span class="payslip-note" style="word-break:break-all;">{{ $leaveRowUrl }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="payslip-note" style="margin-top:4px;">
                {{ __('Pending leave requests: :count', ['count' => (int) ($leaveContext['pending_count'] ?? 0)]) }}
                @if(((int) ($leaveContext['pending_count'] ?? 0)) > 0 && ! $isDownload)
                    · <a href="{{ $leaveContext['leave_inbox_url'] }}" class="emp-docs-action" style="font-size:12px;">{{ __('Review in leave inbox') }}</a>
                @elseif(((int) ($leaveContext['pending_count'] ?? 0)) > 0 && $isDownload)
                    <br><span class="payslip-note" style="word-break:break-all;">{{ $leaveContext['leave_inbox_url'] }}</span>
                @endif
            </p>

            @if($leaveContext['employee_leave_url'])
                <div class="payslip-links">
                @if(! $isDownload)
                    <a href="{{ $leaveContext['employee_leave_url'] }}" class="emp-docs-action">{{ __('Employee leave tab') }}</a>
                @else
                    <span class="payslip-note" style="word-break:break-all;">{{ $leaveContext['employee_leave_url'] }}</span>
                @endif
                </div>
            @endif
        </section>

        <div class="payslip-summary">
            <div class="payslip-summary__row"><span>{{ __('Gross') }}</span><span>{{ number_format((float) $item->gross_earnings, 2) }}</span></div>
            <div class="payslip-summary__row"><span>{{ __('Deductions') }}</span><span>{{ number_format((float) $item->total_deductions, 2) }}</span></div>
            <div class="payslip-summary__row payslip-summary__net"><strong>{{ __('Net pay') }}</strong><strong>{{ number_format((float) $item->net_pay, 2) }}</strong></div>
        </div>

        @if($basicReduction > 0.0)
            <section class="payslip-reduction">
                <h3 class="payslip-reduction__title">{{ __('Reduction reason') }}</h3>
                <p class="payslip-note" style="color:inherit;">
                    {{ __('Basic salary was pro-rated for this payroll period.') }}
                    @if($stdDays > 0)
                        {{ __('Standard days: :std · Worked days: :act', ['std' => rtrim(rtrim(number_format($stdDays, 4, '.', ''), '0'), '.'), 'act' => rtrim(rtrim(number_format($actDays, 4, '.', ''), '0'), '.')]) }}.
                    @endif
                    @if($enteredAttendance)
                        {{ __('Worked days are based on entered attendance for this cycle.') }}
                    @endif
                    @if($joinDate !== '' && $cycleStart !== '' && $cycleEnd !== '')
                        {{ __('Join date: :join (cycle :start to :end).', ['join' => $joinDate, 'start' => $cycleStart, 'end' => $cycleEnd]) }}
                    @endif
                    {{ __('Monthly basic: :m · Earned basic: :e · Reduction: :r', ['m' => number_format($basicMonthly, 2), 'e' => number_format($basicEarned, 2), 'r' => number_format($basicReduction, 2)]) }}.
                </p>
            </section>
        @endif
        <div id="lhFooterZone" class="lh-print-zone lh-print-zone--footer">
            <img id="lhFooterImg" style="display:none;width:100%;vertical-align:bottom;max-width:100%;" alt="">
            <div id="lhFooterFallback">
                <div style="height:4px;background:{{ $accentColor ?? '#3B82F6' }};opacity:.18;margin:0 40px;"></div>
                <div style="height:1px;background:rgba(0,0,0,.08);margin:0 40px;"></div>
                <div style="padding:10px 40px 14px;display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:9px;color:#94a3b8;">{{ $business->name }}</div>
                    <div style="font-size:9px;color:#94a3b8;">{{ now()->format('Y-m-d H:i') }}</div>
                </div>
                <div style="height:6px;background:{{ $accentColor ?? '#3B82F6' }};"></div>
            </div>
        </div>
    </div>
    </div>

    @include('hrmanagement::partials.print-letterhead-script', ['letterheadCanvasJson' => $letterheadCanvasJson ?? null])
@endsection
