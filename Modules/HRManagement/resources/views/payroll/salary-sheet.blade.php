@extends('theme::layouts.app', ['title' => __('Salary sheet'), 'heading' => __('Salary sheet')])

@php
    $sheetCurrency = $cycle->ruleSet?->currency ?: ($business->currency ?? 'LKR');
    $cycleStatusRaw = strtolower((string) $cycle->status);
    $cycleStatusLabel = match ($cycleStatusRaw) {
        'finalized' => __('Finalized'),
        'computed' => __('Computed'),
        'draft' => __('Draft'),
        default => __(ucfirst((string) $cycle->status)),
    };
    $sheetColumns = $sheetColumns ?? [];
    $sheetColumnCount = count($sheetColumns);
    $salarySheetTableMinWidth = max(720, $sheetColumnCount * 94);
    $varianceMeta = $varianceMeta ?? [];
    $statusCounts = $summary['status_counts'] ?? ['computed' => 0, 'finalized' => 0, 'error' => 0, 'other' => 0];
@endphp

@section('content')
    <style>
        .salary-sheet-page{
            max-width:1280px;width:100%;min-width:0;margin:0 auto;display:grid;gap:14px;
            box-sizing:border-box;
        }
        .salary-sheet-hero{
            border:1px solid color-mix(in srgb,var(--border)90%,transparent);
            border-radius:14px;
            background:var(--card);
            padding:16px 18px;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 8px 22px rgba(0,0,0,.045);
        }
        .salary-sheet-hero__top{display:flex;flex-wrap:wrap;gap:12px 16px;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
        .salary-sheet-hero__title{margin:0;font-size:1.06rem;font-weight:800;letter-spacing:-.02em;line-height:1.25;color:var(--text)}
        .salary-sheet-hero__subtitle{margin:6px 0 0;font-size:12px;line-height:1.45;color:var(--muted);max-width:720px}
        .salary-sheet-meta{display:flex;flex-wrap:wrap;gap:8px 10px;margin:12px 0 0;padding:0;list-style:none}
        .salary-sheet-meta__item{
            display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:750;
            text-transform:uppercase;letter-spacing:.05em;color:var(--muted);
            padding:5px 10px;border-radius:999px;
            border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            background:color-mix(in srgb,var(--card)94%,transparent);
        }
        .salary-sheet-meta__item span{font-weight:800;color:var(--text);text-transform:none;letter-spacing:0;font-size:11px}
        .salary-sheet-meta__item i.fa{font-size:11px;opacity:.88;color:color-mix(in srgb,var(--primary)72%,var(--muted))}

        .salary-sheet-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .salary-sheet-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);transition:background .15s ease}
        .salary-sheet-btn:hover{background:color-mix(in srgb,var(--primary)20%,transparent);color:var(--text)}
        .salary-sheet-btn--muted{border-color:color-mix(in srgb,var(--border)88%,transparent);background:color-mix(in srgb,var(--card)96%,transparent)}
        .salary-sheet-btn--muted:hover{background:color-mix(in srgb,var(--primary)10%,transparent)}

        .salary-sheet-kpis{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
        .salary-sheet-kpi{
            position:relative;
            padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            background:linear-gradient(165deg,color-mix(in srgb,var(--card)99%,transparent),color-mix(in srgb,var(--primary)7%,transparent));
            overflow:hidden;
        }
        .salary-sheet-kpi::after{
            content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px;
            background:color-mix(in srgb,var(--primary)55%,transparent);
            opacity:.75;
        }
        .salary-sheet-kpi--net::after{background:color-mix(in srgb,#22c55e 62%,var(--primary));}
        .salary-sheet-kpi--deductions::after{background:color-mix(in srgb,#f97316 55%,var(--primary));}
        .salary-sheet-kpi-label{display:flex;align-items:center;gap:6px;margin:0 0 6px;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .salary-sheet-kpi-label i.fa{font-size:11px;color:color-mix(in srgb,var(--primary)68%,var(--muted));}
        .salary-sheet-kpi-value{margin:0;font-size:clamp(14px,1.9vw,17px);font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em;line-height:1.2;color:var(--text)}
        .salary-sheet-kpi-curr{display:block;margin-top:4px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}

        .salary-sheet-table-card{
            border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            border-radius:14px;background:var(--card);
            padding:12px 14px;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)50%,transparent) inset;
            min-width:0;
            max-width:100%;
            overflow:hidden;
        }
        .salary-sheet-table-card__caption{margin:0 0 10px;font-size:11px;font-weight:750;color:var(--muted)}
        .salary-sheet-table-card__caption strong{font-weight:800;color:var(--text)}
        .salary-sheet-scroll{
            display:block;
            width:100%;
            max-width:100%;
            min-width:0;
            box-sizing:border-box;
            overflow-x:auto;
            overflow-y:auto;
            overscroll-behavior:contain;
            scrollbar-gutter:stable;
            margin:0;border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            border-radius:10px;background:color-mix(in srgb,var(--card)98%,transparent);
            -webkit-overflow-scrolling:touch;
            max-height:min(72vh,min(920px,calc(100dvh - 280px)));
        }
        .salary-sheet-scroll::-webkit-scrollbar{height:10px;width:10px}
        .salary-sheet-scroll::-webkit-scrollbar-thumb{border-radius:8px;background:color-mix(in srgb,var(--border)65%,transparent)}

        .salary-sheet__table{
            width:max-content;
            max-width:none;
            min-width:100%;
            border-collapse:separate;
            border-spacing:0;
        }
        .salary-sheet__table thead th{
            position:sticky;top:0;z-index:3;
            background:color-mix(in srgb,var(--card)92%,transparent);
            color:var(--muted);
            font-size:9.5px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;
            padding:10px 9px;border-bottom:1px solid color-mix(in srgb,var(--border)78%,transparent);
            white-space:nowrap;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)70%,transparent);
        }
        .salary-sheet__table thead th.salary-sheet__num{text-align:right}
        .salary-sheet__table tbody td{
            padding:9px 9px;border-bottom:1px solid color-mix(in srgb,var(--border)70%,transparent);
            vertical-align:middle;font-size:12px;
        }
        .salary-sheet__table tbody tr:nth-child(even) td{background:color-mix(in srgb,var(--card)97%,transparent)}
        .salary-sheet__table tbody tr:hover td{background:color-mix(in srgb,var(--primary)7%,transparent)}
        .salary-sheet__table tbody tr:last-child td{border-bottom:none}
        .salary-sheet__table th + th,.salary-sheet__table td + td{border-left:1px solid color-mix(in srgb,var(--border)55%,transparent)}

        .salary-sheet__table th.salary-sheet__th--employ,.salary-sheet__table td.salary-sheet__cell--employ{
            position:sticky;left:0;z-index:2;
            min-width:170px;max-width:240px;
            box-shadow:1px 0 0 color-mix(in srgb,var(--border)65%,transparent);
        }
        .salary-sheet__table thead th.salary-sheet__th--employ{z-index:4;background:color-mix(in srgb,var(--card)92%,transparent)}
        .salary-sheet__table td.salary-sheet__cell--employ{background:var(--card)}
        .salary-sheet__table tbody tr:nth-child(even) td.salary-sheet__cell--employ{background:color-mix(in srgb,var(--card)97%,transparent)}
        .salary-sheet__table tbody tr:hover td.salary-sheet__cell--employ{background:color-mix(in srgb,var(--primary)7%,transparent)}

        .salary-sheet__name{display:block;font-size:12.5px;font-weight:750;line-height:1.3;color:var(--text)}
        .salary-sheet__meta{display:block;margin-top:3px;font-size:10.5px;color:var(--muted);font-variant-numeric:tabular-nums}
        .salary-sheet__num{text-align:right;font-variant-numeric:tabular-nums;font-weight:650;font-size:12px}
        .salary-sheet__num--emph{font-weight:800;color:color-mix(in srgb,var(--primary)42%,var(--text))}
        .salary-sheet__center{text-align:center}
        .salary-sheet__status{display:inline-flex;align-items:center;justify-content:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.03em;border:1px solid var(--border);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--muted);white-space:nowrap}
        .salary-sheet__status--finalized{border-color:color-mix(in srgb,#22c55e 45%,var(--border));color:#15803d;background:color-mix(in srgb,#22c55e 10%,transparent)}
        .salary-sheet__status--draft{border-color:color-mix(in srgb,var(--primary)38%,var(--border));color:color-mix(in srgb,var(--primary)78%,var(--text));background:color-mix(in srgb,var(--primary)10%,transparent)}
        .salary-sheet__status--error{border-color:color-mix(in srgb,#ef4444 50%,var(--border));color:#b91c1c;background:color-mix(in srgb,#ef4444 11%,transparent)}
        .salary-sheet__num--pos{color:#15803d}
        .salary-sheet__num--neg{color:#b91c1c}
        .salary-sheet-note{margin:0 0 10px;padding:10px 12px;border-radius:10px;border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);font-size:12px;color:var(--text)}

        .salary-sheet-empty{padding:28px 16px;text-align:center;font-size:13px;color:var(--muted);line-height:1.5}
        .salary-sheet-empty i.fa{display:block;margin:0 auto 10px;font-size:28px;opacity:.35;color:var(--muted)}

        @media (max-width:1100px){.salary-sheet-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){
            .salary-sheet-kpis{grid-template-columns:1fr}
            .salary-sheet-hero{padding:14px}
            .salary-sheet-scroll{max-height:min(58vh,min(720px,calc(100dvh - 240px)))}
        }

        @media print{
            .salary-sheet-actions,.sidebar,.navbar,.content-inner > *:not(.salary-sheet-page){display:none!important}
            .content,.content-inner{margin:0!important;padding:8px!important;max-width:none!important}
            .salary-sheet-page{max-width:none;gap:10px}
            .salary-sheet-scroll{max-height:none;overflow:visible;border:none}
            .salary-sheet__table th,.salary-sheet__table td.salary-sheet__cell--employ{position:static!important;box-shadow:none!important}
            .salary-sheet__table tbody tr:hover td{background:transparent!important}
            .salary-sheet-hero,.salary-sheet-table-card{box-shadow:none;border:1px solid #ccc}
        }
    </style>

    @if(session('status'))
        <p class="emp-show__flash" role="status" style="max-width:1280px;margin:0 auto;">{{ session('status') }}</p>
    @endif
    @if(session('warning'))
        <p class="emp-show__err" role="alert" style="max-width:1280px;margin:0 auto;">{{ session('warning') }}</p>
    @endif
    @if($errors->any())
        <div class="emp-show__err" role="alert" style="max-width:1280px;margin:0 auto;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="salary-sheet-page">
        <header class="salary-sheet-hero">
            <div class="salary-sheet-hero__top">
                <div>
                    <h1 class="salary-sheet-hero__title">{{ __('Salary sheet') }} — {{ $cycle->name }}</h1>
                    <p class="salary-sheet-hero__subtitle">{{ __('Period totals and employee lines for this payroll cycle. Amounts are shown in :currency.', ['currency' => $sheetCurrency]) }}</p>
                    <ul class="salary-sheet-meta" aria-label="{{ __('Cycle details') }}">
                        <li class="salary-sheet-meta__item"><i class="fa fa-calendar-days" aria-hidden="true"></i>{{ __('Period') }} <span>{{ $cycle->period_start?->format('M j, Y') }} — {{ $cycle->period_end?->format('M j, Y') }}</span></li>
                        <li class="salary-sheet-meta__item"><i class="fa fa-layer-group" aria-hidden="true"></i>{{ __('Rule set') }} <span>{{ $cycle->ruleSet?->name ?? '—' }}</span></li>
                        <li class="salary-sheet-meta__item"><i class="fa fa-coins" aria-hidden="true"></i>{{ __('Currency') }} <span>{{ $sheetCurrency }}</span></li>
                        <li class="salary-sheet-meta__item"><i class="fa fa-flag-checkered" aria-hidden="true"></i>{{ __('Cycle status') }} <span>{{ $cycleStatusLabel }}</span></li>
                        <li class="salary-sheet-meta__item"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i>{{ __('Error rows') }} <span>{{ (int) ($statusCounts['error'] ?? 0) }}</span></li>
                        @if(!empty($varianceMeta['previous_cycle_label']))
                            <li class="salary-sheet-meta__item"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i>{{ __('Variance baseline') }} <span>{{ $varianceMeta['previous_cycle_label'] }}</span></li>
                        @endif
                    </ul>
                </div>
                <div class="salary-sheet-actions">
                    <a href="{{ route('hr.payroll.cycles.salary-sheet.export', $cycle) }}" class="salary-sheet-btn"><i class="fa-solid fa-table" aria-hidden="true"></i>{{ __('Export Excel') }}</a>
                    <a href="{{ route('hr.payroll.cycles.show', $cycle) }}" class="salary-sheet-btn salary-sheet-btn--muted"><i class="fa fa-arrow-left" aria-hidden="true"></i>{{ __('Back to cycle') }}</a>
                    <a href="{{ route('hr.payroll.index') }}" class="salary-sheet-btn"><i class="fa fa-money-check-dollar" aria-hidden="true"></i>{{ __('Payroll home') }}</a>
                </div>
            </div>

            <div class="salary-sheet-kpis" role="region" aria-label="{{ __('Totals') }}">
                <article class="salary-sheet-kpi">
                    <p class="salary-sheet-kpi-label"><i class="fa fa-users" aria-hidden="true"></i>{{ __('Employees') }}</p>
                    <p class="salary-sheet-kpi-value">{{ count($rows) }}</p>
                </article>
                <article class="salary-sheet-kpi">
                    <p class="salary-sheet-kpi-label"><i class="fa fa-arrow-trend-up" aria-hidden="true"></i>{{ __('Total gross') }}</p>
                    <p class="salary-sheet-kpi-value">{{ number_format((float) $summary['total_gross'], 2) }}</p>
                    <span class="salary-sheet-kpi-curr">{{ $sheetCurrency }}</span>
                </article>
                <article class="salary-sheet-kpi salary-sheet-kpi--deductions">
                    <p class="salary-sheet-kpi-label"><i class="fa fa-arrow-trend-down" aria-hidden="true"></i>{{ __('Total deductions') }}</p>
                    <p class="salary-sheet-kpi-value">{{ number_format((float) $summary['total_deductions'], 2) }}</p>
                    <span class="salary-sheet-kpi-curr">{{ $sheetCurrency }}</span>
                </article>
                <article class="salary-sheet-kpi salary-sheet-kpi--net">
                    <p class="salary-sheet-kpi-label"><i class="fa fa-wallet" aria-hidden="true"></i>{{ __('Total net') }}</p>
                    <p class="salary-sheet-kpi-value">{{ number_format((float) $summary['total_net'], 2) }}</p>
                    <span class="salary-sheet-kpi-curr">{{ $sheetCurrency }}</span>
                </article>
            </div>
        </header>

        <section class="salary-sheet-table-card">
            @if((int) ($statusCounts['error'] ?? 0) > 0)
                <p class="salary-sheet-note">
                    {{ __('Some rows are in Error status and are still included in totals for visibility. Please recompute those employees.') }}
                </p>
            @endif
            <p class="salary-sheet-table-card__caption" id="salary-sheet-table-desc"><strong>{{ __('Detail') }}</strong> — {{ __('Scroll horizontally if needed; the employee column stays visible.') }}</p>
            <div class="salary-sheet-scroll" role="region" aria-labelledby="salary-sheet-table-desc" tabindex="0">
                <table class="salary-sheet__table" style="min-width:{{ $salarySheetTableMinWidth }}px">
                    <thead>
                        <tr>
                            @foreach($sheetColumns as $col)
                                @php $ck = (string) ($col['kind'] ?? ''); @endphp
                                @if($ck === 'employee')
                                    <th scope="col" class="salary-sheet__th--employ">{{ $col['label'] }}</th>
                                @elseif($ck === 'status')
                                    <th scope="col">{{ $col['label'] }}</th>
                                @else
                                    <th scope="col" class="salary-sheet__num">{{ $col['label'] }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $rowStatusRaw = strtolower((string) ($row['status'] ?? ''));
                                $statusClass = match (true) {
                                    str_contains($rowStatusRaw, 'error') => 'salary-sheet__status--error',
                                    str_contains($rowStatusRaw, 'final') => 'salary-sheet__status--finalized',
                                    default => 'salary-sheet__status--draft',
                                };
                                $cellValues = $row['values'] ?? [];
                            @endphp
                            <tr>
                                @foreach($sheetColumns as $col)
                                    @php $ck = (string) ($col['kind'] ?? ''); @endphp
                                    @if($ck === 'employee')
                                        <td class="salary-sheet__cell--employ">
                                            <span class="salary-sheet__name">{{ $row['employee_name'] }}</span>
                                            <span class="salary-sheet__meta">{{ __('ID') }}: {{ ($row['employee_id'] ?? '') ?: '—' }}</span>
                                        </td>
                                    @elseif($ck === 'status')
                                        <td class="salary-sheet__center"><span class="salary-sheet__status {{ $statusClass }}">{{ $row['status'] }}</span></td>
                                    @else
                                        @php
                                            $ckey = (string) ($col['key'] ?? '');
                                            $amount = (float) ($cellValues[$ckey] ?? 0);
                                            $emph = !empty($col['emphasize']);
                                            $isVariance = str_starts_with($ckey, 'var_');
                                            $varianceClass = $isVariance ? ($amount < 0 ? 'salary-sheet__num--neg' : ($amount > 0 ? 'salary-sheet__num--pos' : '')) : '';
                                            $displayAmount = $isVariance && $amount > 0 ? '+'.number_format($amount, 2) : number_format($amount, 2);
                                        @endphp
                                        <td class="salary-sheet__num @if($emph) salary-sheet__num--emph @endif {{ $varianceClass }}">{{ $displayAmount }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(1, $sheetColumnCount) }}" class="salary-sheet-empty">
                                    <i class="fa fa-table" aria-hidden="true"></i>
                                    {{ __('No salary sheet rows found. Compute or finalize this cycle, then generate the sheet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
