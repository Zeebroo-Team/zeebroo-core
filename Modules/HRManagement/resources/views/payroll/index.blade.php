@extends('theme::layouts.app', ['title' => __('Payroll'), 'heading' => __('Payroll')])

@php
    use Modules\HRManagement\Models\PayrollCycle;

    $draftCount = $cycles->where('status', PayrollCycle::STATUS_DRAFT)->count();
    $computedCount = $cycles->where('status', PayrollCycle::STATUS_COMPUTED)->count();
    $finalizedCount = $cycles->where('status', PayrollCycle::STATUS_FINALIZED)->count();
    $hubCurrency = optional($ruleSets->firstWhere('is_default', true))->currency ?? $business->currency ?? 'LKR';
@endphp

@section('content')
    <style>
        .phi-page{max-width:1280px;margin:0 auto;display:grid;gap:14px}
        .phi-hero{
            border:1px solid color-mix(in srgb,var(--border)90%,transparent);
            border-radius:14px;
            background:var(--card);
            padding:18px 20px;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)55%,transparent) inset,0 8px 22px rgba(0,0,0,.045);
        }
        .phi-hero__top{display:flex;flex-wrap:wrap;gap:14px 20px;justify-content:space-between;align-items:flex-start}
        .phi-hero__title{margin:0;font-size:1.12rem;font-weight:800;letter-spacing:-.02em;line-height:1.25;color:var(--text)}
        .phi-hero__sub{margin:6px 0 0;font-size:12px;line-height:1.45;color:var(--muted);max-width:640px}

        .phi-meta{display:flex;flex-wrap:wrap;gap:8px 10px;margin:14px 0 0;padding:0;list-style:none}
        .phi-meta li{
            display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:750;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);
            padding:5px 10px;border-radius:999px;border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            background:color-mix(in srgb,var(--card)94%,transparent);
        }
        .phi-meta li span{font-weight:800;color:var(--text);text-transform:none;letter-spacing:0;font-size:11px}
        .phi-meta li i.fa{font-size:11px;opacity:.9;color:color-mix(in srgb,var(--primary)72%,var(--muted));}

        .phi-actions-top{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
        .phi-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer;border:1px solid color-mix(in srgb,var(--primary)42%,var(--border));background:color-mix(in srgb,var(--primary)12%,transparent);color:var(--text);transition:background .15s ease;-webkit-appearance:none;appearance:none;font-family:inherit}
        .phi-btn:hover{background:color-mix(in srgb,var(--primary)20%,transparent);color:var(--text)}
        .phi-btn--muted{border-color:color-mix(in srgb,var(--border)88%,transparent);background:color-mix(in srgb,var(--card)96%,transparent)}
        .phi-btn--muted:hover{background:color-mix(in srgb,var(--primary)10%,transparent)}
        .phi-btn--ok{border-color:color-mix(in srgb,#22c55e 48%,var(--border));background:color-mix(in srgb,#22c55e 14%,transparent);color:#14532d}
        .phi-btn--ok:hover{background:color-mix(in srgb,#22c55e 22%,transparent)}
        .phi-paid-tag{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#15803d;padding:3px 8px;border-radius:999px;border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent)}
        .phi-btn--sm{padding:5px 9px;font-size:10.5px;border-radius:8px;gap:5px}

        .phi-kpis{display:grid;gap:11px;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:18px;padding-top:18px;border-top:1px solid color-mix(in srgb,var(--border)78%,transparent)}
        .phi-kpi{
            position:relative;padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            background:linear-gradient(165deg,color-mix(in srgb,var(--card)99%,transparent),color-mix(in srgb,var(--primary)6%,transparent));overflow:hidden;
        }
        .phi-kpi::after{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px;background:color-mix(in srgb,var(--primary)50%,transparent);opacity:.85}
        .phi-kpi--final::after{background:color-mix(in srgb,#22c55e 58%,var(--primary));}
        .phi-kpi--draft::after{background:color-mix(in srgb,#f97316 55%,var(--primary));}
        .phi-kpi--computed::after{background:color-mix(in srgb,#6366f1 55%,var(--primary));}
        .phi-kpi-h{display:flex;align-items:center;gap:6px;margin:0 0 6px;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .phi-kpi-h i.fa{font-size:11px;color:color-mix(in srgb,var(--primary)65%,var(--muted));}
        .phi-kpi-v{margin:0;font-size:clamp(15px,2vw,18px);font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em}

        .phi-card{
            border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            border-radius:14px;background:var(--card);
            padding:16px 18px;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)50%,transparent) inset;
        }
        .phi-card__head{display:flex;flex-wrap:wrap;gap:10px 14px;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
        .phi-card__title{margin:0;font-size:1rem;font-weight:800;letter-spacing:-.01em}
        .phi-card__sub{margin:5px 0 0;font-size:12px;line-height:1.45;color:var(--muted);max-width:720px}

        .phi-field label{display:block;font-size:10px;font-weight:700;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em}
        .phi-input{
            width:100%;max-width:420px;box-sizing:border-box;
            border:1px solid color-mix(in srgb,var(--border)88%,transparent);
            background:color-mix(in srgb,var(--card)97%,transparent);color:var(--text);
            border-radius:10px;padding:10px 12px;font-size:12px;line-height:1.35;outline:none;font-family:inherit
        }
        .phi-input:focus{border-color:color-mix(in srgb,var(--primary)48%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary)12%,transparent)}
        .phi-hint{margin:0;font-size:11px;line-height:1.45;color:var(--muted);grid-column:1/-1}

        .phi-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));align-items:end;margin-bottom:12px}
        .phi-form-actions{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:10px;padding-top:4px}

        .phi-scroll{
            overflow:auto;margin:14px -2px 0;border:1px solid color-mix(in srgb,var(--border)82%,transparent);
            border-radius:10px;background:color-mix(in srgb,var(--card)98%,transparent);-webkit-overflow-scrolling:touch;
            max-height:min(58vh,720px);
        }
        .phi-scroll::-webkit-scrollbar{height:10px;width:10px}
        .phi-scroll::-webkit-scrollbar-thumb{border-radius:8px;background:color-mix(in srgb,var(--border)65%,transparent)}

        .phi-table{width:100%;min-width:880px;border-collapse:separate;border-spacing:0}
        .phi-table thead th{
            position:sticky;top:0;z-index:4;
            background:color-mix(in srgb,var(--card)92%,transparent);color:var(--muted);font-size:9.5px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;
            padding:10px 8px;border-bottom:1px solid color-mix(in srgb,var(--border)78%,transparent);white-space:nowrap;
            box-shadow:0 1px 0 color-mix(in srgb,var(--border)70%,transparent);
        }
        .phi-table tbody td{padding:9px 8px;border-bottom:1px solid color-mix(in srgb,var(--border)70%,transparent);vertical-align:middle;font-size:12px}
        .phi-table tbody tr:nth-child(even) td{background:color-mix(in srgb,var(--card)97%,transparent)}
        .phi-table tbody tr:hover td{background:color-mix(in srgb,var(--primary)7%,transparent)}
        .phi-table tbody tr:last-child td{border-bottom:none}
        .phi-table th + th,.phi-table td + td{border-left:1px solid color-mix(in srgb,var(--border)55%,transparent)}

        .phi-table th.phi-th--cyc,.phi-table td.phi-td--cyc{
            position:sticky;left:0;z-index:2;min-width:180px;max-width:260px;box-shadow:1px 0 0 color-mix(in srgb,var(--border)65%,transparent);
        }
        .phi-table thead th.phi-th--cyc{z-index:5;background:color-mix(in srgb,var(--card)92%,transparent)}
        .phi-table td.phi-td--cyc{background:var(--card)}
        .phi-table tbody tr:nth-child(even) td.phi-td--cyc{background:color-mix(in srgb,var(--card)97%,transparent)}
        .phi-table tbody tr:hover td.phi-td--cyc{background:color-mix(in srgb,var(--primary)7%,transparent)}

        .phi-name{display:block;font-size:12.5px;font-weight:750;line-height:1.3;color:var(--text)}
        .phi-yrm{display:block;margin-top:3px;font-size:10.5px;color:var(--muted);font-variant-numeric:tabular-nums;font-weight:650}
        .phi-period{font-size:11.5px;color:var(--muted);line-height:1.35;font-variant-numeric:tabular-nums}
        .phi-ruleset{font-size:11.5px;line-height:1.35;color:var(--text);font-weight:650}
        .phi-num{text-align:right;font-variant-numeric:tabular-nums;font-weight:650}
        .phi-center{text-align:center}
        .phi-pill{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;border:1px solid var(--border);background:color-mix(in srgb,var(--card)96%,transparent);color:var(--muted);white-space:nowrap}
        .phi-pill--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));color:#15803d;background:color-mix(in srgb,#22c55e 10%,transparent)}
        .phi-pill--cmp{border-color:color-mix(in srgb,#6366f1 42%,var(--border));color:#4338ca;background:color-mix(in srgb,#6366f1 10%,transparent)}

        .phi-act{display:flex;flex-wrap:wrap;gap:5px;align-items:center;max-width:420px}
        form.phi-inline-form{display:inline;margin:0;padding:0}
        .phi-btn--danger{border-color:color-mix(in srgb,#b91c1c 45%,var(--border))!important;background:color-mix(in srgb,#b91c1c 8%,transparent)!important;color:#b91c1c!important}
        .phi-btn--danger:hover{background:color-mix(in srgb,#b91c1c 16%,transparent)!important;color:#991b1b!important}

        .phi-delete-dialog{border:none;border-radius:14px;padding:0;max-width:min(440px,94vw);background:var(--card);color:var(--text);
            box-shadow:0 22px 55px rgba(0,0,0,.2),0 0 0 1px color-mix(in srgb,var(--border)70%,transparent);}
        .phi-delete-dialog::backdrop{background:rgba(15,23,42,.45)}
        .phi-delete-dialog__inner{padding:16px 18px 18px;display:grid;gap:12px}
        .phi-delete-dialog__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:0}
        .phi-delete-dialog__head h2{margin:0;font-size:1.02rem;font-weight:800;letter-spacing:-.02em;line-height:1.25;color:var(--text)}
        .phi-delete-dialog__x{border:1px solid color-mix(in srgb,var(--border)80%,transparent);background:color-mix(in srgb,var(--card)96%,transparent);
            border-radius:8px;width:32px;height:32px;font-size:18px;line-height:1;cursor:pointer;color:var(--muted)}
        .phi-delete-dialog__x:hover{border-color:color-mix(in srgb,var(--primary)40%,var(--border));color:var(--text)}
        .phi-delete-dialog__msg{margin:0;font-size:13px;line-height:1.45;color:var(--muted)}
        .phi-delete-dialog__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding-top:4px}

        .phi-empty{padding:32px 16px;text-align:center;font-size:13px;color:var(--muted);line-height:1.5}
        .phi-empty i.fa{display:block;margin:0 auto 10px;font-size:28px;opacity:.35}

        @media (max-width:1024px){.phi-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){
            .phi-kpis{grid-template-columns:1fr;padding-top:14px;margin-top:14px}
            .phi-scroll{max-height:min(52vh,560px)}
        }

        @media print{
            .phi-actions-top,.phi-form-actions{display:none!important}
            .phi-scroll{max-height:none;overflow:visible}
            .phi-table th,.phi-table td.phi-td--cyc{position:static!important;box-shadow:none!important}
            .phi-table tbody tr:hover td{background:transparent!important}
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
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="phi-page">
        <header class="phi-hero">
            <div class="phi-hero__top">
                <div>
                    <h1 class="phi-hero__title">{{ __('Payroll') }}</h1>
                    <p class="phi-hero__sub">{{ __('Configure templates and rule sets, run monthly cycles, compute pay, review salary sheets, and finalize for payout.') }}</p>
                    <ul class="phi-meta" aria-label="{{ __('Overview') }}">
                        <li><i class="fa fa-briefcase" aria-hidden="true"></i>{{ __('Business') }} <span>{{ $business->name ?? __('Current') }}</span></li>
                        <li><i class="fa fa-coins" aria-hidden="true"></i>{{ __('Default currency') }} <span>{{ $hubCurrency }}</span></li>
                        <li><i class="fa fa-layer-group" aria-hidden="true"></i>{{ __('Rule sets') }} <span>{{ $ruleSets->count() }}</span></li>
                        <li><i class="fa fa-calendar-days" aria-hidden="true"></i>{{ __('Cycles') }} <span>{{ $cycles->count() }}</span></li>
                    </ul>
                </div>
                <div class="phi-actions-top">
                    <a href="{{ route('hr.payroll.regional-template') }}" class="phi-btn phi-btn--muted"><i class="fa fa-globe" aria-hidden="true"></i>{{ __('Regional template') }}</a>
                    <a href="{{ route('hr.payroll.rule-sets.index') }}" class="phi-btn"><i class="fa fa-sliders" aria-hidden="true"></i>{{ __('Rule sets') }}</a>
                </div>
            </div>

            <div class="phi-kpis" role="region" aria-label="{{ __('Payroll KPIs') }}">
                <article class="phi-kpi">
                    <p class="phi-kpi-h"><i class="fa fa-folder-open" aria-hidden="true"></i>{{ __('Rule sets') }}</p>
                    <p class="phi-kpi-v">{{ $ruleSets->count() }}</p>
                </article>
                <article class="phi-kpi phi-kpi--draft">
                    <p class="phi-kpi-h"><i class="fa fa-pen-to-square" aria-hidden="true"></i>{{ __('Draft cycles') }}</p>
                    <p class="phi-kpi-v">{{ $draftCount }}</p>
                </article>
                <article class="phi-kpi phi-kpi--computed">
                    <p class="phi-kpi-h"><i class="fa fa-calculator" aria-hidden="true"></i>{{ __('Computed') }}</p>
                    <p class="phi-kpi-v">{{ $computedCount }}</p>
                </article>
                <article class="phi-kpi phi-kpi--final">
                    <p class="phi-kpi-h"><i class="fa fa-circle-check" aria-hidden="true"></i>{{ __('Finalized') }}</p>
                    <p class="phi-kpi-v">{{ $finalizedCount }}</p>
                </article>
            </div>
        </header>

        <section class="phi-card" aria-labelledby="phi-cycles-heading">
            <div class="phi-card__head">
                <div>
                    <h2 id="phi-cycles-heading" class="phi-card__title">{{ __('Payroll cycles') }}</h2>
                    <p class="phi-card__sub">{{ __('Create a run for a period, compute all employees from the linked rule set, then review and finalize.') }}</p>
                </div>
            </div>

            @php
                $phiDefaultCycleName = get_settings('hr.payroll.cycle.default_name', __('Monthly Payroll'), $business);
                $phiPreserveCycleInputs = $errors->any() || old('year') !== null;
            @endphp
            <form method="post" action="{{ route('hr.payroll.cycles.store') }}" class="phi-grid">
                @csrf
                <div class="phi-field">
                    <label for="phi-rs">{{ __('Rule set') }}</label>
                    <select id="phi-rs" name="rule_set_id" class="phi-input" required style="max-width:none;">
                        @foreach($ruleSets as $set)
                            <option value="{{ $set->id }}" @selected((int) old('rule_set_id', $defaultRuleSetId) === (int) $set->id)>{{ $set->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="phi-field">
                    <label for="phi-cname">{{ __('Cycle name') }}</label>
                    <input id="phi-cname" type="text" name="name" class="phi-input" value="{{ old('name', $phiDefaultCycleName) }}" required style="max-width:none;">
                </div>
                <div class="phi-field">
                    <label for="phi-year">{{ __('Year') }}</label>
                    <input id="phi-year" type="number" name="year" class="phi-input" value="{{ old('year', now()->year) }}" min="2020" max="2100" required style="max-width:none;">
                </div>
                <div class="phi-field">
                    <label for="phi-month">{{ __('Month') }}</label>
                    <input id="phi-month" type="number" name="month" class="phi-input" value="{{ old('month', now()->month) }}" min="1" max="12" required style="max-width:none;">
                </div>
                <div class="phi-field">
                    <label for="phi-ps">{{ __('Start') }}</label>
                    <input id="phi-ps" type="date" name="period_start" class="phi-input" value="{{ old('period_start', now()->copy()->startOfMonth()->toDateString()) }}" required style="max-width:none;">
                </div>
                <div class="phi-field">
                    <label for="phi-pe">{{ __('End') }}</label>
                    <input id="phi-pe" type="date" name="period_end" class="phi-input" value="{{ old('period_end', now()->copy()->endOfMonth()->toDateString()) }}" required style="max-width:none;">
                </div>
                <div class="phi-form-actions">
                    <button type="submit" class="phi-btn"><i class="fa fa-calendar-plus" aria-hidden="true"></i>{{ __('Create payroll cycle') }}</button>
                </div>
            </form>
            <script>
                (function () {
                    var yEl = document.getElementById('phi-year');
                    var mEl = document.getElementById('phi-month');
                    var ps = document.getElementById('phi-ps');
                    var pe = document.getElementById('phi-pe');
                    var preserveFromServer = @json($phiPreserveCycleInputs);
                    if (!yEl || !mEl || !ps || !pe) return;
                    function pad2(n) { return n < 10 ? '0' + n : String(n); }
                    function toYmd(d) {
                        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
                    }
                    function syncPeriodFromMonthYear() {
                        var y = parseInt(yEl.value, 10);
                        var mo = parseInt(mEl.value, 10);
                        if (!Number.isFinite(y) || !Number.isFinite(mo)) return;
                        mo = Math.min(12, Math.max(1, mo));
                        if (mEl.value !== String(mo)) mEl.value = mo;
                        var start = new Date(y, mo - 1, 1);
                        var end = new Date(y, mo, 0);
                        ps.value = toYmd(start);
                        pe.value = toYmd(end);
                    }
                    yEl.addEventListener('change', syncPeriodFromMonthYear);
                    mEl.addEventListener('change', syncPeriodFromMonthYear);
                    yEl.addEventListener('input', syncPeriodFromMonthYear);
                    mEl.addEventListener('input', syncPeriodFromMonthYear);
                    if (!preserveFromServer) syncPeriodFromMonthYear();
                })();
            </script>

            <p class="phi-hint" id="phi-table-hint" style="margin-top:4px;">{{ __('Use Open to manage items; generate and view salary sheet when totals are ready. Finalizing locks the cycle.') }}</p>
            <div class="phi-scroll" role="region" aria-labelledby="phi-cycles-heading" aria-describedby="phi-table-hint" tabindex="0">
                <table class="phi-table">
                    <thead>
                        <tr>
                            <th scope="col" class="phi-th--cyc">{{ __('Cycle') }}</th>
                            <th scope="col">{{ __('Period') }}</th>
                            <th scope="col">{{ __('Rule set') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Employees') }}</th>
                            <th scope="col">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cycles as $cycle)
                            @php
                                $st = strtolower((string) $cycle->status);
                                $pillClass = $cycle->isFinalized()
                                    ? ' phi-pill--ok'
                                    : ($st === 'computed' ? ' phi-pill--cmp' : '');
                                $statusLabel = match ($st) {
                                    'finalized' => __('Finalized'),
                                    'computed' => __('Computed'),
                                    'draft' => __('Draft'),
                                    default => ucfirst((string) $cycle->status),
                                };
                            @endphp
                            <tr>
                                <td class="phi-td--cyc">
                                    <span class="phi-name">{{ $cycle->name }}</span>
                                    <span class="phi-yrm">{{ $cycle->year }}-{{ str_pad((string) $cycle->month, 2, '0', STR_PAD_LEFT) }}</span>
                                </td>
                                <td><span class="phi-period">{{ $cycle->period_start?->format('M j') ?? '—' }} — {{ $cycle->period_end?->format('M j, Y') ?? '—' }}</span></td>
                                <td><span class="phi-ruleset">{{ $cycle->ruleSet?->name ?? '—' }}</span></td>
                                <td class="phi-center"><span class="phi-pill{{ $pillClass }}">{{ $statusLabel }}</span></td>
                                <td class="phi-num">{{ $cycle->items_count }}</td>
                                <td>
                                    <div class="phi-act">
                                        <a href="{{ route('hr.payroll.cycles.show', $cycle) }}" class="phi-btn phi-btn--sm">{{ __('Open') }}</a>
                                        <form method="post" action="{{ route('hr.payroll.cycles.salary-sheet.generate', $cycle) }}" class="phi-inline-form">@csrf<button type="submit" class="phi-btn phi-btn--sm phi-btn--muted">{{ __('Generate sheet') }}</button></form>
                                        <a href="{{ route('hr.payroll.cycles.salary-sheet', $cycle) }}" class="phi-btn phi-btn--sm">{{ __('Salary sheet') }}</a>
                                        @if($cycle->isFinalized() && (int) ($cycle->ledger_transactions_count ?? 0) === 0 && (float) ($cycle->items_sum_net_pay ?? 0) > 0)
                                            <a href="{{ route('hr.payroll.cycles.show', $cycle) }}#payroll-payment" class="phi-btn phi-btn--sm phi-btn--ok">{{ __('Make payment') }}</a>
                                        @elseif($cycle->isFinalized() && (int) ($cycle->ledger_transactions_count ?? 0) > 0)
                                            <span class="phi-paid-tag">{{ __('Paid') }}</span>
                                        @endif
                                        @if(! $cycle->isFinalized())
                                            <form method="post" action="{{ route('hr.payroll.cycles.compute', $cycle) }}" class="phi-inline-form">@csrf<button type="submit" class="phi-btn phi-btn--sm">{{ __('Compute') }}</button></form>
                                            <form method="post" action="{{ route('hr.payroll.cycles.finalize', $cycle) }}" class="phi-inline-form" onsubmit="return confirm(@json(__('Finalize this cycle? This will lock updates.')))">@csrf<button type="submit" class="phi-btn phi-btn--sm phi-btn--ok">{{ __('Finalize') }}</button></form>
                                        @endif
                                        @if((int) ($cycle->ledger_transactions_count ?? 0) === 0)
                                            <button type="button" class="phi-btn phi-btn--sm phi-btn--danger phi-js-open-delete-cycle" data-delete-url="{{ route('hr.payroll.cycles.destroy', $cycle) }}" aria-haspopup="dialog" aria-controls="phi-delete-cycle-dialog">{{ __('Delete') }}</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="phi-empty">
                                    <i class="fa fa-calendar-xmark" aria-hidden="true"></i>
                                    {{ __('No payroll cycles yet. Create one above to start this period’s run.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($cycles->isNotEmpty())
                <dialog id="phi-delete-cycle-dialog" class="phi-delete-dialog" aria-labelledby="phi-delete-cycle-title">
                    <div class="phi-delete-dialog__inner">
                        <div class="phi-delete-dialog__head">
                            <h2 id="phi-delete-cycle-title">{{ __('Delete this payroll cycle?') }}</h2>
                            <button type="button" class="phi-delete-dialog__x" id="phi-delete-cycle-close" aria-label="{{ __('Close') }}">&times;</button>
                        </div>
                        <p class="phi-delete-dialog__msg">{{ __('Are you sure you want to delete this cycle? All employee rows and computations for this period will be removed. This cannot be undone.') }}</p>
                        <div class="phi-delete-dialog__actions">
                            <button type="button" class="phi-btn phi-btn--sm phi-btn--muted" id="phi-delete-cycle-cancel">{{ __('Cancel') }}</button>
                            <form id="phi-delete-cycle-form" method="post" action="" class="phi-inline-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="phi-btn phi-btn--sm phi-btn--danger">{{ __('Yes, delete') }}</button>
                            </form>
                        </div>
                    </div>
                </dialog>
                <script>
                    (function () {
                        var dlg = document.getElementById('phi-delete-cycle-dialog');
                        var form = document.getElementById('phi-delete-cycle-form');
                        if (!dlg || !form) return;
                        function close() {
                            if (dlg && typeof dlg.close === 'function') dlg.close();
                        }
                        document.querySelectorAll('.phi-js-open-delete-cycle').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var u = btn.getAttribute('data-delete-url');
                                if (u) form.setAttribute('action', u);
                                if (typeof dlg.showModal === 'function') dlg.showModal();
                            });
                        });
                        document.getElementById('phi-delete-cycle-cancel')?.addEventListener('click', close);
                        document.getElementById('phi-delete-cycle-close')?.addEventListener('click', close);
                        form.addEventListener('submit', function (e) {
                            var a = form.getAttribute('action');
                            if (!a) e.preventDefault();
                        });
                    })();
                </script>
            @endif
        </section>
    </div>
@endsection
